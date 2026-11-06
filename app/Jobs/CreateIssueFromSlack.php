<?php

namespace App\Jobs;

use App\Models\SlackReporter;
use App\Slack\GitHubRepository;
use App\Slack\SlackClient;
use App\Slack\Team;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turn a Slack report into a GitHub issue that enters the normal AI workflow
 * (labelled source:slack, flow:ai, needs-triage; the triage agent picks it up).
 */
class CreateIssueFromSlack implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    private const LABELS = ['bug' => 'type:bug', 'feature' => 'type:feature', 'research' => 'type:research'];

    public function __construct(
        public readonly string $kind,
        public readonly string $text,
        public readonly string $slackUser,
        public readonly string $slackName,
        public readonly ?string $responseUrl = null,
        public readonly ?string $channel = null,
        public readonly ?string $messageTs = null,
    ) {}

    public function handle(GitHubRepository $github, SlackClient $slack, Team $team): void
    {
        $member = $team->bySlack($this->slackUser);
        $title = Str::limit(Str::of($this->text)->before("\n")->squish()->toString(), 80, '…');
        $prefix = ['bug' => 'Bug', 'feature' => 'Feature', 'research' => 'Research'][$this->kind];

        // The markers let the workflow treat a mapped developer as the reporter (issues created
        // by the Fugazi AI App can only be edited by collaborators, so they can be trusted).
        $body = implode("\n", array_filter([
            $this->text,
            '',
            '---',
            "Reported in Slack by {$this->slackName}.",
            "<!-- slack-reporter: {$this->slackUser} -->",
            $member !== null ? "<!-- reported-by-github: {$member['github']} -->" : null,
        ], fn (?string $line) => $line !== null));

        try {
            $issue = $github->createIssue("{$prefix}: {$title}", $body, [self::LABELS[$this->kind], 'source:slack', 'needs-triage', 'flow:ai']);
        } catch (Throwable $e) {
            $this->tell($slack, '⚠️ Sorry, I couldn\'t create the GitHub issue. A developer has been told.');

            throw $e;
        }

        SlackReporter::updateOrCreate(['issue' => $issue['number']], ['slack_user' => $this->slackUser]);

        $this->tell($slack, "✅ Created <{$issue['url']}|#{$issue['number']} {$title}>. The triage agent is reading it now, and I'll message you when it's done.");
    }

    private function tell(SlackClient $slack, string $text): void
    {
        if ($this->responseUrl !== null) {
            SlackClient::respond($this->responseUrl, $text);

            return;
        }

        // From the message shortcut: reply in the original message's thread, or DM the reporter.
        try {
            $slack->post($this->channel ?? $this->slackUser, $text, $this->channel !== null ? $this->messageTs : null);
        } catch (Throwable) {
            $slack->post($this->slackUser, $text);
        }
    }
}
