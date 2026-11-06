#!/usr/bin/env bash
# Deploys one image tag on this Droplet, checks that it's healthy, and rolls back on failure.
# Lives in /opt/fugazi on the server. The Deploy workflow copies it there on every deploy,
# together with compose.yml and the new Laravel settings (app.env.next).
#
#   ./deploy.sh deploy --tag <image tag> --version <commit sha> --domain <host> --registry <registry> [--email <address>]
#   ./deploy.sh rollback    back to the previous tag, without the registry (break-glass)
#   ./deploy.sh status      what runs now, and whether it's healthy
#
# Files next to this script:
#   compose.yml               the stack
#   app.env                   Laravel settings (app.env.previous is kept for rollbacks)
#   .env                      compose settings this script writes: tags, domain, database password
#   ~/.fugazi-registry/       short-lived registry login the workflow uploads; deleted after the pull
#
# Migrations are never rolled back. Write them so the previous release still works with the
# new schema (add columns and tables first; remove them in a later release).
set -euo pipefail
cd "$(dirname "$0")"

REGISTRY_LOGIN="$HOME/.fugazi-registry"

log() { printf '%s  %s\n' "$(date -u +%H:%M:%S)" "$*"; }
fail() {
  log "❌ $*"
  exit 1
}

# Reads KEY=value from a settings file (default .env). Empty when the file doesn't exist yet.
setting() {
  local file=${2:-.env}
  [[ -f "$file" ]] || return 0
  sed -n "s/^$1=//p" "$file" | tail -n1
}

compose() { docker compose --file compose.yml "$@"; }

# Writes compose's .env. Arguments: registry, image tag, previous tag, domain.
write_settings() {
  local db_password http_port https_port
  db_password=$(setting DB_PASSWORD app.env)
  http_port=${HTTP_PORT:-$(setting HTTP_PORT)}
  https_port=${HTTPS_PORT:-$(setting HTTPS_PORT)}
  [[ -n "$db_password" ]] || fail "app.env has no DB_PASSWORD."

  (
    umask 077
    printf '%s\n' \
      "REGISTRY=$1" \
      "IMAGE_TAG=$2" \
      "PREVIOUS_TAG=$3" \
      "APP_DOMAIN=$4" \
      "DB_PASSWORD=${db_password}" \
      "HTTP_PORT=${http_port:-80}" \
      "HTTPS_PORT=${https_port:-443}" >.env.tmp
  )
  mv .env.tmp .env
}

# Waits up to ~90 seconds for /up to answer. With a commit SHA, /version must report it too.
healthy() {
  local expected=${1:-} domain port url version
  domain=$(setting APP_DOMAIN)
  port=$(setting HTTPS_PORT)
  url="https://${domain}:${port}"

  for _ in $(seq 1 30); do
    if curl -fsSk --max-time 5 --resolve "${domain}:${port}:127.0.0.1" "${url}/up" >/dev/null 2>&1; then
      version=$(curl -fsSk --max-time 5 --resolve "${domain}:${port}:127.0.0.1" "${url}/version" 2>/dev/null || true)
      if [[ -z "$expected" || "$version" == *"\"sha\":\"${expected}\""* ]]; then
        return 0
      fi
    fi
    sleep 3
  done

  return 1
}

images_present() {
  docker image inspect "$1/app:$2" "$1/web:$2" >/dev/null 2>&1
}

# First deploy on a new server: swap nginx's temporary self-signed certificate for a real one.
certificate() {
  local domain=$1 email=$2
  local args=(certonly --webroot -w /var/www/certbot -d "$domain" --agree-tos --non-interactive --keep-until-expiring)

  if compose run --rm --no-deps --entrypoint sh certbot -c "test -f /etc/letsencrypt/renewal/${domain}.conf"; then
    return 0
  fi

  if [[ -n "$email" ]]; then args+=(--email "$email"); else args+=(--register-unsafely-without-email); fi

  log "Requesting a Let's Encrypt certificate for ${domain}…"
  # certbot refuses to write over the placeholder, so remove it (nginx keeps the copy it loaded).
  compose run --rm --no-deps --entrypoint sh certbot -c "rm -rf '/etc/letsencrypt/live/${domain}'"

  if compose run --rm --no-deps --entrypoint certbot certbot "${args[@]}"; then
    compose exec -T web nginx -s reload
    log "🔒 Certificate installed for ${domain}."
  else
    compose restart web # brings the placeholder back so nginx keeps serving
    log "⚠️  Let's Encrypt didn't issue a certificate (is port 80 open to the internet, and does ${domain} point here?). The site runs on a temporary self-signed certificate; the next deploy tries again."
  fi
}

# Keeps the images for the current and previous tag, so a rollback never needs the registry.
prune() {
  local registry=$1 keep=$2 previous=${3:-$2}
  docker image ls --format '{{.Repository}}:{{.Tag}}' |
    grep -E "^${registry}/(app|web):" |
    grep -vE ":(${keep}|${previous})$" |
    xargs -r docker image rm >/dev/null 2>&1 || true
  docker image prune -f >/dev/null
}

