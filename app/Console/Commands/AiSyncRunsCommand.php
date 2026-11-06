<?php

namespace App\Console\Commands;

use App\Ai\Contracts\ManagedAgentsGateway;
use App\Ai\Exceptions\AgentConfigurationException;
use App\Ai\Metrics\RecordAgentRun;
use App\Ai\Metrics\SyncPullRequests;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:sync-runs
    {--days=30 : How far back to fetch agent sessions}
    {--skip-github : Only sync agent sessions, not pull requests}')]
#[Description('Copy agent sessions from Anthropic and AI pull requests from GitHub into the metrics tables')]
class AiSyncRunsCommand extends Command
{
    /**
     * Webhooks keep the numbers live; this scheduled sync is the safety net (and the
     * way to fill the tables the first time, or locally where webhooks can't reach you).
     */
    public function handle(ManagedAgentsGateway $gateway, RecordAgentRun $record, SyncPullRequests $pullRequests): int
    {
        $since = CarbonImmutable::now()->subDays(max(1, (int) $this->option('days')));
        $sessions = $gateway->sessionsSince($since);

        foreach ($sessions as $session) {
            $record->handle($session);
        }

        $this->components->info(count($sessions).' agent session(s) synced.');

        if (! $this->option('skip-github')) {
            try {
                $this->components->info($pullRequests->handle().' AI pull request(s) synced.');
            } catch (AgentConfigurationException $e) {
                $this->components->warn('Pull requests skipped: '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
