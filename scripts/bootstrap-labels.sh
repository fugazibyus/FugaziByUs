#!/usr/bin/env bash
# Creates (or updates) every label the AI workflow uses. Safe to run repeatedly.
#   scripts/bootstrap-labels.sh [owner/repo]
set -euo pipefail
REPO="${1:-fugazibyus/FugaziByUs}"

label() { gh label create "$1" --repo "$REPO" --color "$2" --description "$3" --force >/dev/null && echo "  ✓ $1"; }

echo "Labels for $REPO"
# Type
label "type:bug"              "d73a4a" "Something is broken"
label "type:feature"          "a2eeef" "New feature or improvement"
label "type:research"         "c5def5" "Question for the researcher agent"
label "type:chore"            "ededed" "Maintenance, tooling, dependencies"
# Flow – who handles it
label "flow:ai"               "7c3aed" "Handled by the AI workflow"
label "flow:human"            "0e8a16" "Handled by a developer"
# Control – set by developers
label "ai:go"                 "6d28d9" "Developer approves the AI to start work"
label "ai:stop"               "b60205" "Stop all AI work on this issue/PR"
label "ai:review"             "8b5cf6" "Ask the AI reviewer to review this PR"
label "ai:automerge"          "5b21b6" "Developer opts this PR/issue into AI merge"
label "ai:no-automerge"       "e99695" "Always require a human merge"
label "deploy:hold"           "b60205" "Block automatic production deploys"
label "allow-sensitive-paths" "fbca04" "Developer allows an AI PR to touch protected paths"
# Status – mostly set by automation
label "needs-triage"          "fef2c0" "Waiting for the triage agent"
label "ai:needs-info"         "fbca04" "The AI asked the reporter a question"
label "ai:in-progress"        "c4b5fd" "An agent is working on this"
label "ai:pr-open"            "a78bfa" "The AI opened a pull request"
label "ai:needs-human"        "d93f0b" "The AI handed this back to a developer"
label "ai:failed"             "b60205" "The AI could not complete this"
label "ai:automerged"         "5319e7" "Merged by the Merge Gate"
label "ready-for-review"      "0e8a16" "Waiting for a developer review"
label "blocked"               "000000" "Blocked by something else"
# Meta
label "priority:low"          "c2e0c6" "Low priority"
label "priority:medium"       "fef2c0" "Medium priority"
label "priority:high"         "f9d0c4" "High priority"
label "size:s"                "ededed" "Small – under an hour"
label "size:m"                "d4c5f9" "Medium – a few hours"
label "size:l"                "bfd4f2" "Large – needs a developer's go-ahead for AI"
label "source:github"         "ededed" "Reported on GitHub"
label "source:slack"          "4a154b" "Reported from Slack"

# Remove GitHub's default labels we don't use (ignore if already gone).
for l in bug documentation duplicate enhancement "good first issue" "help wanted" invalid question wontfix; do
  gh label delete "$l" --repo "$REPO" --yes >/dev/null 2>&1 && echo "  – removed default '$l'" || true
done
