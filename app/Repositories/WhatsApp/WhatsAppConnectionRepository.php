<?php

namespace App\Repositories\WhatsApp;

use App\Interfaces\Repositories\WhatsApp\WhatsAppConnectionRepositoryInterface;
use App\Models\WhatsAppConnection;

class WhatsAppConnectionRepository implements WhatsAppConnectionRepositoryInterface
{
    public function findByPhoneNumberId(string $phoneNumberId): ?WhatsAppConnection
    {
        return WhatsAppConnection::query()
            ->where('phone_number_id', $phoneNumberId)
            ->first();
    }
}
