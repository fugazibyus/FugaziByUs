<?php

namespace App\Gates;

/**
 * The outcome of a gate check: what to do, and every rule that passed, failed or is still pending.
 */
final class Decision
{
    public const MERGE = 'merge';

    public const UPDATE_BRANCH = 'update-branch';

    public const WAIT = 'wait';

    public const REFUSE = 'refuse';

    /** @var list<string> */
    public array $passed = [];

    /** @var list<string> */
    public array $failed = [];

    /** @var list<string> */
    public array $waiting = [];

    private bool $behind = false;

    public function pass(string $rule): self
    {
        $this->passed[] = $rule;

        return $this;
    }

    public function fail(string $rule): self
    {
        $this->failed[] = $rule;

        return $this;
    }

    public function wait(string $rule): self
    {
        $this->waiting[] = $rule;

        return $this;
    }

    /** Pass or fail one rule. */
    public function check(bool $ok, string $passed, string $failed): self
    {
        return $ok ? $this->pass($passed) : $this->fail($failed);
    }

    /**
     * The branch is behind its base. Unless a rule failed, the gate updates it straight away,
     * so CI and the reviewer look at the code that will actually be merged.
     */
    public function behind(string $rule): self
    {
        $this->behind = true;

        return $this->wait($rule);
    }

    public function action(): string
    {
        return match (true) {
            $this->failed !== [] => self::REFUSE,
            $this->behind => self::UPDATE_BRANCH,
            $this->waiting !== [] => self::WAIT,
            default => self::MERGE,
        };
    }

    public function allowed(): bool
    {
        return $this->action() === self::MERGE;
    }
}
