<?php

namespace App\Gates;

/**
 * The Merge Gate's rules (docs/PLAN.md §6a). A pull request into develop merges without a
 * human only if every rule passes. This class only decides; it never calls GitHub.
 *
 * Three ways through the gate:
 *   1. Automatic: an ai/* PR that the reviewer approved and that meets every policy rule.
 *   2. A developer asks with `/ai merge`: the developer is the approval, so only the
 *      safety rules (CI, holds, workflows, up to date) still apply.
 *   3. The one-file claude-lock.json PR that the agent sync workflow opens.
 */
class MergePolicy
{
    /** Labels that stop any merge by the gate. */
    public const HOLDS = ['ai:no-automerge', 'blocked', 'deploy:hold', 'ai:stop', 'ai:needs-human'];

    private const SIZES = ['s' => 1, 'm' => 2, 'l' => 3];

    /**
     * @param  list<string>  $requiredChecks
     * @param  list<string>  $sensitivePaths  globs that never merge automatically
     * @param  list<string>  $neverAutomergePaths  e.g. migrations
     */
    public function __construct(
        private readonly string $mode,
        private readonly string $maxSize,
        private readonly array $requiredChecks,
        private readonly array $sensitivePaths,
        private readonly array $neverAutomergePaths,
        private readonly string $baseBranch = 'develop',
        private readonly string $aiBot = 'fugazi-ai[bot]',
        private readonly string $lockSyncBranch = 'chore/sync-claude-lock',
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            mode: (string) config('gates.automerge_mode'),
            maxSize: (string) config('gates.max_automerge_size'),
            requiredChecks: array_values((array) config('gates.required_checks')),
            sensitivePaths: PathPatterns::fromFile((string) config('gates.sensitive_paths_file')),
            neverAutomergePaths: array_values((array) config('gates.never_automerge_paths')),
            baseBranch: (string) config('gates.base_branch'),
            aiBot: (string) config('gates.ai_bot'),
            lockSyncBranch: (string) config('gates.lock_sync_branch'),
        );
    }

    public function decide(MergeFacts $pr): Decision
    {
        $decision = new Decision;

        // Rules that apply to every merge by the gate.
        if (! $pr->open) {
            return $decision->fail('The pull request is closed.');
        }

        if ($pr->base !== $this->baseBranch) {
            return $decision->fail("The Merge Gate only merges into `{$this->baseBranch}`. Release PRs go through the Deploy Gate.");
        }

        if ($pr->fromFork) {
            return $decision->fail('Pull requests from forks are always merged by a developer.');
        }

        $decision->check(! $pr->draft, 'Ready for review (not a draft).', 'The pull request is a draft.');

        $holds = array_values(array_filter(self::HOLDS, $pr->hasLabel(...)));
        $decision->check($holds === [], 'No hold labels.', 'Held by '.self::list($holds).'.');

        $this->checkCi($pr, $decision);

        $workflows = PathPatterns::matching($pr->files, ['.github/workflows/**']);
        $decision->check($workflows === [], 'No GitHub workflow changes.', 'Changes GitHub workflows ('.self::list($workflows).'), which only a developer may merge.');

        // How this PR qualifies.
        match (true) {
            $pr->requestedBy !== null => $this->checkRequest($pr, $decision),
            $this->isLockSync($pr) => $decision->pass('Lock file sync from the agent sync workflow (only `claude-lock.json` changed).')
                ->check($this->mode !== 'off', 'AI merging is on.', 'AI merging is off (`AI_AUTOMERGE_MODE=off`).'),
            default => $this->checkAutomatic($pr, $decision),
        };

        if ($pr->behindBy > 0) {
            $decision->behind("The branch is {$pr->behindBy} commit(s) behind `{$this->baseBranch}`; updating it and waiting for CI again.");
        } else {
            $decision->pass("Up to date with `{$this->baseBranch}`.");
        }

        return $decision;
    }

    private function checkCi(MergeFacts $pr, Decision $decision): void
    {
        foreach ($this->requiredChecks as $name) {
            match ($pr->checks[$name] ?? 'missing') {
                'success' => $decision->pass("{$name} is green."),
                'pending' => $decision->wait("{$name} is still running."),
                'missing' => $decision->wait("{$name} hasn't reported on this commit yet."),
                default => $decision->fail("{$name} failed."),
            };
        }
    }

    private function checkRequest(MergeFacts $pr, Decision $decision): void
    {
        $decision->check(
            $pr->requesterIsDev,
            "Requested by @{$pr->requestedBy}, a developer (`/ai merge`).",
            "@{$pr->requestedBy} asked to merge, but only members of @devs can.",
        );
    }

    private function checkAutomatic(MergeFacts $pr, Decision $decision): void
    {
        if ($this->mode === 'off') {
            $decision->fail('AI merging is off (`AI_AUTOMERGE_MODE=off`). A developer merges, or comments `/ai merge`.');

            return;
        }

        if (! str_starts_with($pr->head, 'ai/')) {
            $decision->fail('Only `ai/*` branches merge automatically. A developer can comment `/ai merge`.');

            return;
        }

        $decision->pass('AI branch (`'.$pr->head.'`).');

        // Who stands behind the work?
        if ($this->mode === 'all') {
            $decision->pass('AI merging is open to every issue (`AI_AUTOMERGE_MODE=all`).');
        } elseif ($pr->reportedByDev) {
            $decision->pass("Issue #{$pr->issue} was reported by a developer.");
        } elseif ($pr->optedInBy !== null) {
            $decision->pass("@{$pr->optedInBy} (a developer) opted in with `ai:go` / `ai:automerge`.");
        } else {
            $decision->fail('The issue was reported by a non-developer. A developer must add `ai:go` or `ai:automerge` first.');
        }

        // The AI reviewer's verdict on this exact commit.
        $verdict = $pr->verdict;

        if ($verdict === null) {
            $decision->wait('Waiting for the reviewer agent\'s verdict.');
        } elseif ($verdict['sha'] !== $pr->headSha) {
            $decision->wait('The reviewer\'s verdict is for an older commit; waiting for a review of '.substr($pr->headSha, 0, 7).'.');
        } else {
            $decision->check($verdict['verdict'] === 'approve', 'The reviewer approved.', "The reviewer's verdict is `{$verdict['verdict']}`.");
            $decision->check(in_array($verdict['risk'], ['low', 'medium'], true), "Risk is {$verdict['risk']}.", "Risk is {$verdict['risk']}; only low and medium risk merge automatically.");
            $decision->check($verdict['blocking'] === 0, 'No blocking findings.', "The reviewer found {$verdict['blocking']} blocking problem(s).");
        }

        // Size.
        $size = $pr->size();
        $decision->check(
            $size !== null && self::SIZES[$size] <= (self::SIZES[$this->maxSize] ?? 0),
            'Size '.strtoupper((string) $size).' is within the limit ('.strtoupper($this->maxSize).').',
            $size === null ? 'No size label.' : 'Size '.strtoupper($size).' is above the limit ('.strtoupper($this->maxSize).').',
        );

        // Paths.
        $sensitive = PathPatterns::matching($pr->files, $this->sensitivePaths);
        $decision->check($sensitive === [], 'No sensitive paths changed.', 'Changes sensitive paths ('.self::list($sensitive).').');

        $never = PathPatterns::matching($pr->files, $this->neverAutomergePaths);
        $decision->check($never === [], 'No database migrations.', 'Database migrations never merge automatically ('.self::list($never).').');
    }

    private function isLockSync(MergeFacts $pr): bool
    {
        return $pr->head === $this->lockSyncBranch
            && $pr->author === $this->aiBot
            && $pr->files === ['claude-lock.json'];
    }

    /** @param  list<string>  $items */
    private static function list(array $items): string
    {
        $shown = array_map(fn (string $item) => "`{$item}`", array_slice($items, 0, 5));

        return implode(', ', $shown).(count($items) > 5 ? ' and '.(count($items) - 5).' more' : '');
    }
}
