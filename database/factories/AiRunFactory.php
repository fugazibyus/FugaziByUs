<?php

namespace Database\Factories;

use App\Models\AiRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Realistic agent runs: costs and token counts in the ranges the real agents produce.
 *
 * @extends Factory<AiRun>
 */
class AiRunFactory extends Factory
{
    /** agent => [outcome when it works, typical cost range in cents, typical active seconds] */
    private const PROFILES = [
        'triage' => ['triaged', [5, 25], [30, 90]],
        'researcher' => ['reported', [60, 300], [180, 600]],
        'developer' => ['pr_opened', [150, 900], [300, 1500]],
        'debugger' => ['fixed', [40, 300], [120, 600]],
        'reviewer' => ['reviewed', [30, 150], [90, 300]],
        'release' => ['release_pr_opened', [10, 60], [45, 150]],
    ];

    public function definition(): array
    {
        $agent = fake()->randomElement(array_keys(self::PROFILES));
        [$outcome, $cost, $seconds] = self::PROFILES[$agent];
        $started = fake()->dateTimeBetween('-30 days', '-10 minutes');
        $active = fake()->numberBetween(...$seconds);
        $issue = fake()->numberBetween(2, 60);
        $onPr = in_array($agent, ['debugger', 'reviewer'], true);

        return [
            'session_id' => 'sesn_'.fake()->unique()->regexify('[A-Za-z0-9]{24}'),
            'agent' => $agent,
            'agent_version' => fake()->numberBetween(1, 3),
            'title' => ucfirst($agent).' · '.($onPr ? 'PR #'.($issue + 40) : "issue #{$issue}"),
            'status' => 'idle',
            'outcome' => $outcome,
            'succeeded' => true,
            'issue' => $onPr ? null : $issue,
            'pull_request' => $onPr ? $issue + 40 : null,
            'trigger' => fake()->randomElement(['actions', 'comment', 'issue-opened', 'ci']),
            'input_tokens' => fake()->numberBetween(2_000, 40_000),
            'output_tokens' => fake()->numberBetween(1_000, 30_000),
            'cache_read_tokens' => fake()->numberBetween(50_000, 900_000),
            'cache_creation_tokens' => fake()->numberBetween(5_000, 60_000),
            'cost_cents' => fake()->numberBetween(...$cost),
            'active_seconds' => $active,
            'started_at' => $started,
            'finished_at' => (clone $started)->modify("+{$active} seconds"),
        ];
    }

    public function agent(string $agent): static
    {
        [$outcome] = self::PROFILES[$agent];

        return $this->state(fn (array $attributes) => [
            'agent' => $agent,
            'outcome' => $outcome,
            'title' => ucfirst($agent).' · '.str((string) $attributes['title'])->after(' · '),
        ]);
    }

    public function failed(): static
    {
        return $this->state(['outcome' => 'needs_human', 'succeeded' => false]);
    }

    public function running(): static
    {
        return $this->state(['status' => 'running', 'outcome' => null, 'succeeded' => null, 'finished_at' => null]);
    }

    public function demo(): static
    {
        return $this->state(['demo' => true]);
    }
}
