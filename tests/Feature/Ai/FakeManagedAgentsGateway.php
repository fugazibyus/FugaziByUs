<?php

namespace Tests\Feature\Ai;

use App\Ai\Contracts\ManagedAgentsGateway;
use App\Ai\SessionHandle;

class FakeManagedAgentsGateway implements ManagedAgentsGateway
{
    /** @var list<array<string, mixed>> */
    public array $created = [];

    /** @var list<array{string, string}> */
    public array $messages = [];

    /** @var array<string, string> vault ID => GH_TOKEN */
    public array $vaultTokens = [];

    /** @var list<string> */
    public array $refreshedWith = [];

    /** @var list<array{id: string, metadata: array<string, string>}> */
    public array $running = [];

    /** @var list<string> */
    public array $interrupted = [];

    public function __construct(public float $spentToday = 0.0) {}

    public function createSession(array $params): SessionHandle
    {
        $this->created[] = $params;

        return new SessionHandle('sesn_fake_'.count($this->created), 'idle', 'https://console.test/sesn_fake');
    }

    public function sendMessage(string $sessionId, string $text): void
    {
        $this->messages[] = [$sessionId, $text];
    }

    public function spentTodayUsd(): float
    {
        return $this->spentToday;
    }

    public function storeGithubToken(string $vaultId, string $token): void
    {
        $this->vaultTokens[$vaultId] = $token;
    }

    public function refreshRepositoryTokens(string $token): int
    {
        $this->refreshedWith[] = $token;

        return count($this->created);
    }

    public function runningSessions(): array
    {
        return $this->running;
    }

    public function interruptSession(string $sessionId): void
    {
        $this->interrupted[] = $sessionId;
    }
}
