<?php

namespace Tests\Feature\Ai;

use App\Ai\Contracts\ManagedAgentsGateway;
use App\Ai\Metrics\RecordAgentRun;
use App\Ai\SessionSnapshot;
use App\Jobs\SyncAgentRun;
use App\Models\AiPullRequest;
use App\Models\AiRun;
use App\Support\AiStats;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use StandardWebhooks\Webhook;
use Tests\TestCase;

class AgentMetricsTest extends TestCase
{
    use FakesGitHubApp;
    use RefreshDatabase;

    private FakeManagedAgentsGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new FakeManagedAgentsGateway;
        $this->app->instance(ManagedAgentsGateway::class, $this->gateway);
    }

    private function snapshot(string $id, string $status = 'idle', mixed ...$changes): SessionSnapshot
    {
        return new SessionSnapshot(...array_merge([
            'id' => $id,
            'status' => $status,
            'metadata' => ['app' => 'fugazibyus', 'agent' => 'developer', 'issue' => '2', 'trigger' => 'comment', 'actor' => 'sgoodwin10'],
            'title' => 'Developer · issue #2',
            'agentVersion' => 1,
            'inputTokens' => 12_000,
            'outputTokens' => 8_000,
            'cacheReadTokens' => 400_000,
            'cacheCreationTokens' => 30_000,
            'costCents' => 245,
            'activeSeconds' => 410,
            'createdAt' => CarbonImmutable::parse('2026-11-06 01:33:40'),
            'updatedAt' => CarbonImmutable::parse('2026-11-06 01:41:10'),
        ], $changes));
    }

    public function test_the_sync_saves_finished_runs_with_their_outcome(): void
    {
        $this->gateway->sessions['sesn_dev'] = $this->snapshot('sesn_dev');
        $this->gateway->lastMessages['sesn_dev'] = "Done!\n```json\n{\"outcome\": \"pr_opened\", \"pr\": 6, \"summary\": \"Added the FAQ\"}\n```";

        $this->artisan('ai:sync-runs', ['--skip-github' => true])->expectsOutputToContain('1 agent session(s) synced')->assertSuccessful();

        $run = AiRun::sole();
        $this->assertSame('developer', $run->agent);
        $this->assertSame(2, $run->issue);
        $this->assertSame(6, $run->pull_request);
        $this->assertSame('pr_opened', $run->outcome);
        $this->assertTrue($run->succeeded);
        $this->assertSame('Added the FAQ', $run->summary);
        $this->assertSame(245, $run->cost_cents);
        $this->assertSame(430_000, $run->cache_read_tokens + $run->cache_creation_tokens);
        $this->assertSame('2026-11-06 01:40:55', $run->finished_at?->toDateTimeString()); // the agent's last message, not the session's updated_at
    }

    public function test_running_sessions_are_updated_until_they_finish(): void
    {
        $this->gateway->sessions['sesn_dev'] = $this->snapshot('sesn_dev', 'running', costCents: 40);
        $this->artisan('ai:sync-runs', ['--skip-github' => true]);

        $this->assertSame('running', AiRun::sole()->status);
        $this->assertNull(AiRun::sole()->succeeded);
        $this->assertNull(AiRun::sole()->finished_at);

        $this->gateway->sessions['sesn_dev'] = $this->snapshot('sesn_dev', 'idle', costCents: 260);
        $this->gateway->lastMessages['sesn_dev'] = "```json\n{\"outcome\": \"needs_human\"}\n```";
        $this->artisan('ai:sync-runs', ['--skip-github' => true]);

        $run = AiRun::sole();
        $this->assertSame(260, $run->cost_cents);
        $this->assertFalse($run->succeeded);
        $this->assertSame('needs_human', $run->outcome);
    }

    public function test_a_session_that_ended_without_a_result_counts_as_unsuccessful(): void
    {
        $this->gateway->sessions['sesn_x'] = $this->snapshot('sesn_x', 'terminated');
        $this->artisan('ai:sync-runs', ['--skip-github' => true]);

        $this->assertSame('terminated', AiRun::sole()->outcome);
        $this->assertFalse(AiRun::sole()->succeeded);
    }

    public function test_the_sync_saves_ai_pull_requests_from_github(): void
    {
        $this->fakeGitHubApp();
        Http::fake([
            'api.github.com/repos/fugazibyus/FugaziByUs/pulls*' => Http::response([
                ['number' => 6, 'title' => 'Add a short FAQ', 'body' => 'Closes #2', 'state' => 'closed', 'created_at' => '2026-11-06T01:40:57Z',
                    'merged_at' => '2026-11-06T03:12:36Z', 'head' => ['ref' => 'ai/feature/2-contact-faq'], 'labels' => [['name' => 'ai:automerged']]],
                ['number' => 9, 'title' => 'A human PR', 'body' => '', 'state' => 'open', 'created_at' => '2026-11-06T03:00:00Z',
                    'merged_at' => null, 'head' => ['ref' => 'fix/agents-sync'], 'labels' => []],
            ]),
            'api.github.com/repos/fugazibyus/FugaziByUs/issues/2' => Http::response(['created_at' => '2026-11-06T01:20:57Z']),
        ]);

        $this->artisan('ai:sync-runs')->expectsOutputToContain('1 AI pull request(s) synced')->assertSuccessful();

        $pr = AiPullRequest::sole();
        $this->assertSame(6, $pr->number);
        $this->assertSame('merged', $pr->state);
        $this->assertTrue($pr->auto_merged);
        $this->assertSame(20, (int) $pr->issue_opened_at?->diffInMinutes($pr->opened_at));
    }

    public function test_a_signed_webhook_queues_a_sync_of_that_session(): void
    {
        Queue::fake();
        $secret = 'whsec_'.base64_encode(random_bytes(24));
        config(['services.anthropic.webhook_signing_key' => $secret]);

        $body = json_encode([
            'type' => 'event', 'id' => 'wevt_1', 'created_at' => '2026-11-06T01:41:10Z',
            'data' => ['type' => 'session.status_idled', 'id' => 'sesn_dev', 'organization_id' => 'org_1', 'workspace_id' => 'wrkspc_1'],
        ]);
        $timestamp = time();
        $signature = (new Webhook($secret))->sign('msg_1', $timestamp, $body);

        $this->call('POST', '/webhooks/anthropic', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_WEBHOOK_ID' => 'msg_1',
            'HTTP_WEBHOOK_TIMESTAMP' => (string) $timestamp,
            'HTTP_WEBHOOK_SIGNATURE' => $signature,
        ], $body)->assertNoContent();

        Queue::assertPushed(SyncAgentRun::class, fn (SyncAgentRun $job) => $job->sessionId === 'sesn_dev');
    }

    public function test_unsigned_or_unconfigured_webhooks_are_refused(): void
    {
        Queue::fake();

        $this->postJson('/webhooks/anthropic', ['data' => ['type' => 'session.created', 'id' => 'sesn_x']])->assertStatus(503);

        config(['services.anthropic.webhook_signing_key' => 'whsec_'.base64_encode(random_bytes(24))]);
        $this->postJson('/webhooks/anthropic', ['data' => ['type' => 'session.created', 'id' => 'sesn_x']])->assertStatus(400);

        Queue::assertNothingPushed();
    }

    public function test_the_job_ignores_sessions_from_other_projects(): void
    {
        $this->gateway->sessions['sesn_other'] = $this->snapshot('sesn_other', metadata: ['app' => 'another-project']);

        (new SyncAgentRun('sesn_other'))->handle($this->gateway, app(RecordAgentRun::class));

        $this->assertSame(0, AiRun::count());
    }

    public function test_the_widget_summary_adds_up_runs_and_pull_requests(): void
    {
        AiRun::factory()->agent('developer')->create(['cost_cents' => 300, 'input_tokens' => 10, 'output_tokens' => 20, 'cache_read_tokens' => 30, 'cache_creation_tokens' => 40]);
        AiRun::factory()->agent('triage')->create(['cost_cents' => 12]);
        AiRun::factory()->agent('triage')->failed()->create(['cost_cents' => 0]);
        AiRun::factory()->agent('reviewer')->running()->create(['cost_cents' => 50]);
        AiPullRequest::factory()->create(['auto_merged' => true, 'issue_opened_at' => now()->subMinutes(30), 'opened_at' => now()->subMinutes(10)]);
        AiPullRequest::factory()->open()->create(['issue_opened_at' => now()->subMinutes(50), 'opened_at' => now()->subMinutes(10)]);

        $stats = app(AiStats::class)->summary();

        $this->assertSame(3.62, $stats['spendUsd']);
        $this->assertSame(4, $stats['runs']['total']);
        $this->assertSame(2, $stats['runs']['succeeded']);
        $this->assertSame(1, $stats['runs']['byAgent']['developer']);
        $this->assertSame(0, $stats['runs']['byAgent']['researcher']);
        $this->assertSame(['opened' => 2, 'merged' => 1, 'autoMerged' => 1], $stats['pullRequests']);
        $this->assertSame(30, $stats['avgIssueToPrMinutes']);
        $this->assertCount(3, $stats['recentRuns']); // the running one hasn't finished
    }

    public function test_servers_without_an_anthropic_key_can_turn_the_scheduled_sync_off(): void
    {
        $sync = collect(app(Schedule::class)->events())
            ->first(fn (Event $event) => str_contains((string) $event->command, 'ai:sync-runs'));

        $this->assertNotNull($sync);

        config(['ai.sync_runs' => true]);
        $this->assertTrue($sync->filtersPass($this->app));

        config(['ai.sync_runs' => false]);
        $this->assertFalse($sync->filtersPass($this->app));
    }
}
