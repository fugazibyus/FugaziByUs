<?php

namespace App\Console\Commands;

use App\Ai\AgentTask;
use App\Ai\Exceptions\AgentConfigurationException;
use App\Ai\Exceptions\DailyBudgetExceededException;
use App\Ai\StartAgentSession;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:start
    {agent : triage, researcher, developer, debugger, reviewer or release}
    {--issue= : GitHub issue number}
    {--pr= : Pull request number}
    {--branch= : Branch to check out (defaults to develop)}
    {--run-id= : Failing workflow run (debugger)}
    {--attempt= : Attempt number (debugger)}
    {--staging-url= : Staging URL to smoke-check (release)}
    {--message= : Extra instructions, or the question for the researcher}
    {--trigger=manual : What started this run (actions, slack, manual)}
    {--actor= : Who started it}
    {--dry-run : Show what would be sent without starting a session}')]
#[Description('Start a Claude Managed Agents session for one of the FugaziByUs agents')]
class AiStartCommand extends Command
{
    public function handle(StartAgentSession $starter): int
    {
        $task = new AgentTask(
            agent: (string) $this->argument('agent'),
            issue: $this->intOption('issue'),
            pullRequest: $this->intOption('pr'),
            branch: $this->option('branch') ?: null,
            runId: $this->option('run-id') ?: null,
            attempt: $this->intOption('attempt'),
            instructions: $this->option('message') ?: null,
            stagingUrl: $this->option('staging-url') ?: null,
            trigger: (string) $this->option('trigger'),
            actor: $this->option('actor') ?: (getenv('GITHUB_ACTOR') ?: null),
        );

        try {
            if ($this->option('dry-run')) {
                $plan = $starter->plan($task);
                $plan['repositories'][0]['token'] = '********';
                $this->line(json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

                return self::SUCCESS;
            }

            $session = $starter->handle($task);
        } catch (DailyBudgetExceededException $e) {
            $this->components->warn($e->getMessage());

            return 2;
        } catch (AgentConfigurationException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Started {$task->title()}");
        $this->components->twoColumnDetail('Session', $session->id);
        $this->components->twoColumnDetail('Watch in Console', $session->consoleUrl);
        $this->components->twoColumnDetail('Watch in terminal', "ant beta:sessions connect {$session->id}");

        $this->writeGithubOutputs($task, $session->id, $session->consoleUrl);

        return self::SUCCESS;
    }

    private function intOption(string $name): ?int
    {
        $value = $this->option($name);

        return is_numeric($value) ? (int) $value : null;
    }

    /** When running in GitHub Actions, expose the session for later steps and the job summary. */
    private function writeGithubOutputs(AgentTask $task, string $id, string $url): void
    {
        if ($output = getenv('GITHUB_OUTPUT')) {
            file_put_contents($output, "session_id={$id}\nsession_url={$url}\n", FILE_APPEND);
        }

        if ($summary = getenv('GITHUB_STEP_SUMMARY')) {
            file_put_contents($summary, "### 🤖 {$task->title()}\n\nSession [`{$id}`]({$url}) started.\n", FILE_APPEND);
        }
    }
}
