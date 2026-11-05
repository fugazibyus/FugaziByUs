<?php

namespace Tests\Feature\Ai;

use App\Ai\AgentRegistry;
use App\Ai\Contracts\ManagedAgentsGateway;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AiStartCommandTest extends TestCase
{
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

        config(['ai.lock_file' => $this->lockFile, 'ai.github_token' => 'test-token', 'ai.daily_budget_usd' => 25]);
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

    public function test_dry_run_sends_nothing_and_hides_the_token(): void
    {
        $this->artisan('ai:start', ['agent' => 'triage', '--issue' => 3, '--dry-run' => true])
            ->expectsOutputToContain('"agent": "agent_triage"')
            ->doesntExpectOutputToContain('test-token')
            ->assertSuccessful();

        $this->assertSame([], $this->gateway->created);
    }

    public function test_the_daily_budget_stops_new_sessions(): void
    {
        $this->gateway->spentToday = 25.10;

        $this->artisan('ai:start', ['agent' => 'triage', '--issue' => 3])
            ->expectsOutputToContain('Daily AI budget reached')
            ->assertExitCode(2);

        $this->assertSame([], $this->gateway->created);
    }

    public function test_unknown_agents_and_missing_tokens_fail_clearly(): void
    {
        $this->artisan('ai:start', ['agent' => 'hacker'])
            ->expectsOutputToContain('Unknown agent [hacker]')
            ->assertFailed();

        config(['ai.github_token' => '']);

        $this->artisan('ai:start', ['agent' => 'triage', '--issue' => 1])
            ->expectsOutputToContain('AI_GITHUB_TOKEN is not set')
            ->assertFailed();
    }

    public function test_local_subagents_are_in_sync_with_the_agent_definitions(): void
    {
        $this->artisan('ai:sync-local', ['--check' => true])->assertSuccessful();
    }
}
