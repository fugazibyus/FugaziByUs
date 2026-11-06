<?php

namespace App\Jobs;

use App\Ai\Contracts\ManagedAgentsGateway;
use App\Ai\Metrics\RecordAgentRun;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fetch one agent session from Anthropic and save it. Queued by the Anthropic webhook.
 */
class SyncAgentRun implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** A burst of events for one session only needs one fetch. */
    public int $uniqueFor = 10;

    public function __construct(public readonly string $sessionId) {}

    public function uniqueId(): string
    {
        return $this->sessionId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(ManagedAgentsGateway $gateway, RecordAgentRun $record): void
    {
        $session = $gateway->session($this->sessionId);

        // Sessions from other projects in the same workspace aren't ours to count.
        if (($session->metadata['app'] ?? null) === 'fugazibyus') {
            $record->handle($session);
        }
    }
}
