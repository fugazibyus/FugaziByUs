<?php

namespace App\Ai\Exceptions;

use RuntimeException;

/** AI_MAX_CONCURRENT_SESSIONS agents are already working – try again when one finishes. */
class TooManySessionsException extends RuntimeException {}
