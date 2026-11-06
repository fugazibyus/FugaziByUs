<?php

namespace App\Gates;

use Carbon\CarbonImmutable;

/**
 * Everything the Deploy Gate knows about a release pull request (develop → main).
 */
final readonly class DeployFacts
{
    /**
     * @param  list<string>  $labels
     * @param  array<string, string>  $checks  check name => success | failure | pending | missing
     * @param  list<array{number: int, merged_by: string, labels: list<string>}>  $releasedPrs  PRs merged into develop since the last release
     * @param  list<string>  $devApprovals  developers who approved the release PR
     * @param  array{created_at: CarbonImmutable, state: string}|null  $lastDeploy  latest production deployment
     */
    public function __construct(
        public int $number,
        public bool $open,
        public bool $draft,
        public string $base,
        public string $head,
        public string $headSha,
        public array $labels,
        public array $checks,
        public CarbonImmutable $now,
        public ?string $stagingUrl = null,
        public bool $stagingHealthy = false,
        public ?string $stagingSha = null,
        public ?CarbonImmutable $stagingDeployedAt = null,
        public array $releasedPrs = [],
        public bool $touchesMigrations = false,
        public array $devApprovals = [],
        public ?array $lastDeploy = null,
        public ?string $requestedBy = null,
        public bool $requesterIsDev = false,
    ) {}
}
