# FugaziByUs developer shortcuts. Run `make` or `make help` to list them.
# Everything runs inside Docker, so the only things you need on your Mac are Docker Desktop and make.

export UID := $(shell id -u)
export GID := $(shell id -g)

DC   := docker compose
EXEC := $(DC) exec app
RUN  := $(DC) run --rm --no-deps app

.DEFAULT_GOAL := help

## —— Setup ————————————————————————————————————————————————
setup: ## First-time setup: build images, install deps, create DB, build assets
	@test -f .env || cp .env.example .env
	@mkdir -p $(HOME)/.config/anthropic
	$(DC) build
	$(DC) up -d db
	$(RUN) composer install
	$(RUN) npm install
	$(RUN) php artisan key:generate --force
	$(DC) up -d
	$(EXEC) php artisan migrate --seed --force
	@echo "\n✅  FugaziByUs is running at http://localhost:8090  (mail: http://localhost:8026)\n"

## —— Stack ————————————————————————————————————————————————
up: ## Start all containers
	$(DC) up -d

down: ## Stop all containers
	$(DC) down

restart: ## Restart all containers
	$(DC) restart

build: ## Rebuild images (after Dockerfile changes)
	$(DC) build

ps: ## Show container status
	$(DC) ps

logs: ## Tail logs for all containers (make logs s=app for one)
	$(DC) logs -f --tail=100 $(s)

shell: ## Open a shell in the app container
	$(EXEC) bash

db-shell: ## Open a MySQL shell
	$(DC) exec db sh -c 'mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'

## —— Laravel ——————————————————————————————————————————————
artisan: ## Run an artisan command: make artisan c="route:list"
	$(EXEC) php artisan $(c)

migrate: ## Run database migrations
	$(EXEC) php artisan migrate

fresh: ## Drop all tables, re-run migrations and seeders
	$(EXEC) php artisan migrate:fresh --seed

composer: ## Run a composer command: make composer c="require foo/bar"
	$(EXEC) composer $(c)

npm: ## Run an npm command: make npm c="install foo"
	$(EXEC) npm $(c)

assets: ## Build production assets once (instead of the Vite dev server)
	$(EXEC) npm run build

## —— Quality ——————————————————————————————————————————————
test: ## Run the PHP test suite
	$(EXEC) php artisan test

lint: ## Fix PHP + JS code style
	$(EXEC) composer lint
	$(EXEC) npm run check:fix

check: ## Run everything CI runs (style, static analysis, types, tests)
	$(EXEC) composer lint:check
	$(EXEC) composer types:check
	$(EXEC) npm run check
	$(EXEC) npm run types:check
	$(EXEC) php artisan test

e2e: ## Browser smoke tests (Playwright) against the running local site
	@test -d node_modules || npm ci
	npx playwright install chromium
	npx playwright test

## —— AI agents ——————————————————————————————————————————
ai-agents: ## List the agents, models, budgets and Anthropic IDs
	$(EXEC) php artisan ai:agents

ai-sync: ## Regenerate local Claude Code subagents from /agents
	$(EXEC) php artisan ai:sync-local

ai-plan: ## Show what `ant apply` would change in Anthropic (no changes made)
	ant apply --dry-run agents environments vaults

ai-apply: ## Create/update the agents, environments and vault in Anthropic
	ant apply agents environments vaults
	$(MAKE) ai-sync

ai-github-token: ## Mint a fresh GitHub App token for the vault and running agents (the scheduler does this every 30 min)
	$(EXEC) php artisan ai:refresh-github-token

ai-run: ## Start a cloud agent: make ai-run agent=developer issue=12 [pr=34 branch=… msg="…"]
	$(EXEC) php artisan ai:start $(agent) \
		$(if $(issue),--issue=$(issue)) $(if $(pr),--pr=$(pr)) $(if $(branch),--branch=$(branch)) \
		$(if $(msg),--message="$(msg)") --trigger=local --actor=$(shell git config user.name | tr ' ' '-')

## —— Help —————————————————————————————————————————————————
help:
	@grep -hE '(^[a-zA-Z_-]+:.*?## .*$$)|(^## )' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; /^## /{printf "\n\033[33m%s\033[0m\n", substr($$0, 4); next} {printf "  \033[32m%-10s\033[0m %s\n", $$1, $$2}'

.PHONY: setup up down restart build ps logs shell db-shell artisan migrate fresh composer npm assets test lint check e2e ai-agents ai-sync ai-plan ai-apply ai-github-token ai-run help
