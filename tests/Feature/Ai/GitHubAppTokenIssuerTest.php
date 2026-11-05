<?php

namespace Tests\Feature\Ai;

use App\Ai\Exceptions\AgentConfigurationException;
use App\Ai\GitHubAppTokenIssuer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubAppTokenIssuerTest extends TestCase
{
    use FakesGitHubApp;

    public function test_it_mints_a_token_limited_to_this_repository_and_the_agent_permissions(): void
    {
        $this->fakeGitHubApp('ghs_fresh');

        $token = app(GitHubAppTokenIssuer::class)->mint();

        $this->assertSame('ghs_fresh', $token->value);
        $this->assertSame('2026-11-06 13:00:00', $token->expiresAt->toDateTimeString());

        Http::assertSent(function (Request $request) {
            if (! str_ends_with($request->url(), '/access_tokens')) {
                return false;
            }

            $this->assertSame(['FugaziByUs'], $request['repositories']);
            $this->assertSame(GitHubAppTokenIssuer::PERMISSIONS, $request['permissions']);
            $this->assertArrayNotHasKey('workflows', $request['permissions']);

            $jwt = $this->verifiedJwtPayload($request->header('Authorization')[0]);
            $this->assertSame('123456', $jwt['iss']);
            $this->assertLessThanOrEqual(600, $jwt['exp'] - $jwt['iat']);

            return true;
        });
    }

    public function test_it_reads_the_private_key_from_a_file(): void
    {
        $this->fakeGitHubApp();
        $path = storage_path('framework/testing/github-app.pem');
        file_put_contents($path, config('ai.github_app.private_key'));
        config(['ai.github_app.private_key' => null, 'ai.github_app.private_key_path' => $path]);

        try {
            $this->assertSame('ghs_test_token', app(GitHubAppTokenIssuer::class)->mint()->value);
        } finally {
            unlink($path);
        }
    }

    public function test_a_missing_app_configuration_fails_clearly(): void
    {
        config(['ai.github_app.id' => null, 'ai.github_app.private_key' => null, 'ai.github_app.private_key_path' => null]);

        $issuer = app(GitHubAppTokenIssuer::class);

        $this->assertFalse($issuer->isConfigured());
        $this->expectExceptionMessage('AI_GITHUB_APP_ID');
        $issuer->mint();
    }

    public function test_an_app_that_is_not_installed_on_the_repository_fails_clearly(): void
    {
        $this->fakeGitHubApp(installationStatus: 404);

        $this->expectException(AgentConfigurationException::class);
        $this->expectExceptionMessage('not installed on fugazibyus/FugaziByUs');

        app(GitHubAppTokenIssuer::class)->mint();
    }
}
