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

    public function test_the_ai_workflow_page_renders(): void
    {
        $this->get(route('ai-workflow'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('AiWorkflow'));
    }
}
