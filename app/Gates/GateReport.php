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
     * The Slack message for a decision worth announcing: a merge, a refusal, or an error.
     * (Waiting and branch updates aren't news.)
     */
    public static function slack(string $gate, Decision $decision, string $url, string $label, ?string $note = null): ?string
    {
        $link = '<'.$url.'|'.str_replace(['<', '>', '|'], '', $label).'>';
        $failed = implode('', array_map(fn (string $rule) => "\n• ".str_replace('`', '', $rule), array_slice($decision->failed, 0, 5)));

        return match (true) {
            $note !== null => '⚠️ The '.($gate === 'deploy' ? 'Deploy' : 'Merge')." Gate couldn't act on {$link}: ".str_replace('⚠️ ', '', $note),
            $decision->action() === Decision::MERGE => $gate === 'deploy'
                ? "🚀 The Deploy Gate is shipping {$link} to production."
                : "🟣 The Merge Gate merged {$link} into develop after every check passed.",
            $decision->action() === Decision::REFUSE => $gate === 'deploy'
                ? "✋ {$link} needs a developer to ship it:{$failed}"
                : "👀 {$link} needs a human review. The Merge Gate won't merge it on its own:{$failed}",
            default => null,
        };
    }

    /**
     * Hand values to later workflow steps (multi-line safe).
     *
     * @param  array<string, string>  $values
     */
    public static function writeGithubOutput(array $values): void
    {
        $file = getenv('GITHUB_OUTPUT');

        if (! $file) {
            return;
        }

        foreach ($values as $key => $value) {
            $delimiter = 'EOF_'.bin2hex(random_bytes(8));
            file_put_contents($file, "{$key}<<{$delimiter}\n{$value}\n{$delimiter}\n", FILE_APPEND);
        }
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
