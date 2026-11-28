<?php

namespace App\Dtos\Webhooks;

final readonly class WhatsAppWebhookResponseDto
{
    /** @param array<string, bool|string> $body */
    public function __construct(
        public int $status,
        public array $body,
    ) {}
}
