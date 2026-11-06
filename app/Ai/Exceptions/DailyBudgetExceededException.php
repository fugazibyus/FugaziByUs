<?php

namespace App\Ai\Exceptions;

use RuntimeException;

/** Today's AI spend has reached AI_DAILY_BUDGET_USD – no new sessions until tomorrow (UTC). */
class DailyBudgetExceededException extends RuntimeException {}
