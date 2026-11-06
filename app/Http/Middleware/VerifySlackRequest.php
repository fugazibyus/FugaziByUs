<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only Slack can call /slack/*: every request must carry a valid v0 signature made with the
 * app's signing secret, and be less than five minutes old (no replays).
 */
class VerifySlackRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.slack.signing_secret');
        $timestamp = (string) $request->header('X-Slack-Request-Timestamp');
        $signature = (string) $request->header('X-Slack-Signature');

        if ($secret === '') {
            abort(503, 'Slack is not configured.');
        }

        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
            abort(401, 'Stale or missing Slack timestamp.');
        }

        $expected = 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$request->getContent()}", $secret);

        if (! hash_equals($expected, $signature)) {
            abort(401, 'Invalid Slack signature.');
        }

        return $next($request);
    }
}
