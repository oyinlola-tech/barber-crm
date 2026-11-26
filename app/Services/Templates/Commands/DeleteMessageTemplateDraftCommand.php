<?php

namespace App\Services\Templates\Commands;

use App\Models\AutomationRule;
use App\Models\MessageTemplate;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteMessageTemplateDraftCommand
{
    public function __construct(private readonly RecordAuditLogCommand $audit) {}

    public function execute(int $businessId, int $templateId, int $userId): void
    {
        DB::transaction(function () use ($businessId, $templateId, $userId): void {
            if (! User::query()->findOrFail($userId)->hasBusinessPermission($businessId, 'templates.delete')) {
                throw new AuthorizationException('You are not allowed to delete message templates.');
            }

            $template = MessageTemplate::query()
                ->where('business_id', $businessId)
                ->lockForUpdate()
                ->findOrFail($templateId);

            if ($template->status !== 'draft' || $template->provider_template_id !== null) {
                throw ValidationException::withMessages(['template' => 'Only a local draft can be deleted. Provider templates must be disabled instead.']);
            }

            $isReferencedByAutomation = AutomationRule::query()
                ->where('business_id', $businessId)
                ->whereJsonContains('settings', ['template_id' => $templateId])
                ->exists();
            $isReferencedByScheduledMessage = ScheduledMessage::query()
                ->where('business_id', $businessId)
                ->whereJsonContains('payload', ['template_id' => $templateId])
                ->exists();

            if ($isReferencedByAutomation || $isReferencedByScheduledMessage) {
                throw ValidationException::withMessages(['template' => 'This draft is referenced by an automation or scheduled message and cannot be deleted.']);
            }

            $before = [
                'name' => $template->name,
                'language' => $template->language,
                'status' => $template->status,
            ];
            $templateId = (int) $template->getKey();
            $template->delete();

            $this->audit->execute(
                $businessId,
                $userId,
                'message_template.draft_deleted',
                MessageTemplate::class,
                $templateId,
                $before,
                ['status' => 'deleted'],
            );
        });
    }
}
