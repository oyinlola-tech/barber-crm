<?php

namespace App\Services\WhatsApp\Commands;

use App\Models\User;
use App\Models\WhatsAppConnection;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;

class DisconnectWhatsAppCommand
{
    public function __construct(public RecordAuditLogCommand $audit) {}

    public function execute(int $businessId, int $userId): void
    {
        $actor = User::query()->findOrFail($userId);
        abort_unless($actor->hasBusinessPermission($businessId, 'whatsapp.disconnect'), 403);

        DB::transaction(function () use ($businessId, $userId): void {
            $connection = WhatsAppConnection::query()->where('business_id', $businessId)->lockForUpdate()->first();
            if ($connection !== null) {
                $connection->update(['status' => 'disconnected', 'access_token' => null, 'connected_at' => null]);
                $this->audit->execute($businessId, $userId, 'whatsapp.disconnected', WhatsAppConnection::class, $connection->getKey(), null, ['phone_number_id' => $connection->phone_number_id, 'status' => 'disconnected']);
            }
        });
    }
}
