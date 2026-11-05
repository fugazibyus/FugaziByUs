<?php

namespace App\Console\Commands;

use App\Ai\AgentDefinition;
use App\Ai\AgentRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('ai:sync-local {--check : Fail if the generated files are out of date (for CI)}')]
#[Description('Generate local Claude Code subagents (.claude/agents) from the agent definitions in /agents')]
class AiSyncLocalCommand extends Command
{
    /** Tools each agent may use when running locally inside Claude Code. */
    private const TOOLS = [
        'triage' => 'Read, Grep, Glob, Bash',
        'researcher' => 'Read, Grep, Glob, Write, Edit, Bash, WebSearch, WebFetch',
        'developer' => 'Read, Grep, Glob, Write, Edit, MultiEdit, Bash',
        'debugger' => 'Read, Grep, Glob, Write, Edit, MultiEdit, Bash',
        'reviewer' => 'Read, Grep, Glob, Bash',
        'release' => 'Read, Grep, Glob, Bash',
    ];

    /** The slash command in .claude/commands that runs each agent. */
    private const COMMANDS = [
        'triage' => 'triage',
        'researcher' => 'research',
        'developer' => 'develop',
        'debugger' => 'debug',
        'reviewer' => 'review',
        'release' => 'release',
    ];

    public function handle(AgentRegistry $registry): int
    {
        $target = base_path('.claude/agents');
        File::ensureDirectoryExists($target);
        $stale = [];

        foreach ($registry->all() as $agent) {
            $path = "{$target}/fugazi-{$agent->key}.md";
            $content = $this->render($agent);

            if ($this->option('check')) {
                if (! File::exists($path) || File::get($path) !== $content) {
                    $stale[] = $path;
                }

                continue;
            }

            File::put($path, $content);
            $this->components->twoColumnDetail("fugazi-{$agent->key}", 'updated');
        }

        if ($stale !== []) {
            $this->components->error('Local agents are out of date – run `make ai-sync` and commit: '.implode(', ', array_map('basename', $stale)));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function render(AgentDefinition $agent): string
    {
        $tools = self::TOOLS[$agent->key] ?? 'Read, Grep, Glob, Bash';
        $description = str_replace(["\n", '"'], [' ', "'"], $agent->description);
        $command = self::COMMANDS[$agent->key] ?? $agent->key;

        return <<<MD
            ---
            name: fugazi-{$agent->key}
            description: "{$description} Use for /{$command} or when asked to act as the FugaziByUs {$agent->key} agent."
            tools: {$tools}
            model: opus
            ---

            <!-- GENERATED from agents/{$agent->key}/agent.md by `make ai-sync`. Edit that file, not this one. -->

            ## Running locally

            You're running inside a developer's Claude Code session on their own checkout, not in the cloud sandbox. Differences from the instructions below:

            - The repository is the current working directory, not `/workspace/repo`. Use the `make` commands from `CLAUDE.md` (Docker) instead of running PHP or npm directly.
            - The developer is watching. Before you push, open a PR, comment on GitHub or change labels, summarise what you're about to do and ask for confirmation.
            - Leave the final JSON result block out unless the developer asks for it.

            {$agent->prompt}

            MD;
    }
}
