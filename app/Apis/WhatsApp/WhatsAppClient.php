<?php

namespace App\Apis\WhatsApp;

use App\Configs\WhatsAppConfig;
use App\Models\WhatsAppConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppClient
{
    public function __construct(public WhatsAppConfig $configuration) {}

    private function request(WhatsAppConnection $connection): PendingRequest
    {
        $baseUrl = $this->configuration->graphApiBaseUrl();
        if (! is_string($connection->access_token) || $connection->access_token === '' || ! is_string($connection->phone_number_id) || $connection->phone_number_id === '' || $baseUrl === null) {
            throw new RuntimeException('This business WhatsApp Cloud API connection is incomplete.');
        }

        return Http::baseUrl($baseUrl)
            ->withToken($connection->access_token)
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
