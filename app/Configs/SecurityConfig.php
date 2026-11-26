<?php

namespace App\Configs;

use InvalidArgumentException;

final class SecurityConfig
{
    /** @return array<string, list<string>> */
    public function contentSecurityPolicy(): array
    {
        $policy = config('security.content_security_policy', []);
        if (! is_array($policy)) {
            throw new InvalidArgumentException('The content security policy configuration must be a directive map.');
        }

        $validated = [];
        foreach ($policy as $directive => $sources) {
            if (! is_string($directive) || ! preg_match('/^[a-z-]+$/', $directive) || ! is_array($sources)) {
                throw new InvalidArgumentException('The content security policy configuration contains an invalid directive.');
            }

            foreach ($sources as $source) {
                if (! is_string($source) || preg_match('/[;\r\n]/', $source) === 1) {
                    throw new InvalidArgumentException('The content security policy configuration contains an invalid source.');
                }
            }

            $validated[$directive] = array_values($sources);
        }

        return $validated;
    }

    public function permissionsPolicy(): string
    {
        $policy = config('security.permissions_policy', 'camera=(), microphone=(), geolocation=()');
        if (! is_string($policy) || preg_match('/[\r\n]/', $policy) === 1) {
            throw new InvalidArgumentException('The permissions policy configuration is invalid.');
        }

        return $policy;
    }

    public function hstsHeader(): ?string
    {
        $maxAge = max(0, min((int) config('security.hsts_max_age', 31536000), 63072000));
        if ($maxAge === 0) {
            return null;
        }

        $header = 'max-age='.$maxAge;
        if ((bool) config('security.hsts_include_subdomains', true)) {
            $header .= '; includeSubDomains';
        }

        return $header;
    }
}
