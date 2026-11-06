<?php

namespace App\Slack;

use App\Ai\GitHubAppTokenIssuer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * What Slack needs from GitHub, as the Fugazi AI App: create issues, and ask GitHub Actions
 * to run something (repository_dispatch).
 */
class GitHubRepository
{
    public function __construct(private readonly GitHubAppTokenIssuer $tokens) {}

    /**
     * @param  list<string>  $labels
     * @return array{number: int, url: string}
     */
    public function createIssue(string $title, string $body, array $labels): array
    {
        $issue = $this->http()->post($this->path('/issues'), compact('title', 'body', 'labels'))->throw()->json();

        return ['number' => (int) $issue['number'], 'url' => (string) $issue['html_url']];
    }

    /**
     * Ask a workflow to run. Requests that act on a developer's behalf carry a signature the
     * workflow checks, because the agents' own token could otherwise send the same event.
     *
     * @param  array<string, string>  $payload
     */
    public function dispatch(string $event, array $payload = []): void
    {
        $payload['ts'] = (string) time();
        $payload['signature'] = self::sign($event, $payload);

        $this->http()->post($this->path('/dispatches'), ['event_type' => $event, 'client_payload' => $payload])->throw();
    }

    /**
     * HMAC-SHA256 over "event|requested_by|pr|ts" with AI_DISPATCH_SECRET. The workflows
     * recompute the same string (see .github/workflows/deploy-gate.yml).
     *
     * @param  array<string, string>  $payload
     */
    public static function sign(string $event, array $payload): string
    {
        $message = implode('|', [$event, $payload['requested_by'] ?? '', $payload['pr'] ?? '', $payload['ts'] ?? '']);

        return hash_hmac('sha256', $message, (string) config('services.github.dispatch_secret'));
    }

    private function path(string $path): string
    {
        return '/repos/'.config('ai.repository').$path;
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl('https://api.github.com')
            ->withToken($this->tokens->mint()->value)
            ->accept('application/vnd.github+json')
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->timeout(15);
    }
}
