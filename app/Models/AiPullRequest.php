<?php

namespace App\Models;

use Database\Factories\AiPullRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A pull request opened by an agent (an `ai/*` branch), synced from GitHub.
 *
 * @property int $id
 * @property int $number
 * @property string $title
 * @property string $branch
 * @property string $state
 * @property bool $auto_merged
 * @property int|null $issue
 * @property Carbon|null $issue_opened_at
 * @property Carbon $opened_at
 * @property Carbon|null $merged_at
 * @property bool $demo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['number', 'title', 'branch', 'state', 'auto_merged', 'issue', 'issue_opened_at', 'opened_at', 'merged_at', 'demo'])]
class AiPullRequest extends Model
{
    /** @use HasFactory<AiPullRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'auto_merged' => 'boolean',
            'demo' => 'boolean',
            'issue_opened_at' => 'datetime',
            'opened_at' => 'datetime',
            'merged_at' => 'datetime',
        ];
    }
}
