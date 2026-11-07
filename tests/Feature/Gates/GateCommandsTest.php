<?php

namespace Tests\Feature\Gates;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GateCommandsTest extends TestCase
{
    private const SHA = '7dfccd0111da7fcd301d1c5915827a60d43dae54';

    private const REPO = 'https://api.github.com/repos/fugazibyus/FugaziByUs';

    /** @var array<string, mixed> "METHOD /path" => response body */
    private array $github = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['gates.token' => 'ghs_gate', 'gates.repository' => 'fugazibyus/FugaziByUs', 'gates.staging_url' => null]);

        $verdict = '<!-- ai-verdict {"verdict": "approve", "risk": "low", "sha": "'.self::SHA.'", "blocking": 0, "should_fix": 0} -->'
            ."\n## AI review: approve\n<!-- ai-metadata\nsession: sesn_review1\n-->";

        $this->github = [
            'GET /pulls/6' => [
                'number' => 6, 'title' => 'Add a short FAQ under the contact form', 'state' => 'open', 'draft' => false,
                'body' => "Closes #2\n<!-- ai-metadata\nsession: sesn_dev1\n-->",
                'user' => ['login' => 'fugazi-ai[bot]'], 'labels' => [['name' => 'type:feature'], ['name' => 'flow:ai']],
                'base' => ['ref' => 'develop'],
                'head' => ['ref' => 'ai/feature/2-contact-faq', 'sha' => self::SHA, 'repo' => ['full_name' => 'fugazibyus/FugaziByUs']],
            ],
            'GET /pulls/6/files' => [['filename' => 'resources/js/pages/Contact.vue'], ['filename' => 'tests/e2e/smoke.spec.ts']],
            'GET /pulls/6/reviews' => [['user' => ['login' => 'fugazi-ai[bot]'], 'state' => 'COMMENTED', 'body' => $verdict]],
            'GET /issues/2' => ['user' => ['login' => 'sgoodwin10'], 'labels' => [['name' => 'size:s'], ['name' => 'priority:low']]],
            'GET /commits/'.self::SHA.'/check-runs' => ['check_runs' => [
                ['id' => 1, 'name' => 'CI passed', 'status' => 'completed', 'conclusion' => 'success'],
                ['id' => 2, 'name' => 'Guardrails', 'status' => 'completed', 'conclusion' => 'success'],
            ]],
            'GET /compare/develop...'.self::SHA => ['behind_by' => 0],
            'GET /issues/6/comments' => [],
            'PUT /pulls/6/merge' => ['merged' => true],
            'POST /issues/6/labels' => [],
            'POST /issues/2/labels' => [],
            'POST /issues/6/comments' => ['id' => 99],
            'DELETE /issues/2/labels/ai:pr-open' => [],
        ];

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            // @devs is sgoodwin10; @product is a-pm.
            foreach (['devs' => 'sgoodwin10', 'product' => 'a-pm'] as $team => $member) {
                if (str_contains($request->url(), "/teams/{$team}/memberships/")) {
                    $active = str_ends_with($request->url(), "/{$member}");

                    return Http::response(['state' => $active ? 'active' : null], $active ? 200 : 404);
                }
            }

            $path = rawurldecode((string) parse_url(str_replace(self::REPO, '', $request->url()), PHP_URL_PATH));
            $key = $request->method().' '.$path;

            return array_key_exists($key, $this->github)
                ? Http::response($this->github[$key])
                : Http::response(['message' => "Not faked: {$key}"], 500);
        });
    }

    public function test_the_merge_gate_merges_an_eligible_ai_pull_request(): void
    {
        $this->artisan('gate:merge', ['pr' => 6, '--trigger' => 'review'])
            ->expectsOutputToContain('Merge Gate decision: merge')
            ->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r->method() === 'PUT'
            && str_ends_with($r->url(), '/pulls/6/merge')
            && $r['sha'] === self::SHA
            && $r['merge_method'] === 'squash'
            && $r['commit_title'] === 'Add a short FAQ under the contact form (#6)'
            && str_starts_with($r['commit_message'], "Closes #2\n\nMerged by the Merge Gate:")
            && str_contains($r['commit_message'], 'Agent sessions: sesn_dev1, sesn_review1'));

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/issues/6/labels') && $r['labels'] === ['ai:automerged']);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/issues/2/labels') && $r['labels'] === ['ai:automerged']);
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/issues/2/labels/ai%3Apr-open'));
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/issues/6/comments')
            && str_contains($r['body'], 'Merge Gate: ✅ Merging')
            && str_contains($r['body'], 'Issue #2 was reported by a developer.'));
    }

    public function test_slack_hears_about_a_decision_once(): void
    {
        $output = tempnam(sys_get_temp_dir(), 'gh-output');
        putenv("GITHUB_OUTPUT={$output}");

        try {
            $this->artisan('gate:merge', ['pr' => 6])->assertSuccessful();
            $this->assertStringContainsString('🟣 The Merge Gate merged <https://github.com/fugazibyus/FugaziByUs/pull/6|PR #6 Add a short FAQ under the contact form> into develop', (string) file_get_contents($output));

            // The gate checks again with the same result: the comment only gets a new footer, Slack hears nothing.
            file_put_contents($output, '');
            $sent = collect(Http::recorded())->first(fn ($pair) => str_ends_with($pair[0]->url(), '/issues/6/comments') && $pair[0]->method() === 'POST');
            $this->github['GET /issues/6/comments'] = [['id' => 99, 'user' => ['type' => 'Bot'], 'body' => str_replace('Checked commit', 'Checked earlier commit', $sent[0]['body'])]];
            $this->github['PATCH /issues/comments/99'] = [];

            $this->artisan('gate:merge', ['pr' => 6])->assertSuccessful();
            $this->assertMatchesRegularExpression('/slack<<(EOF_\w+)\n\n\1/', (string) file_get_contents($output));
        } finally {
            putenv('GITHUB_OUTPUT');
            @unlink($output);
        }
    }

    public function test_the_merge_gate_explains_a_refusal_and_merges_nothing(): void
    {
        $this->github['GET /pulls/6/reviews'][0]['body'] = str_replace('"approve"', '"request-changes"', $this->github['GET /pulls/6/reviews'][0]['body']);

        $this->artisan('gate:merge', ['pr' => 6])
            ->expectsOutputToContain('Merge Gate decision: refuse')
            ->assertSuccessful();

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/merge'));
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/issues/6/comments')
            && str_contains($r['body'], 'A developer needs to merge this')
            && str_contains($r['body'], "The reviewer's verdict is `request-changes`."));
    }

    public function test_ai_go_on_a_designers_issue_starts_the_work_but_does_not_let_the_gate_merge(): void
    {
        $this->givenADesignersIssueLabelledByADeveloper('ai:go');

        $this->artisan('gate:merge', ['pr' => 6, '--dry-run' => true])
            ->expectsOutputToContain('Merge Gate decision: refuse')
            ->expectsOutputToContain('The issue was reported by a non-developer. A developer or product member must add `ai:automerge` or comment `/ai merge` first.')
            ->assertSuccessful();
    }

    public function test_ai_automerge_from_a_developer_lets_the_gate_merge_a_designers_issue(): void
    {
        $this->givenADesignersIssueLabelledByADeveloper('ai:automerge');

        $this->artisan('gate:merge', ['pr' => 6, '--dry-run' => true])
            ->expectsOutputToContain('Merge Gate decision: merge')
            ->expectsOutputToContain('@sgoodwin10 opted in with `ai:automerge`.')
            ->assertSuccessful();
    }

    public function test_ai_automerge_from_a_product_member_lets_the_gate_merge_a_designers_issue(): void
    {
        $this->givenADesignersIssueLabelledByADeveloper('ai:automerge', by: 'a-pm');

        $this->artisan('gate:merge', ['pr' => 6, '--dry-run' => true])
            ->expectsOutputToContain('Merge Gate decision: merge')
            ->expectsOutputToContain('@a-pm opted in with `ai:automerge`.')
            ->assertSuccessful();
    }

    public function test_a_product_member_ships_an_approved_ai_pull_request_to_staging_with_an_ai_merge_comment(): void
    {
        $this->givenADesignersIssueLabelledByADeveloper('ai:go');
        $this->givenAnAiMergeComment(by: 'a-pm');

        $this->artisan('gate:merge', ['pr' => 6, '--comment' => 555])
            ->expectsOutputToContain('Requested by @a-pm from @product (`/ai merge`), so the usual AI rules apply.')
            ->expectsOutputToContain('The reviewer approved.')
            ->assertSuccessful();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/pulls/6/merge'));
    }

    public function test_a_product_member_cannot_merge_a_human_branch(): void
    {
        $this->github['GET /pulls/6']['head']['ref'] = 'feature/6-copy-tweak';
        $this->givenAnAiMergeComment(by: 'a-pm');

        $this->artisan('gate:merge', ['pr' => 6, '--comment' => 555])
            ->expectsOutputToContain('Only `ai/*` branches merge automatically.')
            ->assertSuccessful();

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/pulls/6/merge'));
    }

    public function test_someone_outside_devs_and_product_cannot_ask_the_gate(): void
    {
        $this->givenAnAiMergeComment(by: 'a-designer');

        $this->artisan('gate:merge', ['pr' => 6, '--comment' => 555])
            ->expectsOutputToContain('@a-designer asked to merge, but only members of @devs or @product can.')
            ->assertSuccessful();

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/pulls/6/merge'));
    }

    private function givenAnAiMergeComment(string $by): void
    {
        $this->github['GET /issues/comments/555'] = [
            'issue_url' => self::REPO.'/issues/6', 'body' => '/ai merge', 'user' => ['login' => $by],
            'created_at' => now()->subMinute()->toIso8601String(),
        ];
    }

    /** Issue #2 was opened by a designer (not in @devs or @product); $by added the label. */
    private function givenADesignersIssueLabelledByADeveloper(string $label, string $by = 'sgoodwin10'): void
    {
        $this->github['GET /issues/2']['user']['login'] = 'a-designer';
        $this->github['GET /issues/2']['labels'][] = ['name' => $label];
        $this->github['GET /issues/2/events'] = [['event' => 'labeled', 'label' => ['name' => $label], 'actor' => ['login' => $by]]];
        $this->github['GET /issues/6/events'] = [];
    }

    public function test_a_verdict_written_by_anyone_but_the_ai_app_is_ignored(): void
    {
        $this->github['GET /pulls/6/reviews'][0]['user']['login'] = 'helpful-stranger';

        $this->artisan('gate:merge', ['pr' => 6, '--dry-run' => true])
            ->expectsOutputToContain("Waiting for the reviewer agent's verdict.")
            ->assertSuccessful();

        Http::assertNotSent(fn (Request $r) => $r->method() !== 'GET');
    }

    public function test_a_developer_merges_a_human_branch_with_an_ai_merge_comment(): void
    {
        $this->github['GET /pulls/6']['head']['ref'] = 'feature/6-copy-tweak';
        $this->github['GET /pulls/6']['user']['login'] = 'someone';
        $this->github['GET /issues/comments/555'] = [
            'issue_url' => self::REPO.'/issues/6', 'body' => '/ai merge', 'user' => ['login' => 'sgoodwin10'],
            'created_at' => now()->subMinute()->toIso8601String(),
        ];

        $this->artisan('gate:merge', ['pr' => 6, '--comment' => 555])
            ->expectsOutputToContain('Requested by @sgoodwin10, a developer (`/ai merge`).')
            ->assertSuccessful();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/pulls/6/merge'));
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/labels'));
    }

    public function test_a_manual_run_from_the_actions_tab_says_so(): void
    {
        $this->artisan('gate:merge', ['pr' => 6, '--requested-by' => 'sgoodwin10', '--dry-run' => true])
            ->expectsOutputToContain('Requested by @sgoodwin10, a developer (run by hand from the Actions tab).')
            ->assertSuccessful();
    }

    public function test_a_request_must_be_proven_by_a_recent_comment_on_the_same_pull_request(): void
    {
        $this->github['GET /issues/comments/556'] = [
            'issue_url' => self::REPO.'/issues/99', 'body' => '/ai merge', 'user' => ['login' => 'sgoodwin10'],
            'created_at' => now()->toIso8601String(),
        ];
        $this->github['GET /issues/comments/557'] = [
            'issue_url' => self::REPO.'/issues/6', 'body' => '/ai merge', 'user' => ['login' => 'sgoodwin10'],
            'created_at' => now()->subHours(2)->toIso8601String(),
        ];

        $this->artisan('gate:merge', ['pr' => 6, '--comment' => 556])->expectsOutputToContain("isn't a recent `/ai merge` on #6")->assertFailed();
        $this->artisan('gate:merge', ['pr' => 6, '--comment' => 557])->assertFailed();
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/merge'));
    }

    public function test_the_gates_need_a_token(): void
    {
        config(['gates.token' => null]);

        $this->artisan('gate:merge', ['pr' => 6])->expectsOutputToContain('GATE_GITHUB_TOKEN')->assertFailed();
        $this->artisan('gate:deploy')->expectsOutputToContain('GATE_GITHUB_TOKEN')->assertFailed();
    }

    public function test_the_deploy_gate_does_nothing_without_a_release_pull_request(): void
    {
        $this->github['GET /pulls'] = [];

        $this->artisan('gate:deploy')->expectsOutputToContain('No release pull request is open.')->assertSuccessful();
    }

    public function test_the_version_endpoint_reports_the_running_commit(): void
    {
        config(['app.version' => self::SHA, 'app.deployed_at' => '2026-11-06T09:00:00+11:00']);

        $this->getJson('/version')
            ->assertOk()
            ->assertExactJson(['sha' => self::SHA, 'deployed_at' => '2026-11-06T09:00:00+11:00']);
    }
}
