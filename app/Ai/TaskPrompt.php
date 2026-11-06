<?php

namespace App\Ai;

/**
 * Builds the first user message for a session. The agent's system prompt (agents/<key>/agent.md)
 * says *how* to work; this message says *what* to work on right now.
 */
class TaskPrompt
{
    public function build(AgentTask $task, string $sessionId): string
    {
        $repository = (string) config('ai.repository');
        $base = (string) config('ai.base_branch');

        $what = match ($task->agent) {
            'triage' => "Triage issue #{$task->issue}.",
            'researcher' => $task->issue !== null
                ? "Research the question in issue #{$task->issue}."
                : 'Research this question: '.($task->instructions ?? ''),
            'developer' => "Implement issue #{$task->issue} and open a pull request into `{$base}`.",
            'debugger' => "CI is failing on PR #{$task->pullRequest}".($task->runId ? " (workflow run {$task->runId})" : '').
                '. Diagnose and fix it. This is attempt '.($task->attempt ?? 1).' of 2.',
            'reviewer' => "Review PR #{$task->pullRequest}".($task->issue ? " (for issue #{$task->issue})" : '').'.',
            'release' => $task->instructions === 'report'
                ? 'Report on the production deploy.'
                : 'Prepare the release pull request from `develop` to `main`.',
            default => $task->instructions ?? 'Follow your instructions.',
        };

        $context = array_filter([
            "- Repository: `{$repository}`, checked out at `".config('ai.mount_path').'`',
            $task->branch ? "- Branch: `{$task->branch}`" : null,
            $task->issue ? "- Issue: #{$task->issue} (`gh issue view {$task->issue} --comments`)" : null,
            $task->pullRequest ? "- Pull request: #{$task->pullRequest} (`gh pr view {$task->pullRequest} --comments`)" : null,
            $task->stagingUrl ? "- Staging URL: {$task->stagingUrl}" : null,
            "- Session: `{$sessionId}` (put this in the PR's ai-metadata block)",
            "- Triggered by: {$task->trigger}".($task->actor ? " ({$task->actor})" : ''),
        ]);

        $extra = $task->instructions !== null && $task->agent !== 'researcher' && $task->agent !== 'release'
            ? "\n\nAdditional instructions from the person who started this run:\n> ".str_replace("\n", "\n> ", $task->instructions)
            : '';

        return "{$what}\n\n".implode("\n", $context).$extra."\n\nWhen you're done, end with the JSON result block from your instructions.";
    }
}
