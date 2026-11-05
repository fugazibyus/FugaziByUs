<?php

namespace App\Ai;

/** The minimum we need back after starting a session. */
final readonly class SessionHandle
{
    public function __construct(
        public string $id,
        public string $status,
        public string $consoleUrl,
    ) {}
}
