<?php

namespace App\Console\Commands;

use App\Ai\AgentRegistry;
use App\Ai\Exceptions\AgentConfigurationException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:agents')]
#[Description('List the FugaziByUs agents, their models, budgets and Anthropic IDs')]
class AiAgentsCommand extends Command
{
    public function handle(AgentRegistry $registry): int
    {
        $rows = [];

        foreach ($registry->all() as $agent) {
            try {
                $id = $registry->agentId($agent);
            } catch (AgentConfigurationException) {
                $id = '– not applied yet –';
            }

            $rows[] = [$agent->key, $agent->model.' ('.$agent->effort.')', '$'.number_format($agent->budgetUsd, 2), $agent->environment, $agent->usesWeb ? 'yes' : 'no', $id];
        }

        $this->table(['Agent', 'Model', 'Budget/run', 'Sandbox', 'Web', 'Anthropic ID'], $rows);

        return self::SUCCESS;
    }
}
