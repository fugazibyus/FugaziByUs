<?php

namespace App\Gates;

use App\Ai\Exceptions\AgentConfigurationException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Turns GitHub (and the staging site) into the plain facts the policies decide on.
 */
class GateFactsCollector
{
    private const CLOSES = '/\b(?:close[sd]?|fix(?:e[sd])?|resolve[sd]?)\s+#(\d+)/i';

    public function __construct(private readonly GitHub $github) {}

    /**
     * Who asked, proven by their own comment: it must be on this PR, recent, and say the command.
     * (A name typed into a workflow input could be anyone's.)
     */
    public function requesterFromComment(int $number, int $commentId, string $command): string
    {
        $comment = $this->github->comment($commentId);
        $onThisPr = str_ends_with((string) $comment['issue_url'], "/issues/{$number}");
        $recent = CarbonImmutable::parse((string) $comment['created_at'])->greaterThan(CarbonImmutable::now()->subMinutes(30));
        $says = preg_match('/^\/ai\s+'.preg_quote($command, '/').'\b/i', (string) $comment['body']) === 1;

        if (! $onThisPr || ! $recent || ! $says) {
            throw new AgentConfigurationException("Comment {$commentId} isn't a recent `/ai {$command}` on #{$number}.");
        }

        return (string) $comment['user']['login'];
    }

    public function merge(int $number, ?string $requestedBy = null, ?string $requestedVia = null): MergeFacts
    {
        $pr = $this->github->pullRequest($number);
        $headSha = (string) $pr['head']['sha'];
        $issueNumber = preg_match(self::CLOSES, (string) ($pr['body'] ?? ''), $m) ? (int) $m[1] : null;
        $issue = $issueNumber !== null ? $this->github->issue($issueNumber) : null;
        $reviews = $this->github->reviews($number);

        $labels = array_values(array_unique([
            ...array_column($pr['labels'], 'name'),
            ...array_column($issue['labels'] ?? [], 'name'),
        ]));

        return new MergeFacts(
            number: $number,
            title: (string) $pr['title'],
            open: $pr['state'] === 'open',
            draft: (bool) $pr['draft'],
            fromFork: ($pr['head']['repo']['full_name'] ?? null) !== config('gates.repository'),
            base: (string) $pr['base']['ref'],
            head: (string) $pr['head']['ref'],
            headSha: $headSha,
            author: (string) $pr['user']['login'],
            labels: $labels,
            checks: $this->github->checkStates($headSha, $this->requiredChecks()),
            files: $this->github->pullRequestFiles($number),
            behindBy: (int) ($this->github->compare((string) $pr['base']['ref'], $headSha)['behind_by'] ?? 0),
            verdict: $this->verdict($reviews),
            issue: $issueNumber,
            reportedByDev: $issue !== null && $this->github->isDev($issue['user']['login'] ?? null),
            optedInBy: $this->optedInBy(array_filter([$number, $issueNumber]), $labels),
            requestedBy: $requestedBy,
            requesterIsDev: $requestedBy !== null && $this->github->isDev($requestedBy),
            requesterIsProduct: $requestedBy !== null && $this->github->isProduct($requestedBy),
            requestedVia: $requestedVia,
            sessions: $this->sessions([(string) ($pr['body'] ?? ''), ...array_map(fn (array $r) => (string) $r['body'], $reviews)]),
        );
    }

    public function deploy(int $number, ?string $requestedBy = null, ?string $requestedVia = null): DeployFacts
    {
        $pr = $this->github->pullRequest($number);
        $headSha = (string) $pr['head']['sha'];
        $base = (string) $pr['base']['ref'];
        $head = (string) $pr['head']['ref'];
        $compare = $this->github->compare($base, $head);
        $staging = $this->staging();

        /** @var list<string> $approvals */
        $approvals = collect($this->github->reviews($number))
            ->filter(fn (array $r) => $r['state'] === 'APPROVED')
            ->pluck('user.login')
            ->unique()
            ->filter(fn (string $login) => $this->github->isDev($login))
            ->values()
            ->all();

        return new DeployFacts(
            number: $number,
            open: $pr['state'] === 'open',
            draft: (bool) $pr['draft'],
            base: $base,
            head: $head,
            headSha: $headSha,
            labels: array_column($pr['labels'], 'name'),
            checks: $this->github->checkStates($headSha, $this->requiredChecks()),
            now: CarbonImmutable::now(),
            stagingUrl: $staging['url'],
            stagingHealthy: $staging['healthy'],
            stagingSha: $staging['sha'],
            stagingDeployedAt: $staging['deployed_at'],
            releasedPrs: $this->releasedPullRequests($compare, $head),
            touchesMigrations: PathPatterns::matching(array_column($compare['files'] ?? [], 'filename'), ['database/migrations/**']) !== [],
            devApprovals: $approvals,
            lastDeploy: $this->github->latestDeployment('production'),
            requestedBy: $requestedBy,
            requesterIsDev: $requestedBy !== null && $this->github->isDev($requestedBy),
            requestedVia: $requestedVia,
        );
    }

