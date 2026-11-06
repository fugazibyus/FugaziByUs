#!/usr/bin/env bash
# Posts one message to Slack, straight from GitHub Actions, as the "Fugazi AI" bot.
#
#   SLACK_BOT_TOKEN  repository secret (xoxb-…). Without it this does nothing.
#   TO               space-separated channels (#fugazi-alerts or fugazi-alerts) and/or member IDs (U012AB3CD = a DM)
#   TEXT             the message (Slack mrkdwn; <url|label> for links)
#
# Alerts are best-effort: a Slack problem is a warning, never a failed workflow.
set -uo pipefail

: "${SLACK_BOT_TOKEN:=}" "${TO:=}" "${TEXT:=}"

if [[ -z "$SLACK_BOT_TOKEN" || -z "$TO" || -z "$TEXT" ]]; then
  echo "Slack alert skipped (no token, recipient or text)."
  exit 0
fi

for recipient in $TO; do
  response=$(jq -n --arg channel "${recipient#\#}" --arg text "$TEXT" '{channel: $channel, text: $text, unfurl_links: false}' |
    curl -sS --max-time 15 https://slack.com/api/chat.postMessage \
      -H "Authorization: Bearer $SLACK_BOT_TOKEN" \
      -H 'Content-Type: application/json; charset=utf-8' \
      --data @-)

  if [[ "$(jq -r '.ok' <<<"$response" 2>/dev/null)" != "true" ]]; then
    echo "::warning::Slack didn't accept the alert for ${recipient}: $(jq -r '.error // "no response"' <<<"$response" 2>/dev/null)"
  fi
done
