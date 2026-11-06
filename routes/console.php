<?php

use App\Ai\GitHubAppTokenIssuer;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The agents' GitHub App tokens last an hour; keep long-running sessions supplied.
Schedule::command('ai:refresh-github-token')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->when(fn () => app(GitHubAppTokenIssuer::class)->isConfigured());

// Metrics safety net: webhooks keep the homepage widget live, this catches anything they missed.
Schedule::command('ai:sync-runs --days=2')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->when(fn () => (bool) config('ai.sync_runs'));
