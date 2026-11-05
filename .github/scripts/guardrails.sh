#!/usr/bin/env bash
# Guardrails check for pull requests. Fails (exit 1) when:
#   1. the branch name doesn't follow the naming convention
#   2. the branch targets the wrong base (e.g. a feature straight into main)
#   3. an AI branch (ai/*) touches a protected path without the
#      `allow-sensitive-paths` label
#
# Inputs (env): HEAD_REF, BASE_REF, LABELS (comma-separated), CHANGED_FILES (newline-separated)
set -euo pipefail
shopt -s extglob globstar

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROTECTED_FILE="$HERE/../guardrails/protected-paths.txt"
failures=()
notes=()

human_re='^(feature|fix|chore|docs|refactor|test)/[0-9]+-[a-z0-9][a-z0-9-]*$|^(feature|fix|chore|docs|refactor|test)/[a-z0-9][a-z0-9-]*$'
hotfix_re='^hotfix/[a-z0-9][a-z0-9-]*$'
ai_re='^ai/(feature|fix|research|chore|docs)/[0-9]+-[a-z0-9][a-z0-9-]*$'
bot_re='^(dependabot|renovate)/'

# 1. Branch naming
if [[ "$HEAD_REF" == "develop" ]]; then
  kind="release"
elif [[ "$HEAD_REF" =~ $ai_re ]]; then
  kind="ai"
elif [[ "$HEAD_REF" =~ $hotfix_re ]]; then
  kind="hotfix"
elif [[ "$HEAD_REF" =~ $human_re || "$HEAD_REF" =~ $bot_re ]]; then
  kind="human"
else
  failures+=("Branch \`$HEAD_REF\` doesn't follow the naming convention. Use \`feature/<issue>-short-name\`, \`fix/…\`, \`chore/…\`, \`docs/…\`, \`hotfix/…\` (humans) or \`ai/<type>/<issue>-short-name\` (AI). See the Branching tab on the AI Workflow page.")
  kind="unknown"
fi

# 2. Base branch
case "$BASE_REF" in
  main)
    if [[ "$kind" != "release" && "$kind" != "hotfix" ]]; then
      failures+=("Only release PRs (\`develop\` → \`main\`) and \`hotfix/*\` branches may target \`main\`. Point this PR at \`develop\` instead.")
    fi
    ;;
  develop)
    if [[ "$kind" == "release" ]]; then
      failures+=("\`develop\` can't target itself.")
    fi
    ;;
  *)
    notes+=("Base branch \`$BASE_REF\` is not \`main\` or \`develop\` – no base rules applied.")
    ;;
esac

# 3. Protected paths on AI branches
if [[ "$kind" == "ai" ]]; then
  if [[ ",$LABELS," == *",allow-sensitive-paths,"* ]]; then
    notes+=("\`allow-sensitive-paths\` label present – protected path check skipped (a developer approved this).")
  else
    mapfile -t patterns < <(grep -vE '^\s*(#|$)' "$PROTECTED_FILE")
    hits=()
    while IFS= read -r file; do
      [[ -z "$file" ]] && continue
      for pattern in "${patterns[@]}"; do
        # shellcheck disable=SC2053
        if [[ "$file" == $pattern ]]; then hits+=("$file"); break; fi
      done
    done <<< "$CHANGED_FILES"
    if (( ${#hits[@]} )); then
      failures+=("This AI branch changes protected paths: $(printf '\`%s\` ' "${hits[@]}"). A developer must review and add the \`allow-sensitive-paths\` label.")
    fi
  fi
  if grep -q '^database/migrations/' <<< "$CHANGED_FILES"; then
    notes+=("Contains database migrations – the Merge Gate will require a human merge.")
  fi
fi

# Report
{
  echo "### Guardrails"
  echo
  echo "- Branch: \`$HEAD_REF\` → \`$BASE_REF\` (detected: **$kind**)"
  for n in "${notes[@]}"; do echo "- ℹ️ $n"; done
  if (( ${#failures[@]} )); then
    for f in "${failures[@]}"; do echo "- ❌ $f"; done
  else
    echo "- ✅ All guardrails passed"
  fi
} | tee -a "${GITHUB_STEP_SUMMARY:-/dev/null}"

for f in "${failures[@]}"; do echo "::error::$f"; done
(( ${#failures[@]} == 0 ))
