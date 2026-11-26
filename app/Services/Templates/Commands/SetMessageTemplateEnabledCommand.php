<?php

namespace App\Services\Templates\Commands;

use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SetMessageTemplateEnabledCommand
{
    public function __construct(public RecordAuditLogCommand $audit) {}

    public function execute(int $businessId, int $templateId, int $userId, bool $enabled): MessageTemplate
    {
        return DB::transaction(function () use ($businessId, $templateId, $userId, $enabled): MessageTemplate {
            if (! User::query()->findOrFail($userId)->hasBusinessPermission($businessId, 'templates.update')) {
                throw new AuthorizationException('You are not allowed to update message templates.');
            }

            $template = MessageTemplate::query()->where('business_id', $businessId)->lockForUpdate()->findOrFail($templateId);
            if ($enabled && (strtoupper((string) $template->provider_status) !== 'APPROVED' || $template->provider_template_id === null)) {
                throw new RuntimeException('Only a Meta-approved template can be enabled for sending.');
            }

            $before = ['is_enabled' => $template->is_enabled];
            $template->update(['is_enabled' => $enabled]);
            $this->audit->execute($businessId, $userId, $enabled ? 'message_template.enabled' : 'message_template.disabled', MessageTemplate::class, $template->getKey(), $before, ['is_enabled' => $enabled]);

            return $template->refresh();
        });
    }
}
