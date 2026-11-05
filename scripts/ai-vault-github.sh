#!/usr/bin/env bash
# Stores the agents' GitHub token in the Anthropic vault as GH_TOKEN, so the GitHub CLI
# inside the agent sandbox is authenticated – without the token ever entering the sandbox
# (Anthropic substitutes it at egress, and only for GitHub hosts).
#
# Re-run to rotate the token. Requires: ant (logged in), jq, an applied vault (make ai-apply).
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

VAULT_ID="$(jq -r '.resources | to_entries[] | select(.key | test("vaults/github.yaml$")) | .value.id' claude-lock.json)"
[[ -n "$VAULT_ID" && "$VAULT_ID" != null ]] || { echo "Vault not found in claude-lock.json – run 'make ai-apply' first." >&2; exit 1; }

read -r -s -p "Paste the agents' fine-grained GitHub token (input hidden): " TOKEN; echo
[[ -n "$TOKEN" ]] || { echo "No token given." >&2; exit 1; }

EXISTING="$(ant beta:vaults:credentials list --vault-id "$VAULT_ID" --format jsonl \
  --transform '{id,secret:auth.secret_name}' | jq -r 'select(.secret == "GH_TOKEN") | .id' | head -1)"

if [[ -n "$EXISTING" ]]; then
  ant beta:vaults:credentials update --vault-id "$VAULT_ID" --credential-id "$EXISTING" \
    --auth "{type: environment_variable, secret_value: \"$TOKEN\"}" >/dev/null
  echo "✓ Rotated GH_TOKEN in vault $VAULT_ID"
else
  ant beta:vaults:credentials create --vault-id "$VAULT_ID" --display-name "GitHub CLI token (GH_TOKEN)" \
    --auth "{type: environment_variable, secret_name: GH_TOKEN, secret_value: \"$TOKEN\", networking: {type: limited, allowed_hosts: [api.github.com, github.com, uploads.github.com]}, injection_location: {header: true}}" >/dev/null
  echo "✓ Added GH_TOKEN to vault $VAULT_ID"
fi
