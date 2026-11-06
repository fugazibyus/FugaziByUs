<?php

namespace App\Gates;

use Carbon\CarbonImmutable;

/**
 * The comment a gate keeps on a pull request (one per gate, edited in place).
 */
final class GateReport
{
    public const MERGE_MARKER = '<!-- merge-gate -->';

    public const DEPLOY_MARKER = '<!-- deploy-gate -->';

    public static function render(string $gate, Decision $decision, string $sha, string $trigger, ?string $note = null): string
    {
        $deploy = $gate === 'deploy';

        $headline = match ($decision->action()) {
            Decision::MERGE => $deploy ? '✅ Shipping: merging the release into `main`' : '✅ Merging',
            Decision::UPDATE_BRANCH => '🔄 Updating the branch first',
            Decision::WAIT => '⏳ Waiting',
            default => $deploy ? '✋ A developer needs to ship this release' : '✋ A developer needs to merge this',
        };

        $lines = [
            $deploy ? self::DEPLOY_MARKER : self::MERGE_MARKER,
            '### 🚦 '.($deploy ? 'Deploy Gate' : 'Merge Gate').": {$headline}",
            '',
            ...array_map(fn (string $rule) => "- ❌ {$rule}", $decision->failed),
            ...array_map(fn (string $rule) => "- ⏳ {$rule}", $decision->waiting),
            ...array_map(fn (string $rule) => "- ✅ {$rule}", $decision->passed),
        ];

        if ($decision->action() === Decision::REFUSE) {
            $lines[] = '';
            $lines[] = $deploy
                ? 'Not every rule passed, so the AI won\'t ship this on its own. A developer can review and merge the release as usual, or comment `/ai deploy production`.'
                : 'Not every rule passed, so the AI won\'t merge this on its own. A developer can review and merge it as usual, or comment `/ai merge`.';
        }

        if ($decision->action() === Decision::WAIT) {
            $lines[] = '';
            $lines[] = 'The gate checks again automatically.';
        }

        if ($note !== null) {
            $lines[] = '';
            $lines[] = $note;
        }

        $lines[] = '';
        $lines[] = '<sub>Checked commit '.substr($sha, 0, 7).' at '.CarbonImmutable::now('UTC')->format('Y-m-d H:i').' UTC · trigger: '.$trigger.' · rules: <code>app/Gates/'.($deploy ? 'DeployPolicy' : 'MergePolicy').'.php</code></sub>';

        return implode("\n", $lines);
    }

    /**
     * The squash commit message: the issue it closes, the checks that passed and the agent sessions.
     *
     * @param  list<string>  $sessions
     */
    public static function commitMessage(Decision $decision, ?int $issue, array $sessions): string
    {
        $lines = array_filter([
            $issue !== null ? "Closes #{$issue}" : null,
            $issue !== null ? '' : null,
            'Merged by the Merge Gate:',
            ...array_map(fn (string $rule) => "- {$rule}", $decision->passed),
        ], fn (?string $line) => $line !== null);

        if ($sessions !== []) {
            $lines[] = '';
            $lines[] = 'Agent sessions: '.implode(', ', $sessions);
        }

        return implode("\n", $lines);
    }
}
