<?php

namespace App\Ai\Contracts;

use App\Ai\SessionHandle;

/**
 * The few Managed Agents API calls the app makes. Swapped for a fake in tests.
 */
interface ManagedAgentsGateway
{
    /**
     * @param  array<string, mixed>  $params  agent, environmentID, resources, vaultIDs, budgetUsd, metadata, title
     */
    public function createSession(array $params): SessionHandle;

    public function sendMessage(string $sessionId, string $text): void;

    /** List-price spend (USD) of every session created since midnight UTC. */
    public function spentTodayUsd(): float;
}
