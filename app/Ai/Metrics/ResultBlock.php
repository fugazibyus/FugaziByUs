<?php

namespace App\Ai\Metrics;

/**
 * Every agent ends its last message with a fenced ```json block (see agents/*\/agent.md).
 * This pulls that block out.
 */
final class ResultBlock
{
    /** @return array<string, mixed>|null */
    public static function parse(?string $message): ?array
    {
        if ($message === null || ! preg_match_all('/```json\s*(\{.*?\})\s*```/s', $message, $matches)) {
            return null;
        }

        $data = json_decode((string) end($matches[1]), true);

        return is_array($data) ? $data : null;
    }
}
