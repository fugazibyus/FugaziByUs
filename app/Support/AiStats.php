<?php

namespace App\Support;

/**
 * Numbers for the homepage "AI at work" widget.
 *
 * Phase 6 replaces this with real data from the `ai_runs` table (fed by Anthropic
 * webhooks) and GitHub. Until then it returns an empty, correctly-shaped summary
 * so the UI can be built and tested.
 */
class AiStats
{
    /**
     * @return array{
     *     spendUsd: float,
     *     tokens: array{input: int, output: int, cached: int},
     *     runs: array{total: int, succeeded: int, byAgent: array<string, int>},
     *     pullRequests: array{opened: int, merged: int, autoMerged: int},
     *     avgIssueToPrMinutes: int|null,
     *     recentRuns: list<array{agent: string, title: string, status: string, costUsd: float, finishedAt: string}>,
     *     updatedAt: string|null,
     * }
     */
    public function summary(): array
    {
        return [
            'spendUsd' => 0.0,
            'tokens' => ['input' => 0, 'output' => 0, 'cached' => 0],
            'runs' => [
                'total' => 0,
                'succeeded' => 0,
                'byAgent' => ['triage' => 0, 'researcher' => 0, 'developer' => 0, 'debugger' => 0, 'reviewer' => 0, 'release' => 0],
            ],
            'pullRequests' => ['opened' => 0, 'merged' => 0, 'autoMerged' => 0],
            'avgIssueToPrMinutes' => null,
            'recentRuns' => [],
            'updatedAt' => null,
        ];
    }
}
