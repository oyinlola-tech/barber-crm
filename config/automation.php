<?php

return [
    'enabled' => (bool) env('AUTOMATIONS_ENABLED', false),
    'max_attempts' => (int) env('AUTOMATION_MAX_ATTEMPTS', 5),
    'manual_retry_limit' => (int) env('AUTOMATION_MANUAL_RETRY_LIMIT', 2),
    'stalled_after_minutes' => (int) env('AUTOMATION_STALLED_AFTER_MINUTES', 10),
    'queued_recovery_after_minutes' => (int) env('AUTOMATION_QUEUED_RECOVERY_AFTER_MINUTES', 5),
    'minimum_customer_message_interval_hours' => (int) env('AUTOMATION_MINIMUM_CUSTOMER_MESSAGE_INTERVAL_HOURS', 72),
    'retry_backoff_seconds' => [60, 300, 900, 3600],
    'default_sending_hours' => ['start' => '09:00', 'end' => '18:00'],
    'allowed_template_variables' => ['customer_name', 'business_name', 'last_visit_date', 'business_phone', 'review_link'],
    'return_reminder_template_purposes' => ['return_intention', 'retention', 'reengagement'],
];
