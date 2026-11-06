<?php

namespace App\Ai\Exceptions;

use RuntimeException;

/** Agent files, lock file or config are missing or invalid. */
class AgentConfigurationException extends RuntimeException {}
