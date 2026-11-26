<?php

namespace App\Services\Analytics\Queries;

use App\Models\Customer;
use App\Models\Message;
use App\Models\Review;
use App\Models\ScheduledMessage;
use App\Models\Visit;
use Carbon\CarbonImmutable;

class GetAnalyticsReportQuery
{
    /**
     * @return array{customers_registered:int,visits_recorded:int,returning_customers:int,messages_sent:int,messages_delivered:int,messages_failed:int,follow_ups_scheduled:int,reviews_received:int,average_rating:float|null}
     */
    public function execute(int $businessId, string $startDate, string $endDate, string $timezone): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $startDate, $timezone)->utc();
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $endDate, $timezone)->endOfDay()->utc();

        $visitsInPeriod = Visit::query()
            ->where('business_id', $businessId)
            ->whereBetween('visited_at', [$start, $end]);
        $returningCustomers = (clone $visitsInPeriod)
            ->select('customer_id')
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) > 1');
        $messages = Message::query()->where('business_id', $businessId)->where('direction', 'outbound');
        $ratings = Review::query()->where('business_id', $businessId)->whereNotNull('rating')
            ->whereBetween('reviewed_at', [$start, $end]);

        return [
            'customers_registered' => Customer::query()
                ->where('business_id', $businessId)
                ->whereBetween('created_at', [$start, $end])
                ->count(),
            'visits_recorded' => (clone $visitsInPeriod)->count(),
            'returning_customers' => (clone $returningCustomers)->count(),
            'messages_sent' => (clone $messages)->whereNotNull('sent_at')
                ->whereBetween('sent_at', [$start, $end])->count(),
            'messages_delivered' => (clone $messages)->whereNotNull('delivered_at')
                ->whereBetween('delivered_at', [$start, $end])->count(),
            'messages_failed' => (clone $messages)->whereNotNull('failed_at')
                ->whereBetween('failed_at', [$start, $end])->count(),
            'follow_ups_scheduled' => ScheduledMessage::query()
                ->where('business_id', $businessId)
                ->whereBetween('scheduled_at', [$start, $end])
                ->count(),
            'reviews_received' => (clone $ratings)->count(),
            'average_rating' => (clone $ratings)->avg('rating') === null
                ? null
                : round((float) (clone $ratings)->avg('rating'), 1),
        ];
    }
}
