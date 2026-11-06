<?php

namespace App\Ai;

/**
 * One agent as defined in agents/<key>/agent.md (frontmatter + prompt).
 */
final readonly class AgentDefinition
{
    /**
     * @param  array<string, mixed>  $frontmatter
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $description,
        public string $model,
        public string $effort,
        public float $budgetUsd,
        public string $environment,
        public bool $usesWeb,
        public string $prompt,
        public string $path,
        public array $frontmatter,
    ) {}
}
