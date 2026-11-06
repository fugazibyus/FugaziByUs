<?php

namespace App\Slack;

use Illuminate\Support\Facades\Cache;

/**
 * Turns GitHub webhook events into Slack alerts (routes in config/ai-workflow.php).
 */
class GitHubAlerts
{
    private const ISSUE_ICONS = ['bug' => '🐞', 'feature' => '✨', 'research' => '🔎', 'chore' => '🧹'];

    public function __construct(private readonly Notifier $notifier) {}

    /** @param  array<string, mixed>  $payload */
    public function handle(string $event, array $payload): void
    {
        match ($event) {
            'issues' => $this->issue($payload),
            'pull_request' => $this->pullRequest($payload),
            'issue_comment' => $this->comment($payload),
            default => null,
        };
    }

    /** @param  array<string, mixed>  $payload */
    private function issue(array $payload): void
    {
        $issue = $payload['issue'];
        $number = (int) $issue['number'];
        $link = "<{$issue['html_url']}|#{$number} {$issue['title']}>";
        $author = (string) ($issue['user']['login'] ?? '');

        switch ($payload['action']) {
            case 'opened':
                $type = $this->type($issue['labels'] ?? []);
                $flow = in_array('flow:human', self::labels($issue['labels'] ?? []), true) ? 'a developer' : 'the AI workflow';
                $this->notifier->notify("issue.{$type}", (self::ISSUE_ICONS[$type] ?? '📝')." New {$type}: {$link}, for {$flow}.", $number, $author);
                break;

            case 'labeled':
                if (in_array($payload['label']['name'] ?? null, ['ai:needs-human', 'ai:failed'], true)) {
                    $this->notifier->notify('ai.needs_human', "🙋 {$link} needs a developer (`{$payload['label']['name']}`).", $number, $author);
                }
                break;

            case 'closed':
                if (($issue['state_reason'] ?? null) === 'completed') {
                    $this->notifier->notify('issue.done', "✅ Done: {$link}. It's merged and will reach the live site with the next release.", $number, $author);
                }
                break;
        }
    }

    /** @param  array<string, mixed>  $payload */
    private function pullRequest(array $payload): void
    {
        $pr = $payload['pull_request'];
        $link = "<{$pr['html_url']}|PR #{$pr['number']} {$pr['title']}>";
        $issue = $this->linkedIssue((string) ($pr['body'] ?? ''));
        $isAi = str_starts_with((string) $pr['head']['ref'], 'ai/');

        if ($payload['action'] === 'opened' && $isAi) {
            $this->notifier->notify('pr.opened', "🤖 The AI opened {$link}".($issue ? " for #{$issue}" : '').'.', $issue);
        }

        if ($payload['action'] === 'opened' && $pr['base']['ref'] === 'main' && $pr['head']['ref'] === 'develop') {
            $this->notifier->notify('release.ready', "📦 Release ready: {$link}. The Deploy Gate checks it every 30 minutes.");
        }

        if ($payload['action'] === 'labeled' && ($payload['label']['name'] ?? null) === 'ai:automerged') {
            $this->notifier->notify('pr.automerged', "🟣 The Merge Gate merged {$link} into develop after all its checks passed.", $issue);
        }

        if ($payload['action'] === 'labeled' && ($payload['label']['name'] ?? null) === 'ai:needs-human') {
            $this->notifier->notify('ai.needs_human', "🙋 {$link} needs a developer: the AI has stopped.", $issue);
        }
    }

    /** @param  array<string, mixed>  $payload */
    private function comment(array $payload): void
    {
        if (! in_array($payload['action'], ['created', 'edited'], true) || ($payload['comment']['user']['type'] ?? null) !== 'Bot') {
            return;
        }

        $body = (string) $payload['comment']['body'];
        $issue = $payload['issue'];
        $link = "<{$issue['html_url']}|#{$issue['number']} {$issue['title']}>";
        $commit = preg_match('/Checked commit ([0-9a-f]{7})/', $body, $m) ? $m[1] : '';

        $alert = match (true) {
            str_contains($body, '<!-- merge-gate -->') && str_contains($body, 'A developer needs to merge this') => ['pr.needs_human', "👀 {$link} needs a human review: the Merge Gate won't merge it on its own.\n".$this->failedRules($body)],
            str_contains($body, '<!-- deploy-gate -->') && str_contains($body, 'Shipping') => ['release.shipping', "🚀 The Deploy Gate is shipping {$link} to production."],
            str_contains($body, '<!-- deploy-gate -->') && str_contains($body, 'A developer needs to ship') => ['release.needs_approval', "✋ {$link} needs a developer to ship it.\n".$this->failedRules($body)],
            str_contains($body, '<!-- ai-alert:budget -->') => ['ai.budget', "💸 Today's AI budget is used up, so an agent didn't start on {$link}."],
            default => null,
        };

        // Gates re-check often; alert once per decision and commit.
        if ($alert !== null && Cache::add('slack-alert:'.md5("{$alert[0]}:{$issue['number']}:{$commit}"), true, now()->addDay())) {
            $this->notifier->notify($alert[0], $alert[1], (int) $issue['number']);
        }
    }

    private function failedRules(string $body): string
    {
        preg_match_all('/^- ❌ (.+)$/m', $body, $m);

        return implode("\n", array_map(fn (string $rule) => "• {$rule}", array_slice($m[1], 0, 5)));
    }

    /** @param  list<array<string, mixed>|string>  $labels */
    private function type(array $labels): string
    {
        foreach (self::labels($labels) as $label) {
            if (str_starts_with($label, 'type:')) {
                return substr($label, 5) === 'chore' ? 'feature' : substr($label, 5);
            }
        }

        return 'feature';
    }

    /**
     * @param  list<array<string, mixed>|string>  $labels
     * @return list<string>
     */
    private static function labels(array $labels): array
    {
        return array_map(fn ($label) => is_array($label) ? (string) $label['name'] : (string) $label, $labels);
    }

    private function linkedIssue(string $body): ?int
    {
        return preg_match('/\b(?:close[sd]?|fix(?:e[sd])?|resolve[sd]?)\s+#(\d+)/i', $body, $m) ? (int) $m[1] : null;
    }
}
