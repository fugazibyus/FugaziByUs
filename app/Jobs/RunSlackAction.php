<?php

namespace App\Jobs;

use App\Slack\GitHubRepository;
use App\Slack\SlackClient;
use App\Slack\Team;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Developer commands from Slack, carried out by GitHub Actions (repository_dispatch):
 * release → ai-release.yml, deploy → deploy-gate.yml, pause/resume → ai-switches.yml.
 */
class RunSlackAction implements ShouldQueue
{
    use Queueable;

    private const EVENTS = [
        'release' => ['ai-release', 'Asked the release agent to prepare a release PR.'],
        'deploy' => ['deploy-gate', 'Asked the Deploy Gate to ship the release PR now on your behalf. Its decision appears on the PR.'],
        'pause' => ['ai-pause', 'AI merging and AI production deploys are being switched off. `/fugazi resume` turns them back on.'],
        'resume' => ['ai-resume', 'AI merging and AI production deploys are being switched back on (devs-only, business hours).'],
    ];

    public function __construct(
        public readonly string $action,
        public readonly string $slackUser,
        public readonly string $responseUrl,
    ) {}

    public function handle(GitHubRepository $github, Team $team): void
    {
        [$event, $done] = self::EVENTS[$this->action];
        $member = $team->bySlack($this->slackUser);

        try {
            $github->dispatch($event, ['requested_by' => (string) ($member['github'] ?? ''), 'pr' => '']);
        } catch (Throwable $e) {
            SlackClient::respond($this->responseUrl, '⚠️ GitHub didn\'t accept the request. Try again, or use the Actions tab.');

            throw $e;
        }

        SlackClient::respond($this->responseUrl, "✅ {$done}", public: in_array($this->action, ['pause', 'resume'], true));
    }
}