cmd_deploy() {
  local tag='' version='' domain='' registry='' email=''
  while [[ $# -gt 0 ]]; do
    case $1 in
      --tag) tag=$2 ;;
      --version) version=$2 ;;
      --domain) domain=$2 ;;
      --registry) registry=$2 ;;
      --email) email=$2 ;;
      *) fail "Unknown option $1" ;;
    esac
    shift 2
  done
  [[ -n "$tag" && -n "$version" && -n "$domain" && -n "$registry" ]] || fail "deploy needs --tag, --version, --domain and --registry."
  [[ -f app.env.next ]] || fail "app.env.next is missing (the Deploy workflow uploads it)."
  trap 'rm -f "$REGISTRY_LOGIN/config.json"' EXIT

  local current previous earlier
  current=$(setting IMAGE_TAG)
  earlier=$(setting PREVIOUS_TAG)
  # Re-deploying the running tag (a settings change) keeps the older rollback target.
  previous=$([[ "$current" == "$tag" ]] && setting PREVIOUS_TAG || echo "$current")

  log "🚀 Deploying ${tag} (commit ${version:0:7}) to ${domain}. Running now: ${current:-nothing}."

  [[ -f app.env ]] && cp -p app.env app.env.previous
  install -m 600 app.env.next app.env && rm -f app.env.next
  write_settings "$registry" "$tag" "$previous" "$domain"

  if images_present "$registry" "$tag"; then
    log "Images are already on this server."
  else
    log "Pulling images…"
    DOCKER_CONFIG="$REGISTRY_LOGIN" compose pull --quiet web app || {
      restore "$registry" "$current" "$earlier" "$domain"
      fail "Couldn't pull ${registry}/{app,web}:${tag}. Nothing changed."
    }
  fi

  compose up -d --wait db

  # No --isolated: its lock lives in the cache table, which a new database doesn't have yet.
  # The Deploy workflow's concurrency group already runs one deploy at a time.
  log "Running migrations…"
  if ! compose run --rm --no-deps app php artisan migrate --force; then
    restore "$registry" "$current" "$earlier" "$domain"
    fail "Migrations failed. The previous release (${current:-none}) is still running."
  fi

  compose up -d --remove-orphans

  if ! healthy "$version"; then
    log "Health check failed: /up or /version didn't answer with ${version:0:7} within 90 seconds."
    compose logs --tail=40 app web || true

    if [[ -z "$current" ]]; then
      fail "This was the first deploy, so there's nothing to roll back to. The containers are left running for debugging."
    fi

    restore "$registry" "$current" "$earlier" "$domain"
    compose up -d --remove-orphans
    if healthy; then
      fail "Rolled back to ${current}, which is healthy. Database migrations from ${tag} stay applied."
    fi
    fail "Rolled back to ${current}, but it isn't healthy either. A developer needs to look now."
  fi

  log "✅ ${domain} runs ${tag} and is healthy."
  certificate "$domain" "$email"
  prune "$registry" "$tag" "$previous"
}

# Puts the settings files back to the release that was running.
# Arguments: registry, tag, its previous tag, domain.
restore() {
  [[ -n "$2" ]] || return 0
  [[ -f app.env.previous ]] && cp -p app.env.previous app.env
  write_settings "$1" "$2" "$3" "$4"
}

cmd_rollback() {
  local registry current previous domain
  registry=$(setting REGISTRY)
  current=$(setting IMAGE_TAG)
  previous=$(setting PREVIOUS_TAG)
  domain=$(setting APP_DOMAIN)

  [[ -n "$previous" ]] || fail "There's no previous release to roll back to."
  images_present "$registry" "$previous" || fail "The images for ${previous} aren't on this server any more. Run the Deploy workflow with that commit instead."

  log "⏪ Rolling back from ${current} to ${previous}…"
  if [[ -f app.env.previous ]]; then
    mv app.env app.env.swap && mv app.env.previous app.env && mv app.env.swap app.env.previous
  fi
  write_settings "$registry" "$previous" "$current" "$domain"
  compose up -d --remove-orphans

  healthy || fail "${previous} isn't healthy either. A developer needs to look now."
  log "✅ Back on ${previous}. Database migrations from ${current} stay applied."
}

cmd_status() {
  local domain port
  domain=$(setting APP_DOMAIN)
  port=$(setting HTTPS_PORT)
  echo "Running:  $(setting IMAGE_TAG)"
  echo "Previous: $(setting PREVIOUS_TAG)"
  compose ps --format 'table {{.Service}}\t{{.Status}}'
  echo "Version:  $(curl -fsSk --max-time 5 --resolve "${domain}:${port}:127.0.0.1" "https://${domain}:${port}/version" || echo 'not answering')"
}

case ${1:-} in
  deploy) shift && cmd_deploy "$@" ;;
  rollback) cmd_rollback ;;
  status) cmd_status ;;
  *) sed -n '2,10p' "$0" && exit 1 ;;
esac
