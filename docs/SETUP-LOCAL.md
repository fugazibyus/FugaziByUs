# Local setup (macOS)

Everything runs in Docker. Your Mac needs only the tools below; PHP, Composer and Node run inside the containers.

## 1. Install the tools (once)

```bash
brew install --cask docker        # Docker Desktop – open it once so the engine starts
brew install gh                   # GitHub CLI
gh auth login                     # pick GitHub.com → HTTPS → login with browser
```

Later phases also use these (install now or when we get there):

```bash
brew install anthropics/tap/ant   # Anthropic CLI – manages the AI agents
brew install doctl                # DigitalOcean CLI – staging/prod
```

Claude Code is already installed on this Mac (`claude --version`).

## 2. Get the code

If you're starting from GitHub (once the repo exists):

```bash
gh repo clone fugazibyus/FugaziByUs ~/Sites/FugaziByUs
cd ~/Sites/FugaziByUs
```

## 3. First-time setup

```bash
make setup
```

This builds the images, installs Composer and npm packages inside the containers, creates the database and starts everything. When it finishes:

| What                                     | URL / port                                                       |
| ---------------------------------------- | ---------------------------------------------------------------- |
| Website                                  | http://localhost:8090                                            |
| Mail catcher (all emails land here)      | http://localhost:8026                                            |
| Vite dev server (hot reload)             | http://localhost:5173 (used automatically)                       |
| MySQL from a GUI client (TablePlus etc.) | `127.0.0.1:33060`, user `fugazi`, password `secret`, db `fugazi` |

**Demo login:** `make setup` (and `make fresh`) creates a local demo user, `demo@fugazibyus.test`. Its password is in `database/seeders/DatabaseSeeder.php`. This user exists only locally; seeders never run on staging or production.

The ports avoid your other local project, which uses 8080 and 8025. To change any port, edit `APP_PORT`, `MAILPIT_UI_PORT`, `FORWARD_DB_PORT` or `VITE_PORT` in `.env`, then run `make up`.

## 4. Day-to-day

Run `make` to see every shortcut. The common ones:

```bash
make up            # start
make down          # stop
make logs s=app    # follow one container's logs
make shell         # bash inside the app container
make artisan c="route:list"
make test          # PHP tests (in-memory SQLite – never touches your dev database)
make check         # everything CI runs
make fresh         # reset the database
```

## 5. The containers

| Container   | What it does                                             |
| ----------- | -------------------------------------------------------- |
| `app`       | PHP-FPM 8.4 running Laravel (your code is mounted live)  |
| `nginx`     | Web server on port 8090 → forwards PHP requests to `app` |
| `db`        | MySQL 8.4 (data kept in the `dbdata` Docker volume)      |
| `vite`      | Vite dev server for hot-reloading Vue/Tailwind changes   |
| `queue`     | Runs background jobs (Slack messages, webhooks…)         |
| `scheduler` | Runs scheduled tasks (metrics sync…)                     |
| `mailpit`   | Catches outgoing email                                   |

`queue` and `scheduler` don't reload code changes automatically. Run `make restart` after changing jobs.

## 6. Troubleshooting

- **"port is already allocated":** another app is using that port. Change it in `.env` (step 3) and run `make up`.
- **Page has no styling / Vite errors:** check `make logs s=vite`. If you changed `package.json`, run `make npm c=install` and then `make restart`.
- **Editor can't find `node_modules` types:** the Linux `node_modules` lives in a Docker volume so it never clashes with macOS binaries. If your editor needs them locally too, run `npm install` on your Mac (Node 24).
- **Debugging with Xdebug:** run `XDEBUG_MODE=debug make up`, set your IDE to listen on port 9003, and use the Xdebug browser extension trigger.
- **Start completely fresh (deletes the local database):** `docker compose down -v && make setup`.
