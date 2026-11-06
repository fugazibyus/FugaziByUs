<?php

namespace App\Http\Controllers;

use App\Ai\AgentDefinition;
use App\Ai\AgentRegistry;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The AI Workflow handbook. The agents section is read from agents/<name>/agent.md, the same
 * files Anthropic runs, so the prompts on the page are always the ones in production.
 */
class AiWorkflowController extends Controller
{
    public function __invoke(AgentRegistry $registry): Response
    {
        return Inertia::render('AiWorkflow', [
            'agents' => array_values(array_map(fn (AgentDefinition $agent) => [
                'key' => $agent->key,
                'name' => $agent->name,
                'description' => $agent->description,
                'model' => $agent->model,
                'effort' => $agent->effort,
                'budgetUsd' => $agent->budgetUsd,
                'usesWeb' => $agent->usesWeb,
                'path' => 'agents/'.$agent->key.'/agent.md',
                'prompt' => $agent->prompt,
            ], $registry->all())),
        ]);
    }
}
