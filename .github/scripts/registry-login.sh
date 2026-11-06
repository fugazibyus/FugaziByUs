#!/usr/bin/env bash
# Writes a short-lived Docker login for DigitalOcean Container Registry, so no long-lived
# registry password is stored anywhere. The DigitalOcean token itself never leaves Actions.
#
#   DO_TOKEN=… registry-login.sh <read-only|read-write> <seconds> <directory>
#   → <directory>/config.json  (use with DOCKER_CONFIG=<directory>)
set -euo pipefail

mode=$1 seconds=$2 dir=$3
read_write=$([[ "$mode" == read-write ]] && echo true || echo false)

if [[ -z "${DO_TOKEN:-}" ]]; then
  echo "::error::The DIGITALOCEAN_ACCESS_TOKEN secret is missing. See docs/SETUP-HOSTED.md."
  exit 1
fi

mkdir -p "$dir"
(
  umask 077
  curl -fsS --max-time 20 -H "Authorization: Bearer $DO_TOKEN" \
    "https://api.digitalocean.com/v2/registry/docker-credentials?read_write=${read_write}&expiry_seconds=${seconds}" \
    >"$dir/config.json"
)

# Keep the login out of the logs, just in case anything prints it.
jq -r '.auths[].auth' "$dir/config.json" | while read -r auth; do echo "::add-mask::$auth"; done
