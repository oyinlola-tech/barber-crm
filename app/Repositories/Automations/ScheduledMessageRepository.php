<?php

namespace App\Repositories\Automations;

use App\Enums\ScheduledMessageStatus;
use App\Models\ScheduledMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ScheduledMessageRepository
{
    /** @return list<int> */
    public function claimDue(int $limit = 100): array
    {
        return DB::transaction(function () use ($limit): array {
            $messages = ScheduledMessage::query()
                ->where(function ($query): void {
                    $query->where(function ($query): void {
                        $query->where('status', ScheduledMessageStatus::Pending->value)
                            ->where('scheduled_at', '<=', now());
                    })->orWhere(function ($query): void {
                        $query->where('status', ScheduledMessageStatus::Queued->value)
                            ->where(function ($query): void {
                                $query->whereNull('claimed_at')
                                    ->orWhere('claimed_at', '<=', now()->subMinutes((int) config('automation.queued_recovery_after_minutes')));
                            });
                    });
                })
                ->orderBy('scheduled_at')
                ->limit(min(max($limit, 1), 500))
                ->lock('FOR UPDATE SKIP LOCKED')
                ->get();

            foreach ($messages as $message) {
                $message->update([
                    'status' => ScheduledMessageStatus::Queued->value,
                    'claimed_at' => now(),
                ]);
            }

            return $messages->modelKeys();
        });
    }

    /** @return Collection<int, ScheduledMessage> */
    public function pendingForBusiness(int $businessId, int $limit = 100): Collection
    {
        return ScheduledMessage::query()->where('business_id', $businessId)
            ->where('status', ScheduledMessageStatus::Pending->value)
            ->orderBy('scheduled_at')->limit(min(max($limit, 1), 500))->get();
    }

    public function findForBusiness(int $businessId, int $scheduledMessageId): ScheduledMessage
    {
        return ScheduledMessage::query()->where('business_id', $businessId)->findOrFail($scheduledMessageId);
    }

    public function lockForBusiness(int $businessId, int $scheduledMessageId): ScheduledMessage
    {
        return ScheduledMessage::query()->where('business_id', $businessId)
            ->lockForUpdate()->findOrFail($scheduledMessageId);
    }

    public function listForBusiness(int $businessId, ?string $status = null): LengthAwarePaginator
    {
        return ScheduledMessage::query()->where('business_id', $businessId)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->with('customer:id,first_name,last_name,phone')
            ->orderByDesc('scheduled_at')->orderByDesc('id')->paginate(50)->withQueryString()
            ->through(static function (ScheduledMessage $message): array {
                $customer = $message->customer;

                return [
                    'id' => $message->getKey(),
                    'status' => $message->status,
                    'scheduled_at' => $message->scheduled_at,
                    'last_error' => $message->last_error,
                    'attempts' => $message->attempts,
                    'manual_retry_count' => (int) (($message->payload ?? [])['manual_retry_count'] ?? 0),
                    'customer' => $customer === null ? null : [
                        'first_name' => $customer->first_name,
                        'last_name' => $customer->last_name,
                        'phone' => $customer->phone,
                    ],
                ];
            });
    }
}
