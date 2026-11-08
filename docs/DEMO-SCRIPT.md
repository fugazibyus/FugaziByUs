# Demo script

A 30-minute walkthrough for the CEO, developers and the designer. It shows one real request going from Slack to staging while you talk, the guardrails that keep people in charge, and a production release.

**The one idea to land:** the AI does the work, plain tested rules decide what it may merge and ship, and people can step in or switch it off at any moment.

## Timings to plan around

Measured on this project with a small (size S) change:

| Step                                 | Usually     |
| ------------------------------------ | ----------- |
| Issue → triage done                  | 1 minute    |
| Triage → pull request open           | 5 minutes   |
| CI                                   | 3 minutes   |
| Reviewer verdict → Merge Gate merges | 3–5 minutes |
| Merge → live on staging              | 4 minutes   |
| Staging soak before the Deploy Gate  | 30 minutes  |

So a request reaches staging in about **15–20 minutes**, which is why Act 2 starts it first. Production can't be shown end to end in the same half hour (the soak alone is 30 minutes), so Act 5 uses a release prepared that morning.

## The day before

- [ ] **Deploy window:** the Deploy Gate ships Monday–Thursday in business hours (`AI_TIMEZONE`). Book the demo inside that window.
- [ ] **Switches on:** `gh variable list` shows `AI_AGENTS=on`, `AI_AUTOMERGE_MODE=devs-only`, `AI_AUTODEPLOY_STAGING=on`, `AI_AUTODEPLOY_PRODUCTION=business-hours`.
- [ ] **Budget:** today's spend is well under `AI_DAILY_BUDGET_USD` (homepage widget). A demo uses a few dollars.
- [ ] **Staging and production healthy:** `/up` returns 200 and `/version` shows the expected commits.
- [ ] **Slack:** the GitHub app and the Fugazi bot are in `#fugazi-features`, `#fugazi-ai-activity`, `#fugazi-reviews` and `#fugazi-deploys`. Post a test message so the channels are on screen.
- [ ] **Product account:** for Act 4, a GitHub account in the `@product` team (it has the Triage role on the repository), signed in on a second browser or device.
- [ ] **Rehearse once** with a throwaway request and keep its PR as the fallback (see [If something goes wrong](#if-something-goes-wrong)).

## The morning of

- [ ] Merge something small into `develop` (or let the agents do it) and comment `/ai release` on any issue before 9:30, so a release PR exists and staging has soaked by the time you present.
- [ ] Open these tabs, in order:
    1. Production homepage (the AI widget)
    2. `/ai-workflow` on production
    3. Slack, `#fugazi-features`
    4. GitHub → Issues
    5. GitHub → Pull requests
    6. GitHub → Actions
    7. Staging homepage
- [ ] Have the request ready to paste (Act 2).

---

## Act 1 · What this is (3 minutes)

**Show:** the production homepage, then `/ai-workflow`.

- "Everything on this site was built through the process I'm about to show you. Some of it by people, some by the AI, all of it through the same pull requests and checks."
- Point at the **AI at work** widget: spend, tokens, runs and pull requests, live from the agents.
- On `/ai-workflow`, open **Workflow**: ten steps, colour-coded by who acts. Point out the two purple steps, the **gates**. "These aren't AI. They're ordinary, tested code with rules we wrote down."

## Act 2 · A request from Slack (3 minutes, then it runs in the background)

**Show:** Slack, `#fugazi-features`.

1. Post the request as a message, for example:

    > Feature: on the AI Workflow page, add a "Back to top" button that appears after scrolling down, matching our design system. Size S.

2. Hover the message → **⋮ → Create issue** → FugaziByUs → submit.
3. Switch to the new GitHub issue. Within a minute the **triage agent** comments: type, priority, size and acceptance criteria, and labels it `flow:ai`.
4. "Because a developer asked, and it's small, the developer agent starts on its own. If it were big, or from outside the team, it would wait for a person."

Move on; come back to it in Act 3 and at the end.

## Act 3 · Watching it work (8 minutes)

**Show:** GitHub → Pull requests, then Actions, then `#fugazi-ai-activity`.

- About five minutes after triage, the **developer agent** opens a pull request on an `ai/feature/…` branch. Open it:
    - The description: what changed, how it was tested, the issue it closes.
    - The `ai-metadata` block links the agent's session, so every line can be traced back to the run that wrote it.
- **CI** runs the same checks as for any developer. If it fails, the **debugger agent** fixes the branch (twice at most, then it hands over).
- The **reviewer agent** posts a verdict: approve or request changes, with a risk level.
- Then the **Merge Gate** comments its decision with every rule it checked. Open `/ai-workflow#guardrails` side by side: green CI, reviewer approval on the latest commit, low or medium risk, small, no migrations, no protected files.

**For developers:** "You can do all of this locally too: `/develop 12` in Claude Code runs the same agent on your machine. And any AI branch can be taken over by checking it out and pushing."

**For the CEO:** open `/ai-workflow#agents` and expand one prompt. "This is the full prompt. It's a file in the repository; changing it is a pull request a developer must approve."

## Act 4 · Who may ship what (5 minutes)

**Show:** a second issue, from the product account, then `/ai-workflow#guardrails`.

1. The product team member opens a Feature issue (GitHub → New issue → Feature), for example "Make the footer links slightly larger on mobile." Size S.
2. Triage runs the same way, and the developer agent starts on its own, just as it did for the developer's request in Act 2. "Product can ship small and medium AI work to staging without waiting for a developer."
3. On `/ai-workflow#guardrails`, point at what still applies: "The Merge Gate checks the same rules as for a developer's request: green CI, the reviewer's approval, low or medium risk, size M at most, no migrations, no protected files. If any fails, the PR waits for a person."
4. Who decides what:
    - **Someone outside `@devs` and `@product`:** the agents still build it, but the Merge Gate refuses to merge until a developer or product member adds `ai:automerge` or comments `/ai merge`. The PR waits in `#fugazi-reviews`.
    - **Size L:** waits for a developer to add `ai:go` before any agent starts.
    - **Production:** product can't ship it. Only the Deploy Gate or a developer merges the release (Act 5).

Come back to this issue in Act 7: it should reach staging about 15–20 minutes after it was opened.

## Act 5 · Shipping to production (5 minutes)

**Show:** the release PR prepared this morning, then Actions → **Deploy**, then `#fugazi-deploys`.

1. The **release agent's** notes: highlights in plain language, every PR with who built it (human or AI), and the risks (migrations, new dependencies, security surface).
2. The **Deploy Gate** comment: deploy window, staging has run this exact commit for 30 minutes and is healthy, every PR in the release was merged by a developer or the Merge Gate, nothing on hold.
3. If it has merged: open the **Deploy** run. Production reuses the images staging ran, migrates, checks `/up` and `/version`, and **rolls back by itself** if the new version isn't healthy. Show the 🚢 message in `#fugazi-deploys` and `/version` on production.
4. If it hasn't merged yet, comment `/ai deploy production` to ask the gate to check now, and walk through its reasons.

## Act 6 · The off switch (2 minutes)

**Show:** the GitHub mobile app (or Actions in the browser).

- Actions → **AI · pause and resume** → Run workflow → pause. Slack posts that AI merging and production deploys are off. "From now on a person merges and ships everything. Agents can still help, but nothing lands without us."
- Resume it straight away.
- Mention the other controls: `/ai stop` on any issue, a hard budget per run and per day, and at most three agents at once.

## Act 7 · Back to the request, and questions (4 minutes)

**Show:** the staging homepage, then `/ai-workflow` on staging.

- By now the Slack request from Act 2 should be merged and live on staging (`#fugazi-deploys` or the PR's timeline shows it). Scroll down the page and click the new button. If the product request from Act 4 has landed too, show it on a phone-width window.
- "About twenty minutes from a Slack message to a reviewed, tested change on staging, with nobody typing code."
- For questions, `/ai-workflow#faq` has answers by role. Common ones:
    - **Cost:** about $29 a month of hosting plus AI usage; this demo cost a few dollars (show the widget).
    - **Quality:** the same CI, review and conventions as people; the reviewer agent plus a human for anything risky.
    - **Security:** agents never see server credentials or production; their GitHub token lasts an hour.

---

## If something goes wrong

| Problem                             | What to do                                                                                                          |
| ----------------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Triage hasn't run after 3 minutes   | Check Actions → **Issue intake** and **AI · agent**. Comment `/ai triage` to start it again                         |
| The developer agent is slow         | Carry on with Acts 4–6 and come back. Or show the rehearsal PR                                                      |
| CI fails                            | Good material: show the debugger agent fixing it                                                                    |
| The Merge Gate refuses unexpectedly | Read its comment aloud: that's the point of the gate. Comment `/ai merge` after fixing the reason, or merge by hand |
| "Today's AI budget is used up"      | `gh variable set AI_DAILY_BUDGET_USD --body 40` and comment the `/ai …` command again                               |
| Anything else                       | Pause the AI (Act 6) and continue with the rehearsal PRs. docs/RUNBOOK.md covers the rest                           |

## After the demo

- Close or keep the demo issues and PRs; label any you don't want shipped with `deploy:hold`.
- Check the day's spend on the widget and note it for the follow-up.
