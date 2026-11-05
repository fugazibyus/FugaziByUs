#!/usr/bin/env bash
# One-time (and safely re-runnable) GitHub setup for FugaziByUs.
#
# Prerequisites (see docs/SETUP-GITHUB.md):
#   - the organisation exists (created on github.com)
#   - `gh auth refresh -h github.com -s admin:org` has been run
#
# Usage: scripts/bootstrap-github.sh [org] [repo]
set -euo pipefail

ORG="${1:-fugazibyus}"
NAME="${2:-FugaziByUs}"
REPO="$ORG/$NAME"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ME="$(gh api user --jq .login)"

step() { printf '\n\033[35m▶ %s\033[0m\n' "$1"; }

step "Repository $REPO"
if gh repo view "$REPO" >/dev/null 2>&1; then
  echo "  exists"
else
  gh repo create "$REPO" --public \
    --description "AI-built, human-approved: a demo of a complete AI development lifecycle" \
    --homepage "https://github.com/$REPO"
fi
git -C "$ROOT" remote get-url origin >/dev/null 2>&1 \
  || git -C "$ROOT" remote add origin "https://github.com/$REPO.git"

step "Push main and develop"
git -C "$ROOT" push -u origin main
git -C "$ROOT" show-ref --verify --quiet refs/heads/develop || git -C "$ROOT" branch develop main
git -C "$ROOT" push -u origin develop

step "Repository settings"
gh api -X PATCH "repos/$REPO" --silent \
  -f default_branch=develop \
  -F delete_branch_on_merge=true \
  -F allow_squash_merge=true -F allow_merge_commit=true -F allow_rebase_merge=false \
  -F allow_auto_merge=false -F allow_update_branch=true \
  -f squash_merge_commit_title=PR_TITLE -f squash_merge_commit_message=PR_BODY \
  -F has_wiki=false -F has_discussions=false
echo "  default branch develop, squash for features, merge commits for releases"

step "Security: secret scanning + push protection, Dependabot alerts"
gh api -X PATCH "repos/$REPO" --silent --input - <<JSON || echo "  (skipped – not available)"
{"security_and_analysis":{"secret_scanning":{"status":"enabled"},"secret_scanning_push_protection":{"status":"enabled"}}}
JSON
gh api -X PUT "repos/$REPO/vulnerability-alerts" --silent || true

step "Actions: read-only token by default, Actions may not approve PRs"
gh api -X PUT "repos/$REPO/actions/permissions/workflow" --silent \
  -f default_workflow_permissions=read -F can_approve_pull_request_reviews=false

step "Team @$ORG/devs"
if ! gh api "orgs/$ORG/teams/devs" >/dev/null 2>&1; then
  gh api -X POST "orgs/$ORG/teams" --silent -f name=devs -f privacy=closed \
    -f description="Developers who review and merge – CODEOWNERS"
fi
gh api -X PUT "orgs/$ORG/teams/devs/memberships/$ME" --silent -f role=maintainer
gh api -X PUT "orgs/$ORG/teams/devs/repos/$REPO" --silent -f permission=maintain
TEAM_ID="$(gh api "orgs/$ORG/teams/devs" --jq .id)"
echo "  $ME is a maintainer of @$ORG/devs (team id $TEAM_ID)"

step "Branch rules (rulesets)"
for file in "$ROOT"/.github/rulesets/*.json; do
  name="$(jq -r .name "$file")"
  id="$(gh api "repos/$REPO/rulesets" --jq ".[] | select(.name == \"$name\") | .id" || true)"
  if [[ -n "$id" ]]; then
    gh api -X PUT "repos/$REPO/rulesets/$id" --silent --input "$file" && echo "  updated: $name"
  else
    gh api -X POST "repos/$REPO/rulesets" --silent --input "$file" && echo "  created: $name"
  fi
done

step "Environments"
gh api -X PUT "repos/$REPO/environments/staging" --silent --input - <<JSON
{"deployment_branch_policy":{"protected_branches":false,"custom_branch_policies":true}}
JSON
gh api -X PUT "repos/$REPO/environments/production" --silent --input - <<JSON
{"reviewers":[{"type":"Team","id":$TEAM_ID}],"prevent_self_review":false,
 "deployment_branch_policy":{"protected_branches":false,"custom_branch_policies":true}}
JSON
add_branch_policy() {
  local env="$1" branch="$2"
  gh api "repos/$REPO/environments/$env/deployment-branch-policies" --jq '.branch_policies[].name' | grep -qx "$branch" \
    || gh api -X POST "repos/$REPO/environments/$env/deployment-branch-policies" --silent -f name="$branch" -f type=branch
}
add_branch_policy staging develop
add_branch_policy production main
echo "  staging ← develop · production ← main (needs a @devs approval until the Deploy Gate exists)"

step "AI policy variables (change any time in Settings → Variables)"
set_var() { gh variable set "$1" --repo "$REPO" --body "$2" && echo "  $1=$2"; }
set_var AI_AUTOMERGE_MODE devs-only
set_var AI_AUTODEPLOY_STAGING on
set_var AI_AUTODEPLOY_PRODUCTION business-hours
set_var AI_MAX_AUTOMERGE_SIZE m
set_var AI_DAILY_BUDGET_USD 25

step "Labels"
"$ROOT/scripts/bootstrap-labels.sh" "$REPO"

printf '\n\033[32m✔ GitHub is set up: https://github.com/%s\033[0m\n' "$REPO"
