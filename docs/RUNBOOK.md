# Runbook

What to do when something needs a person. Each entry says how you'll notice, what to check and what to do. Setup lives in the `SETUP-*` guides; this page is for running the system day to day.

| Environment | URL                                     | Server                     |
| ----------- | --------------------------------------- | -------------------------- |
| Staging     | `https://staging.<staging-ip>.sslip.io` | `ssh root@<staging-ip>`    |
| Production  | `https://<production-ip>.sslip.io`      | `ssh root@<production-ip>` |

The IPs are the `DEPLOY_HOST` variables on the `staging` and `production` environments (GitHub → Settings → Environments). On a server, the stack lives in `/opt/fugazi`. Run the deploy script as the `deploy` user: `sudo -u deploy /opt/fugazi/deploy.sh status`.

## The controls

| I want to…                                    | Do this                                                                                                                                                        |
| --------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Stop the AI merging and shipping, keep agents | Actions → **AI · pause and resume** → Run workflow → pause. Sets `AI_AUTOMERGE_MODE` and `AI_AUTODEPLOY_PRODUCTION` to `off`. Works from the GitHub mobile app |
| Stop all agents from starting                 | `gh variable set AI_AGENTS --body off`                                                                                                                         |
| Stop the agents on one issue or PR            | Comment `/ai stop`, or `make ai-stop issue=12` (`pr=34`, `all=1` for everything running)                                                                       |
| Stop automatic staging deploys                | `gh variable set AI_AUTODEPLOY_STAGING --body off`                                                                                                             |
| Keep one release out of production            | Add `deploy:hold` to the release PR (or to any PR in it)                                                                                                       |
| Undo all of the above                         | **AI · pause and resume** → resume, and set the variables back to `on`                                                                                         |

A developer can always merge a PR and run the **Deploy** workflow by hand, whatever the switches say.

## Where alerts go

| Slack channel         | What arrives                                                            |
| --------------------- | ----------------------------------------------------------------------- |
| `#fugazi-alerts`      | Agent failures, budget used up, pause and resume                        |
| `#fugazi-deploys`     | Every production deploy, and failed deploys of either environment       |
| `#fugazi-reviews`     | Pull requests waiting for a person, with the Merge Gate's reason        |
| `#fugazi-ai-activity` | Agents starting and finishing                                           |
| DM                    | The developer on duty (`SLACK_ON_DUTY`) for failures and failed deploys |

---

## A deploy failed

**You'll see:** 🔥 in `#fugazi-deploys` and a DM, and a red **Deploy** run in Actions.

`deploy.sh` checks every release: `/up` must answer and `/version` must report the new commit within 90 seconds. If not, **it has already rolled back** to the previous release, so the site is up on the old version.

1. Open the failed **Deploy** run. The last lines of the deploy step show which check failed.
2. If the log isn't enough, look at the app on the server:

    ```bash
    ssh root@<ip>
    cd /opt/fugazi && docker compose logs --tail=200 app queue
    ```

3. Fix forward with a normal pull request. Production picks it up through the next release.

If the build step failed instead (nothing reached the server), it's a Docker or registry problem: re-run the job. If the DigitalOcean token expired, see [Rotating secrets](#rotating-secrets).

## The site is down or broken

1. Check what's running: `https://<host>/version` and `https://<host>/up`.
2. If the last release is to blame, roll back. The previous release's images are kept on the server, so this needs neither GitHub nor the registry:

    ```bash
    ssh root@<ip> sudo -u deploy /opt/fugazi/deploy.sh rollback
    ```

    Or redeploy a known good commit: Actions → **Deploy** → Run workflow from `main`, environment `production`, ref = that commit.

