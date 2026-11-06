<?php

namespace App\Ai;

use App\Ai\Contracts\ManagedAgentsGateway;
use App\Ai\Exceptions\DailyBudgetExceededException;
use App\Ai\Exceptions\TooManySessionsException;

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
        private readonly GitHubAppTokenIssuer $github,
    ) {}

    /**
     * Everything that would be sent, without calling any API (for --dry-run).
     *
     * @return array<string, mixed>
     */
    public function plan(AgentTask $task, string $githubToken = '********'): array
    {
        $agent = $this->registry->find($task->agent);
        $this->github->ensureConfigured();

        return [
            'agent' => $this->registry->agentId($agent),
            'environmentID' => $this->registry->environmentId($agent->environment),
            'vaultIDs' => [$this->registry->vaultId()],
            'budgetUsd' => $agent->budgetUsd,
            'title' => $task->title(),
            'metadata' => $task->metadata(),
            'repositories' => [[
                'url' => 'https://github.com/'.config('ai.repository'),
                'token' => $githubToken,
                'mount_path' => (string) config('ai.mount_path'),
                'branch' => $task->branch ?? (string) config('ai.base_branch'),
            ]],
        ];
    }

    public function handle(AgentTask $task): SessionHandle
    {
        $this->plan($task);
        $this->guardDailyBudget();
        $this->guardConcurrency();

        // A fresh one-hour token for this run: it authenticates the repository mount and,
        // through the vault, the GitHub CLI. `ai:refresh-github-token` renews both while it runs.
        $token = $this->github->mint()->value;
        $this->gateway->storeGithubToken($this->registry->vaultId(), $token);

        $plan = $this->plan($task, $token);

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

    private function guardConcurrency(): void
    {
        $limit = (int) config('ai.max_concurrent_sessions');

        if ($limit <= 0) {
            return;
        }

        $running = count($this->gateway->runningSessions());

        if ($running >= $limit) {
            throw new TooManySessionsException("{$running} agents are already working (limit {$limit}). Try again when one finishes, or raise AI_MAX_CONCURRENT_SESSIONS.");
        }
    }
}
