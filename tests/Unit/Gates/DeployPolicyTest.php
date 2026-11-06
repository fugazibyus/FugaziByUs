<?php

namespace Tests\Unit\Gates;

use App\Gates\Decision;
use App\Gates\DeployFacts;
use App\Gates\DeployPolicy;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DeployPolicyTest extends TestCase
{
    private const SHA = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2';

    /** Tuesday 10:00 in Sydney. */
    private function tuesdayMorning(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-11-10 10:00', 'Australia/Sydney');
    }

    /** A release that passes every rule. Each test changes one thing. */
    private function release(mixed ...$changes): DeployFacts
    {
        $now = $changes['now'] ?? $this->tuesdayMorning();

        return new DeployFacts(...array_merge([
            'number' => 12,
            'open' => true,
            'draft' => false,
            'base' => 'main',
            'head' => 'develop',
            'headSha' => self::SHA,
            'labels' => [],
            'checks' => ['CI passed' => 'success', 'Guardrails' => 'success'],
            'now' => $now,
            'stagingUrl' => 'https://staging.1-2-3-4.sslip.io',
            'stagingHealthy' => true,
            'stagingSha' => self::SHA,
            'stagingDeployedAt' => $now->subHours(2),
            'releasedPrs' => [
                ['number' => 6, 'merged_by' => 'fugazi-merge-gate[bot]', 'labels' => ['ai:automerged']],
                ['number' => 7, 'merged_by' => 'sgoodwin10', 'labels' => []],
            ],
            'lastDeploy' => ['created_at' => $now->subDay(), 'state' => 'success'],
        ], $changes));
    }

    private function decide(DeployFacts $release, string $mode = 'business-hours'): Decision
    {
        $policy = new DeployPolicy(
            mode: $mode,
            timezone: 'Australia/Sydney',
            businessHours: ['days' => [1, 2, 3, 4], 'from' => '09:00', 'until' => '16:00'],
            requiredChecks: ['CI passed', 'Guardrails'],
        );

        return $policy->decide($release);
    }

    private function assertAction(string $expected, Decision $decision, ?string $because = null): void
    {
        $this->assertSame($expected, $decision->action(), 'Failed: '.implode(' | ', $decision->failed).' Waiting: '.implode(' | ', $decision->waiting));

        if ($because !== null) {
            $this->assertStringContainsString($because, implode("\n", [...$decision->failed, ...$decision->waiting]));
        }
    }

    public function test_a_healthy_release_ships_inside_the_window(): void
    {
        $this->assertAction(Decision::MERGE, $this->decide($this->release()));
    }

    public function test_the_deploy_window_is_respected(): void
    {
        $friday = CarbonImmutable::parse('2026-11-13 10:00', 'Australia/Sydney');
        $tuesdayEvening = CarbonImmutable::parse('2026-11-10 16:30', 'Australia/Sydney');

        $this->assertAction(Decision::WAIT, $this->decide($this->release(now: $friday)), 'Outside the deploy window');
        $this->assertAction(Decision::WAIT, $this->decide($this->release(now: $tuesdayEvening)), 'Outside the deploy window');
        $this->assertAction(Decision::MERGE, $this->decide($this->release(now: $friday), mode: 'on'));
        $this->assertAction(Decision::REFUSE, $this->decide($this->release(), mode: 'off'), 'AI_AUTODEPLOY_PRODUCTION=off');
    }

    public function test_staging_must_run_the_release_commit_healthily_for_30_minutes(): void
    {
        $now = $this->tuesdayMorning();

        $this->assertAction(Decision::REFUSE, $this->decide($this->release(stagingUrl: null)), "Staging isn't set up");
        $this->assertAction(Decision::REFUSE, $this->decide($this->release(stagingHealthy: false)), "isn't healthy");
        $this->assertAction(Decision::WAIT, $this->decide($this->release(stagingSha: 'f00ba12')), 'not the release commit');
        $this->assertAction(Decision::WAIT, $this->decide($this->release(stagingDeployedAt: $now->subMinutes(10))), 'for 10 minutes; it needs 30');
    }

    public function test_every_released_pull_request_was_checked_and_none_is_on_hold(): void
    {
        $this->assertAction(Decision::REFUSE, $this->decide($this->release(releasedPrs: [['number' => 8, 'merged_by' => 'dependabot[bot]', 'labels' => []]])), '#8');
        $this->assertAction(Decision::REFUSE, $this->decide($this->release(releasedPrs: [['number' => 9, 'merged_by' => 'sgoodwin10', 'labels' => ['deploy:hold']]])), 'On hold: #9');
        $this->assertAction(Decision::REFUSE, $this->decide($this->release(labels: ['deploy:hold'])), '`deploy:hold`');
    }

    public function test_migrations_need_a_developer_approval(): void
    {
        $this->assertAction(Decision::REFUSE, $this->decide($this->release(touchesMigrations: true)), 'database migrations');
        $this->assertAction(Decision::MERGE, $this->decide($this->release(touchesMigrations: true, devApprovals: ['sgoodwin10'])));
    }

    public function test_recent_or_failed_production_deploys_hold_the_release(): void
    {
        $now = $this->tuesdayMorning();

        $this->assertAction(Decision::REFUSE, $this->decide($this->release(lastDeploy: ['created_at' => $now->subDay(), 'state' => 'failure'])), 'last production deploy failed');
        $this->assertAction(Decision::WAIT, $this->decide($this->release(lastDeploy: ['created_at' => $now->subMinutes(20), 'state' => 'success'])), '20 minutes ago');
        $this->assertAction(Decision::MERGE, $this->decide($this->release(lastDeploy: null)));
    }

    public function test_ci_must_be_green(): void
    {
        $this->assertAction(Decision::WAIT, $this->decide($this->release(checks: ['CI passed' => 'pending', 'Guardrails' => 'success'])));
        $this->assertAction(Decision::REFUSE, $this->decide($this->release(checks: ['CI passed' => 'failure', 'Guardrails' => 'success'])), 'CI passed failed');
    }

    public function test_a_developer_can_ship_outside_the_rules_but_not_past_ci_or_a_hold(): void
    {
        $friday = CarbonImmutable::parse('2026-11-13 18:00', 'Australia/Sydney');
        $request = ['requestedBy' => 'sgoodwin10', 'requesterIsDev' => true, 'now' => $friday, 'stagingUrl' => null];

        $this->assertAction(Decision::MERGE, $this->decide($this->release(...$request), mode: 'off'));
        $this->assertAction(Decision::REFUSE, $this->decide($this->release(...$request, labels: ['deploy:hold'])));
        $this->assertAction(Decision::REFUSE, $this->decide($this->release(...$request, checks: ['CI passed' => 'failure', 'Guardrails' => 'success'])));
        $this->assertAction(Decision::REFUSE, $this->decide($this->release(requestedBy: 'visitor', requesterIsDev: false)), 'only members of @devs');
    }

    public function test_only_the_release_pull_request_goes_through_the_gate(): void
    {
        $this->assertAction(Decision::REFUSE, $this->decide($this->release(base: 'develop', head: 'ai/feature/2-faq')), 'Only the release pull request');
    }
}
