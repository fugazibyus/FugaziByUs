<?php

namespace App\Jobs;

use App\Models\ContactMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Post a contact form message to #fugazi-contact, so the team sees it straight away.
 * Queued, so the visitor never waits for Slack.
 */
class PostContactMessageToSlack implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public readonly ContactMessage $message) {}

    public function handle(): void
    {
        $token = (string) config('services.slack.bot_token');

        if ($token === '') {
            return;
        }

        $m = $this->message;
        $response = Http::withToken($token)->asJson()->timeout(10)->post('https://slack.com/api/chat.postMessage', [
            'channel' => config('services.slack.contact_channel'),
            'text' => "📬 *{$m->subject}* from {$m->name} ({$m->email})\n>".str_replace("\n", "\n>", Str::limit($m->message, 1500)),
            'unfurl_links' => false,
        ])->throw();

        if ($response->json('ok') !== true) {
            throw new RuntimeException('Slack refused the contact message: '.$response->json('error', 'unknown error'));
        }
    }
}
