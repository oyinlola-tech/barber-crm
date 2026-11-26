<?php

return [
    'app_secret' => env('WHATSAPP_APP_SECRET'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'api_version' => env('WHATSAPP_API_VERSION'),
    'webhook_max_bytes' => (int) env('WHATSAPP_WEBHOOK_MAX_BYTES', 1048576),
    'webhook_payload_retention_days' => (int) env('WHATSAPP_WEBHOOK_PAYLOAD_RETENTION_DAYS', 30),
    'opt_out_keywords' => ['STOP', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'],
];
