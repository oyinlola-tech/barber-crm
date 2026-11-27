<?php

namespace App\Interfaces\Apis;

use App\Models\WhatsAppConnection;

interface MessagingProviderInterface
{
    /** @return array<string, mixed> */
    public function sendText(WhatsAppConnection $connection, string $recipient, string $body): array;

    /** @param list<array{id:string,title:string}> $buttons @return array<string, mixed> */
    public function sendInteractiveButtons(WhatsAppConnection $connection, string $recipient, string $body, array $buttons): array;

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function sendTemplate(WhatsAppConnection $connection, string $recipient, string $templateName, string $language, array $payload = []): array;
}
