#!/usr/bin/env bash
# Checks a repository_dispatch sent by the app on a developer's behalf (Slack commands).
# The app signs "event|requested_by|pr|ts" with AI_DISPATCH_SECRET (App\Slack\GitHubRepository::sign).
# Prints the developer's GitHub login if the signature is valid and under 10 minutes old,
# otherwise prints nothing, so the request is treated as nobody's.
set -uo pipefail

: "${DISPATCH_SECRET:=}" "${EVENT:=}" "${REQUESTED_BY:=}" "${PR:=}" "${TS:=}" "${SIGNATURE:=}"

if [[ -z "$DISPATCH_SECRET" || -z "$REQUESTED_BY" || ! "$TS" =~ ^[0-9]+$ ]]; then
  exit 0
fi

age=$(( $(date +%s) - TS ))
if (( age < -60 || age > 600 )); then
  echo "::warning::Ignoring a stale dispatch (${age}s old)." >&2
  exit 0
fi

expected=$(printf '%s' "${EVENT}|${REQUESTED_BY}|${PR}|${TS}" | openssl dgst -sha256 -hmac "$DISPATCH_SECRET" -r | cut -d' ' -f1)

if [[ "$expected" == "$SIGNATURE" ]]; then
  echo "$REQUESTED_BY"
else
  echo "::warning::Ignoring a dispatch with a bad signature." >&2
fi
