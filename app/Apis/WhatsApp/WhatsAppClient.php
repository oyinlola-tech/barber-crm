<?php

namespace App\Apis\WhatsApp;

use App\Configs\WhatsAppConfig;
use App\Errors\WhatsApp\WhatsAppConnectionUnavailable;
use App\Models\WhatsAppConnection;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class WhatsAppClient
{
    public function __construct(public WhatsAppConfig $configuration) {}

    private function request(WhatsAppConnection $connection): PendingRequest
    {
        $baseUrl = $this->configuration->graphApiBaseUrl();
        try {
            $encryptedAccessToken = $connection->getRawOriginal('access_token');
            if (! is_string($encryptedAccessToken) || $encryptedAccessToken === '') {
                throw new WhatsAppConnectionUnavailable('This business WhatsApp Cloud API connection is incomplete.');
            }

            $accessToken = Crypt::decryptString($encryptedAccessToken);
        } catch (DecryptException $exception) {
            throw new WhatsAppConnectionUnavailable('The saved WhatsApp connection credentials are unavailable.', previous: $exception);
        }

        if ($accessToken === '' || ! is_string($connection->phone_number_id) || $connection->phone_number_id === '' || $baseUrl === null) {
            throw new WhatsAppConnectionUnavailable('This business WhatsApp Cloud API connection is incomplete.');
        }

        return Http::baseUrl($baseUrl)
            ->withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function send(WhatsAppConnection $connection, array $payload): array
    {
        return $this->request($connection)->post('/'.$connection->phone_number_id.'/messages', $payload)->throw()->json();
    }
}
