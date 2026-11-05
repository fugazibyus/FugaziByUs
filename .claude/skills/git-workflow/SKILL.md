---
name: git-workflow
description: Branch naming, commits, pull request descriptions, labels and issue comments for the FugaziByUs human + AI workflow. Use when creating branches, committing, opening or updating PRs, or commenting on issues.
---

# Git workflow

## Branches

| Who     | Pattern                                                                            | From → into           |
| ------- | ---------------------------------------------------------------------------------- | --------------------- |
| AI      | `ai/<feature\|fix\|research\|chore\|docs>/<issue>-short-name`                      | `develop` → `develop` |
| Human   | `feature/<issue>-short-name`, `fix/…`, `chore/…`, `docs/…`, `refactor/…`, `test/…` | `develop` → `develop` |
| Human   | `hotfix/short-name`                                                                | `main` → `main`       |
| Release | `develop`                                                                          | → `main`              |

The short name is kebab-case, at most 5 words: `ai/feature/12-dark-mode-toggle`.

```bash
git fetch origin && git switch -c ai/feature/12-dark-mode-toggle origin/develop
```

## Commits

- Imperative summary, 72 characters at most ("Add dark mode toggle to header"), a blank line, then _why_, plus anything surprising.
- AI commits end with a `Co-Authored-By: Claude <noreply@anthropic.com>` trailer.
- Group related changes into a few meaningful commits rather than one per file.

## Pull requests (`gh pr create`)

- Base `develop`, title = the issue title in plain words, body from `.github/pull_request_template.md`:
    - **What & why**, with `Closes #<issue>`
    - **How it was tested**: tick only what you actually ran
    - **Screenshots** for UI changes (describe them if you can't attach images)
    - **Notes for reviewers**: risks, migrations, follow-ups
    - AI agents also fill the `ai-metadata` block (agent, session, issue).
- Labels: AI PRs get `flow:ai` and the issue's `type:*` label. Never add `ai:automerge` or `allow-sensitive-paths` yourself; only developers do.

## Issue comments

Keep them short, friendly and written for non-developers: what you did, what happens next, and a link to the PR. Use a checklist for multi-step work.

## Never

Push to `main`/`develop`, force-push someone else's branch, merge, approve your own PR, delete branches you didn't create, or rewrite published history.
