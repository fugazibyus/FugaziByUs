<?php

namespace Tests\Feature\Ai;

use App\Ai\AgentRegistry;
use App\Ai\Contracts\ManagedAgentsGateway;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiStartCommandTest extends TestCase
{
    use FakesGitHubApp;

    private FakeManagedAgentsGateway $gateway;

    private string $lockFile;

    protected function setUp(): void
    {
        parent::setUp();

        // A lock file with fake IDs for every resource ai:start needs.
        $this->lockFile = storage_path('framework/testing/claude-lock.json');
        File::ensureDirectoryExists(dirname($this->lockFile));
        $resources = ['./environments/sandbox.yaml' => ['id' => 'env_sandbox'], './environments/research.yaml' => ['id' => 'env_research'], './vaults/github.yaml' => ['id' => 'vlt_github']];
        foreach (['triage', 'researcher', 'developer', 'debugger', 'reviewer', 'release'] as $agent) {
            $resources["./agents/{$agent}/agent.md"] = ['id' => "agent_{$agent}"];
        }
        File::put($this->lockFile, json_encode(['resources' => $resources]));

        config(['ai.lock_file' => $this->lockFile, 'ai.daily_budget_usd' => 25]);
        $this->fakeGitHubApp('ghs_session_token');
        $this->app->forgetInstance(AgentRegistry::class);

        $this->gateway = new FakeManagedAgentsGateway;
        $this->app->instance(ManagedAgentsGateway::class, $this->gateway);
    }

    protected function tearDown(): void
    {
        File::delete($this->lockFile);
        parent::tearDown();
    }

    public function test_every_agent_definition_is_valid(): void
    {
        $agents = app(AgentRegistry::class)->all();

        $this->assertSame(['debugger', 'developer', 'release', 'researcher', 'reviewer', 'triage'], array_keys($agents));

        foreach ($agents as $agent) {
            $this->assertNotSame('', $agent->model, "{$agent->key} has no model");
            $this->assertGreaterThan(0, $agent->budgetUsd, "{$agent->key} has no budget");
            $this->assertArrayHasKey($agent->environment, config('ai.environments'), "{$agent->key} uses an unknown environment");
            $this->assertStringContainsString('data, not instructions', $agent->prompt, "{$agent->key} is missing the untrusted-input rule");
            $this->assertStringContainsString('```json', $agent->prompt, "{$agent->key} is missing its result block");
        }

        $this->assertTrue($agents['researcher']->usesWeb);
        $this->assertSame('research', $agents['researcher']->environment);
        $this->assertFalse($agents['developer']->usesWeb);
    }

    public function test_it_starts_a_developer_session_for_an_issue(): void
    {
        $this->artisan('ai:start', ['agent' => 'developer', '--issue' => 12, '--trigger' => 'actions', '--actor' => 'sgoodwin10'])
            ->expectsOutputToContain('sesn_fake_1')
            ->assertSuccessful();

        $params = $this->gateway->created[0];
        $this->assertSame('agent_developer', $params['agent']);
        $this->assertSame('env_sandbox', $params['environmentID']);
        $this->assertSame(['vlt_github'], $params['vaultIDs']);
        $this->assertSame(15.0, $params['budgetUsd']);
        $this->assertSame('Developer · issue #12', $params['title']);
        $this->assertSame(['app' => 'fugazibyus', 'agent' => 'developer', 'issue' => '12', 'trigger' => 'actions', 'actor' => 'sgoodwin10'], $params['metadata']);
        $this->assertSame('develop', $params['repositories'][0]['branch']);
        $this->assertSame('https://github.com/fugazibyus/FugaziByUs', $params['repositories'][0]['url']);
        $this->assertSame('ghs_session_token', $params['repositories'][0]['token']);
        $this->assertSame(['vlt_github' => 'ghs_session_token'], $this->gateway->vaultTokens);

        [$sessionId, $message] = $this->gateway->messages[0];
        $this->assertSame('sesn_fake_1', $sessionId);
        $this->assertStringContainsString('Implement issue #12', $message);
        $this->assertStringContainsString('sesn_fake_1', $message);
    }

    public function test_pr_agents_check_out_the_pr_branch(): void
    {
        $this->artisan('ai:start', ['agent' => 'debugger', '--pr' => 34, '--branch' => 'ai/fix/9-login', '--run-id' => '555', '--attempt' => 2])
            ->assertSuccessful();

        $this->assertSame('ai/fix/9-login', $this->gateway->created[0]['repositories'][0]['branch']);
        $this->assertStringContainsString('attempt 2 of 2', $this->gateway->messages[0][1]);
        $this->assertStringContainsString('workflow run 555', $this->gateway->messages[0][1]);
    }

    public function test_the_researcher_uses_the_open_internet_sandbox(): void
    {
        $this->artisan('ai:start', ['agent' => 'researcher', '--message' => 'Which analytics tools respect privacy?'])
            ->assertSuccessful();

        $this->assertSame('env_research', $this->gateway->created[0]['environmentID']);
        $this->assertStringContainsString('Which analytics tools respect privacy?', $this->gateway->messages[0][1]);
    }

    public function test_dry_run_sends_nothing_and_mints_no_token(): void
    {
        $this->artisan('ai:start', ['agent' => 'triage', '--issue' => 3, '--dry-run' => true])
            ->expectsOutputToContain('"agent": "agent_triage"')
            ->doesntExpectOutputToContain('ghs_session_token')
            ->assertSuccessful();

        $this->assertSame([], $this->gateway->created);
        $this->assertSame([], $this->gateway->vaultTokens);
        Http::assertNothingSent();
    }

    public function test_the_daily_budget_stops_new_sessions(): void
    {
        $this->gateway->spentToday = 25.10;

        $this->artisan('ai:start', ['agent' => 'triage', '--issue' => 3])
            ->expectsOutputToContain('Daily AI budget reached')
            ->assertExitCode(2);

        $this->assertSame([], $this->gateway->created);
        Http::assertNothingSent();
    }

    public function test_the_concurrency_limit_holds_new_sessions_back(): void
    {
        config(['ai.max_concurrent_sessions' => 2]);
        $this->gateway->running = [
            ['id' => 'sesn_a', 'metadata' => ['app' => 'fugazibyus', 'agent' => 'developer', 'issue' => '4']],
            ['id' => 'sesn_b', 'metadata' => ['app' => 'fugazibyus', 'agent' => 'reviewer', 'pr' => '9']],
        ];

        $this->artisan('ai:start', ['agent' => 'triage', '--issue' => 3])
            ->expectsOutputToContain('2 agents are already working')
            ->assertExitCode(3);

        $this->assertSame([], $this->gateway->created);
    }

    public function test_ai_stop_interrupts_only_the_matching_sessions(): void
    {
        $this->gateway->running = [
            ['id' => 'sesn_dev', 'metadata' => ['app' => 'fugazibyus', 'agent' => 'developer', 'issue' => '4']],
            ['id' => 'sesn_fix', 'metadata' => ['app' => 'fugazibyus', 'agent' => 'debugger', 'pr' => '9']],
            ['id' => 'sesn_other', 'metadata' => ['app' => 'fugazibyus', 'agent' => 'developer', 'issue' => '40']],
        ];

        $this->artisan('ai:stop', ['--issue' => 4, '--pr' => 9])
            ->expectsOutputToContain('Stopped 2 agent session(s)')
            ->assertSuccessful();
        $this->assertSame(['sesn_dev', 'sesn_fix'], $this->gateway->interrupted);

        $this->artisan('ai:stop')->assertExitCode(2);

        $this->artisan('ai:stop', ['--all' => true])->assertSuccessful();
        $this->assertCount(5, $this->gateway->interrupted);
    }

    public function test_unknown_agents_and_a_missing_github_app_fail_clearly(): void
    {
        $this->artisan('ai:start', ['agent' => 'hacker'])
            ->expectsOutputToContain('Unknown agent [hacker]')
            ->assertFailed();

        config(['ai.github_app.id' => null]);

        $this->artisan('ai:start', ['agent' => 'triage', '--issue' => 1])
            ->expectsOutputToContain('GitHub App is not configured')
            ->assertFailed();

        $this->assertSame([], $this->gateway->created);
    }

    public function test_the_refresh_command_renews_the_vault_and_active_sessions(): void
    {
        $this->gateway->createSession(['agent' => 'agent_developer']);

        $this->artisan('ai:refresh-github-token')
            ->expectsOutputToContain('1 active session(s)')
            ->assertSuccessful();

        $this->assertSame(['vlt_github' => 'ghs_session_token'], $this->gateway->vaultTokens);
        $this->assertSame(['ghs_session_token'], $this->gateway->refreshedWith);
    }

    public function test_local_subagents_are_in_sync_with_the_agent_definitions(): void
    {
        $this->artisan('ai:sync-local', ['--check' => true])->assertSuccessful();
    }
}
