<?php

namespace App\Validators\Notifications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWebPushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'url', 'max:2048'],
            'keys' => ['required', 'array:p256dh,auth'],
            'keys.p256dh' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{40,100}$/'],
            'keys.auth' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,64}$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['endpoint.push_service' => 'This browser push service is not supported.'];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $endpoint = $this->input('endpoint');
            if (! is_string($endpoint)) {
                return;
            }

            $parts = parse_url($endpoint);
            $host = is_array($parts) && is_string($parts['host'] ?? null)
                ? strtolower($parts['host'])
                : '';
            $scheme = is_array($parts) ? ($parts['scheme'] ?? null) : null;
            $port = is_array($parts) ? ($parts['port'] ?? 443) : null;
            $isMozilla = $host === 'updates.push.services.mozilla.com'
                || str_ends_with($host, '.push.services.mozilla.com');
            $isWindows = str_ends_with($host, '.notify.windows.com');
            $isSupported = in_array($host, ['fcm.googleapis.com', 'web.push.apple.com'], true)
                || $isMozilla
                || $isWindows;

            if ($scheme !== 'https' || $port !== 443 || isset($parts['user']) || isset($parts['pass']) || ! $isSupported) {
                $validator->errors()->add('endpoint', 'This browser push service is not supported.');
            }
        }];
    }
}
