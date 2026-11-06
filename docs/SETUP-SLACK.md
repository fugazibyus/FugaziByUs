# Slack setup

The **Fugazi AI** Slack app does three things:

| What         | How                                                                                                                                                                                                                                                     |
| ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Intake**   | `/fugazi bug …`, `/fugazi feature …`, `/fugazi research …`, or **Send to Fugazi AI** from any message's ⋮ menu. Each creates a GitHub issue (`source:slack`, `flow:ai`) and the triage agent takes it from there. The reporter gets a DM when it's done |
| **Commands** | `/fugazi status` (anyone) · `/fugazi pause` (anyone in `config/team.php`) · `/fugazi release`, `/fugazi deploy production`, `/fugazi resume` (developers)                                                                                               |
| **Alerts**   | New issues, agent runs, PRs that need a human, auto-merges, releases, budget and failures, contact form messages. Routing is in `config/ai-workflow.php`                                                                                                |

Alerts work as soon as the app has a bot token, even locally. The slash command and the shortcut need Slack to reach the site, so they work once it's online (phase 8).

## 1. Create the Slack app

1. https://api.slack.com/apps → **Create New App** → **From a manifest** → pick your workspace.
2. Paste `slack/manifest.yml` → **Create**. (It points at `fugazibyus.example.com` until the site is online; you change that in step 6.)
3. **Install to Workspace** → Allow.
4. Copy two values into `.env` (locally) or the server's `.env`:
    - **OAuth & Permissions → Bot User OAuth Token** (`xoxb-…`) → `SLACK_BOT_TOKEN`
    - **Basic Information → Signing Secret** → `SLACK_SIGNING_SECRET`

    Then `make restart` locally, so the queue worker picks them up.

5. Create the channels (or rename them in `.env` with `SLACK_CHANNEL_BUGS=…` etc.): `#fugazi-bugs`, `#fugazi-features`, `#fugazi-research`, `#fugazi-ai-activity`, `#fugazi-reviews`, `#fugazi-deploys`, `#fugazi-alerts`, `#fugazi-contact`. The app can post to public channels without an invite; invite it (`/invite @Fugazi AI`) to private ones.
6. **Once the site is online:** in the app's settings, change `fugazibyus.example.com` to your host in **Slash Commands → /fugazi** and **Interactivity & Shortcuts → Request URL**. Slack checks the URLs when you save.

## 2. Who's who

Edit `config/team.php`: one entry per person with their GitHub username, whether they're a developer, and whether they're on duty (on-duty developers get the "this PR needs a human review" DMs). Put each Slack member ID in `.env` (Slack → profile → ⋮ → **Copy member ID**):

```bash
TEAM_SLACK_SGOODWIN10=U012AB3CD
```

A developer's Slack reports count as developer issues: the AI starts on S/M issues straight away, and the Merge Gate may merge the result. Anyone else's reports wait for a developer's `ai:go`.

## 3. GitHub → app webhook (alerts about issues, PRs and the gates)

Once the site is online:

1. Make a secret: `openssl rand -hex 32`. Put it in the server's `.env` as `GITHUB_WEBHOOK_SECRET`.
2. GitHub → repo **Settings → Webhooks → Add webhook**:
    - Payload URL: `https://<your host>/webhooks/github`
    - Content type: `application/json`, Secret: the value above
    - Events: **Let me select** → **Issues**, **Issue comments**, **Pull requests**

## 4. Developer commands from Slack (release, deploy, pause, resume)

These reach GitHub Actions as `repository_dispatch` events. Because the agents' token could send the same events, the app signs each one and the workflows check the signature.

1. Make another secret: `openssl rand -hex 32`. Put it in the server's `.env` as `AI_DISPATCH_SECRET`, and in GitHub:

    ```bash
    gh secret set AI_DISPATCH_SECRET --repo fugazibyus/FugaziByUs
    ```

2. Pause and resume change repository variables, which needs one more permission on the **Fugazi Merge Gate** App: its settings → **Permissions & events → Repository permissions → Variables: Read and write** → Save, then accept the new permission on the installation (Settings → GitHub Apps → Fugazi Merge Gate → **Review request**).

You can also pause and resume without Slack: Actions → **AI · pause and resume** → Run workflow.

## Alert routing

| Event                                | Channel                          | DM                |
| ------------------------------------ | -------------------------------- | ----------------- |
| New bug / feature / research         | bugs / features / research       |                   |
| Agent started / finished (with cost) | ai-activity (threaded per issue) |                   |
| Agent ended badly                    | ai-activity + alerts             | on-duty developer |
| AI opened a PR                       | ai-activity                      |                   |
| Merge Gate: a human must review      | reviews (with the failed rules)  | on-duty developer |
| Merge Gate merged a PR               | ai-activity                      | the reporter      |
| `ai:needs-human` / `ai:failed`       | alerts                           | on-duty developer |
| Daily AI budget used up              | alerts                           | all developers    |
| Release PR opened / shipping         | deploys                          |                   |
| Deploy Gate: a developer must ship   | deploys (with the failed rules)  | all developers    |
| Issue done                           |                                  | the reporter      |
| Contact form message                 | contact                          |                   |

Change any of it in `config/ai-workflow.php`. A gate decision is announced once per commit, however often the gate re-checks.
