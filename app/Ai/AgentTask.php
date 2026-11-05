<?php

namespace App\Ai;

/**
 * What an agent should work on in one session.
 */
final readonly class AgentTask
{
    public function __construct(
        public string $agent,
        public ?int $issue = null,
        public ?int $pullRequest = null,
        public ?string $branch = null,
        public ?string $runId = null,
        public ?int $attempt = null,
        public ?string $instructions = null,
        public ?string $stagingUrl = null,
        public string $trigger = 'manual',
        public ?string $actor = null,
    ) {}

    public function title(): string
    {
        $target = match (true) {
            $this->pullRequest !== null => "PR #{$this->pullRequest}",
            $this->issue !== null => "issue #{$this->issue}",
            default => 'ad-hoc task',
        };

        return ucfirst($this->agent)." · {$target}";
    }

    /**
     * Session metadata (max 16 string pairs) – lets dashboards and webhooks link runs back.
     *
     * @return array<string, string>
     */
    public function metadata(): array
    {
        return array_filter([
            'app' => 'fugazibyus',
            'agent' => $this->agent,
            'issue' => $this->issue !== null ? (string) $this->issue : null,
            'pr' => $this->pullRequest !== null ? (string) $this->pullRequest : null,
            'branch' => $this->branch,
            'attempt' => $this->attempt !== null ? (string) $this->attempt : null,
            'trigger' => $this->trigger,
            'actor' => $this->actor,
        ], fn (?string $value) => $value !== null && $value !== '');
    }
}
