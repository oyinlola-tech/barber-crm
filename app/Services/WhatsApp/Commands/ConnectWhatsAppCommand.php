<?php

namespace App\Services\WhatsApp\Commands;

use App\Apis\WhatsApp\WhatsAppBusinessApi;
use App\Dtos\WhatsApp\ConnectWhatsAppDto;
use App\Errors\WhatsApp\ConnectionVerificationFailed;
use App\Models\WhatsAppConnection;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConnectWhatsAppCommand
{
    public function __construct(public RecordAuditLogCommand $audit, public WhatsAppBusinessApi $api) {}

    public function execute(int $businessId, int $userId, ConnectWhatsAppDto $credentials): WhatsAppConnection
    {
        try {
            $providerDetails = $this->api->verifyConnection(
                $credentials->phoneNumberId,
                $credentials->businessAccountId,
                $credentials->accessToken,
            );
        } catch (ConnectionException|RequestException|ConnectionVerificationFailed) {
            throw ValidationException::withMessages([
                'access_token' => 'Meta could not verify these WhatsApp credentials. Check the account IDs, token permissions, and configured Graph API version.',
            ]);
        }

        return DB::transaction(function () use ($businessId, $userId, $credentials, $providerDetails): WhatsAppConnection {
            $connection = WhatsAppConnection::query()->updateOrCreate(
                ['business_id' => $businessId],
                [
                    'phone_number_id' => $credentials->phoneNumberId,
                    'business_account_id' => $credentials->businessAccountId,
                    'access_token' => $credentials->accessToken,
                    'status' => 'connected',
                    'connected_at' => now(),
                    'metadata' => $providerDetails,
                ],
            );
            $this->audit->execute($businessId, $userId, 'whatsapp.connected', WhatsAppConnection::class, $connection->getKey(), null, ['phone_number_id' => $connection->phone_number_id, 'status' => 'connected']);

            return $connection;
        });
    }
}
