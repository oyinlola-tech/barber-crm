<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('automations:dispatch-due-messages')
    ->everyMinute()
    ->withoutOverlapping(2);

Schedule::command('automations:recover-stalled-messages')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

Schedule::command('automations:recover-sent-follow-ups')
    ->everyMinute()
    ->withoutOverlapping(2);

Schedule::command('automations:schedule-inactive-customers')
    ->hourly()
    ->withoutOverlapping(55);

Schedule::command('operations:record-heartbeat')
    ->everyMinute()
    ->withoutOverlapping(2);

Schedule::command('webhooks:redact-expired-payloads')
    ->dailyAt('02:30')
    ->withoutOverlapping(60);

Schedule::command('webhooks:reconcile-delivery-statuses')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->runInBackground();

Schedule::command('webhooks:requeue-stalled')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->runInBackground();
