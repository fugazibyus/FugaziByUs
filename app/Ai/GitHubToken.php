<?php

namespace App\Ai;

use Carbon\CarbonImmutable;

/** A short-lived GitHub App installation token (valid for one hour). */
final readonly class GitHubToken
{
    public function __construct(
        public string $value,
        public CarbonImmutable $expiresAt,
    ) {}
}
