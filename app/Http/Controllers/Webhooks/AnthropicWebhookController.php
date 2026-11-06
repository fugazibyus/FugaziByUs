<?php

namespace App\Http\Controllers\Webhooks;

use Anthropic\Client;
use Anthropic\Core\Exceptions\WebhookException;
use App\Http\Controllers\Controller;
use App\Jobs\SyncAgentRun;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives Claude Managed Agents webhooks (session created, running, idled, terminated…).
 *
 * The signature is checked with ANTHROPIC_WEBHOOK_SIGNING_KEY. The event only names the
 * session, so a queued job fetches the details; the endpoint answers straight away.
 */
class AnthropicWebhookController extends Controller
{
    public function __invoke(Request $request, Client $anthropic): Response
    {
        $secret = (string) config('services.anthropic.webhook_signing_key');

        if ($secret === '') {
            return response('Webhooks are not configured.', 503);
        }

        try {
            $event = $anthropic->beta->webhooks->unwrap($request->getContent(), $request->headers->all(), $secret);
        } catch (WebhookException $e) {
            Log::warning('Rejected an Anthropic webhook', ['reason' => $e->getMessage()]);

            return response('Invalid signature.', 400);
        }

        if (str_starts_with($event->data->type, 'session.')) {
            SyncAgentRun::dispatch($event->data->id);
        }

        return response()->noContent();
    }
}
