<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Slack\GitHubAlerts;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives repository events from GitHub (issues, pull requests, comments) and turns them
 * into Slack alerts. Every request must be signed with GITHUB_WEBHOOK_SECRET.
 */
class GitHubWebhookController extends Controller
{
    public function __invoke(Request $request, GitHubAlerts $alerts): Response
    {
        $secret = (string) config('services.github.webhook_secret');

        if ($secret === '') {
            return response('Webhooks are not configured.', 503);
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, (string) $request->header('X-Hub-Signature-256'))) {
            return response('Invalid signature.', 401);
        }

        $payload = json_decode($request->getContent(), true);

        if (is_array($payload)) {
            $alerts->handle((string) $request->header('X-GitHub-Event'), $payload);
        }

        return response()->noContent();
    }
}
