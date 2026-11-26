<?php

namespace App\Enums;

enum ScheduledMessageStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case ReconciliationRequired = 'reconciliation_required';
}
