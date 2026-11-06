<?php

namespace App\Slack;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The Slack Web API calls the app makes, as the Fugazi AI bot.
 */
class SlackClient
{
    public function __construct(private readonly ?string $token) {}

    public function enabled(): bool
    {
        return (string) $this->token !== '';
    }

    /**
     * Post a message to a channel, or to a member ID to DM them. Returns the message timestamp.
     *
     * @param  list<array<string, mixed>>  $blocks
     */
    public function post(string $channel, string $text, ?string $threadTs = null, array $blocks = []): ?string
    {
        $response = $this->call('chat.postMessage', array_filter([
            'channel' => $channel,
            'text' => $text,
            'thread_ts' => $threadTs,
            'blocks' => $blocks ?: null,
            'unfurl_links' => false,
        ]));

        return $response['ts'] ?? null;
    }

    /** @param  array<string, mixed>  $view */
    public function openView(string $triggerId, array $view): void
    {
        $this->call('views.open', ['trigger_id' => $triggerId, 'view' => $view]);
    }

    /** Reply to a slash command or shortcut after the 3-second window (valid for 30 minutes). */
    public static function respond(string $responseUrl, string $text, bool $public = false): void
    {
        if (! str_starts_with($responseUrl, 'https://hooks.slack.com/')) {
            return;
        }

        Http::timeout(10)->post($responseUrl, ['text' => $text, 'response_type' => $public ? 'in_channel' : 'ephemeral']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function call(string $method, array $payload): array
    {
        if (! $this->enabled()) {
            Log::debug("Slack is not configured; skipped {$method}.");

            return [];
        }

        $response = $this->http()->post("https://slack.com/api/{$method}", $payload)->throw()->json();

        if (! is_array($response) || ($response['ok'] ?? false) !== true) {
            throw new RuntimeException("Slack {$method} failed: ".(is_array($response) ? ($response['error'] ?? 'unknown') : 'no response'));
        }

        return $response;
    }

    private function http(): PendingRequest
    {
        return Http::withToken((string) $this->token)->asJson()->timeout(10);
    }
}
