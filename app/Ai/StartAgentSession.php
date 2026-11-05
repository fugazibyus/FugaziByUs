<?php

namespace App\Ai;

use App\Ai\Contracts\ManagedAgentsGateway;
use App\Ai\Exceptions\AgentConfigurationException;
use App\Ai\Exceptions\DailyBudgetExceededException;

/**
 * Starts one Managed Agents session for a task. Used by `php artisan ai:start`
 * (GitHub Actions and developers' terminals) and, later, by the Slack integration.
 */
class StartAgentSession
{
    public function __construct(
        private readonly AgentRegistry $registry,
        private readonly ManagedAgentsGateway $gateway,
        private readonly TaskPrompt $prompt,
    ) {}

    /**
     * Everything that would be sent, without calling the API (for --dry-run).
     *
     * @return array<string, mixed>
     */
    public function plan(AgentTask $task): array
    {
        $agent = $this->registry->find($task->agent);
        $token = (string) config('ai.github_token');

        if ($token === '') {
            throw new AgentConfigurationException('AI_GITHUB_TOKEN is not set – the agent needs it to clone and push the repository.');
        }

        return [
            'agent' => $this->registry->agentId($agent),
            'environmentID' => $this->registry->environmentId($agent->environment),
            'vaultIDs' => [$this->registry->vaultId()],
            'budgetUsd' => $agent->budgetUsd,
            'title' => $task->title(),
            'metadata' => $task->metadata(),
            'repositories' => [[
                'url' => 'https://github.com/'.config('ai.repository'),
                'token' => $token,
                'mount_path' => (string) config('ai.mount_path'),
                'branch' => $task->branch ?? (string) config('ai.base_branch'),
            ]],
        ];
    }

    public function handle(AgentTask $task): SessionHandle
    {
        $plan = $this->plan($task);
        $this->guardDailyBudget();

        $session = $this->gateway->createSession($plan);
        $this->gateway->sendMessage($session->id, $this->prompt->build($task, $session->id));

        return $session;
    }

    private function guardDailyBudget(): void
    {
        $limit = (float) config('ai.daily_budget_usd');

        if ($limit <= 0) {
            return;
        }

        $spent = $this->gateway->spentTodayUsd();

        if ($spent >= $limit) {
            throw new DailyBudgetExceededException(sprintf(
                'Daily AI budget reached: $%.2f spent of $%.2f today (UTC). Raise AI_DAILY_BUDGET_USD or wait until tomorrow.',
                $spent,
                $limit,
            ));
        }
    }
}
