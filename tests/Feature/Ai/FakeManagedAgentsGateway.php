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
}
