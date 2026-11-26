<?php

namespace App\Services\Automations\Commands;

use App\Models\AutomationRule;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class DeleteAutomationRuleCommand
{
    public function __construct(public RecordAuditLogCommand $audit) {}

    public function execute(int $businessId, int $ruleId, int $userId): void
    {
        DB::transaction(function () use ($businessId, $ruleId, $userId): void {
            if (! User::query()->findOrFail($userId)->hasBusinessPermission($businessId, 'automations.delete')) {
                throw new AuthorizationException('You are not allowed to delete automation rules.');
            }

            $rule = AutomationRule::query()->where('business_id', $businessId)->lockForUpdate()->findOrFail($ruleId);
            ScheduledMessage::query()
                ->where('business_id', $businessId)
                ->where('automation_rule_id', $ruleId)
                ->whereIn('status', ['pending', 'queued', 'processing'])
                ->update([
                    'status' => 'cancelled',
                    'last_error' => 'Cancelled because the automation rule was deleted.',
                    'updated_at' => now(),
                ]);

            $this->audit->execute($businessId, $userId, 'automation_rule.deleted', AutomationRule::class, $ruleId, ['name' => $rule->name], null);
            $rule->delete();
        });
    }
}