3. Pause the AI (see [The controls](#the-controls)) so the Deploy Gate doesn't ship the same thing again.
4. If the containers are the problem rather than the code:

    ```bash
    ssh root@<ip>
    cd /opt/fugazi && docker compose ps
    docker compose restart app queue scheduler web
    ```

**Database migrations are not rolled back.** That's why migrations must be backwards compatible (add first, remove in a later release) and never auto-merge.

## The certificate is about to expire or is invalid

Renewal is automatic: the `certbot` container tries twice a day and nginx reloads every six hours. To check:

```bash
ssh root@<ip>
cd /opt/fugazi && docker compose logs --tail=50 certbot
```

Let's Encrypt needs port 80 open to the internet (the DigitalOcean firewall) and the hostname resolving to the Droplet. Any deploy also requests a certificate if one is missing.

## An agent is stuck, looping or doing the wrong thing

**You'll see:** an issue sitting at `ai:in-progress` for a long time, repeated pushes, or a pull request that misses the point.

1. Comment `/ai stop` on the issue or PR. It adds `ai:stop` and interrupts the sessions.
2. Read what it did: the session is linked from the PR (`ai-metadata`), or watch a running one with `ant beta:sessions connect <session-id>`.
3. Either take over (check out the branch and push) or remove `ai:stop` and comment `/ai develop` with clearer instructions.

The debugger stops on its own after two attempts and labels the PR `ai:needs-human`.

## "Today's AI budget is used up"

**You'll see:** 💸 in `#fugazi-alerts` and a comment saying an agent didn't start.

The daily circuit breaker refused a new run because today's spend reached `AI_DAILY_BUDGET_USD`. Running sessions finish normally. Either wait until tomorrow (UTC) and comment the `/ai …` command again, or raise the cap:

```bash
gh variable set AI_DAILY_BUDGET_USD --body 40
```

Check the homepage widget for what the money went on before raising it.

## "Too many agents are working"

At most `AI_MAX_CONCURRENT_SESSIONS` (3) run at once. Wait for one to finish, then comment the `/ai …` command again.

## The Merge Gate or Deploy Gate said no

That's the gates doing their job. The gate's comment on the PR lists every rule it checked and which one failed. A developer can:

- fix the reason (for example get a fresh reviewer verdict after new commits with `/ai review`), then comment `/ai merge` (or `/ai deploy production` on the release PR), or
- review and merge by hand.

## Slack is quiet

1. Is the GitHub app still in the channel? In Slack, `/github subscribe list` in the channel.
2. For the AI alerts, check the **Tell Slack** step of the relevant Actions run. A `not_in_channel` error means the Fugazi bot needs inviting to that channel. An `invalid_auth` error means `SLACK_BOT_TOKEN` needs replacing (see docs/SETUP-SLACK.md).

## Restoring the database

Each Droplet dumps its database at 03:30 server time (UTC) into `/opt/fugazi/backups`, kept for 7 days. Production also has DigitalOcean's weekly Droplet backups.

```bash
ssh root@<ip>
cd /opt/fugazi && ls backups/
gunzip -c backups/db-<date>.sql.gz | docker compose -f compose.yml exec -T db \
  sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u"$MYSQL_USER" "$MYSQL_DATABASE"'
```

Take a fresh dump first (`sudo -u deploy /opt/fugazi/backup.sh`) so the restore can itself be undone.

## The server is out of disk or memory

```bash
ssh root@<ip>
df -h / && free -h
docker system df
```

Deploys already prune old images, keeping only the current and previous release, and Docker logs are capped at 50 MB per container. If the disk is still full, `docker image prune -a` frees the most, but it also deletes the previous release's images, so `deploy.sh rollback` would then need the registry. Old database dumps are in `/opt/fugazi/backups`.

## Rotating secrets

Never paste secret values into issues, PRs or Slack. Generate them in place with the commands below; none of them print the value.

| Secret                                       | When                 | How                                                                                                                                                                                                             |
| -------------------------------------------- | -------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `DIGITALOCEAN_ACCESS_TOKEN`                  | Yearly, or if leaked | Create a new registry-scoped token in DigitalOcean, `gh secret set DIGITALOCEAN_ACCESS_TOKEN`, then delete the old token                                                                                        |
| `DEPLOY_SSH_KEY`                             | If leaked            | Delete the secret and variable, run `scripts/deploy-secrets.sh`, then re-run `scripts/droplet-init.sh` on each Droplet with the new public key, and remove the old key from `/home/deploy/.ssh/authorized_keys` |
| `DB_PASSWORD`                                | Rarely               | MySQL only reads it when the database is first created. Change the user's password inside MySQL first, then the environment secret, then redeploy                                                               |
| `APP_KEY`                                    | Only if leaked       | Replacing it logs everyone out and makes encrypted data unreadable. Set the old key in `APP_PREVIOUS_KEYS` when replacing it                                                                                    |
| `ANTHROPIC_API_KEY`, `APP_ANTHROPIC_API_KEY` | If leaked            | New key in the Claude Console, update the secret, revoke the old key                                                                                                                                            |
| GitHub App private keys                      | If leaked            | GitHub → the App's settings → Generate a private key, update `AI_GITHUB_APP_PRIVATE_KEY` or `MERGE_GATE_APP_PRIVATE_KEY`, delete the old key                                                                    |
| `SLACK_BOT_TOKEN`                            | If leaked            | Slack app → OAuth & Permissions → Reinstall (rotates the token), update the secret                                                                                                                              |

## Server maintenance

Security updates install themselves (`unattended-upgrades`). Some need a reboot; the stack starts again on its own because every container is `restart: unless-stopped`. Reboot staging first, then production outside business hours:

```bash
ssh root@<ip> reboot
```

Then check `https://<host>/up`.
