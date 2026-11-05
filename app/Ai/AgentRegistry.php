<?php

namespace App\Ai;

use App\Ai\Exceptions\AgentConfigurationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads agent definitions from /agents and the Anthropic IDs from claude-lock.json.
 */
class AgentRegistry
{
    /** @var array<string, AgentDefinition>|null */
    private ?array $agents = null;

    /** @var array<string, mixed>|null */
    private ?array $lock = null;

    public function __construct(
        private readonly string $agentsPath,
        private readonly string $lockFile,
    ) {}

    /**
     * @return array<string, AgentDefinition>
     */
    public function all(): array
    {
        if ($this->agents !== null) {
            return $this->agents;
        }

        $agents = [];

        foreach (File::glob($this->agentsPath.'/*/agent.md') as $file) {
            $definition = $this->parse($file);
            $agents[$definition->key] = $definition;
        }

        ksort($agents);

        return $this->agents = $agents;
    }

    public function find(string $key): AgentDefinition
    {
        return $this->all()[$key]
            ?? throw new AgentConfigurationException("Unknown agent [{$key}]. Known agents: ".implode(', ', array_keys($this->all())));
    }

    /**
     * The Anthropic ID recorded by `ant apply` for a file, e.g. "agents/developer/agent.md".
     */
    public function lockedId(string $path): string
    {
        $resources = $this->lock()['resources'] ?? [];
        $normalised = ltrim(preg_replace('#^\./#', '', $path) ?? $path, '/');

        foreach (is_array($resources) ? $resources : [] as $key => $resource) {
            if (ltrim(preg_replace('#^\./#', '', (string) $key) ?? '', '/') === $normalised) {
                $id = is_array($resource) ? ($resource['id'] ?? null) : null;

                if (is_string($id) && $id !== '') {
                    return $id;
                }
            }
        }

        throw new AgentConfigurationException("No Anthropic ID for [{$path}] in claude-lock.json. Run `make ai-apply` first.");
    }

    public function agentId(AgentDefinition $agent): string
    {
        return $this->lockedId('agents/'.$agent->key.'/agent.md');
    }

    public function environmentId(string $name): string
    {
        $path = config("ai.environments.{$name}")
            ?? throw new AgentConfigurationException("Unknown environment [{$name}] in config/ai.php.");

        return $this->lockedId((string) $path);
    }

    public function vaultId(): string
    {
        return $this->lockedId((string) config('ai.vault'));
    }

    private function parse(string $file): AgentDefinition
    {
        $raw = File::get($file);

        if (! preg_match('/\A---\R(.*?)\R---\R?(.*)\z/s', $raw, $matches)) {
            throw new AgentConfigurationException("{$file} has no YAML frontmatter.");
        }

        /** @var array<string, mixed> $front */
        $front = Yaml::parse($matches[1]) ?? [];
        $model = $front['model'] ?? null;
        $metadata = is_array($front['metadata'] ?? null) ? $front['metadata'] : [];
        $configs = Arr::get($front, 'tools.0.configs', []);
        $usesWeb = collect(is_array($configs) ? $configs : [])
            ->contains(fn ($config) => is_array($config)
                && in_array($config['name'] ?? null, ['web_search', 'web_fetch'], true)
                && ($config['enabled'] ?? true) === true);

        return new AgentDefinition(
            key: basename(dirname($file)),
            name: (string) ($front['name'] ?? basename(dirname($file))),
            description: (string) ($front['description'] ?? ''),
            model: (string) (is_array($model) ? ($model['id'] ?? '') : $model),
            effort: (string) (is_array($model) ? ($model['effort'] ?? 'medium') : 'medium'),
            budgetUsd: (float) ($metadata['budget_usd'] ?? 0),
            environment: (string) ($metadata['environment'] ?? 'sandbox'),
            usesWeb: $usesWeb,
            prompt: trim($matches[2]),
            path: $file,
            frontmatter: $front,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function lock(): array
    {
        if ($this->lock !== null) {
            return $this->lock;
        }

        if (! File::exists($this->lockFile)) {
            throw new AgentConfigurationException('claude-lock.json not found. Run `make ai-apply` to create the agents first.');
        }

        $decoded = json_decode(File::get($this->lockFile), true);

        return $this->lock = is_array($decoded) ? $decoded : [];
    }
}
