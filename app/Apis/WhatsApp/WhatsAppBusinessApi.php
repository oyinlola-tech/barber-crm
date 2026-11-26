<?php

namespace App\Apis\WhatsApp;

use App\Configs\WhatsAppConfig;
use App\Errors\WhatsApp\ConnectionVerificationFailed;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WhatsAppBusinessApi
{
    public function __construct(public WhatsAppConfig $configuration) {}

    private function request(string $accessToken): PendingRequest
    {
        $baseUrl = $this->configuration->graphApiBaseUrl();
        if ($baseUrl === null) {
            throw new ConnectionVerificationFailed('The WhatsApp Graph API version is not configured.');
        }

        return Http::baseUrl($baseUrl)
            ->withToken($accessToken)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15);
    }

    /** @return array{display_phone_number:string|null,verified_name:string|null,business_name:string|null} */
    public function verifyConnection(string $phoneNumberId, string $businessAccountId, string $accessToken): array
    {
        $phone = $this->request($accessToken)
            ->get('/'.$phoneNumberId, ['fields' => 'id,display_phone_number,verified_name'])
            ->throw()
            ->json();
        $phoneNumbers = $this->request($accessToken)
            ->get('/'.$businessAccountId.'/phone_numbers', ['fields' => 'id', 'limit' => 100])
            ->throw()
            ->json('data');
        $business = $this->request($accessToken)
            ->get('/'.$businessAccountId, ['fields' => 'id'])
            ->throw()
            ->json();

        if (! is_array($phone) || (string) ($phone['id'] ?? '') !== $phoneNumberId
            || ! is_array($phoneNumbers) || ! $this->containsPhoneNumber($phoneNumbers, $phoneNumberId)
            || ! is_array($business) || (string) ($business['id'] ?? '') !== $businessAccountId) {
            throw new ConnectionVerificationFailed('Meta did not verify the supplied WhatsApp identifiers.');
        }

        return [
            'display_phone_number' => is_string($phone['display_phone_number'] ?? null) ? $phone['display_phone_number'] : null,
            'verified_name' => is_string($phone['verified_name'] ?? null) ? $phone['verified_name'] : null,
            'business_name' => null,
        ];
    }

    /** @param array<mixed> $phoneNumbers */
    private function containsPhoneNumber(array $phoneNumbers, string $phoneNumberId): bool
    {
        foreach ($phoneNumbers as $phoneNumber) {
            if (is_array($phoneNumber) && (string) ($phoneNumber['id'] ?? '') === $phoneNumberId) {
                return true;
            }
        }

        return false;
    }
}
