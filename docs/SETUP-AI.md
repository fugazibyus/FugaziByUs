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
- Each run has a hard dollar cap (`budget_usd` in the agent file): triage $1, reviewer $3, debugger $5, researcher $5, release $2, developer $15.
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

| When                                                                     | Agent                                            | Workflow               |
| ------------------------------------------------------------------------ | ------------------------------------------------ | ---------------------- |
| An issue is opened                                                       | triage                                           | `issue-intake.yml`     |
| The reporter replies to an issue labelled `ai:needs-info`                | triage (again)                                   | `ai-commands.yml`      |
| Triage finishes an issue that qualifies (below)                          | developer, or researcher                         | `ai-issues.yml`        |
| CI passes on an `ai/*` PR                                                | reviewer (once per commit)                       | `ai-pull-requests.yml` |
| CI fails on an `ai/*` PR                                                 | debugger (2 attempts max, then `ai:needs-human`) | `ai-pull-requests.yml` |
| A person adds `ai:review` to any PR                                      | reviewer                                         | `ai-pull-requests.yml` |
| Mon–Thu 07:00 UTC, if develop is ahead of main and no release PR is open | release                                          | `ai-release.yml`       |
| `/ai …` comment                                                          | see below                                        | `ai-commands.yml`      |
| An agent file is merged into develop                                     | `ant apply` + lock file PR                       | `ai-agents-sync.yml`   |
| Every 30 minutes                                                         | fresh GitHub token for running agents            | `ai-token-refresh.yml` |

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

`/ai merge` and `/ai deploy` arrive with the Merge and Deploy Gates (phase 5b).

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
