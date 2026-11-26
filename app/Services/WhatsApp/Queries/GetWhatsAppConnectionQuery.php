<?php

namespace App\Services\WhatsApp\Queries;

use App\Models\WhatsAppConnection;

class GetWhatsAppConnectionQuery
{
    public function execute(int $businessId): ?WhatsAppConnection
    {
        return WhatsAppConnection::query()->where('business_id', $businessId)->first();
    }
}
