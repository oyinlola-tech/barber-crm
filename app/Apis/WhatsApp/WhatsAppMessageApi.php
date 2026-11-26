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
