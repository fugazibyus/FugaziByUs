<?php

namespace App\Jobs;

use App\Models\SlackThread;
use App\Slack\SlackClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Post one alert to its channels (threaded per issue) and DMs.
 */
class SendSlackAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<string>  $channels
     * @param  list<string>  $people  Slack member IDs to DM
     */
    public function __construct(
        public readonly string $text,
        public readonly array $channels,
        public readonly array $people = [],
        public readonly ?int $issue = null,
    ) {}

    public function handle(SlackClient $slack): void
    {
        if (! $slack->enabled()) {
            return;
        }

        foreach ($this->channels as $channel) {
            $thread = $this->issue !== null ? SlackThread::firstWhere(['issue' => $this->issue, 'channel' => $channel]) : null;
            $ts = $slack->post($channel, $this->text, $thread?->ts);

            if ($this->issue !== null && $thread === null && $ts !== null) {
                SlackThread::create(['issue' => $this->issue, 'channel' => $channel, 'ts' => $ts]);
            }
        }

        foreach ($this->people as $person) {
            $slack->post($person, $this->text);
        }
    }
}
