<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $issue
 * @property string $channel
 * @property string $ts
 */
#[Fillable(['issue', 'channel', 'ts'])]
class SlackThread extends Model {}
