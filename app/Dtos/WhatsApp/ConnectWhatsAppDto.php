<?php

namespace App\Dtos\WhatsApp;

final readonly class ConnectWhatsAppDto
{
    public function __construct(
        public string $phoneNumberId,
        public string $businessAccountId,
        public string $accessToken,
    ) {}
}
