<?php

namespace App\Services\Automations\Commands;

use App\Models\MessageTemplate;
use App\Models\ScheduledMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CancelCustomerFollowUpsCommand
{
    public function execute(
        int $businessId,
        int $customerId,
        ?int $exceptRuleId = null,
        ?string $onlyTemplatePurpose = null,
        string $reason = 'Cancelled because a customer activity superseded the follow-up.',
    ): int {
        $templateIds = $onlyTemplatePurpose === null
            ? []
            : MessageTemplate::query()
                ->where('business_id', $businessId)
                ->where('purpose', $onlyTemplatePurpose)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

        return DB::transaction(function () use ($businessId, $customerId, $exceptRuleId, $onlyTemplatePurpose, $templateIds, $reason): int {
            $messages = ScheduledMessage::query()
                ->where('business_id', $businessId)
                ->where('customer_id', $customerId)
                ->whereIn('status', ['pending', 'queued'])
                ->when($exceptRuleId !== null, fn (Builder $query) => $query->where('automation_rule_id', '!=', $exceptRuleId));

            if ($onlyTemplatePurpose !== null) {
                if ($templateIds === []) {
                    return 0;
                }

                $messages->where(function (Builder $query) use ($templateIds): void {
                    foreach ($templateIds as $templateId) {
                        $query->orWhereJsonContains('payload', ['template_id' => $templateId]);
                    }
                });
            }

            return $messages->update([
                'status' => 'cancelled',
                'last_error' => $reason,
                'claimed_at' => null,
                'updated_at' => now(),
            ]);
        });
    }
}
