<?php

namespace App\Ai\Contracts;

use App\Ai\SessionHandle;
use App\Ai\SessionSnapshot;
use Carbon\CarbonImmutable;

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

    /** Create or replace the vault's GH_TOKEN credential (used by the GitHub CLI in the sandbox). */
    public function storeGithubToken(string $vaultId, string $token): void;

    /**
     * Give every active FugaziByUs session a fresh token for its mounted repository.
     *
     * @return int how many sessions were updated
     */
    public function refreshRepositoryTokens(string $token): int;

    /**
     * FugaziByUs sessions that are working right now (not idle, finished or archived).
     *
     * @return list<array{id: string, metadata: array<string, string>}>
     */
    public function runningSessions(): array;

    /** Stop a session mid-task. It goes idle and keeps its transcript. */
    public function interruptSession(string $sessionId): void;

    public function session(string $sessionId): SessionSnapshot;

    /**
     * FugaziByUs sessions created since a moment, archived ones included (for metrics).
     *
     * @return list<SessionSnapshot>
     */
    public function sessionsSince(CarbonImmutable $since): array;

    /**
     * The agent's last message (where its JSON result block lives) and when it was sent.
     *
     * @return array{text: string, at: CarbonImmutable}|null
     */
    public function lastAgentMessage(string $sessionId): ?array;
}
