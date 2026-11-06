<?php

namespace App\Ai;

use Carbon\CarbonImmutable;

/**
 * What the Managed Agents API says about one session right now: status, tokens, cost, timing.
 */
final readonly class SessionSnapshot
{
    /**
     * @param  array<string, string>  $metadata  what `ai:start` attached (agent, issue, pr, trigger…)
     */
    public function __construct(
        public string $id,
        public string $status,
        public array $metadata,
        public ?string $title = null,
        public ?int $agentVersion = null,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cacheReadTokens = 0,
        public int $cacheCreationTokens = 0,
        public int $costCents = 0,
        public int $activeSeconds = 0,
        public ?CarbonImmutable $createdAt = null,
        public ?CarbonImmutable $updatedAt = null,
    ) {}

    /** The agent stopped: it's waiting for input (done) or was ended. */
    public function finished(): bool
    {
        return in_array($this->status, ['idle', 'terminated'], true);
    }
}
