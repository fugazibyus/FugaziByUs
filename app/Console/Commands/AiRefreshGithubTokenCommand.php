<?php

namespace App\Console\Commands;

use App\Ai\AgentRegistry;
use App\Ai\Contracts\ManagedAgentsGateway;
use App\Ai\Exceptions\AgentConfigurationException;
use App\Ai\GitHubAppTokenIssuer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:refresh-github-token')]
#[Description('Mint a fresh GitHub App token for the agents and hand it to the vault and every active session')]
class AiRefreshGithubTokenCommand extends Command
{
    /**
     * App tokens expire after an hour. The scheduler runs this every 30 minutes so agent
     * sessions that run longer than that can keep pushing and commenting.
     */
    public function handle(GitHubAppTokenIssuer $github, ManagedAgentsGateway $gateway, AgentRegistry $registry): int
    {
        try {
            $token = $github->mint();
            $gateway->storeGithubToken($registry->vaultId(), $token->value);
            $sessions = $gateway->refreshRepositoryTokens($token->value);
        } catch (AgentConfigurationException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("GitHub token refreshed for the vault and {$sessions} active session(s). It expires at {$token->expiresAt->toDateTimeString()} UTC.");

        return self::SUCCESS;
    }
}
