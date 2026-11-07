<?php

namespace App\Gates;

use App\Ai\Exceptions\AgentConfigurationException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The GitHub REST calls the gates make, authenticated as the "Fugazi Merge Gate" App.
 */
class GitHub
{
    /** @var array<string, bool> "team/login" => active member */
    private array $members = [];

    public function __construct(
        private readonly string $token,
        private readonly string $repository,
        private readonly string $devsTeam = 'devs',
        private readonly string $productTeam = 'product',
    ) {}

    public static function fromConfig(): self
    {
        $token = (string) config('gates.token');

        if ($token === '') {
            throw new AgentConfigurationException('No gate token. GATE_GITHUB_TOKEN must hold a Fugazi Merge Gate App token (the gate workflows mint one).');
        }

        return new self(
            $token,
            (string) config('gates.repository'),
            (string) config('gates.devs_team'),
            (string) config('gates.product_team'),
        );
    }

    /** @return array<string, mixed> */
    public function pullRequest(int $number): array
    {
        return $this->get("/pulls/{$number}");
    }

    /** @return list<array<string, mixed>> */
    public function openPullRequests(string $base, string $head): array
    {
        return $this->list('/pulls', ['state' => 'open', 'base' => $base, 'head' => $this->owner().':'.$head]);
    }

    /** @return list<string> */
    public function pullRequestFiles(int $number): array
    {
        return array_column($this->paginate("/pulls/{$number}/files"), 'filename');
    }

    /** @return list<array<string, mixed>> */
    public function reviews(int $number): array
    {
        return $this->paginate("/pulls/{$number}/reviews");
    }

    /** @return array<string, mixed> */
    public function issue(int $number): array
    {
        return $this->get("/issues/{$number}");
    }

    /** @return array<string, mixed> */
    public function comment(int $id): array
    {
        return $this->get("/issues/comments/{$id}");
    }

    /** @return list<array<string, mixed>> */
    public function issueEvents(int $number): array
    {
        return $this->paginate("/issues/{$number}/events");
    }

    /**
     * The state of each named check on a commit: success, failure, pending or missing.
     *
     * @param  list<string>  $names
     * @return array<string, string>
     */
    public function checkStates(string $sha, array $names): array
    {
        $runs = $this->get("/commits/{$sha}/check-runs", ['per_page' => 100])['check_runs'] ?? [];
        usort($runs, fn (array $a, array $b) => $b['id'] <=> $a['id']); // newest first
        $states = [];

        foreach ($names as $name) {
            $run = collect($runs)->firstWhere('name', $name);

            $states[$name] = match (true) {
                $run === null => 'missing',
                $run['status'] !== 'completed' => 'pending',
                $run['conclusion'] === 'success' => 'success',
                default => 'failure',
            };
        }

        return $states;
    }

    /** @return array<string, mixed> */
    public function compare(string $base, string $head): array
    {
        return $this->get('/compare/'.rawurlencode($base).'...'.rawurlencode($head));
    }

    /** @return list<array<string, mixed>> */
    public function pullRequestsForCommit(string $sha): array
    {
        return $this->list("/commits/{$sha}/pulls");
    }

    /** @return array{created_at: CarbonImmutable, state: string}|null */
    public function latestDeployment(string $environment): ?array
    {
        $deployment = $this->get('/deployments', ['environment' => $environment, 'per_page' => 1])[0] ?? null;

        if ($deployment === null) {
            return null;
        }

        $status = $this->get("/deployments/{$deployment['id']}/statuses", ['per_page' => 1])[0] ?? null;

        return [
            'created_at' => CarbonImmutable::parse($deployment['created_at']),
            'state' => (string) ($status['state'] ?? 'pending'),
        ];
    }

    /** Is this login an active member of the developers team? */
    public function isDev(?string $login): bool
    {
        return $this->isMember($this->devsTeam, $login);
    }

    /** Is this login an active member of the product team? They may ship AI work to staging. */
    public function isProduct(?string $login): bool
    {
        return $this->isMember($this->productTeam, $login);
    }

    private function isMember(string $team, ?string $login): bool
    {
        if ($login === null || $login === '' || str_ends_with($login, '[bot]')) {
            return false;
        }

        return $this->members["{$team}/{$login}"] ??= $this->http()
            ->get("/orgs/{$this->owner()}/teams/{$team}/memberships/{$login}")
            ->json('state') === 'active';
    }

    public function merge(int $number, string $sha, string $method, string $title, string $message): void
    {
        $this->http()->put($this->path("/pulls/{$number}/merge"), [
            'sha' => $sha,
            'merge_method' => $method,
            'commit_title' => $title,
            'commit_message' => $message,
        ])->throw();
    }

    public function updateBranch(int $number, string $sha): void
    {
        $this->http()->put($this->path("/pulls/{$number}/update-branch"), ['expected_head_sha' => $sha])->throw();
    }

    /** @param  list<string>  $labels */
    public function addLabels(int $number, array $labels): void
    {
        $this->http()->post($this->path("/issues/{$number}/labels"), ['labels' => $labels])->throw();
    }

    /** Remove a label; it's fine if it isn't there. */
    public function removeLabel(int $number, string $label): void
    {
        $response = $this->http()->delete($this->path("/issues/{$number}/labels/".rawurlencode($label)));

        if ($response->failed() && $response->status() !== 404) {
            $response->throw();
        }
    }

    /**
     * Create the gate's comment on a PR, or edit it if it's already there.
     *
     * @return bool whether the decision changed (ignoring the "checked at" footer), so the
     *              workflow only tells Slack about news, not about every re-check
     */
    public function upsertComment(int $number, string $marker, string $body): bool
    {
        $existing = collect($this->paginate("/issues/{$number}/comments"))
            ->first(fn (array $c) => ($c['user']['type'] ?? null) === 'Bot' && str_contains((string) $c['body'], $marker));

        $request = $existing
            ? $this->http()->patch($this->path("/issues/comments/{$existing['id']}"), ['body' => $body])
            : $this->http()->post($this->path("/issues/{$number}/comments"), ['body' => $body]);

        $request->throw();

        $withoutFooter = fn (string $text) => trim((string) preg_replace('#<sub>.*?</sub>#s', '', $text));

        return $existing === null || $withoutFooter((string) $existing['body']) !== $withoutFooter($body);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     */
    private function get(string $path, array $query = []): array
    {
        return (array) $this->http()->get($this->path($path), $query)->throw()->json();
    }

    /**
     * An endpoint that returns a JSON array of objects.
     *
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function list(string $path, array $query = []): array
    {
        /** @var list<array<string, mixed>> $items */
        $items = array_values(array_filter($this->get($path, $query), is_array(...)));

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function paginate(string $path): array
    {
        $items = [];

        for ($page = 1; $page <= 10; $page++) {
            $batch = $this->list($path, ['per_page' => 100, 'page' => $page]);
            $items = [...$items, ...$batch];

            if (count($batch) < 100) {
                break;
            }
        }

        return $items;
    }

    private function path(string $path): string
    {
        return "/repos/{$this->repository}{$path}";
    }

    private function owner(): string
    {
        return explode('/', $this->repository)[0];
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl('https://api.github.com')
            ->withToken($this->token)
            ->accept('application/vnd.github+json')
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->timeout(20);
    }
}
