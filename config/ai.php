<?php

/*
| AI workflow configuration. Agent prompts and settings live in /agents/*\/agent.md;
| this file only says how the Laravel side finds and starts them.
*/

return [

    // GitHub repository the agents work on, and the branch they start from.
    'repository' => env('AI_REPOSITORY', 'fugazibyus/FugaziByUs'),
    'base_branch' => env('AI_BASE_BRANCH', 'develop'),

    // The "Fugazi AI" GitHub App the agents act as. Every run mints its own one-hour token
    // from it (App\Ai\GitHubAppTokenIssuer). Locally, point the path at the downloaded .pem.
    // In GitHub Actions, put the key's contents in AI_GITHUB_APP_PRIVATE_KEY instead.
    'github_app' => [
        'id' => env('AI_GITHUB_APP_ID'),
        'private_key' => env('AI_GITHUB_APP_PRIVATE_KEY'),
        'private_key_path' => env('AI_GITHUB_APP_PRIVATE_KEY_PATH'),
    ],

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

    // Bounded loops: at most this many agents work at the same time. 0 disables the check.
    'max_concurrent_sessions' => (int) env('AI_MAX_CONCURRENT_SESSIONS', 3),

    // The scheduled metrics sync (ai:sync-runs) needs Anthropic credentials. Hosted servers
    // without an Anthropic key turn it off, so it doesn't log an error every ten minutes.
    'sync_runs' => (bool) env('AI_SYNC_RUNS', true),

    // Link to a session in the Claude Console. Replace "default" with your workspace ID
    // if your API key belongs to a non-default workspace.
    'console_session_url' => env('ANTHROPIC_CONSOLE_SESSION_URL', 'https://platform.claude.com/workspaces/default/sessions/{id}'),

];
