#!/usr/bin/env bash
# Creates the generated deploy secrets in GitHub, without ever printing them:
#
#   staging and production environments:  APP_KEY, DB_PASSWORD
#   repository:                           DEPLOY_SSH_KEY (private key the Deploy workflow logs in with)
#                                         DEPLOY_SSH_PUBLIC_KEY variable (its public half, for droplet-init.sh)
#
# Existing values are left alone, so it's safe to run again. Changing APP_KEY later logs
# everyone out; changing DB_PASSWORD later also needs the password changed inside MySQL.
#
#   scripts/deploy-secrets.sh             # uses fugazibyus/FugaziByUs
#   REPO=owner/name scripts/deploy-secrets.sh
set -euo pipefail

REPO=${REPO:-fugazibyus/FugaziByUs}

has_secret() { gh secret list --repo "$REPO" "${@:2}" --json name --jq '.[].name' | grep -qx "$1"; }

for env in staging production; do
  if has_secret APP_KEY --env "$env"; then
    echo "· $env: APP_KEY already set"
  else
    printf 'base64:%s' "$(openssl rand -base64 32)" | gh secret set APP_KEY --repo "$REPO" --env "$env"
    echo "✓ $env: APP_KEY created"
  fi

  if has_secret DB_PASSWORD --env "$env"; then
    echo "· $env: DB_PASSWORD already set"
  else
    openssl rand -hex 24 | tr -d '\n' | gh secret set DB_PASSWORD --repo "$REPO" --env "$env"
    echo "✓ $env: DB_PASSWORD created"
  fi
done

if has_secret DEPLOY_SSH_KEY; then
  echo "· DEPLOY_SSH_KEY already set"
else
  dir=$(mktemp -d)
  trap 'rm -f "$dir/key" "$dir/key.pub"; rmdir "$dir"' EXIT
  ssh-keygen -q -t ed25519 -N '' -C 'fugazi-deploy' -f "$dir/key"
  gh secret set DEPLOY_SSH_KEY --repo "$REPO" <"$dir/key"
  gh variable set DEPLOY_SSH_PUBLIC_KEY --repo "$REPO" --body "$(cat "$dir/key.pub")"
  echo "✓ DEPLOY_SSH_KEY created (public half in the DEPLOY_SSH_PUBLIC_KEY variable)"
fi
