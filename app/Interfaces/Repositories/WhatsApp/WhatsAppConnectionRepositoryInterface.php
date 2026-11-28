<?php

namespace App\Interfaces\Repositories\WhatsApp;

use App\Models\WhatsAppConnection;

interface WhatsAppConnectionRepositoryInterface
{
    public function findByPhoneNumberId(string $phoneNumberId): ?WhatsAppConnection;
}
