<?php

namespace App\Console\Commands;

use App\Ai\Exceptions\AgentConfigurationException;
use App\Gates\Decision;
use App\Gates\GateFactsCollector;
use App\Gates\GateReport;
use App\Gates\GitHub;
use App\Gates\MergePolicy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;

#[Signature('gate:merge
    {pr : Pull request number}
    {--comment= : ID of the `/ai merge` comment that asked (proves who asked)}
    {--requested-by= : The developer who started this run by hand}
    {--trigger=manual : What started this check}
    {--dry-run : Decide and print, but change nothing on GitHub}')]
#[Description('Merge Gate: check a pull request into develop against the AI merge policy, and merge it if every rule passes')]
class GateMergeCommand extends Command
{
    public function handle(): int
    {
        $number = (int) $this->argument('pr');
        try {
            $github = GitHub::fromConfig();
            $collector = new GateFactsCollector($github);
            $requestedBy = $this->option('comment')
                ? $collector->requesterFromComment($number, (int) $this->option('comment'), 'merge')
                : ($this->option('requested-by') ?: null);
            $pr = $collector->merge($number, $requestedBy);
        } catch (AgentConfigurationException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $decision = MergePolicy::fromConfig()->decide($pr);
        $this->print($decision);

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $note = null;

        try {
            if ($decision->action() === Decision::MERGE) {
                $github->merge($number, $pr->headSha, 'squash', "{$pr->title} (#{$number})", GateReport::commitMessage($decision, $pr->issue, $pr->sessions));

                if ($pr->requestedBy === null && str_starts_with($pr->head, 'ai/')) {
                    foreach (array_filter([$number, $pr->issue]) as $target) {
                        $github->addLabels($target, ['ai:automerged']);
                    }
                }
            } elseif ($decision->action() === Decision::UPDATE_BRANCH) {
                $github->updateBranch($number, $pr->headSha);
            }
        } catch (RequestException $e) {
            $note = '⚠️ GitHub refused: '.($e->response->json('message') ?? 'HTTP '.$e->response->status()).'. A developer should take over.';
            $this->components->error($note);
        }

        $github->upsertComment($number, GateReport::MERGE_MARKER, GateReport::render('merge', $decision, $pr->headSha, (string) $this->option('trigger'), $note));
        $this->writeOutput($note === null ? $decision->action() : 'error');

        return $note === null ? self::SUCCESS : self::FAILURE;
    }

    private function print(Decision $decision): void
    {
        $this->components->info('Merge Gate decision: '.$decision->action());

        foreach (['❌' => $decision->failed, '⏳' => $decision->waiting, '✅' => $decision->passed] as $icon => $rules) {
            foreach ($rules as $rule) {
                $this->line("  {$icon} {$rule}");
            }
        }
    }

    private function writeOutput(string $action): void
    {
        if ($output = getenv('GITHUB_OUTPUT')) {
            file_put_contents($output, "action={$action}\n", FILE_APPEND);
        }
    }
}
