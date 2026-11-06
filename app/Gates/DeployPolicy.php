<?php

namespace App\Gates;

/**
 * The Deploy Gate's rules (docs/PLAN.md §6a). The release PR (develop → main) is merged,
 * which deploys production, only if every rule passes. Otherwise a developer merges it.
 *
 * A developer can also ask with `/ai deploy production` on the release PR. They are then
 * the approval, so only CI and `deploy:hold` still apply.
 */
class DeployPolicy
{
    /**
     * @param  list<string>  $requiredChecks
     * @param  array{days: list<int>, from: string, until: string}  $businessHours
     */
    public function __construct(
        private readonly string $mode,
        private readonly string $timezone,
        private readonly array $businessHours,
        private readonly array $requiredChecks,
        private readonly int $soakMinutes = 30,
        private readonly int $cooldownMinutes = 60,
        private readonly string $gateBot = 'fugazi-merge-gate[bot]',
        private readonly string $baseBranch = 'develop',
        private readonly string $productionBranch = 'main',
    ) {}

    public static function fromConfig(): self
    {
        /** @var array{days: list<int>, from: string, until: string} $hours */
        $hours = config('gates.business_hours');

        return new self(
            mode: (string) config('gates.autodeploy_production'),
            timezone: (string) config('gates.timezone'),
            businessHours: $hours,
            requiredChecks: array_values((array) config('gates.required_checks')),
            soakMinutes: (int) config('gates.staging_soak_minutes'),
            cooldownMinutes: (int) config('gates.deploy_cooldown_minutes'),
            gateBot: (string) config('gates.gate_bot'),
            baseBranch: (string) config('gates.base_branch'),
            productionBranch: (string) config('gates.production_branch'),
        );
    }

    public function decide(DeployFacts $release): Decision
    {
        $decision = new Decision;

        if (! $release->open) {
            return $decision->fail('The release pull request is closed.');
        }

        if ($release->base !== $this->productionBranch || $release->head !== $this->baseBranch) {
            return $decision->fail("Only the release pull request (`{$this->baseBranch}` → `{$this->productionBranch}`) goes through the Deploy Gate.");
        }

        $decision->check(! $release->draft, 'Ready (not a draft).', 'The release pull request is a draft.');
        $decision->check(! in_array('deploy:hold', $release->labels, true), 'No `deploy:hold`.', 'The release is on hold (`deploy:hold`).');

        foreach ($this->requiredChecks as $name) {
            match ($release->checks[$name] ?? 'missing') {
                'success' => $decision->pass("{$name} is green."),
                'pending', 'missing' => $decision->wait("Waiting for {$name}."),
                default => $decision->fail("{$name} failed."),
            };
        }

        if ($release->requestedBy !== null) {
            $decision->check(
                $release->requesterIsDev,
                "Requested by @{$release->requestedBy}, a developer (`/ai deploy production`).",
                "@{$release->requestedBy} asked to deploy, but only members of @devs can.",
            );

            return $decision;
        }

        $this->checkWindow($release, $decision);
        $this->checkStaging($release, $decision);
        $this->checkReleasedPrs($release, $decision);

        $decision->check(
            ! $release->touchesMigrations || $release->devApprovals !== [],
            $release->touchesMigrations ? 'Contains migrations, approved by @'.implode(', @', $release->devApprovals).'.' : 'No database migrations.',
            'Contains database migrations, so a developer must approve the release pull request.',
        );

        $this->checkLastDeploy($release, $decision);

        return $decision;
    }

    private function checkWindow(DeployFacts $release, Decision $decision): void
    {
        if ($this->mode === 'off') {
            $decision->fail('AI production deploys are off (`AI_AUTODEPLOY_PRODUCTION=off`). A developer merges the release.');

            return;
        }

        if ($this->mode === 'on') {
            $decision->pass('AI production deploys are on at any time.');

            return;
        }

        $local = $release->now->setTimezone($this->timezone);
        $time = $local->format('H:i');
        $window = 'Mon–Thu '.$this->businessHours['from'].'–'.$this->businessHours['until'].' '.$this->timezone;
        $open = in_array($local->dayOfWeekIso, $this->businessHours['days'], true)
            && $time >= $this->businessHours['from']
            && $time < $this->businessHours['until'];

        if ($open) {
            $decision->pass("Inside the deploy window ({$window}).");
        } else {
            $decision->wait("Outside the deploy window ({$window}); it's {$local->format('D H:i')} there.");
        }
    }

    private function checkStaging(DeployFacts $release, Decision $decision): void
    {
        if ($release->stagingUrl === null) {
            $decision->fail('Staging isn\'t set up yet (`STAGING_URL`), so there\'s nothing to check the release against.');

            return;
        }

        if (! $release->stagingHealthy) {
            $decision->fail("Staging ({$release->stagingUrl}) isn't healthy.");

            return;
        }

        if ($release->stagingSha !== $release->headSha) {
            $decision->wait('Staging runs '.substr((string) $release->stagingSha, 0, 7).', not the release commit '.substr($release->headSha, 0, 7).'.');

            return;
        }

        $minutes = $release->stagingDeployedAt !== null ? (int) $release->stagingDeployedAt->diffInMinutes($release->now) : 0;
        $decision->pass('Staging is healthy on the release commit.');

        if ($minutes < $this->soakMinutes) {
            $decision->wait("Staging has run the release for {$minutes} minutes; it needs {$this->soakMinutes}.");
        } else {
            $decision->pass("Staging has run the release for {$minutes} minutes.");
        }
    }

    private function checkReleasedPrs(DeployFacts $release, Decision $decision): void
    {
        $unchecked = [];
        $held = [];

        foreach ($release->releasedPrs as $pr) {
            if (in_array('deploy:hold', $pr['labels'], true)) {
                $held[] = "#{$pr['number']}";
            }

            // Merged by a person, or by the Merge Gate after its checks. Any other bot is suspicious.
            if (str_ends_with($pr['merged_by'], '[bot]') && $pr['merged_by'] !== $this->gateBot) {
                $unchecked[] = "#{$pr['number']}";
            }
        }

        $count = count($release->releasedPrs);
        $decision->check($held === [], 'No pull request in the release is on hold.', 'On hold: '.implode(', ', $held).'.');
        $decision->check($unchecked === [], "All {$count} pull request(s) were merged by a developer or by the Merge Gate.", 'Merged by something other than a developer or the Merge Gate: '.implode(', ', $unchecked).'.');
    }

    private function checkLastDeploy(DeployFacts $release, Decision $decision): void
    {
        $last = $release->lastDeploy;

        if ($last === null) {
            $decision->pass('No earlier production deploy to wait for.');

            return;
        }

        $decision->check(
            ! in_array($last['state'], ['failure', 'error'], true),
            'The last production deploy succeeded.',
            'The last production deploy failed. A developer must deploy and check production first.',
        );

        $minutes = (int) $last['created_at']->diffInMinutes($release->now);

        if ($minutes < $this->cooldownMinutes) {
            $decision->wait("Production was deployed {$minutes} minutes ago; the gate waits {$this->cooldownMinutes} between deploys.");
        } else {
            $decision->pass("No production deploy in the last {$this->cooldownMinutes} minutes.");
        }
    }
}
