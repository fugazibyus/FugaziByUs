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

3. For GitHub Actions, create an API key in that workspace (Console → API keys) and add it as the repository secret `ANTHROPIC_API_KEY`. That's covered in phase 5.

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

5. Phase 5 adds the same two values to GitHub Actions: the variable `AI_GITHUB_APP_ID` and the secret `AI_GITHUB_APP_PRIVATE_KEY`, which holds the `.pem` contents. Production gets them too, so its scheduler can renew tokens for long runs.

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
| GitHub & Slack            | Phases 5 and 7                                                                                                                                                                                                      |

## 5. Editing an agent

1. Edit `agents/<name>/agent.md` on a branch: the prompt, the model, the `budget_usd` metadata or the tools.
2. Run `make ai-sync` to refresh the local subagents. CI fails if you forget.
3. Open a PR. Agent files are code-owned, so a developer must review the change.
4. After merging, run `make ai-apply` (phase 5 automates this). The agent gets a new version; running sessions keep their old one.

## Costs and limits

- Each run has a hard dollar cap (`budget_usd` in the agent file): triage $1, reviewer $3, debugger $5, researcher $5, release $2, developer $15.
- `AI_DAILY_BUDGET_USD` (default $25) stops new runs once today's spend reaches it.
- The workspace spend limit in the Console is the final backstop.
