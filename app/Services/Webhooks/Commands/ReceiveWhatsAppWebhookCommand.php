<?php

namespace App\Services\Webhooks\Commands;

use App\Configs\WhatsAppConfig;
use App\Dtos\Webhooks\WhatsAppWebhookResponseDto;
use App\Interfaces\Repositories\Webhooks\WebhookEventRepositoryInterface;
use App\Interfaces\Repositories\WhatsApp\WhatsAppConnectionRepositoryInterface;
use App\Jobs\WhatsApp\ProcessWhatsAppWebhookJob;
use JsonException;

class ReceiveWhatsAppWebhookCommand
{
    public function __construct(
        private readonly WhatsAppConfig $configuration,
        private readonly WhatsAppConnectionRepositoryInterface $connections,
        private readonly WebhookEventRepositoryInterface $events,
    ) {}

    public function execute(string $body, ?string $signature): WhatsAppWebhookResponseDto
    {
        if (strlen($body) > $this->configuration->webhookMaxBytes()) {
            return $this->error(413, 'Webhook payload is too large.');
        }

        $secret = $this->configuration->appSecret();
        $expected = is_string($secret) && $secret !== ''
            ? 'sha256='.hash_hmac('sha256', $body, $secret)
            : null;
        if (! is_string($signature) || $expected === null || ! hash_equals($expected, $signature)) {
            return $this->error(401, 'Webhook signature is invalid.');
        }

        try {
            $payload = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->error(400, 'Webhook payload is malformed.');
        }

        if (! is_array($payload)
            || ! is_array($payload['entry'] ?? null)
            || (isset($payload['object']) && $payload['object'] !== 'whatsapp_business_account')) {
            return $this->error(422, 'Webhook payload is invalid.');
        }

        $pending = [];
        foreach ($payload['entry'] as $entry) {
            if (! is_array($entry) || ! is_array($entry['changes'] ?? null)) {
                return $this->error(422, 'Webhook entry is invalid.');
            }

            foreach ($entry['changes'] as $change) {
                if (! is_array($change) || ! is_array($change['value'] ?? null)) {
                    return $this->error(422, 'Webhook change is invalid.');
                }

                $value = $change['value'];
                $phoneNumberId = data_get($value, 'metadata.phone_number_id');
                if (! is_string($phoneNumberId) || $phoneNumberId === '') {
                    return $this->error(422, 'Webhook phone number metadata is missing.');
                }

                if ((array_key_exists('messages', $value) && ! is_array($value['messages']))
                    || (array_key_exists('statuses', $value) && ! is_array($value['statuses']))) {
                    return $this->error(422, 'Webhook message and delivery event collections must be arrays.');
                }

                $connection = $this->connections->findByPhoneNumberId($phoneNumberId);
                $accountId = $entry['id'] ?? null;
                $connectionMatches = $connection !== null
                    && is_string($accountId)
                    && is_string($connection->business_account_id)
                    && hash_equals($connection->business_account_id, $accountId);

                foreach ($value['messages'] ?? [] as $message) {
                    if (! is_array($message) || ! is_string($message['id'] ?? null) || $message['id'] === '') {
                        return $this->error(422, 'Webhook message event is invalid.');
                    }

                    $messageBody = data_get($message, 'text.body')
                        ?? data_get($message, 'button.text')
                        ?? data_get($message, 'interactive.button_reply.title')
                        ?? data_get($message, 'interactive.list_reply.title');
                    if (! is_string($messageBody) || mb_strlen($messageBody) > 4096) {
                        $messageBody = null;
                    }

                    $pending[] = [[
                        'kind' => 'message',
                        'phone_number_id' => $phoneNumberId,
                        'provider_message_id' => $message['id'],
                        'from' => is_string($message['from'] ?? null) ? $message['from'] : null,
                        'type' => is_string($message['type'] ?? null) ? $message['type'] : 'unknown',
                        'body' => $messageBody,
                        'interactive_reply_id' => is_string(data_get($message, 'interactive.button_reply.id'))
                            ? data_get($message, 'interactive.button_reply.id')
                            : (is_string(data_get($message, 'interactive.list_reply.id')) ? data_get($message, 'interactive.list_reply.id') : null),
                        'interactive_reply_title' => is_string(data_get($message, 'interactive.button_reply.title'))
                            ? data_get($message, 'interactive.button_reply.title')
                            : (is_string(data_get($message, 'interactive.list_reply.title')) ? data_get($message, 'interactive.list_reply.title') : null),
                        'timestamp' => is_string($message['timestamp'] ?? null) && ctype_digit($message['timestamp']) ? (int) $message['timestamp'] : null,
                    ], 'message:'.$message['id'], $connectionMatches ? $connection : null];
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    if (! is_array($status)
                        || ! is_string($status['id'] ?? null)
                        || $status['id'] === ''
                        || ! is_string($status['status'] ?? null)
                        || $status['status'] === ''
                        || mb_strlen($status['status']) > 64) {
                        return $this->error(422, 'Webhook delivery event is invalid.');
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
                    return $this->error(413, 'Webhook contains too many events.');
                }
            }
        }

        foreach ($pending as [$eventPayload, $eventId, $connection]) {
            $storedPayload = $connection === null
                ? [
                    'kind' => $eventPayload['kind'],
                    'phone_number_id' => $eventPayload['phone_number_id'],
                    'unmatched' => true,
                ]
                : $eventPayload;
            $event = $this->events->storeIfAbsent(
                $connection?->business_id === null ? null : (int) $connection->business_id,
                $eventId,
                $eventPayload['kind'],
                $storedPayload,
                $connection === null ? 'unmatched_connection' : 'received',
            );

            if ($event !== null && $connection !== null) {
                ProcessWhatsAppWebhookJob::dispatch((int) $event->getKey())->afterCommit();
            }
        }

        return new WhatsAppWebhookResponseDto(200, ['accepted' => true]);
    }

    private function error(int $status, string $message): WhatsAppWebhookResponseDto
    {
        return new WhatsAppWebhookResponseDto($status, ['message' => $message]);
    }
}
