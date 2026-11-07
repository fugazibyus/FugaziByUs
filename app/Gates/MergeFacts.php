<?php

namespace App\Gates;

/**
 * Everything the Merge Gate knows about one pull request. Collected from GitHub by
 * GateFactsCollector; built by hand in tests.
 */
final readonly class MergeFacts
{
    /**
     * @param  list<string>  $labels  labels on the PR and on its linked issue
     * @param  array<string, string>  $checks  check name => success | failure | pending | missing
     * @param  array{verdict: string, risk: string, sha: string, blocking: int}|null  $verdict  the AI reviewer's latest verdict
     * @param  list<string>  $files  changed paths
     * @param  list<string>  $sessions  agent session IDs mentioned on the PR
     * @param  string|null  $optedInBy  the developer or product member who added `ai:automerge`
     * @param  string|null  $requestedVia  how the person asked, e.g. "`/ai merge`" or "a manual run"
     */
    public function __construct(
        public int $number,
        public string $title,
        public bool $open,
        public bool $draft,
        public bool $fromFork,
        public string $base,
        public string $head,
        public string $headSha,
        public string $author,
        public array $labels,
        public array $checks,
        public array $files,
        public int $behindBy,
        public ?array $verdict = null,
        public ?int $issue = null,
        public bool $reportedByDev = false,
        public ?string $optedInBy = null,
        public ?string $requestedBy = null,
        public bool $requesterIsDev = false,
        public bool $requesterIsProduct = false,
        public ?string $requestedVia = null,
        public array $sessions = [],
    ) {}

    public function hasLabel(string $label): bool
    {
        return in_array($label, $this->labels, true);
    }

    public function size(): ?string
    {
        foreach (['s', 'm', 'l'] as $size) {
            if ($this->hasLabel("size:{$size}")) {
                return $size;
            }
        }

        return null;
    }
}
