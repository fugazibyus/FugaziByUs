<?php

/*
| AI workflow configuration. Agent prompts and settings live in /agents/*\/agent.md;
| this file only says how the Laravel side finds and starts them.
*/

return [

    // GitHub repository the agents work on, and the branch they start from.
    'repository' => env('AI_REPOSITORY', 'fugazibyus/FugaziByUs'),
    'base_branch' => env('AI_BASE_BRANCH', 'develop'),

    // Token the agents use for git (mounted repo) and the GitHub CLI. In GitHub Actions this
    // is the bot's token; locally you can use your own fine-grained token.
    'github_token' => env('AI_GITHUB_TOKEN'),

    // Where the repo is mounted inside the agent sandbox.
    'mount_path' => '/workspace/repo',

    // Agent definitions and the lock file `ant apply` writes (holds the Anthropic IDs).
    'agents_path' => base_path('agents'),
    'lock_file' => base_path('claude-lock.json'),

    // Lock file keys for the shared resources (paths as `ant apply` records them).
    'environments' => [
        'sandbox' => 'environments/sandbox.yaml',
        'research' => 'environments/research.yaml',
    ],
    'vault' => 'vaults/github.yaml',

    // Circuit breaker: refuse to start new sessions once today's spend (UTC, list price)
    // reaches this many US dollars. 0 disables the check.
    'daily_budget_usd' => (float) env('AI_DAILY_BUDGET_USD', 25),

    // Link to a session in the Claude Console. Replace "default" with your workspace ID
    // if your API key belongs to a non-default workspace.
    'console_session_url' => env('ANTHROPIC_CONSOLE_SESSION_URL', 'https://platform.claude.com/workspaces/default/sessions/{id}'),

];
