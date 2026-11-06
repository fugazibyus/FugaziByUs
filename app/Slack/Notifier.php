<?php

namespace App\Slack;

use App\Jobs\SendSlackAlert;
use App\Models\SlackReporter;

/**
 * Routes an event to its Slack channels and DMs (config/ai-workflow.php). The sending
 * happens in a queued job, so webhooks and page requests never wait for Slack.
 */
class Notifier
{
    public function __construct(private readonly Team $team) {}

    /**
     * @param  string  $event  a key in config('ai-workflow.routes'), e.g. "pr.needs_human"
     * @param  ?int  $issue  threads messages about the same issue, and finds its reporter
     * @param  ?string  $reporterGithub  the issue author's GitHub login, if known
     */
    public function notify(string $event, string $text, ?int $issue = null, ?string $reporterGithub = null): void
    {
        // Route names contain dots ("pr.needs_human"), so look them up directly, not with dot notation.
        /** @var array{channels?: list<string>, dm?: list<string>}|null $route */
        $route = ((array) config('ai-workflow.routes'))[$event] ?? null;

        if ($route === null) {
            return;
        }

        $channels = array_values(array_filter(array_map(
            fn (string $key) => config("ai-workflow.channels.{$key}"),
            $route['channels'] ?? [],
        )));

        $people = [];

        foreach ($route['dm'] ?? [] as $who) {
            $people = [...$people, ...match ($who) {
                'reporter' => array_filter([$this->reporter($issue, $reporterGithub)]),
                'on_duty' => $this->team->onDuty(),
                'devs' => $this->team->devs(),
                default => [],
            }];
        }

        if ($channels !== [] || $people !== []) {
            SendSlackAlert::dispatch($text, $channels, array_values(array_unique($people)), $issue);
        }
    }

    private function reporter(?int $issue, ?string $github): ?string
    {
        $fromSlack = $issue !== null ? SlackReporter::firstWhere('issue', $issue)?->slack_user : null;

        return $fromSlack ?? $this->team->slackIdFor($github);
    }
}
