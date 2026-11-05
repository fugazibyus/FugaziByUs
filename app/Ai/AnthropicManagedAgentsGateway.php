<?php

namespace App\Ai;

use Anthropic\Beta\BetaMonetaryAmount;
use Anthropic\Beta\Sessions\BetaManagedAgentsBudgetLimit;
use Anthropic\Beta\Sessions\BetaManagedAgentsGitHubRepositoryResourceParams;
use Anthropic\Client;
use App\Ai\Contracts\ManagedAgentsGateway;
use Carbon\CarbonImmutable;

/**
 * Talks to Claude Managed Agents through the official Anthropic PHP SDK.
 */
class AnthropicManagedAgentsGateway implements ManagedAgentsGateway
{
    public function __construct(private readonly Client $client) {}

    public function createSession(array $params): SessionHandle
    {
        $resources = array_values(array_map(
            fn (array $repo) => BetaManagedAgentsGitHubRepositoryResourceParams::with(
                url: $repo['url'],
                authorizationToken: $repo['token'],
                mountPath: $repo['mount_path'],
                checkout: ['type' => 'branch', 'name' => $repo['branch']],
            ),
            $params['repositories'] ?? [],
        ));

        $budget = ($params['budgetUsd'] ?? 0) > 0
            ? BetaManagedAgentsBudgetLimit::with(maxListCost: BetaMonetaryAmount::with(
                amount: (string) (int) round($params['budgetUsd'] * 100),
                currency: 'USD',
            ))
            : null;

        $session = $this->client->beta->sessions->create(
            agent: $params['agent'],
            environmentID: $params['environmentID'],
            budget: $budget,
            metadata: $params['metadata'] ?? null,
            resources: $resources ?: null,
            title: $params['title'] ?? null,
            vaultIDs: $params['vaultIDs'] ?? null,
        );

        return new SessionHandle(
            id: $session->id,
            status: $session->status,
            consoleUrl: str_replace('{id}', $session->id, (string) config('ai.console_session_url')),
        );
    }

    public function sendMessage(string $sessionId, string $text): void
    {
        $this->client->beta->sessions->events->send(
            $sessionId,
            events: [[
                'type' => 'user.message',
                'content' => [['type' => 'text', 'text' => $text]],
            ]],
        );
    }

    public function spentTodayUsd(): float
    {
        $cents = 0;
        $page = $this->client->beta->sessions->list(
            createdAtGte: CarbonImmutable::now('UTC')->startOfDay(),
            limit: 100,
            includeArchived: true,
        );

        foreach ($page->pagingEachItem() as $session) {
            if (($session->metadata['app'] ?? null) !== 'fugazibyus') {
                continue;
            }

            $cents += (int) ($session->usage->listCost->amount ?? 0);
        }

        return $cents / 100;
    }
}
