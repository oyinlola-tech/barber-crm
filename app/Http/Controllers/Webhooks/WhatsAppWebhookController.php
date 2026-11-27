<?php

namespace App\Http\Controllers\Webhooks;

use App\Configs\WhatsAppConfig;
use App\Http\Controllers\Controller;
use App\Jobs\WhatsApp\ProcessWhatsAppWebhookJob;
use App\Models\WebhookEvent;
use App\Models\WhatsAppConnection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request, WhatsAppConfig $configuration): Response
    {
        $query = $request->query->all();
        $mode = $query['hub.mode'] ?? $query['hub_mode'] ?? null;
        $token = $query['hub.verify_token'] ?? $query['hub_verify_token'] ?? null;
        $challenge = $query['hub.challenge'] ?? $query['hub_challenge'] ?? null;
        $expected = $configuration->verifyToken();

        if ($mode === 'subscribe' && is_string($expected) && $expected !== '' && is_string($token) && hash_equals($expected, $token) && is_string($challenge)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response()->json(['message' => 'Webhook verification failed.'], 403);
    }

    public function receive(Request $request, WhatsAppConfig $configuration): JsonResponse
    {
        $body = $request->getContent();
        $maxBytes = $configuration->webhookMaxBytes();
        if (strlen($body) > $maxBytes) {
            return response()->json(['message' => 'Webhook payload is too large.'], 413);
        }

        $secret = $configuration->appSecret();
        $signature = $request->header('X-Hub-Signature-256');
        $expected = is_string($secret) && $secret !== ''
            ? 'sha256='.hash_hmac('sha256', $body, $secret)
            : null;

        if (! is_string($signature) || $expected === null || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Webhook signature is invalid.'], 401);
        }

        try {
            $payload = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->json(['message' => 'Webhook payload is malformed.'], 400);
        }

        if (! is_array($payload)
            || ! is_array($payload['entry'] ?? null)
            || (isset($payload['object']) && $payload['object'] !== 'whatsapp_business_account')) {
            return response()->json(['message' => 'Webhook payload is invalid.'], 422);
        }

        $pending = [];
        foreach ($payload['entry'] as $entry) {
            if (! is_array($entry) || ! is_array($entry['changes'] ?? null)) {
                return response()->json(['message' => 'Webhook entry is invalid.'], 422);
            }

            foreach ($entry['changes'] as $change) {
                if (! is_array($change) || ! is_array($change['value'] ?? null)) {
                    return response()->json(['message' => 'Webhook change is invalid.'], 422);
                }

                $value = $change['value'];
                $phoneNumberId = data_get($value, 'metadata.phone_number_id');
                if (! is_string($phoneNumberId) || $phoneNumberId === '') {
                    return response()->json(['message' => 'Webhook phone number metadata is missing.'], 422);
                }

                if ((array_key_exists('messages', $value) && ! is_array($value['messages']))
                    || (array_key_exists('statuses', $value) && ! is_array($value['statuses']))) {
                    return response()->json(['message' => 'Webhook message and delivery event collections must be arrays.'], 422);
                }

                $connection = WhatsAppConnection::query()
                    ->where('phone_number_id', $phoneNumberId)
                    ->first();
                $accountId = $entry['id'] ?? null;
                $connectionMatches = $connection !== null
                    && is_string($accountId)
                    && is_string($connection->business_account_id)
                    && hash_equals($connection->business_account_id, $accountId);

                foreach ($value['messages'] ?? [] as $message) {
                    if (! is_array($message) || ! is_string($message['id'] ?? null) || $message['id'] === '') {
                        return response()->json(['message' => 'Webhook message event is invalid.'], 422);
                    }

                    $messageBody = data_get($message, 'text.body')
                        ?? data_get($message, 'button.text')
                        ?? data_get($message, 'interactive.button_reply.title')
                        ?? data_get($message, 'interactive.list_reply.title');
                    if (! is_string($messageBody) || mb_strlen($messageBody) > 4096) {
                        $messageBody = null;
                    }

                    $normalized = [
                        'kind' => 'message',
                        'phone_number_id' => $phoneNumberId,
                        'provider_message_id' => $message['id'],
                        'from' => is_string($message['from'] ?? null) ? $message['from'] : null,
                        'type' => is_string($message['type'] ?? null) ? $message['type'] : 'unknown',
                        'body' => $messageBody,
                        'timestamp' => is_string($message['timestamp'] ?? null) && ctype_digit($message['timestamp']) ? (int) $message['timestamp'] : null,
                    ];

                    $pending[] = [$normalized, 'message:'.$message['id'], $connectionMatches ? $connection : null];
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    if (! is_array($status)
                        || ! is_string($status['id'] ?? null)
                        || $status['id'] === ''
                        || ! is_string($status['status'] ?? null)
                        || $status['status'] === ''
                        || mb_strlen($status['status']) > 64) {
                        return response()->json(['message' => 'Webhook delivery event is invalid.'], 422);
                    }

                    $normalized = [
                        'kind' => 'status',
                        'phone_number_id' => $phoneNumberId,
                        'provider_message_id' => $status['id'],
                        'status' => $status['status'],
                        'timestamp' => is_string($status['timestamp'] ?? null) && ctype_digit($status['timestamp']) ? (int) $status['timestamp'] : null,
                        'error_code' => is_string(data_get($status, 'errors.0.code')) ? data_get($status, 'errors.0.code') : null,
                    ];
                    $fingerprint = hash('sha256', implode('|', [
                        $phoneNumberId,
                        $normalized['provider_message_id'],
                        $normalized['status'],
                        (string) ($normalized['timestamp'] ?? ''),
                        (string) ($normalized['error_code'] ?? ''),
                    ]));

                    $pending[] = [$normalized, 'status:'.$fingerprint, $connectionMatches ? $connection : null];
                }

                if (count($pending) > 100) {
                    return response()->json(['message' => 'Webhook contains too many events.'], 413);
                }
            }
        }

        foreach ($pending as [$eventPayload, $eventId, $connection]) {
            try {
                $storedPayload = $connection === null
                    ? [
                        'kind' => $eventPayload['kind'],
                        'phone_number_id' => $eventPayload['phone_number_id'],
                        'unmatched' => true,
                    ]
                    : $eventPayload;
                $event = WebhookEvent::query()->create([
                    'business_id' => $connection?->business_id,
                    'provider' => 'whatsapp',
                    'event_id' => $eventId,
                    'event_type' => $eventPayload['kind'],
                    'payload' => $storedPayload,
                    'status' => $connection === null ? 'unmatched_connection' : 'received',
                ]);

                if ($connection !== null) {
                    ProcessWhatsAppWebhookJob::dispatch((int) $event->getKey())->afterCommit();
                }
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        return response()->json(['accepted' => true], 200);
    }
}
