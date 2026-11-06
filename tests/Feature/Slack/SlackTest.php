<?php

namespace Tests\Feature\Slack;

use App\Jobs\CreateIssueFromSlack;
use App\Jobs\RunSlackAction;
use App\Jobs\SendSlackAlert;
use App\Models\AiRun;
use App\Models\SlackReporter;
use App\Models\SlackThread;
use App\Slack\GitHubRepository;
use App\Slack\SlackClient;
use App\Slack\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Ai\FakesGitHubApp;
use Tests\TestCase;

class SlackTest extends TestCase
{
    use FakesGitHubApp;
    use RefreshDatabase;

    private const SECRET = 'slack-signing-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.slack.signing_secret' => self::SECRET,
            'services.slack.bot_token' => 'xoxb-test',
            'services.github.dispatch_secret' => 'dispatch-secret',
            'team.members' => [
                ['name' => 'Sam', 'github' => 'sgoodwin10', 'slack' => 'UDEV', 'dev' => true, 'on_duty' => true],
                ['name' => 'Casey', 'github' => 'casey-ceo', 'slack' => 'UCEO', 'dev' => false],
            ],
        ]);
    }

    /** @param  array<string, string>  $fields */
    private function slack(string $uri, array $fields, ?string $secret = self::SECRET, ?int $timestamp = null): TestResponse
    {
        $body = http_build_query($fields);
        $timestamp ??= time();

        // Like PHP does for Slack's real requests: the parsed fields plus the raw body that was signed.
        return $this->call('POST', $uri, $fields, [], [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'HTTP_X_SLACK_REQUEST_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_SLACK_SIGNATURE' => 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$body}", (string) $secret),
        ], $body);
    }

    private function command(string $text, string $user = 'UCEO'): TestResponse
    {
        return $this->slack('/slack/commands', [
            'command' => '/fugazi', 'text' => $text, 'user_id' => $user, 'user_name' => 'casey',
            'response_url' => 'https://hooks.slack.com/commands/T1/1/abc',
        ]);
    }

    public function test_slack_requests_must_be_signed_and_fresh(): void
    {
        Queue::fake();

        $this->slack('/slack/commands', ['text' => 'bug the page is broken'], secret: 'wrong')->assertUnauthorized();
        $this->slack('/slack/commands', ['text' => 'bug the page is broken'], timestamp: time() - 600)->assertUnauthorized();

        config(['services.slack.signing_secret' => null]);
        $this->slack('/slack/commands', ['text' => 'help'])->assertStatus(503);

        Queue::assertNothingPushed();
    }

    public function test_fugazi_bug_queues_a_github_issue(): void
    {
        Queue::fake();

        $this->command('bug The contact form shows no error when the email is wrong')
            ->assertOk()
            ->assertJsonPath('response_type', 'ephemeral')
            ->assertJsonPath('text', '👍 Got it. Creating a GitHub bug for the AI workflow…');

        Queue::assertPushed(CreateIssueFromSlack::class, fn (CreateIssueFromSlack $job) => $job->kind === 'bug'
            && $job->text === 'The contact form shows no error when the email is wrong'
            && $job->slackUser === 'UCEO');
    }

    public function test_too_short_reports_and_unknown_commands_get_help(): void
    {
        Queue::fake();

        $this->command('feature dark')->assertJsonPath('text', fn (string $text) => str_contains($text, 'Tell me a bit more'));
        $this->command('')->assertJsonPath('text', fn (string $text) => str_contains($text, '/fugazi bug'));
        Queue::assertNothingPushed();
    }

    public function test_status_reports_today(): void
    {
        AiRun::factory()->create(['started_at' => now(), 'cost_cents' => 150]);
        AiRun::factory()->running()->create(['started_at' => now(), 'cost_cents' => 20, 'title' => 'Developer · issue #9']);

        $this->command('status')->assertJsonPath('text', fn (string $text) => str_contains($text, 'Spend: $1.70 across 2 run(s)')
            && str_contains($text, 'Working now: Developer · issue #9'));
    }

    public function test_developer_commands_are_for_developers(): void
    {
        Queue::fake();

        $this->command('release', user: 'UCEO')->assertJsonPath('text', 'Only developers can use `/fugazi release`.');
        $this->command('deploy production', user: 'UNOBODY')->assertJsonPath('text', 'Only developers can use `/fugazi deploy`.');
        $this->command('deploy staging', user: 'UDEV')->assertJsonPath('text', fn (string $t) => str_contains($t, 'Staging deploys by itself'));
        $this->command('pause', user: 'UNOBODY')->assertJsonPath('text', fn (string $t) => str_contains($t, 'config/team.php'));
        Queue::assertNothingPushed();

        $this->command('deploy production', user: 'UDEV')->assertOk();
        $this->command('pause', user: 'UCEO')->assertOk(); // anyone on the team may pull the kill switch
        Queue::assertPushed(RunSlackAction::class, 2);
    }

    public function test_a_developer_command_is_sent_to_github_actions_signed(): void
    {
        $this->fakeGitHubApp();
        Http::fake(['api.github.com/repos/fugazibyus/FugaziByUs/dispatches' => Http::response(null, 204), 'hooks.slack.com/*' => Http::response('ok')]);

        (new RunSlackAction('deploy', 'UDEV', 'https://hooks.slack.com/commands/T1/1/abc'))->handle(app(GitHubRepository::class), app(Team::class));

        Http::assertSent(function (Request $r) {
            if (! str_ends_with($r->url(), '/dispatches')) {
                return false;
            }

            $payload = $r['client_payload'];

            return $r['event_type'] === 'deploy-gate'
                && $payload['requested_by'] === 'sgoodwin10'
                && $payload['signature'] === hash_hmac('sha256', "deploy-gate|sgoodwin10||{$payload['ts']}", 'dispatch-secret');
        });
    }

    public function test_the_job_creates_the_issue_and_remembers_the_reporter(): void
    {
        $this->fakeGitHubApp();
        Http::fake([
            'api.github.com/repos/fugazibyus/FugaziByUs/issues' => Http::response(['number' => 14, 'html_url' => 'https://github.com/fugazibyus/FugaziByUs/issues/14'], 201),
            'hooks.slack.com/*' => Http::response('ok'),
        ]);

        (new CreateIssueFromSlack('feature', "Add a dark mode toggle\nIt should remember my choice.", 'UDEV', 'sam', 'https://hooks.slack.com/commands/T1/1/abc'))
            ->handle(app(GitHubRepository::class), app(SlackClient::class), app(Team::class));

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/issues')
            && $r['title'] === 'Feature: Add a dark mode toggle'
            && $r['labels'] === ['type:feature', 'source:slack', 'needs-triage', 'flow:ai']
            && str_contains($r['body'], '<!-- slack-reporter: UDEV -->')
            && str_contains($r['body'], '<!-- reported-by-github: sgoodwin10 -->'));
        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://hooks.slack.com/') && str_contains($r['text'], '#14'));
        $this->assertSame('UDEV', SlackReporter::firstWhere('issue', 14)?->slack_user);
    }

    public function test_the_message_shortcut_opens_a_form_and_the_form_queues_an_issue(): void
    {
        Queue::fake();
        Http::fake(['slack.com/api/views.open' => Http::response(['ok' => true])]);

        $this->slack('/slack/interactions', ['payload' => json_encode([
            'type' => 'message_action', 'callback_id' => 'send_to_fugazi', 'trigger_id' => 'trig1',
            'channel' => ['id' => 'C1'], 'message' => ['ts' => '1.2', 'text' => 'The logo is blurry on retina screens'],
        ])])->assertOk();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'views.open')
            && $r['view']['callback_id'] === 'fugazi_issue'
            && $r['view']['blocks'][1]['element']['initial_value'] === 'The logo is blurry on retina screens');

        $this->slack('/slack/interactions', ['payload' => json_encode([
            'type' => 'view_submission', 'user' => ['id' => 'UCEO', 'name' => 'casey'],
            'view' => ['callback_id' => 'fugazi_issue', 'private_metadata' => json_encode(['channel' => 'C1', 'ts' => '1.2']), 'state' => ['values' => [
                'kind' => ['value' => ['selected_option' => ['value' => 'bug']]],
                'details' => ['value' => ['value' => 'The logo is blurry on retina screens']],
            ]]],
        ])])->assertJsonPath('response_action', 'clear');

        Queue::assertPushed(CreateIssueFromSlack::class, fn (CreateIssueFromSlack $job) => $job->kind === 'bug' && $job->channel === 'C1' && $job->messageTs === '1.2');
    }

    /** @param  array<string, mixed>  $payload */
    private function github(string $event, array $payload, string $secret = 'gh-secret'): TestResponse
    {
        config(['services.github.webhook_secret' => 'gh-secret']);
        $body = (string) json_encode($payload);

        return $this->call('POST', '/webhooks/github', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => $event,
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, $secret),
        ], $body);
    }

    public function test_github_webhooks_must_be_signed(): void
    {
        Queue::fake();

        $this->github('issues', ['action' => 'opened'], secret: 'wrong')->assertUnauthorized();
        Queue::assertNothingPushed();
    }

    public function test_a_new_issue_goes_to_its_channel(): void
    {
        Queue::fake();

        $this->github('issues', ['action' => 'opened', 'issue' => [
            'number' => 15, 'title' => 'Logo is blurry', 'html_url' => 'https://github.com/x/15', 'user' => ['login' => 'casey-ceo'],
            'labels' => [['name' => 'type:bug'], ['name' => 'flow:ai']],
        ]])->assertNoContent();

        Queue::assertPushed(SendSlackAlert::class, fn (SendSlackAlert $job) => $job->channels === ['fugazi-bugs']
            && $job->issue === 15 && str_contains($job->text, 'New bug') && str_contains($job->text, 'the AI workflow'));
    }

    public function test_a_gate_refusal_alerts_the_reviews_channel_and_the_developer_on_duty_once(): void
    {
        Queue::fake();
        $comment = [
            'action' => 'edited',
            'comment' => ['user' => ['type' => 'Bot'], 'body' => "<!-- merge-gate -->\n### 🚦 Merge Gate: ✋ A developer needs to merge this\n\n- ❌ Size L is above the limit (M).\n- ✅ CI passed is green.\n\n<sub>Checked commit abc1234 at …</sub>"],
            'issue' => ['number' => 21, 'title' => 'Add a blog', 'html_url' => 'https://github.com/x/21'],
        ];

        $this->github('issue_comment', $comment)->assertNoContent();
        $this->github('issue_comment', $comment)->assertNoContent(); // the gate re-checked: no second alert

        Queue::assertPushed(SendSlackAlert::class, 1);
        Queue::assertPushed(SendSlackAlert::class, fn (SendSlackAlert $job) => $job->channels === ['fugazi-reviews']
            && $job->people === ['UDEV']
            && str_contains($job->text, '• Size L is above the limit (M).'));
    }

    public function test_the_slack_reporter_hears_when_their_issue_is_done(): void
    {
        Queue::fake();
        SlackReporter::create(['issue' => 14, 'slack_user' => 'UCEO']);

        $this->github('issues', ['action' => 'closed', 'issue' => [
            'number' => 14, 'title' => 'Dark mode', 'html_url' => 'https://github.com/x/14', 'user' => ['login' => 'fugazi-ai[bot]'],
            'state_reason' => 'completed', 'labels' => [],
        ]]);

        Queue::assertPushed(SendSlackAlert::class, fn (SendSlackAlert $job) => $job->channels === [] && $job->people === ['UCEO']);
    }

    public function test_alerts_about_one_issue_are_threaded_per_channel(): void
    {
        Http::fake(['slack.com/api/chat.postMessage' => Http::sequence()
            ->push(['ok' => true, 'ts' => '100.1'])
            ->push(['ok' => true, 'ts' => '100.2'])
            ->push(['ok' => true, 'ts' => '100.3'])]);

        (new SendSlackAlert('first', ['fugazi-ai-activity'], issue: 2))->handle(app(SlackClient::class));
        (new SendSlackAlert('second', ['fugazi-ai-activity'], ['UDEV'], issue: 2))->handle(app(SlackClient::class));

        $this->assertSame('100.1', SlackThread::firstWhere(['issue' => 2, 'channel' => 'fugazi-ai-activity'])?->ts);
        Http::assertSent(fn (Request $r) => $r['text'] === 'second' && $r['channel'] === 'fugazi-ai-activity' && $r['thread_ts'] === '100.1');
        Http::assertSent(fn (Request $r) => $r['text'] === 'second' && $r['channel'] === 'UDEV' && ! isset($r['thread_ts']));
    }

    public function test_contact_messages_go_to_the_contact_channel(): void
    {
        Queue::fake();

        $this->post('/contact', [
            'name' => 'Ada', 'email' => 'ada@example.com', 'subject' => 'Hello there',
            'message' => 'I love the AI workflow page, can we talk?',
        ])->assertRedirect();

        Queue::assertPushed(SendSlackAlert::class, fn (SendSlackAlert $job) => $job->channels === ['fugazi-contact'] && str_contains($job->text, '*Hello there* from Ada'));
    }
}
