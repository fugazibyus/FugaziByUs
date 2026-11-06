<?php

namespace App\Slack;

/**
 * The people in config/team.php: GitHub username ↔ Slack member ID, and who's a developer.
 */
class Team
{
    /** @return list<array{name: string, github: string, slack: ?string, dev: bool, on_duty: bool}> */
    public function members(): array
    {
        return array_values(array_map(fn (array $m) => [
            'name' => (string) ($m['name'] ?? $m['github']),
            'github' => strtolower((string) $m['github']),
            'slack' => isset($m['slack']) && $m['slack'] !== '' ? (string) $m['slack'] : null,
            'dev' => (bool) ($m['dev'] ?? false),
            'on_duty' => (bool) ($m['on_duty'] ?? false),
        ], (array) config('team.members')));
    }

    /** @return array{name: string, github: string, slack: ?string, dev: bool, on_duty: bool}|null */
    public function bySlack(string $slackId): ?array
    {
        return collect($this->members())->first(fn (array $m) => $m['slack'] === $slackId);
    }

    public function slackIdFor(?string $github): ?string
    {
        return collect($this->members())->firstWhere('github', strtolower((string) $github))['slack'] ?? null;
    }

    public function isDev(string $slackId): bool
    {
        return (bool) ($this->bySlack($slackId)['dev'] ?? false);
    }

    /** @return list<string> Slack IDs */
    public function devs(): array
    {
        return $this->slackIds(fn (array $m) => $m['dev']);
    }

    /** @return list<string> Slack IDs */
    public function onDuty(): array
    {
        return $this->slackIds(fn (array $m) => $m['on_duty']);
    }

    /**
     * @param  callable(array{name: string, github: string, slack: ?string, dev: bool, on_duty: bool}): bool  $filter
     * @return list<string>
     */
    private function slackIds(callable $filter): array
    {
        return array_values(array_filter(array_column(array_filter($this->members(), $filter), 'slack')));
    }
}
