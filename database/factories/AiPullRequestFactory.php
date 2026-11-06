<?php

namespace Database\Factories;

use App\Models\AiPullRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiPullRequest>
 */
class AiPullRequestFactory extends Factory
{
    public function definition(): array
    {
        $issue = fake()->numberBetween(2, 60);
        $issueOpened = fake()->dateTimeBetween('-30 days', '-2 hours');
        $opened = (clone $issueOpened)->modify('+'.fake()->numberBetween(8, 45).' minutes');
        $title = fake()->randomElement(['Add a dark mode toggle', 'Fix the contact form error message', 'Add a short FAQ', 'Show the AI stats on mobile']);

        return [
            'number' => fake()->unique()->numberBetween(10, 999),
            'title' => $title,
            'branch' => 'ai/feature/'.$issue.'-'.str($title)->slug(),
            'state' => 'merged',
            'auto_merged' => fake()->boolean(70),
            'issue' => $issue,
            'issue_opened_at' => $issueOpened,
            'opened_at' => $opened,
            'merged_at' => (clone $opened)->modify('+'.fake()->numberBetween(10, 120).' minutes'),
        ];
    }

    public function open(): static
    {
        return $this->state(['state' => 'open', 'auto_merged' => false, 'merged_at' => null]);
    }

    public function demo(): static
    {
        return $this->state(['demo' => true]);
    }
}
