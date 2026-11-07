<?php

/*
| The Merge Gate and the Deploy Gate (docs/PLAN.md §6a).
|
| Both gates are deterministic code: app/Gates/MergePolicy.php and DeployPolicy.php decide,
| GitHub Actions run them (merge-gate.yml, deploy-gate.yml) with the "Fugazi Merge Gate"
| GitHub App's token. That App is the only identity on the branch rules' bypass list.
|
| The switches come from GitHub repository variables, so you can change them without a deploy.
| Empty values fall back to the safe defaults below.
*/

return [

    'repository' => env('AI_REPOSITORY', 'fugazibyus/FugaziByUs'),

    // Token the gate commands use (minted from the Merge Gate App by the workflow).
    'token' => env('GATE_GITHUB_TOKEN'),

    // GitHub logins of the two Apps. Reviews only count when the AI App wrote them,
    // and PRs merged by the gate App count as policy-checked merges.
    'ai_bot' => env('AI_GITHUB_APP_BOT') ?: 'fugazi-ai[bot]',
    'gate_bot' => env('MERGE_GATE_BOT') ?: 'fugazi-merge-gate[bot]',

    // Members of this org team are "developers": they can opt issues in and ask the gates.
    'devs_team' => env('GATE_DEVS_TEAM') ?: 'devs',

    // Members of this org team may ship AI work to staging: their `/ai merge` or `ai:automerge`
    // vouches for an AI pull request into develop, but every review, size and path rule still
    // applies. They can't merge other branches or anything into main (production).
    'product_team' => env('GATE_PRODUCT_TEAM') ?: 'product',

    'base_branch' => 'develop',
    'production_branch' => 'main',

    // Both must be green on the PR's latest commit.
    'required_checks' => ['CI passed', 'Guardrails'],

    // ---- Merge Gate ----------------------------------------------------------------------

    // off · devs-only (issue raised or opted in by a developer) · all
    'automerge_mode' => env('AI_AUTOMERGE_MODE') ?: 'devs-only',

    // Largest size label that may merge automatically: s, m or l.
    'max_automerge_size' => env('AI_MAX_AUTOMERGE_SIZE') ?: 'm',

    // Changes to these never merge automatically (one glob per line; same file as Guardrails).
    'sensitive_paths_file' => base_path('.github/guardrails/protected-paths.txt'),
    'never_automerge_paths' => ['database/migrations/**'],

    // The one-file lock PR that ai-agents-sync.yml opens. The gate may merge it on its own.
    'lock_sync_branch' => 'chore/sync-claude-lock',

    // ---- Deploy Gate ---------------------------------------------------------------------

    // off · business-hours · on
    'autodeploy_production' => env('AI_AUTODEPLOY_PRODUCTION') ?: 'business-hours',

    'timezone' => env('AI_TIMEZONE') ?: 'Australia/Sydney',

    // ISO weekdays (1 = Monday) and local times when the AI may ship to production.
    'business_hours' => ['days' => [1, 2, 3, 4], 'from' => '09:00', 'until' => '16:00'],

    // Staging must have run the release commit for this long, healthy.
    'staging_url' => env('STAGING_URL'),
    'staging_soak_minutes' => 30,

    // At most one production deploy per this many minutes.
    'deploy_cooldown_minutes' => 60,

];
