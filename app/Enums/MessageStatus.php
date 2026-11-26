<?php

namespace App\Enums;

enum MessageStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Received = 'received';
    case Accepted = 'accepted';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';
    case DeliveryUncertain = 'delivery_uncertain';
    case Cancelled = 'cancelled';
    case Skipped = 'skipped';
}
