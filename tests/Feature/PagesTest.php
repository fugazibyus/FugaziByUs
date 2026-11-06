<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_renders_with_deferred_ai_stats(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->missing('aiStats')
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('aiStats.spendUsd')
                    ->has('aiStats.runs.byAgent', 6)
                    ->has('aiStats.pullRequests')));
    }

    public function test_the_ai_workflow_page_shows_every_agent_from_its_prompt_file(): void
    {
        $this->get(route('ai-workflow'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AiWorkflow')
                ->has('agents', 6)
                ->where('agents.0.key', 'debugger')
                ->has('agents.0', fn (Assert $agent) => $agent
                    ->where('name', 'FugaziByUs Debugger')
                    ->whereType('description', 'string')
                    ->whereType('model', 'string')
                    ->whereType('effort', 'string')
                    ->whereType('budgetUsd', 'integer|double')
                    ->whereType('usesWeb', 'boolean')
                    ->where('path', 'agents/debugger/agent.md')
                    ->where('prompt', fn (string $prompt) => $prompt !== '' && ! str_starts_with($prompt, '---'))
                    ->etc()));
    }
}
