<?php

namespace App\Ai;

use App\Ai\Exceptions\AgentConfigurationException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Mints the agents' GitHub tokens from the "Fugazi AI" GitHub App.
 *
 * Each token lasts one hour, works on this one repository only, and carries only the
 * permissions below. Even if the App is later installed on more repositories or given
 * more permissions, these tokens stay this narrow.
 */
class GitHubAppTokenIssuer
{
    /** What the agents may do. `workflows` is deliberately absent: agents can never change CI. */
    public const PERMISSIONS = [
        'contents' => 'write',      // clone, push ai/* branches
        'pull_requests' => 'write', // open PRs, post reviews
        'issues' => 'write',        // comment, labels
        'actions' => 'read',        // read failing CI logs (debugger)
        'checks' => 'read',         // `gh pr checks`
        'statuses' => 'read',       // commit statuses
    ];

    public function __construct(
        private readonly string $appId,
        private readonly string $privateKey,
        private readonly string $repository,
    ) {}

    public static function fromConfig(): self
    {
        $key = (string) config('ai.github_app.private_key');
        $path = (string) config('ai.github_app.private_key_path');

        if ($key === '' && $path !== '') {
            $file = str_starts_with($path, '/') ? $path : base_path($path);
            $key = is_readable($file) ? (string) file_get_contents($file) : '';
        }

        return new self((string) config('ai.github_app.id'), $key, (string) config('ai.repository'));
    }

    public function isConfigured(): bool
    {
        return $this->appId !== '' && $this->privateKey !== '';
    }

    public function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new AgentConfigurationException(
                'The Fugazi AI GitHub App is not configured – set AI_GITHUB_APP_ID and AI_GITHUB_APP_PRIVATE_KEY_PATH (or AI_GITHUB_APP_PRIVATE_KEY). See docs/SETUP-AI.md.',
            );
        }
    }

    public function mint(): GitHubToken
    {
        $this->ensureConfigured();
        $github = $this->asApp();

        try {
            $installationId = $github->get("/repos/{$this->repository}/installation")->throw()->json('id');

            $response = $github->post("/app/installations/{$installationId}/access_tokens", [
                'repositories' => [substr($this->repository, (int) strrpos($this->repository, '/') + 1)],
                'permissions' => self::PERMISSIONS,
            ])->throw();
        } catch (RequestException $e) {
            throw new AgentConfigurationException(match ($e->response->status()) {
                401 => 'GitHub rejected the App credentials – check AI_GITHUB_APP_ID and the private key.',
                404 => "The Fugazi AI GitHub App is not installed on {$this->repository}.",
                422 => 'The Fugazi AI GitHub App is missing a permission the agents need – compare its settings with docs/SETUP-AI.md.',
                default => "GitHub refused to issue an agent token (HTTP {$e->response->status()}).",
            }, previous: $e);
        }

        return new GitHubToken(
            (string) $response->json('token'),
            CarbonImmutable::parse((string) $response->json('expires_at')),
        );
    }

    /** A GitHub API client authenticated as the App itself (only used to mint installation tokens). */
    private function asApp(): PendingRequest
    {
        return Http::baseUrl('https://api.github.com')
            ->withToken($this->jwt())
            ->accept('application/vnd.github+json')
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->timeout(15);
    }

    /** A 9-minute RS256 JWT signed with the App's private key (GitHub allows at most 10). */
    private function jwt(): string
    {
        $key = openssl_pkey_get_private($this->privateKey);

        if ($key === false) {
            throw new AgentConfigurationException('The GitHub App private key could not be read – it must be the .pem file GitHub generated.');
        }

        $now = time();
        $unsigned = self::base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
            .'.'.self::base64Url((string) json_encode(['iat' => $now - 60, 'exp' => $now + 540, 'iss' => $this->appId]));

        openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256);

        return $unsigned.'.'.self::base64Url($signature);
    }

    private static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
