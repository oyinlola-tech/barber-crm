<?php

namespace App\Configs;

final class WhatsAppConfig
{
    public function graphApiBaseUrl(): ?string
    {
        $apiVersion = config('whatsapp.api_version');
        if (! is_string($apiVersion) || ! preg_match('/^v[0-9]+\.[0-9]+$/', $apiVersion)) {
            return null;
        }

        return 'https://graph.facebook.com/'.$apiVersion;
    }

    public function graphApiConfigured(): bool
    {
        return $this->graphApiBaseUrl() !== null;
    }

    public function appSecret(): ?string
    {
        return $this->optionalString(config('whatsapp.app_secret'));
    }

    public function verifyToken(): ?string
    {
        return $this->optionalString(config('whatsapp.verify_token'));
    }

    public function webhookMaxBytes(): int
    {
        return max(1024, (int) config('whatsapp.webhook_max_bytes', 1048576));
    }

    public function webhookPayloadRetentionDays(): int
    {
        return min(3650, max(1, (int) config('whatsapp.webhook_payload_retention_days', 30)));
    }

    public function webhookCallbackStaleMinutes(): int
    {
        return min(1440, max(1, (int) config('whatsapp.webhook_callback_stale_minutes', 10)));
    }

    public function webhookDispatchRecoveryMinutes(): int
    {
        return min(1440, max(1, (int) config('whatsapp.webhook_dispatch_recovery_minutes', 5)));
    }

    /** @return list<string> */
    public function optOutKeywords(): array
    {
        $keywords = config('whatsapp.opt_out_keywords', []);
        if (! is_array($keywords)) {
            return [];
        }

        return array_values(array_filter($keywords, 'is_string'));
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
