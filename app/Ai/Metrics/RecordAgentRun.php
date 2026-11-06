<?php

namespace App\Ai\Metrics;

use App\Ai\Contracts\ManagedAgentsGateway;
use App\Ai\SessionSnapshot;
use App\Models\AiRun;
use App\Slack\Notifier;
use App\Support\AiStats;
use Illuminate\Support\Carbon;

/**
 * Saves one agent session as an `ai_runs` row. Safe to call any number of times: webhooks
 * and the scheduled sync both use it, and the latest numbers win.
 */
class RecordAgentRun
{
    public function __construct(
        private readonly ManagedAgentsGateway $gateway,
        private readonly Notifier $notifier,
    ) {}

    public function handle(SessionSnapshot $session): AiRun
    {
        $run = AiRun::firstOrNew(['session_id' => $session->id]);
        $meta = $session->metadata;

        $run->fill([
            'agent' => $meta['agent'] ?? 'unknown',
            'agent_version' => $session->agentVersion,
            'title' => $session->title,
            'status' => $session->status,
            'issue' => isset($meta['issue']) ? (int) $meta['issue'] : null,
            'pull_request' => isset($meta['pr']) ? (int) $meta['pr'] : null,
            'branch' => $meta['branch'] ?? null,
            'trigger' => $meta['trigger'] ?? null,
            'actor' => $meta['actor'] ?? null,
            'input_tokens' => $session->inputTokens,
            'output_tokens' => $session->outputTokens,
            'cache_read_tokens' => $session->cacheReadTokens,
            'cache_creation_tokens' => $session->cacheCreationTokens,
            'cost_cents' => $session->costCents,
            'active_seconds' => $session->activeSeconds,
            'started_at' => $session->createdAt,
        ]);

        if (! $session->finished()) {
            $run->finished_at = null;
        } elseif ($run->outcome === null) {
            // Read the result once, when the agent has finished.
            $this->readResult($run, $session);
        }

        $wasRunning = $run->exists === false || $run->getOriginal('outcome') === null;
        $run->save();
        AiStats::forget();
        $this->announce($run, $wasRunning);

        return $run;
    }

    private function readResult(AiRun $run, SessionSnapshot $session): void
    {
        $message = $this->gateway->lastAgentMessage($session->id);
        $result = ResultBlock::parse($message['text'] ?? null);

        // When the agent last spoke. (The session's own updated_at moves whenever the token refresh touches it.)
        $finishedAt = $message['at'] ?? $session->updatedAt;
        $run->finished_at = $finishedAt !== null ? Carbon::instance($finishedAt) : null;

        if ($result === null) {
            // Ended without a result: stopped, out of budget, or crashed.
            $run->outcome = $session->status === 'terminated' ? 'terminated' : 'no_result';
            $run->succeeded = false;

            return;
        }

        $run->outcome = mb_substr((string) ($result['outcome'] ?? 'done'), 0, 40);
        $run->succeeded = ! in_array($run->outcome, AiRun::UNSUCCESSFUL_OUTCOMES, true);
        $run->summary = isset($result['summary']) ? mb_substr((string) $result['summary'], 0, 500) : null;
        $run->pull_request ??= isset($result['pr']) && (int) $result['pr'] > 0 ? (int) $result['pr'] : null;
    }

    /** Tell #fugazi-ai-activity when a run starts and when it finishes (recent runs only, so a backfill stays quiet). */
    private function announce(AiRun $run, bool $wasRunning): void
    {
        $recent = ($run->finished_at ?? $run->started_at)?->greaterThan(now()->subHour()) ?? false;

        if (! $recent) {
            return;
        }

        $name = (string) ($run->title ?? $run->agent);

        if ($run->wasRecentlyCreated && $run->finished_at === null) {
            $this->notifier->notify('run.started', "▶️ {$name} started.", $run->issue);
        } elseif ($wasRunning && $run->outcome !== null) {
            $minutes = max(1, (int) round($run->active_seconds / 60));
            $cost = sprintf('$%.2f', $run->cost_cents / 100);
            $summary = $run->summary !== null ? " {$run->summary}" : '';

            $run->succeeded
                ? $this->notifier->notify('run.finished', "✔️ {$name}: `{$run->outcome}` ({$cost}, {$minutes} min).{$summary}", $run->issue)
                : $this->notifier->notify('run.failed', "⚠️ {$name} ended with `{$run->outcome}` ({$cost}, {$minutes} min).{$summary}", $run->issue);
        }
    }
}
