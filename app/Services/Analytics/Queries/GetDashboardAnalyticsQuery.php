<?php

namespace App\Services\Analytics\Queries;

use App\Models\Customer;
use App\Models\CustomerConsent;
use App\Models\Message;
use App\Models\Review;
use App\Models\ScheduledMessage;
use App\Models\Visit;
use App\Models\WhatsAppConnection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetDashboardAnalyticsQuery
{
    /** @return array{customer_count:int,consented_customer_count:int,opted_out_customer_count:int,visit_count:int,returning_customer_count:int,pending_follow_up_count:int,sent_message_count:int,delivered_message_count:int,failed_message_count:int,review_count:int,average_rating:float|null,whatsapp_connected:bool,recent_customers:Collection} */
    public function execute(int $businessId): array
    {
        $customerQuery = Customer::query()->where('business_id', $businessId)->whereNull('archived_at');
        $latestConsentIds = CustomerConsent::query()
            ->where('business_id', $businessId)
            ->where('channel', 'whatsapp')
            ->selectRaw('MAX(id)')
            ->groupBy('customer_id');
        $latestConsents = CustomerConsent::query()->whereIn('id', $latestConsentIds);
        $activeConsentCustomerIds = (clone $latestConsents)
            ->where('granted', true)
            ->whereNull('revoked_at')
            ->select('customer_id');

        $ratings = Review::query()->where('business_id', $businessId)->whereNotNull('rating');

        return [
            'customer_count' => (clone $customerQuery)->count(),
            'consented_customer_count' => (clone $customerQuery)->whereIn('id', $activeConsentCustomerIds)->count(),
            'opted_out_customer_count' => (clone $latestConsents)
                ->where('granted', false)
                ->distinct('customer_id')
                ->count('customer_id'),
            'visit_count' => Visit::query()->where('business_id', $businessId)->count(),
            'returning_customer_count' => DB::query()->fromSub(
                Visit::query()->where('business_id', $businessId)
                    ->select('customer_id')->groupBy('customer_id')->havingRaw('COUNT(*) > 1'),
                'returning_customers',
            )->count(),
            'pending_follow_up_count' => ScheduledMessage::query()->where('business_id', $businessId)->whereIn('status', ['pending', 'queued'])->count(),
            'sent_message_count' => Message::query()->where('business_id', $businessId)->whereIn('status', ['sent', 'delivered', 'read'])->count(),
            'delivered_message_count' => Message::query()->where('business_id', $businessId)->whereIn('status', ['delivered', 'read'])->count(),
            'failed_message_count' => Message::query()->where('business_id', $businessId)->where('status', 'failed')->count(),
            'review_count' => (clone $ratings)->count(),
            'average_rating' => (clone $ratings)->avg('rating') === null ? null : round((float) (clone $ratings)->avg('rating'), 1),
            'whatsapp_connected' => WhatsAppConnection::query()->where('business_id', $businessId)->where('status', 'connected')->exists(),
            'recent_customers' => (clone $customerQuery)->latest()->limit(5)->get(['id', 'first_name', 'last_name', 'phone', 'created_at']),
        ];
    }
}
