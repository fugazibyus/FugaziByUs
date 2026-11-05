<?php

namespace Tests\Feature\Ai;

use Illuminate\Support\Facades\Http;

/** Configures a throwaway GitHub App key and fakes GitHub's token endpoints. */
trait FakesGitHubApp
{
    private static ?string $githubAppKey = null;

    protected function fakeGitHubApp(string $token = 'ghs_test_token', int $installationStatus = 200): void
    {
        if (self::$githubAppKey === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, self::$githubAppKey);
        }

        config([
            'ai.repository' => 'fugazibyus/FugaziByUs',
            'ai.github_app.id' => '123456',
            'ai.github_app.private_key' => self::$githubAppKey,
            'ai.github_app.private_key_path' => null,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'api.github.com/repos/fugazibyus/FugaziByUs/installation' => Http::response(['id' => 987], $installationStatus),
            'api.github.com/app/installations/987/access_tokens' => Http::response(['token' => $token, 'expires_at' => '2026-11-06T13:00:00Z'], 201),
        ]);
    }

    /** @return array<string, mixed> the JWT payload GitHub received, after checking its signature */
    protected function verifiedJwtPayload(string $authorization): array
    {
        [$header, $payload, $signature] = explode('.', substr($authorization, strlen('Bearer ')));
        $publicKey = openssl_pkey_get_details(openssl_pkey_get_private((string) self::$githubAppKey))['key'];
        $decode = fn (string $part) => (string) base64_decode(strtr($part, '-_', '+/'));

        $this->assertSame(1, openssl_verify("{$header}.{$payload}", $decode($signature), $publicKey, OPENSSL_ALGO_SHA256));

        return json_decode($decode($payload), true);
    }
}
