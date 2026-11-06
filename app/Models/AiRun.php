<?php

namespace App\Models;

use Database\Factories\AiRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One agent session (Claude Managed Agents), with its tokens, cost and outcome.
 *
 * @property int $id
 * @property string $session_id
 * @property string $agent
 * @property int|null $agent_version
 * @property string|null $title
 * @property string $status
 * @property string|null $outcome
 * @property bool|null $succeeded
 * @property string|null $summary
 * @property int|null $issue
 * @property int|null $pull_request
 * @property string|null $branch
 * @property string|null $trigger
 * @property string|null $actor
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $cache_read_tokens
 * @property int $cache_creation_tokens
 * @property int $cost_cents
 * @property int $active_seconds
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property bool $demo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'session_id', 'agent', 'agent_version', 'title', 'status', 'outcome', 'succeeded', 'summary',
    'issue', 'pull_request', 'branch', 'trigger', 'actor',
    'input_tokens', 'output_tokens', 'cache_read_tokens', 'cache_creation_tokens', 'cost_cents', 'active_seconds',
    'started_at', 'finished_at', 'demo',
])]
class AiRun extends Model
{
    /** @use HasFactory<AiRunFactory> */
    use HasFactory;

    /** Outcomes (from the agents' JSON results) that mean the run didn't get its job done. */
    public const UNSUCCESSFUL_OUTCOMES = ['needs_human', 'failed', 'blocked'];

    protected function casts(): array
    {
        return [
            'succeeded' => 'boolean',
            'demo' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