    /**
     * The reviewer agent's latest verdict. Only reviews written by the AI App count, so nobody
     * can post a fake verdict.
     *
     * @param  list<array<string, mixed>>  $reviews
     * @return array{verdict: string, risk: string, sha: string, blocking: int}|null
     */
    private function verdict(array $reviews): ?array
    {
        $aiBot = (string) config('gates.ai_bot');

        foreach (array_reverse($reviews) as $review) {
            if (($review['user']['login'] ?? null) !== $aiBot) {
                continue;
            }

            if (preg_match('/<!--\s*ai-verdict\s*(\{.*?\})\s*-->/s', (string) $review['body'], $m)) {
                $data = json_decode($m[1], true);

                if (is_array($data)) {
                    return [
                        'verdict' => (string) ($data['verdict'] ?? ''),
                        'risk' => (string) ($data['risk'] ?? 'high'),
                        'sha' => (string) ($data['sha'] ?? ''),
                        'blocking' => (int) ($data['blocking'] ?? 1),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * The developer or product member who most recently added `ai:automerge` (still present)
     * to the PR or its issue.
     *
     * `ai:go` doesn't count: it only lets the agent start work. Someone must opt in to merging
     * separately, so a designer's ticket can't merge just because work began.
     *
     * @param  array<int>  $numbers
     * @param  list<string>  $labels
     */
    private function optedInBy(array $numbers, array $labels): ?string
    {
        if (! in_array('ai:automerge', $labels, true)) {
            return null;
        }

        $by = null;

        foreach ($numbers as $number) {
            foreach ($this->github->issueEvents($number) as $event) {
                $label = $event['label']['name'] ?? null;
                $actor = $event['actor']['login'] ?? null;

                if ($event['event'] === 'labeled' && $label === 'ai:automerge' && ($this->github->isDev($actor) || $this->github->isProduct($actor))) {
                    $by = $actor;
                }
            }
        }

        return $by;
    }

    /**
     * @param  array<string, mixed>  $compare
     * @return list<array{number: int, merged_by: string, labels: list<string>}>
     */
    private function releasedPullRequests(array $compare, string $branch): array
    {
        $numbers = [];

        foreach ($compare['commits'] ?? [] as $commit) {
            foreach ($this->github->pullRequestsForCommit((string) $commit['sha']) as $pr) {
                if (($pr['merged_at'] ?? null) !== null && ($pr['base']['ref'] ?? null) === $branch) {
                    $numbers[(int) $pr['number']] = true;
                }
            }
        }

        return array_map(function (int $number) {
            $pr = $this->github->pullRequest($number);

            return [
                'number' => $number,
                'merged_by' => (string) ($pr['merged_by']['login'] ?? ''),
                'labels' => array_column($pr['labels'], 'name'),
            ];
        }, array_keys($numbers));
    }

    /** @return array{url: ?string, healthy: bool, sha: ?string, deployed_at: ?CarbonImmutable} */
    private function staging(): array
    {
        $url = rtrim((string) config('gates.staging_url'), '/');

        if ($url === '') {
            return ['url' => null, 'healthy' => false, 'sha' => null, 'deployed_at' => null];
        }

        try {
            $up = Http::timeout(10)->get("{$url}/up")->successful();
            $version = Http::timeout(10)->acceptJson()->get("{$url}/version")->json();

            return [
                'url' => $url,
                'healthy' => $up,
                'sha' => is_array($version) ? ($version['sha'] ?? null) : null,
                'deployed_at' => is_array($version) && ! empty($version['deployed_at']) ? CarbonImmutable::parse($version['deployed_at']) : null,
            ];
        } catch (Throwable) {
            return ['url' => $url, 'healthy' => false, 'sha' => null, 'deployed_at' => null];
        }
    }

    /** @return list<string> */
    private function requiredChecks(): array
    {
        return array_map(strval(...), array_values((array) config('gates.required_checks')));
    }

    /**
     * @param  list<string>  $texts
     * @return list<string>
     */
    private function sessions(array $texts): array
    {
        preg_match_all('/\b(sesn_[A-Za-z0-9]+)\b/', implode("\n", $texts), $m);

        return array_values(array_unique($m[1]));
    }
}
