<?php

namespace App\Support;

use App\Models\AiPullRequest;
use App\Models\AiRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Numbers for the homepage "AI at work" widget, from `ai_runs` (Anthropic) and
 * `ai_pull_requests` (GitHub). Cached for a minute; every sync clears the cache.
 *
 * @phpstan-type Summary array{
 *     spendUsd: float,
 *     tokens: array{input: int, output: int, cached: int},
 *     runs: array{total: int, succeeded: int, byAgent: array<string, int>},
 *     pullRequests: array{opened: int, merged: int, autoMerged: int},
 *     avgIssueToPrMinutes: int|null,
 *     recentRuns: list<array{agent: string, title: string, status: string, costUsd: float, finishedAt: string}>,
 *     updatedAt: string|null,
 * }
 */
class AiStats
{
    private const CACHE_KEY = 'ai-stats.summary';

    public const AGENTS = ['triage', 'researcher', 'developer', 'debugger', 'reviewer', 'release'];

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return Summary
     */
    public function summary(): array
    {
        /** @var Summary $summary */
        $summary = Cache::remember(self::CACHE_KEY, 60, fn () => $this->build());

        return $summary;
    }

    /** @return array<string, mixed> */
    private function build(): array
    {
        $totals = AiRun::query()->selectRaw(
            'COALESCE(SUM(cost_cents), 0) AS cents, COALESCE(SUM(input_tokens), 0) AS input, COALESCE(SUM(output_tokens), 0) AS output,
             COALESCE(SUM(cache_read_tokens + cache_creation_tokens), 0) AS cached, COUNT(*) AS runs,
             COALESCE(SUM(CASE WHEN succeeded THEN 1 ELSE 0 END), 0) AS succeeded_runs, MAX(updated_at) AS updated'
        )->first();

        $byAgent = AiRun::query()->selectRaw('agent, COUNT(*) AS runs')->groupBy('agent')->pluck('runs', 'agent');

        $issueToPr = AiPullRequest::query()->whereNotNull('issue_opened_at')->get(['issue_opened_at', 'opened_at'])
            ->map(fn (AiPullRequest $pr) => $pr->issue_opened_at?->diffInMinutes($pr->opened_at))
            ->filter(fn (?float $minutes) => $minutes !== null && $minutes >= 0);

        return [
            'spendUsd' => round((int) $totals?->getAttribute('cents') / 100, 2),
            'tokens' => [
                'input' => (int) $totals?->getAttribute('input'),
                'output' => (int) $totals?->getAttribute('output'),
                'cached' => (int) $totals?->getAttribute('cached'),
            ],
            'runs' => [
                'total' => (int) $totals?->getAttribute('runs'),
                'succeeded' => (int) $totals?->getAttribute('succeeded_runs'),
                'byAgent' => collect(self::AGENTS)->mapWithKeys(fn (string $agent) => [$agent => (int) ($byAgent[$agent] ?? 0)])->all(),
            ],
            'pullRequests' => [
                'opened' => AiPullRequest::count(),
                'merged' => AiPullRequest::where('state', 'merged')->count(),
                'autoMerged' => AiPullRequest::where('auto_merged', true)->count(),
            ],
            'avgIssueToPrMinutes' => $issueToPr->isEmpty() ? null : (int) round((float) $issueToPr->avg()),
            'recentRuns' => AiRun::query()->whereNotNull('finished_at')->latest('finished_at')->limit(5)->get()
                ->map(fn (AiRun $run) => [
                    'agent' => $run->agent,
                    'title' => (string) str((string) $run->title)->after(' · ')->whenEmpty(fn () => str($run->agent)),
                    'status' => $run->succeeded === false ? 'failed' : 'succeeded',
                    'costUsd' => round($run->cost_cents / 100, 2),
                    'finishedAt' => (string) $run->finished_at?->toIso8601String(),
                ])->values()->all(),
            'updatedAt' => $totals?->getAttribute('updated') !== null ? Carbon::parse((string) $totals->getAttribute('updated'))->toIso8601String() : null,
        ];
    }
}
