# GitHub setup

Repository: https://github.com/fugazibyus/FugaziByUs (public, in the free `fugazibyus` organisation).

Everything below was applied by `scripts/bootstrap-github.sh`. The script is safe to re-run, for example after you edit a ruleset file.

## One-time steps you do by hand

1. Create the organisation on github.com (free plan): https://github.com/account/organizations/new?plan=free
2. Let the GitHub CLI manage org teams and settings:

    ```bash
    gh auth refresh -h github.com -s admin:org
    ```

3. Run the bootstrap from the repo root:

    ```bash
    scripts/bootstrap-github.sh
    ```

## What the bootstrap sets up

| Area         | Setting                                                                                                                                                                                                                           |
| ------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Branches     | `main` = production, `develop` = staging and the **default branch** (new PRs target it)                                                                                                                                           |
| Merging      | `develop`: squash merges only. `main`: merge commits (release PRs keep history). Rebase merging is off. Branches are deleted after merge                                                                                          |
| Branch rules | Rulesets in `.github/rulesets/*.json`: no direct pushes or force-pushes, PR required, 1 approval from a code owner, stale approvals dismissed, conversations resolved, checks **CI passed** + **Guardrails** green and up to date |
| Admin bypass | Repo admins may merge a PR without an approval (`pull_request` bypass only, never a direct push). This lets a solo developer merge their own PRs; remove it once there are two or more devs                                       |
| Team         | `@fugazibyus/devs`: the people who approve and merge (CODEOWNERS). You're a maintainer                                                                                                                                            |
| Security     | Secret scanning + push protection (blocks committing keys), Dependabot alerts                                                                                                                                                     |
| Actions      | Workflow token is read-only by default. Actions **cannot approve PRs**                                                                                                                                                            |
| Environments | `staging` (deploys from `develop`) and `production` (deploys from `main`, needs a `@devs` approval until the Deploy Gate exists)                                                                                                  |
| Variables    | `AI_AUTOMERGE_MODE=devs-only`, `AI_AUTODEPLOY_STAGING=on`, `AI_AUTODEPLOY_PRODUCTION=business-hours`, `AI_MAX_AUTOMERGE_SIZE=m`, `AI_DAILY_BUDGET_USD=25`, `AI_MAX_CONCURRENT_SESSIONS=3`, `AI_AGENTS=on`                         |
| Labels       | 30 labels for type, flow, control, status, priority, size and source (`scripts/bootstrap-labels.sh`)                                                                                                                              |

## Checks on every pull request

| Check                          | What it does                                                                                                           |
| ------------------------------ | ---------------------------------------------------------------------------------------------------------------------- |
| **CI passed**                  | Summary of the CI jobs below. The only CI check the rules require, so jobs can change freely                           |
| ↳ PHP style & static analysis  | Pint + PHPStan                                                                                                         |
| ↳ Frontend lint, types & build | Vite+ lint/format, vue-tsc, production build                                                                           |
| ↳ PHP tests (MySQL)            | PHPUnit against a real MySQL 8.4 database                                                                              |
| ↳ Browser smoke tests          | Playwright: homepage, register → name → logout, failed login, contact form (desktop + mobile)                          |
| **Guardrails**                 | Branch naming, allowed base branch, and protected paths for `ai/*` branches (`.github/guardrails/protected-paths.txt`) |

## Branch naming

| Who     | Pattern                                                                                                    | Into      |
| ------- | ---------------------------------------------------------------------------------------------------------- | --------- |
| Humans  | `feature/<issue>-short-name`, `fix/…`, `chore/…`, `docs/…`, `refactor/…`, `test/…` (issue number optional) | `develop` |
| Humans  | `hotfix/short-name`                                                                                        | `main`    |
| AI      | `ai/<feature\|fix\|research\|chore\|docs>/<issue>-short-name` (issue number required)                      | `develop` |
| Release | `develop`                                                                                                  | `main`    |

## Issues

- Issue forms: **Bug**, **Feature** and **Research**, written for non-developers. Blank issues are off.
- Each form asks "Who should handle this?". The **Issue intake** workflow turns the answer into `flow:ai` or `flow:human`.
- The triage agent picks up every new issue, and the developer or researcher follows when the issue qualifies. See `docs/SETUP-AI.md` §6.

## Day-to-day git (humans)

```bash
git switch develop && git pull
git switch -c feature/12-dark-mode
# …work, commit…
make check
git push -u origin HEAD
gh pr create --fill          # targets develop automatically
```

Releases: open a PR from `develop` to `main` (`gh pr create --base main --head develop`).
