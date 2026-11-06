<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $issue
 * @property string $slack_user
 */
#[Fillable(['issue', 'slack_user'])]
class SlackReporter extends Model {}
