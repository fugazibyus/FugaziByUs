# AI agents setup (Claude Managed Agents)

The six agents (triage, researcher, developer, debugger, reviewer, release) are defined in `agents/<name>/agent.md`. The frontmatter holds the model, tools and budget, and the body is the prompt. `ant apply` creates them in your Anthropic workspace, and `claude-lock.json` records their IDs. The same files generate the local Claude Code subagents.

## 1. Anthropic account and CLI

1. In the Claude Console (https://platform.claude.com), create a workspace called **fugazibyus** and give it a monthly spend limit (Settings → Limits).
2. Install the CLI and log in. This opens your browser; pick the `fugazibyus` workspace:

    ```bash
    brew install anthropics/tap/ant
    ant auth login
    ant auth status
    ```

    The local Docker app uses this login automatically (`~/.config/anthropic` is shared with the `app` container), so no API key goes in `.env`.

3. For GitHub Actions, create an API key in that workspace. See [section 6](#6-automation-in-github-actions).

## 2. The agents' GitHub identity: the **Fugazi AI** GitHub App

The agents push branches, open PRs and comment as a GitHub App, so their work shows up as `fugazi-ai[bot]`. An App is owned by the organisation, not a person, so there's no account, password or 2FA to look after. It doesn't use a paid seat, and its tokens last only an hour.

- **Short-lived tokens:** each agent run gets its own token (`App\Ai\GitHubAppTokenIssuer`). The token works on this repository only and has only the permissions in the table below.
- **Long runs:** the scheduler renews the token every 30 minutes for runs that go past an hour (`ai:refresh-github-token`).
- **It can't approve or merge:** the App isn't in `@devs`, so its reviews never count as a code-owner approval. It isn't on the ruleset bypass list, so it can't merge. That job belongs to the separate **Fugazi Merge Gate** App.

1. Create the App under the organisation: github.com/organizations/fugazibyus/settings/apps → **New GitHub App**.
    - Name: **Fugazi AI**. App names are unique across GitHub. If it's taken, pick another name; nothing in the code depends on it.
    - Homepage URL: `https://github.com/fugazibyus/FugaziByUs`.
    - Webhook: untick **Active**. The App only needs tokens, not events.
    - Repository permissions:

        | Permission      | Access         | Why                                                             |
        | --------------- | -------------- | --------------------------------------------------------------- |
        | Contents        | Read and write | Clone, push `ai/*` branches                                     |
        | Pull requests   | Read and write | Open PRs, post reviews                                          |
        | Issues          | Read and write | Comment, labels                                                 |
        | Actions         | Read-only      | Read failing CI logs (debugger)                                 |
        | Checks          | Read-only      | `gh pr checks`                                                  |
        | Commit statuses | Read-only      | See check results                                               |
        | Metadata        | Read-only      | Required (set automatically)                                    |
        | **Workflows**   | **No access**  | Leave this off: the agents can never change `.github/workflows` |

    - Where can this App be installed: **Only on this account**. Then **Create GitHub App**.
2. On the App's page, note the **App ID**, then click **Generate a private key**. A `.pem` file downloads.
3. Click **Install App** → `fugazibyus` → **Only select repositories → FugaziByUs** → Install.
4. Give the app the key. `storage/app/private/` is git-ignored:

    ```bash
    mv ~/Downloads/fugazi-ai.*.private-key.pem storage/app/private/fugazi-ai.pem
    ```

    In `.env`: `AI_GITHUB_APP_ID=<the App ID>` (and `AI_GITHUB_APP_PRIVATE_KEY_PATH`, which `.env.example` already sets).

5. GitHub Actions needs the same two values ([section 6](#6-automation-in-github-actions)). Production gets them too (phase 8), so its scheduler can renew tokens for long runs.

The private key is the only long-lived secret. It never enters the agent sandbox: the app mints a token from it, and that token reaches the sandbox only through the repository mount and the vault. To rotate the key, generate a new one on the App's page, swap the file and the secret, then delete the old key.

## 3. Create the agents in Anthropic

```bash
make ai-plan      # dry run: shows what will be created
make ai-apply     # creates agents, environments and the vault; asks (y/n) first
make ai-github-token  # checks the App setup and puts a first token in the vault
make ai-agents    # lists each agent with its Anthropic ID
```

Commit the updated `claude-lock.json`.

## 4. Using the agents

| Where                     | How                                                                                                                                                                                                                 |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Cloud, from your terminal | `make ai-run agent=triage issue=12`, `make ai-run agent=developer issue=12`, `make ai-run agent=reviewer pr=34 branch=ai/feature/12-x`, `make ai-run agent=researcher msg="Which analytics tools respect privacy?"` |
| Watch a cloud run         | The Console link that `ai:start` prints, or `ant beta:sessions connect <session-id>`                                                                                                                                |
| Locally in Claude Code    | `/triage 12`, `/develop 12`, `/review 34`, `/debug <error>`, `/research <question>`, `/release`                                                                                                                     |
| GitHub                    | Automatic, plus `/ai …` comments: see section 6                                                                                                                                                                     |

## 5. Editing an agent

1. Edit `agents/<name>/agent.md` on a branch: the prompt, the model, the `budget_usd` metadata or the tools.
2. Run `make ai-sync` to refresh the local subagents. CI fails if you forget.
3. Open a PR. Agent files are code-owned, so a developer must review the change.
4. After merging, the **AI · sync agents** workflow runs `ant apply`. The agent gets a new version; running sessions keep their old one. `ant apply` records the new version in `claude-lock.json`, so the workflow opens a one-file PR, `chore/sync-claude-lock`. Merge it.

## Costs and limits

- At most `AI_MAX_CONCURRENT_SESSIONS` (default 3) agents work at once. A start waits up to 20 minutes for a free slot, then gives up with a comment.
- `make ai-stop issue=12` (or `pr=34`, or `all=1`) interrupts running agents. On GitHub, comment `/ai stop`.
- Each run has a hard dollar cap (`budget_usd` in the agent file): triage $2, reviewer $6, debugger $10, researcher $10, release $4, developer $30.
- `AI_DAILY_BUDGET_USD` (default $25) stops new runs once today's spend reaches it.
- The workspace spend limit in the Console is the final backstop.

## 6. Automation in GitHub Actions

### One-time setup

Add these in GitHub: **Settings → Secrets and variables → Actions**.

| Name                        | Kind     | Value                                                                                                                                   |
| --------------------------- | -------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| `ANTHROPIC_API_KEY`         | Secret   | Console → `fugazibyus` workspace → API keys → **Create key** (name it `github-actions`). It must be in the same workspace as the agents |
| `AI_GITHUB_APP_PRIVATE_KEY` | Secret   | The whole `.pem` file, including the `BEGIN`/`END` lines                                                                                |
| `AI_GITHUB_APP_ID`          | Variable | The Fugazi AI App ID                                                                                                                    |

From the terminal:

```bash
gh secret set ANTHROPIC_API_KEY --repo fugazibyus/FugaziByUs          # paste the key when asked
gh secret set AI_GITHUB_APP_PRIVATE_KEY --repo fugazibyus/FugaziByUs < storage/app/private/fugazi-ai.pem
gh variable set AI_GITHUB_APP_ID --repo fugazibyus/FugaziByUs --body "<the App ID>"
gh variable set AI_MAX_CONCURRENT_SESSIONS --repo fugazibyus/FugaziByUs --body 3
gh variable set AI_AGENTS --repo fugazibyus/FugaziByUs --body on
```

To check it works: Actions → **AI · agent** → Run workflow → agent `triage`, issue `2`.

### What starts each agent

| When                                                                        | Agent                                            | Workflow               |
| --------------------------------------------------------------------------- | ------------------------------------------------ | ---------------------- |
| An issue is opened                                                          | triage                                           | `issue-intake.yml`     |
| The reporter replies to an issue labelled `ai:needs-info`                   | triage (again)                                   | `ai-commands.yml`      |
| Triage finishes an issue that qualifies (below)                             | developer, or researcher                         | `ai-issues.yml`        |
| CI passes on an `ai/*` PR (or it's marked ready after CI passed as a draft) | reviewer (once per commit)                       | `ai-pull-requests.yml` |
| CI fails on an `ai/*` PR                                                    | debugger (2 attempts max, then `ai:needs-human`) | `ai-pull-requests.yml` |
| A person adds `ai:review` to any PR                                         | reviewer                                         | `ai-pull-requests.yml` |
| Mon–Thu 08:30 Sydney, if develop is ahead of main and no release PR is open | release                                          | `ai-release.yml`       |
| `/ai …` comment                                                             | see below                                        | `ai-commands.yml`      |
| An agent file is merged into develop                                        | `ant apply` + lock file PR                       | `ai-agents-sync.yml`   |
| Every 30 minutes                                                            | fresh GitHub token for running agents            | `ai-token-refresh.yml` |

**An issue qualifies for the developer or researcher** when it's open, labelled `flow:ai`, has a type and a size, and triage is done (no `needs-triage` or `ai:needs-info`). Nothing may be holding it: `ai:in-progress`, `ai:pr-open`, `ai:stop`, `ai:needs-human`, `ai:failed`, `blocked` or `flow:human`. Then:

- **Size S or M, reported by a collaborator:** the agent starts straight away.
- **Size L, or an outside reporter:** it waits until a person adds `ai:go`. A label added by a bot doesn't count.

Every start, and every start that couldn't happen (budget used up, too many agents running, an error), is posted as a comment on the issue or PR with a link to the session.

### `/ai` commands

Only repository collaborators can use these. Write the command at the start of a comment. Any text after it goes to the agent as extra instructions.

| Command                | On         | Does                                                                                                         |
| ---------------------- | ---------- | ------------------------------------------------------------------------------------------------------------ |
| `/ai triage`           | issue      | Re-run triage                                                                                                |
| `/ai develop [notes]`  | issue      | Start the developer (the researcher for research issues). Clears `ai:stop`, `ai:failed` and `ai:needs-human` |
| `/ai research [notes]` | issue      | Start the researcher                                                                                         |
| `/ai fix [hint]`       | PR         | Start the debugger. Doesn't count towards the 2 automatic attempts                                           |
| `/ai review [focus]`   | PR         | Start the reviewer                                                                                           |
| `/ai release`          | anywhere   | Open or refresh the release PR                                                                               |
| `/ai stop`             | issue / PR | Add `ai:stop` and interrupt the agents working on it                                                         |
| `/ai help`             | anywhere   | List the commands                                                                                            |

Developers can also comment `/ai merge` on a PR, or `/ai deploy production` on the release PR, to ask the gates (section 7).

### Switches

| Variable                     | Effect                                                               |
| ---------------------------- | -------------------------------------------------------------------- |
| `AI_AGENTS=off`              | No agent starts from GitHub. `/ai stop` still works                  |
| `AI_DAILY_BUDGET_USD`        | Circuit breaker: no new sessions once today's spend (UTC) reaches it |
| `AI_MAX_CONCURRENT_SESSIONS` | How many agents may work at the same time                            |

### Why it's safe to give Actions these keys

- Every AI workflow runs the code on `develop`, never code from a pull request (`workflow_run`, `pull_request_target`, and an explicit checkout of the default branch). An AI branch can't change what runs next to the keys.
- The agents' App has no `workflows` permission, and the Guardrails check fails any `ai/*` PR that touches `.github/`.
- The agent sandbox only gets a one-hour token for this repository. The API key and the App's private key never leave Actions.
- Comment and issue text reaches the agents through environment variables, never pasted into a script.

## 7. The Merge Gate and the Deploy Gate

The AI can merge into `develop` and ship to production, but only through two gates. They are plain, tested code, not a prompt: `app/Gates/MergePolicy.php` and `app/Gates/DeployPolicy.php` (tests in `tests/Unit/Gates`). Each gate keeps one comment on the PR listing every rule with ✅, ⏳ or ❌, so anyone can see why it did or didn't act.

### Create the Fugazi Merge Gate App

The gates act as a second GitHub App. It's the only App on the branch rules' bypass list, and the agents never get its key.

1. github.com/organizations/fugazibyus/settings/apps → **New GitHub App**.
    - Name: **Fugazi Merge Gate** (any free name works).
    - Homepage URL: `https://github.com/fugazibyus/FugaziByUs`.
    - Webhook: untick **Active**.
    - Repository permissions:

        | Permission    | Access         | Why                                                     |
        | ------------- | -------------- | ------------------------------------------------------- |
        | Contents      | Read and write | Merge pull requests, update branches                    |
        | Pull requests | Read and write | Read PRs and reviews, merge                             |
        | Issues        | Read and write | The gate comment and the `ai:automerged` label          |
        | Checks        | Read-only      | Is CI green?                                            |
        | Deployments   | Read-only      | When was production last deployed, and did it work?     |
        | Metadata      | Read-only      | Required                                                |
        | **Workflows** | **No access**  | PRs that change workflows are always merged by a person |

    - Organization permissions: **Members: Read-only** (to check who's in `@devs`).
    - Where can this App be installed: **Only on this account** → **Create GitHub App**.
2. Note the **App ID**, then **Generate a private key**.
3. **Install App** → `fugazibyus` → **Only select repositories → FugaziByUs** → Install.
4. Give GitHub Actions the ID and key, then put the App on the bypass list (re-running the bootstrap is safe):

    ```bash
    gh variable set MERGE_GATE_APP_ID --repo fugazibyus/FugaziByUs --body "<the App ID>"
    gh secret set MERGE_GATE_APP_PRIVATE_KEY --repo fugazibyus/FugaziByUs < ~/Downloads/fugazi-merge-gate.*.private-key.pem
    scripts/bootstrap-github.sh
    ```

5. Delete the downloaded `.pem`; GitHub holds the only copy you need.

Until `MERGE_GATE_APP_ID` is set, both gates stay switched off and developers merge as before.

### Merge Gate (into `develop`)

It runs when CI finishes, when the reviewer posts a verdict, and when labels change, but only for `ai/*` branches and the `chore/sync-claude-lock` PR. An AI pull request merges by itself only if **every** rule passes:

| Rule                              | Detail                                                                                                                                                             |
| --------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| AI merging is on                  | `AI_AUTOMERGE_MODE` is `devs-only` (default) or `all`                                                                                                              |
| Someone stands behind it          | `devs-only`: the issue was reported by a `@devs` or `@product` member, or one of them added `ai:automerge` or commented `/ai merge` (`ai:go` only starts the work) |
| CI and Guardrails are green       | On the PR's latest commit                                                                                                                                          |
| The reviewer approved this commit | Verdict `approve`, risk low or medium, 0 blocking. Only reviews written by the Fugazi AI App count                                                                 |
| Small enough                      | Size label ≤ `AI_MAX_AUTOMERGE_SIZE` (default M)                                                                                                                   |
| Nothing sensitive                 | No paths from `.github/guardrails/protected-paths.txt`, no migrations, no workflow changes                                                                         |
| Not held                          | No `ai:no-automerge`, `blocked`, `deploy:hold`, `ai:stop`, `ai:needs-human`; not a draft                                                                           |
| Up to date                        | If `develop` moved on, the gate updates the branch first; CI and the reviewer then check the new commit                                                            |

It squash-merges, links the issue and the agent sessions in the commit message, and labels the PR and issue `ai:automerged`.

**`/ai merge`** (developers, on any PR into `develop`): the developer is the approval, so the gate only checks CI, Guardrails, hold labels, workflow changes and that the branch is up to date. The gate re-reads the comment to prove who asked. If the gate first has to wait (CI, the reviewer) or bring the branch up to date with `develop`, the request is kept in the gate comment and the next check still honours it. It lapses if anyone other than the gate pushes to the branch, or a check fails, so ask again after that.

**Product can ship to staging, not production.** Bugs and small features reported by members of the `@product` team (`GATE_PRODUCT_TEAM`, Triage role on the repo) go all the way to staging on their own, like a developer's. They can also comment `/ai merge` or add `ai:automerge` on an AI pull request into `develop` for someone else's issue. Either way that only counts as "someone stands behind it", but every other rule above still applies, so they can't skip the reviewer, merge a human branch, or merge anything large or sensitive. Once it's on `develop`, staging deploys by itself. They can't use `/ai merge` or `/ai deploy production` on the release PR: production stays with `@devs` and the Deploy Gate.

### Deploy Gate (release PR `develop` → `main`)

It re-checks every 30 minutes while a release PR is open, and whenever `develop`'s CI finishes or labels or approvals change. It merges the release (which deploys production, from phase 8) only if **every** rule passes:

| Rule                          | Detail                                                                                                                                       |
| ----------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| Inside the deploy window      | `AI_AUTODEPLOY_PRODUCTION=business-hours` (default): Mon–Thu 09:00–16:00 in `AI_TIMEZONE` (Australia/Sydney). `on` = any time, `off` = never |
| Staging runs this release     | `STAGING_URL/up` is healthy and `STAGING_URL/version` reports the release commit, deployed ≥ 30 minutes ago                                  |
| Every released PR was checked | Each was merged by a person or by the Merge Gate, and none has `deploy:hold`                                                                 |
| Migrations need a developer   | If the release contains migrations, a `@devs` member must approve the release PR                                                             |
| No recent or failed deploy    | No production deploy in the last 60 minutes, and the last one succeeded                                                                      |
| CI green, not held            | CI passed + Guardrails, no `deploy:hold`, not a draft                                                                                        |

**`/ai deploy production`** (developers only, on the release PR) ships now: only CI and `deploy:hold` still apply.

Until phase 8 sets `STAGING_URL`, the Deploy Gate always says a developer needs to ship, because there's no staging to check against.

### Switches

| Variable                   | Values                          | Default            |
| -------------------------- | ------------------------------- | ------------------ |
| `AI_AUTOMERGE_MODE`        | `off` · `devs-only` · `all`     | `devs-only`        |
| `AI_MAX_AUTOMERGE_SIZE`    | `s` · `m` · `l`                 | `m`                |
| `AI_AUTODEPLOY_PRODUCTION` | `off` · `business-hours` · `on` | `business-hours`   |
| `AI_TIMEZONE`              | Any time zone name              | `Australia/Sydney` |

Setting `AI_AUTOMERGE_MODE=off` and `AI_AUTODEPLOY_PRODUCTION=off` returns everything to "a person merges and ships". A developer can always merge any PR by hand, whatever the gates say.

## 8. Metrics: the "AI at work" widget

The homepage widget shows real numbers: spend, tokens, runs by agent, AI pull requests, auto-merges, and the average time from issue to PR. They come from two tables:

| Table              | Filled from                                                                                          | How                                                             |
| ------------------ | ---------------------------------------------------------------------------------------------------- | --------------------------------------------------------------- |
| `ai_runs`          | Claude Managed Agents: one row per session (tokens, list cost, active time, the agent's JSON result) | Anthropic webhooks (live), plus `ai:sync-runs` every 10 minutes |
| `ai_pull_requests` | GitHub: every `ai/*` pull request (opened, merged, auto-merged, linked issue)                        | `ai:sync-runs` every 10 minutes                                 |

A run counts as **succeeded** when the agent finished with a result whose outcome isn't `needs_human`, `failed` or `blocked`. A session that ends with no result (stopped, out of budget) counts as unsuccessful.

### Locally

Webhooks can't reach your laptop, so pull the numbers yourself:

```bash
make ai-metrics       # sync sessions from Anthropic and PRs from GitHub
make ai-demo          # add a month of demo activity (marked as demo rows)
make ai-demo-clear    # remove the demo rows, keep the real ones
```

The `scheduler` container also runs the sync every 10 minutes.

### Hosted: the Anthropic webhook

Once the site has a public URL (phase 8):

1. Claude Console → `fugazibyus` workspace → **Webhooks** → **Add endpoint**.
2. URL: `https://<your host>/webhooks/anthropic`. Events: all **session** events.
3. Copy the signing secret (`whsec_…`) into the server's `.env` as `ANTHROPIC_WEBHOOK_SIGNING_KEY`.

The endpoint checks every signature and rejects anything unsigned. The event only names the session, so a queued job (`SyncAgentRun`) fetches the details.
