<?php

namespace App\Configs;

class WebPushConfig
{
    public function isConfigured(): bool
    {
        return $this->publicKey() !== null
            && is_string(config('webpush.private_key'))
            && config('webpush.private_key') !== ''
            && is_string(config('webpush.subject'))
            && config('webpush.subject') !== '';
    }

    public function publicKey(): ?string
    {
        $key = config('webpush.public_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function privateKey(): ?string
    {
        $key = config('webpush.private_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function subject(): string
    {
        $subject = config('webpush.subject');

        return is_string($subject) && $subject !== '' ? $subject : 'mailto:admin@example.com';
    }

    public function ttl(): int
    {
        return max(60, min(86400, (int) config('webpush.ttl', 3600)));
    }
}
