<?php

namespace Tests\Unit\Gates;

use App\Gates\Decision;
use App\Gates\MergeFacts;
use App\Gates\MergePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MergePolicyTest extends TestCase
{
    private const SHA = '7dfccd0111da7fcd301d1c5915827a60d43dae54';

    /** An AI pull request that passes every rule. Each test changes one thing. */
    private function pr(mixed ...$changes): MergeFacts
    {
        return new MergeFacts(...array_merge([
            'number' => 6,
            'title' => 'Add a short FAQ under the contact form',
            'open' => true,
            'draft' => false,
            'fromFork' => false,
            'base' => 'develop',
            'head' => 'ai/feature/2-contact-faq',
            'headSha' => self::SHA,
            'author' => 'fugazi-ai[bot]',
            'labels' => ['type:feature', 'flow:ai', 'size:s'],
            'checks' => ['CI passed' => 'success', 'Guardrails' => 'success'],
            'files' => ['resources/js/pages/Contact.vue', 'tests/e2e/smoke.spec.ts'],
            'behindBy' => 0,
            'verdict' => ['verdict' => 'approve', 'risk' => 'low', 'sha' => self::SHA, 'blocking' => 0],
            'issue' => 2,
            'reportedByDev' => true,
        ], $changes));
    }

    private function decide(MergeFacts $pr, string $mode = 'devs-only', string $maxSize = 'm'): Decision
    {
        $policy = new MergePolicy(
            mode: $mode,
            maxSize: $maxSize,
            requiredChecks: ['CI passed', 'Guardrails'],
            sensitivePaths: ['.github/**', 'agents/**', 'composer.json', '.env*', 'app/Providers/**'],
            neverAutomergePaths: ['database/migrations/**'],
        );

        return $policy->decide($pr);
    }

    private function assertAction(string $expected, Decision $decision, ?string $because = null): void
    {
        $this->assertSame($expected, $decision->action(), 'Failed: '.implode(' | ', $decision->failed).' Waiting: '.implode(' | ', $decision->waiting));

        if ($because !== null) {
            $this->assertStringContainsString($because, implode("\n", [...$decision->failed, ...$decision->waiting]));
        }
    }

    public function test_an_approved_small_ai_pull_request_from_a_developer_issue_merges(): void
    {
        $decision = $this->decide($this->pr());

        $this->assertAction(Decision::MERGE, $decision);
        $this->assertContains('The reviewer approved.', $decision->passed);
    }

    public function test_nothing_merges_automatically_when_the_mode_is_off(): void
    {
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(), mode: 'off'), 'AI_AUTOMERGE_MODE=off');
    }

    public function test_human_branches_never_merge_automatically(): void
    {
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(head: 'feature/2-faq', author: 'sgoodwin10')), 'Only `ai/*` branches');
    }

    public function test_issues_from_non_developers_need_a_developer_opt_in(): void
    {
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(reportedByDev: false)), 'outside @devs and @product');
        $this->assertAction(Decision::MERGE, $this->decide($this->pr(reportedByDev: false, optedInBy: 'sgoodwin10')));
        $this->assertAction(Decision::MERGE, $this->decide($this->pr(reportedByDev: false), mode: 'all'));
    }

    /** @return array<string, array{array<string, mixed>, string, string}> */
    public static function verdicts(): array
    {
        return [
            'no review yet' => [['verdict' => null], Decision::WAIT, "reviewer agent's verdict"],
            'review of an older commit' => [['verdict' => ['verdict' => 'approve', 'risk' => 'low', 'sha' => 'abc1234', 'blocking' => 0]], Decision::WAIT, 'older commit'],
            'changes requested' => [['verdict' => ['verdict' => 'request-changes', 'risk' => 'low', 'sha' => self::SHA, 'blocking' => 1]], Decision::REFUSE, '`request-changes`'],
            'high risk' => [['verdict' => ['verdict' => 'approve', 'risk' => 'high', 'sha' => self::SHA, 'blocking' => 0]], Decision::REFUSE, 'Risk is high'],
            'blocking findings' => [['verdict' => ['verdict' => 'approve', 'risk' => 'low', 'sha' => self::SHA, 'blocking' => 2]], Decision::REFUSE, '2 blocking'],
        ];
    }

    /** @param array<string, mixed> $changes */
    #[DataProvider('verdicts')]
    public function test_the_reviewer_must_approve_this_exact_commit(array $changes, string $action, string $because): void
    {
        $this->assertAction($action, $this->decide($this->pr(...$changes)), $because);
    }

    public function test_size_must_be_within_the_limit(): void
    {
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(labels: ['flow:ai', 'size:l'])), 'Size L is above the limit (M)');
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(labels: ['flow:ai'])), 'No size label');
        $this->assertAction(Decision::MERGE, $this->decide($this->pr(labels: ['flow:ai', 'size:l']), maxSize: 'l'));
    }

    public function test_sensitive_paths_and_migrations_never_merge_automatically(): void
    {
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(files: ['app/Providers/AppServiceProvider.php'])), 'sensitive paths');
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(files: ['.env.example'])), 'sensitive paths');
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(files: ['database/migrations/2026_11_06_000000_add_faqs.php'])), 'migrations never merge');
    }

    public function test_ci_must_be_green(): void
    {
        $this->assertAction(Decision::WAIT, $this->decide($this->pr(checks: ['CI passed' => 'pending', 'Guardrails' => 'success'])), 'still running');
        $this->assertAction(Decision::WAIT, $this->decide($this->pr(checks: ['CI passed' => 'success'])), "Guardrails hasn't reported");
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(checks: ['CI passed' => 'failure', 'Guardrails' => 'success'])), 'CI passed failed');
    }

    public function test_hold_labels_drafts_forks_and_other_bases_stop_the_gate(): void
    {
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(labels: ['size:s', 'ai:no-automerge'])), '`ai:no-automerge`');
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(draft: true)), 'draft');
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(fromFork: true)), 'forks');
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(base: 'main')), 'Deploy Gate');
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(open: false)), 'closed');
    }

    public function test_a_branch_behind_develop_is_updated_first(): void
    {
        $this->assertAction(Decision::UPDATE_BRANCH, $this->decide($this->pr(behindBy: 3)));
        $this->assertAction(Decision::UPDATE_BRANCH, $this->decide($this->pr(behindBy: 3, verdict: null)));
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(behindBy: 3, labels: ['size:l'])));
    }

    public function test_a_developer_can_ask_the_gate_to_merge_any_branch(): void
    {
        // A human branch with no review, no size and a dependency change: the developer is the approval.
        $human = ['head' => 'feature/9-tweak', 'author' => 'sgoodwin10', 'labels' => [], 'verdict' => null, 'files' => ['composer.json']];

        $this->assertAction(Decision::MERGE, $this->decide($this->pr(...$human, requestedBy: 'sgoodwin10', requesterIsDev: true), mode: 'off'));
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(head: 'feature/9-tweak', requestedBy: 'visitor', requesterIsDev: false)), 'only members of @devs or @product');
    }

    public function test_issues_reported_by_product_members_merge_like_developers_issues(): void
    {
        $product = ['reportedByDev' => false, 'reportedByProduct' => true];

        $this->assertAction(Decision::MERGE, $this->decide($this->pr(...$product)));
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(...$product, labels: ['size:l'])), 'above the limit');
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(...$product, files: ['composer.json'])), 'sensitive paths');
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(...$product, verdict: ['verdict' => 'request-changes', 'risk' => 'low', 'sha' => self::SHA, 'blocking' => 1])), 'blocking problem');
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(...$product), mode: 'off'), 'AI merging is off');
    }

    public function test_a_product_member_can_ship_approved_ai_work_to_staging(): void
    {
        $product = ['reportedByDev' => false, 'requestedBy' => 'a-pm', 'requesterIsProduct' => true];

        $this->assertAction(Decision::MERGE, $this->decide($this->pr(...$product)));
        $this->assertAction(Decision::MERGE, $this->decide($this->pr(reportedByDev: false, optedInBy: 'a-pm')));
    }

    public function test_a_product_request_still_needs_every_ai_rule(): void
    {
        $product = ['reportedByDev' => false, 'requestedBy' => 'a-pm', 'requesterIsProduct' => true];

        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(...$product, head: 'feature/9-tweak')), 'Only `ai/*` branches');
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(...$product, files: ['composer.json'])), 'sensitive paths');
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(...$product, labels: ['size:l'])), 'above the limit');
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(...$product), mode: 'off'), 'AI merging is off');
        $this->assertAction(Decision::WAIT, $this->decide($this->pr(...$product, verdict: null)), "reviewer agent's verdict");
    }

    public function test_workflow_changes_and_failing_ci_stop_even_a_developer_request(): void
    {
        $request = ['requestedBy' => 'sgoodwin10', 'requesterIsDev' => true];

        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(...$request, files: ['.github/workflows/ci.yml'])), 'GitHub workflows');
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(...$request, checks: ['CI passed' => 'failure', 'Guardrails' => 'success'])), 'CI passed failed');
    }

    public function test_the_lock_file_sync_pull_request_merges_on_its_own(): void
    {
        $lock = ['head' => 'chore/sync-claude-lock', 'labels' => ['type:chore'], 'verdict' => null, 'issue' => null, 'reportedByDev' => false];

        $this->assertAction(Decision::MERGE, $this->decide($this->pr(...$lock, files: ['claude-lock.json'])));
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(...$lock, files: ['claude-lock.json']), mode: 'off'));
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(...$lock, files: ['claude-lock.json', 'agents/triage/agent.md'])));
        $this->assertAction(Decision::REFUSE, $this->decide($this->pr(...$lock, files: ['claude-lock.json'], author: 'someone')));
    }
}
