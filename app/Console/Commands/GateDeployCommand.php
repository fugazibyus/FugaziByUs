<?php

namespace App\Console\Commands;

use App\Ai\Exceptions\AgentConfigurationException;
use App\Gates\DeployPolicy;
use App\Gates\GateFactsCollector;
use App\Gates\GateReport;
use App\Gates\GitHub;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;

#[Signature('gate:deploy
    {pr? : Release pull request number (defaults to the open develop → main PR)}
    {--comment= : ID of the `/ai merge` or `/ai deploy production` comment that asked (proves who asked)}
    {--requested-by= : The developer who started this run by hand}
    {--trigger=manual : What started this check}
    {--dry-run : Decide and print, but change nothing on GitHub}')]
#[Description('Deploy Gate: check the release pull request against the production deploy policy, and merge it into main (which deploys) if every rule passes')]
class GateDeployCommand extends Command
{
    public function handle(): int
    {
        try {
            $github = GitHub::fromConfig();
            $number = (int) $this->argument('pr')
                ?: (int) ($github->openPullRequests((string) config('gates.production_branch'), (string) config('gates.base_branch'))[0]['number'] ?? 0);

            if ($number === 0) {
                $this->components->info('No release pull request is open.');

                return self::SUCCESS;
            }

            $collector = new GateFactsCollector($github);
            $requestedBy = $this->option('requested-by') ?: null;

            if ($this->option('comment')) {
                try {
                    $requestedBy = $collector->requesterFromComment($number, (int) $this->option('comment'), 'deploy production');
                } catch (AgentConfigurationException) {
                    $requestedBy = $collector->requesterFromComment($number, (int) $this->option('comment'), 'merge');
                }
            }

            $release = $collector->deploy($number, $requestedBy);
        } catch (AgentConfigurationException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $decision = DeployPolicy::fromConfig()->decide($release);
        $this->components->info("Deploy Gate decision for #{$number}: ".$decision->action());

        foreach (['❌' => $decision->failed, '⏳' => $decision->waiting, '✅' => $decision->passed] as $icon => $rules) {
            foreach ($rules as $rule) {
                $this->line("  {$icon} {$rule}");
            }
        }

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $note = null;

        if ($decision->allowed()) {
            try {
                $github->merge($number, $release->headSha, 'merge', "Release (#{$number})", "Shipped by the Deploy Gate:\n".implode("\n", array_map(fn (string $r) => "- {$r}", $decision->passed)));
            } catch (RequestException $e) {
                $note = '⚠️ GitHub refused the merge: '.($e->response->json('message') ?? 'HTTP '.$e->response->status()).'. A developer should take over.';
                $this->components->error($note);
            }
        }

        $github->upsertComment($number, GateReport::DEPLOY_MARKER, GateReport::render('deploy', $decision, $release->headSha, (string) $this->option('trigger'), $note));

        if ($output = getenv('GITHUB_OUTPUT')) {
            file_put_contents($output, 'action='.($note === null ? $decision->action() : 'error')."\n", FILE_APPEND);
        }

        return $note === null ? self::SUCCESS : self::FAILURE;
    }
}
