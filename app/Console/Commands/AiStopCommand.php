<?php

namespace App\Console\Commands;

use App\Ai\Contracts\ManagedAgentsGateway;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:stop
    {--issue= : Stop the agents working on this issue}
    {--pr= : Stop the agents working on this pull request}
    {--all : Stop every running agent}')]
#[Description('Kill switch: interrupt running agent sessions for an issue, a pull request, or everything')]
class AiStopCommand extends Command
{
    public function handle(ManagedAgentsGateway $gateway): int
    {
        $issue = (string) $this->option('issue');
        $pr = (string) $this->option('pr');

        if ($issue === '' && $pr === '' && ! $this->option('all')) {
            $this->components->error('Say what to stop: --issue=, --pr= or --all.');

            return self::INVALID;
        }

        $stopped = 0;

        foreach ($gateway->runningSessions() as $session) {
            $matches = $this->option('all')
                || ($issue !== '' && ($session['metadata']['issue'] ?? null) === $issue)
                || ($pr !== '' && ($session['metadata']['pr'] ?? null) === $pr);

            if ($matches) {
                $gateway->interruptSession($session['id']);
                $this->components->twoColumnDetail('Stopped '.($session['metadata']['agent'] ?? 'agent'), $session['id']);
                $stopped++;
            }
        }

        $this->components->info($stopped === 0 ? 'No matching agents were running.' : "Stopped {$stopped} agent session(s).");

        return self::SUCCESS;
    }
}
