#!/usr/bin/env bash
# Called by .github/workflows/ai-agent.yml. Turns the workflow inputs (environment variables,
# never interpolated into the script) into an `ai:start` or `ai:stop` call and writes `result`
# to $GITHUB_OUTPUT: started | budget | busy | stopped (any other failure fails the step).
set -uo pipefail

# Empty repository variables must not switch the safety limits off.
export AI_DAILY_BUDGET_USD="${AI_DAILY_BUDGET_USD:-25}"
export AI_MAX_CONCURRENT_SESSIONS="${AI_MAX_CONCURRENT_SESSIONS:-3}"

if [[ "${ACTION:-start}" == "stop" ]]; then
  args=()
  [[ -n "${ISSUE:-}" ]] && args+=("--issue=$ISSUE")
  [[ -n "${PR:-}" ]] && args+=("--pr=$PR")
  php artisan ai:stop "${args[@]}" || exit 1
  echo "result=stopped" >> "$GITHUB_OUTPUT"
  exit 0
fi

args=("$AGENT" "--trigger=${TRIGGER:-actions}" "--actor=${ACTOR:-}")
[[ -n "${ISSUE:-}" ]] && args+=("--issue=$ISSUE")
[[ -n "${PR:-}" ]] && args+=("--pr=$PR")
[[ -n "${BRANCH:-}" ]] && args+=("--branch=$BRANCH")
[[ -n "${RUN_ID:-}" ]] && args+=("--run-id=$RUN_ID")
[[ -n "${ATTEMPT:-}" ]] && args+=("--attempt=$ATTEMPT")
[[ -n "${MESSAGE:-}" ]] && args+=("--message=$MESSAGE")
[[ "$AGENT" == "release" && -n "${STAGING_URL:-}" ]] && args+=("--staging-url=$STAGING_URL")

# Exit codes from AiStartCommand: 2 = daily budget reached, 3 = too many agents running.
for wait in 0 60 120 240 300 300 300; do
  if (( wait > 0 )); then
    echo "::notice::Waiting ${wait}s for a free agent slot…"
    sleep "$wait"
  fi

  php artisan ai:start "${args[@]}"
  code=$?

  case $code in
    0) echo "result=started" >> "$GITHUB_OUTPUT"; exit 0 ;;
    2) echo "result=budget" >> "$GITHUB_OUTPUT"; exit 0 ;;
    3) continue ;;
    *) exit "$code" ;;
  esac
done

echo "result=busy" >> "$GITHUB_OUTPUT"
