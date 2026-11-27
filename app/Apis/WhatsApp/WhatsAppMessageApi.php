<?php

namespace App\Apis\WhatsApp;

use App\Interfaces\Apis\MessagingProviderInterface;
use App\Models\WhatsAppConnection;

class WhatsAppMessageApi implements MessagingProviderInterface
{
    public function __construct(public WhatsAppClient $client) {}

    public function sendText(WhatsAppConnection $connection, string $recipient, string $body): array
    {
        return $this->client->send($connection, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $recipient,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $body],
        ]);
    }

    /** @param list<array{id:string,title:string}> $buttons @return array<string, mixed> */
    public function sendInteractiveButtons(WhatsAppConnection $connection, string $recipient, string $body, array $buttons): array
    {
        return $this->client->send($connection, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $recipient,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $body],
                'action' => ['buttons' => array_map(fn (array $button): array => [
                    'type' => 'reply',
                    'reply' => $button,
                ], $buttons)],
            ],
        ]);
    }

    public function sendTemplate(WhatsAppConnection $connection, string $recipient, string $templateName, string $language, array $payload = []): array
    {
        return $this->client->send($connection, [
            'messaging_product' => 'whatsapp',
            'to' => $recipient,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => $payload['components'] ?? [],
            ],
        ]);
    }
}
