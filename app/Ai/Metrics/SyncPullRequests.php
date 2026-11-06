<?php

namespace App\Ai\Metrics;

use App\Ai\GitHubAppTokenIssuer;
use App\Models\AiPullRequest;
use App\Support\AiStats;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Copies the agents' pull requests (`ai/*` branches) from GitHub into `ai_pull_requests`,
 * so the widget can show PRs opened, merged and auto-merged, and the time from issue to PR.
 */
class SyncPullRequests
{
    private const CLOSES = '/\b(?:close[sd]?|fix(?:e[sd])?|resolve[sd]?)\s+#(\d+)/i';

    public function __construct(private readonly GitHubAppTokenIssuer $tokens) {}

    /** @return int how many AI pull requests were saved */
    public function handle(): int
    {
        $github = $this->client();
        $repo = (string) config('ai.repository');
        $saved = 0;

        for ($page = 1; $page <= 10; $page++) {
            $pulls = (array) $github->get("/repos/{$repo}/pulls", ['state' => 'all', 'per_page' => 100, 'page' => $page])->throw()->json();

            foreach ($pulls as $pr) {
                if (is_array($pr) && str_starts_with((string) ($pr['head']['ref'] ?? ''), 'ai/')) {
                    $this->save($github, $repo, $pr);
                    $saved++;
                }
            }

            if (count($pulls) < 100) {
                break;
            }
        }

        AiStats::forget();

        return $saved;
    }

    /** @param  array<string, mixed>  $pr */
    private function save(PendingRequest $github, string $repo, array $pr): void
    {
        $existing = AiPullRequest::firstWhere('number', $pr['number']);
        $issue = preg_match(self::CLOSES, (string) ($pr['body'] ?? ''), $m) ? (int) $m[1] : null;
        $issueOpenedAt = $existing?->issue_opened_at;

        if ($issue !== null && $issueOpenedAt === null) {
            $created = $github->get("/repos/{$repo}/issues/{$issue}")->json('created_at');
            $issueOpenedAt = is_string($created) ? CarbonImmutable::parse($created) : null;
        }

        AiPullRequest::updateOrCreate(['number' => $pr['number']], [
            'title' => mb_substr((string) $pr['title'], 0, 255),
            'branch' => (string) $pr['head']['ref'],
            'state' => $pr['merged_at'] !== null ? 'merged' : (string) $pr['state'],
            'auto_merged' => in_array('ai:automerged', array_column((array) $pr['labels'], 'name'), true),
            'issue' => $issue,
            'issue_opened_at' => $issueOpenedAt,
            'opened_at' => CarbonImmutable::parse((string) $pr['created_at']),
            'merged_at' => $pr['merged_at'] !== null ? CarbonImmutable::parse((string) $pr['merged_at']) : null,
        ]);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl('https://api.github.com')
            ->withToken($this->tokens->mint()->value)
            ->accept('application/vnd.github+json')
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->timeout(20);
    }
}
