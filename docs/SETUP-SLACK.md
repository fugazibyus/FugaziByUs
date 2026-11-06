# Slack setup

Slack and GitHub talk to each other directly; the website isn't in the middle.

| Direction          | What                                                                                                                                             | Tool                                                 |
| ------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------ | ---------------------------------------------------- |
| **Slack → GitHub** | Report a bug, idea or question: **⋮ → Create issue** on any message, or `/github open fugazibyus/FugaziByUs`                                     | GitHub's official **GitHub** app for Slack           |
| **GitHub → Slack** | New issues, PRs, comments, reviews, merges: each channel subscribes to what it needs, threaded per issue/PR                                      | GitHub app subscriptions (`/github subscribe`)       |
| **GitHub → Slack** | The AI's own news: agent started, the Merge/Deploy Gate's decisions (with the rules that failed), budget used up, debugger gave up, pause/resume | GitHub Actions post with the **Fugazi AI** bot token |

An issue created from Slack has no form answers, so the Issue intake workflow labels it `flow:ai`, `needs-triage` and `source:slack`, and the triage agent works out the type, size and route. The issue's author is the reporter's own GitHub account (the GitHub app asks everyone to `/github signin` once), so the usual rules apply: a collaborator's S/M issue starts straight away, anyone else's waits for a developer's `ai:go`.

## 1. Channels

Create these (public is simplest): `#fugazi-bugs`, `#fugazi-features`, `#fugazi-research`, `#fugazi-ai-activity`, `#fugazi-reviews`, `#fugazi-deploys`, `#fugazi-alerts`, `#fugazi-contact`.

## 2. The GitHub app for Slack (intake and GitHub's own notifications)

1. Install it: https://slack.github.com → **Add to Slack**, and allow it on the `fugazibyus` organisation (only the FugaziByUs repository is enough).
2. Everyone who reports: `/github signin` once in Slack.
3. In each channel, `/invite @GitHub`, then paste its subscriptions. The app starts each channel with a default set (issues, pulls, commits, releases, deployments), so each block first removes what that channel doesn't need.

    **#fugazi-bugs** (and the same for **#fugazi-features** with `type:feature`, **#fugazi-research** with `type:research`):

    ```
    /github subscribe fugazibyus/FugaziByUs issues comments +label:"type:bug"
    /github unsubscribe fugazibyus/FugaziByUs pulls commits releases deployments
    ```

    **#fugazi-ai-activity** (everything the AI touches; updates are threaded under each issue/PR):

    ```
    /github subscribe fugazibyus/FugaziByUs issues pulls comments reviews +label:"flow:ai"
    /github unsubscribe fugazibyus/FugaziByUs commits releases deployments
    ```

    **#fugazi-reviews** (PRs waiting for a person):

    ```
    /github subscribe fugazibyus/FugaziByUs pulls reviews +label:"ready-for-review"
    /github unsubscribe fugazibyus/FugaziByUs issues commits releases deployments
    ```

    **#fugazi-alerts** (anything the AI handed back to a person):

    ```
    /github subscribe fugazibyus/FugaziByUs issues pulls +label:"ai:needs-human"
    /github unsubscribe fugazibyus/FugaziByUs commits releases deployments
    ```

    **#fugazi-deploys** (releases, deployments and the workflows that ship):

    ```
    /github subscribe fugazibyus/FugaziByUs releases deployments workflows:{name:"Deploy Gate","Deploy"}
    /github unsubscribe fugazibyus/FugaziByUs issues pulls commits
    ```

    Issues created from Slack get their labels a few seconds after they're opened, so in the label-filtered channels they first appear with triage's comment.

    `/github subscribe list features` in a channel shows what it gets. People also get personal DMs from the GitHub app (review requests, mentions, their own PRs) after `/github signin`.

## 3. The Fugazi AI bot (the AI's own alerts and the contact form)

1. https://api.slack.com/apps → **Create New App** → **From a manifest** → your workspace → paste `slack/manifest.yml` → **Create** → **Install to Workspace**.
2. Copy **OAuth & Permissions → Bot User OAuth Token** (`xoxb-…`) to GitHub, and to the site's `.env` (for contact form messages):

    ```bash
    gh secret set SLACK_BOT_TOKEN --repo fugazibyus/FugaziByUs
    ```

    Locally: `SLACK_BOT_TOKEN=xoxb-…` in `.env`, then `make restart`.

3. Who gets DMs when something needs a person: the on-duty developer's Slack member ID (Slack → their profile → ⋮ → **Copy member ID**). Several IDs can go in, separated by spaces:

    ```bash
    gh variable set SLACK_ON_DUTY --repo fugazibyus/FugaziByUs --body "U012AB3CD"
    ```

4. Optional: different channel names. Set any of the repository variables `SLACK_CHANNEL_ACTIVITY`, `SLACK_CHANNEL_REVIEWS`, `SLACK_CHANNEL_DEPLOYS`, `SLACK_CHANNEL_ALERTS` (and `SLACK_CHANNEL_CONTACT` in the site's `.env`).

Private channels: `/invite @Fugazi AI` there too.

### What the bot posts

| When                                          | Where                                                |
| --------------------------------------------- | ---------------------------------------------------- |
| An agent starts (with a link to watch it)     | #fugazi-ai-activity                                  |
| An agent couldn't start (budget, error)       | #fugazi-alerts + DM on duty                          |
| The Merge Gate merged a PR                    | #fugazi-ai-activity                                  |
| The Merge Gate says a person must review a PR | #fugazi-reviews (with the failed rules) + DM on duty |
| The Deploy Gate ships, or needs a developer   | #fugazi-deploys (+ DM on duty when it needs one)     |
| The debugger gave up after 2 attempts         | #fugazi-alerts + DM on duty                          |
| Someone paused or resumed the AI              | #fugazi-alerts                                       |
| Contact form message (from the site)          | #fugazi-contact                                      |

A gate decision is posted once, when it changes, not on every re-check. Without `SLACK_BOT_TOKEN` the workflows simply skip these posts.

## 4. The kill switch

Actions → **AI · pause and resume** → Run workflow (the GitHub mobile app works too). Pause turns AI merging and AI production deploys off; resume turns them back on. It changes repository variables, so the **Fugazi Merge Gate** App needs one more permission: its settings → **Permissions & events → Repository permissions → Variables: Read and write** → Save, then approve the change on the installation (org Settings → GitHub Apps → Fugazi Merge Gate → **Review request**).
