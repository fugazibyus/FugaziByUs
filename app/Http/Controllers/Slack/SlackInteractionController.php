<?php

namespace App\Http\Controllers\Slack;

use App\Http\Controllers\Controller;
use App\Jobs\CreateIssueFromSlack;
use App\Slack\SlackClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The "Send to Fugazi AI" message shortcut: opens a small form pre-filled with the message,
 * then creates the GitHub issue when it's submitted.
 */
class SlackInteractionController extends Controller
{
    public function __invoke(Request $request, SlackClient $slack): JsonResponse|Response
    {
        $payload = json_decode((string) $request->input('payload'), true);

        if (! is_array($payload)) {
            return response()->noContent(400);
        }

        return match ([$payload['type'] ?? null, $payload['callback_id'] ?? $payload['view']['callback_id'] ?? null]) {
            ['message_action', 'send_to_fugazi'] => $this->openForm($slack, $payload),
            ['view_submission', 'fugazi_issue'] => $this->submit($payload),
            default => response()->noContent(),
        };
    }

    /** @param  array<string, mixed>  $payload */
    private function openForm(SlackClient $slack, array $payload): Response
    {
        $message = mb_substr((string) ($payload['message']['text'] ?? ''), 0, 2900);

        $slack->openView((string) $payload['trigger_id'], [
            'type' => 'modal',
            'callback_id' => 'fugazi_issue',
            'private_metadata' => json_encode(['channel' => $payload['channel']['id'] ?? null, 'ts' => $payload['message']['ts'] ?? null]),
            'title' => ['type' => 'plain_text', 'text' => 'Send to Fugazi AI'],
            'submit' => ['type' => 'plain_text', 'text' => 'Create issue'],
            'blocks' => [
                [
                    'type' => 'input', 'block_id' => 'kind',
                    'label' => ['type' => 'plain_text', 'text' => 'What is it?'],
                    'element' => [
                        'type' => 'static_select', 'action_id' => 'value',
                        'initial_option' => self::option('bug', '🐞 A bug'),
                        'options' => [self::option('bug', '🐞 A bug'), self::option('feature', '✨ A feature idea'), self::option('research', '🔎 A research question')],
                    ],
                ],
                [
                    'type' => 'input', 'block_id' => 'details',
                    'label' => ['type' => 'plain_text', 'text' => 'Details'],
                    'element' => ['type' => 'plain_text_input', 'action_id' => 'value', 'multiline' => true, 'initial_value' => $message, 'min_length' => 10],
                ],
            ],
        ]);

        return response()->noContent(200);
    }

    /** @param  array<string, mixed>  $payload */
    private function submit(array $payload): JsonResponse
    {
        $values = $payload['view']['state']['values'] ?? [];
        $kind = (string) ($values['kind']['value']['selected_option']['value'] ?? 'bug');
        $details = trim((string) ($values['details']['value']['value'] ?? ''));
        $meta = json_decode((string) ($payload['view']['private_metadata'] ?? '{}'), true) ?: [];

        if (! in_array($kind, ['bug', 'feature', 'research'], true) || mb_strlen($details) < 10) {
            return response()->json(['response_action' => 'errors', 'errors' => ['details' => 'Please describe it in a sentence or two.']]);
        }

        CreateIssueFromSlack::dispatch(
            $kind, $details,
            (string) ($payload['user']['id'] ?? ''), (string) ($payload['user']['name'] ?? $payload['user']['username'] ?? ''),
            null, $meta['channel'] ?? null, $meta['ts'] ?? null,
        );

        return response()->json(['response_action' => 'clear']);
    }

    /** @return array<string, mixed> */
    private static function option(string $value, string $label): array
    {
        return ['value' => $value, 'text' => ['type' => 'plain_text', 'text' => $label]];
    }
}
