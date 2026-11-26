<?php

namespace App\Services\Templates\Commands;

use App\Mappers\Templates\WhatsAppTemplateComponentMapper;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UpdateMessageTemplateDraftCommand
{
    public function __construct(
        public WhatsAppTemplateComponentMapper $componentMapper,
        public RecordAuditLogCommand $audit,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function execute(int $businessId, int $templateId, int $userId, array $attributes): MessageTemplate
    {
        return DB::transaction(function () use ($businessId, $templateId, $userId, $attributes): MessageTemplate {
            if (! User::query()->findOrFail($userId)->hasBusinessPermission($businessId, 'templates.update')) {
                throw new AuthorizationException('You are not allowed to update message templates.');
            }

            $template = MessageTemplate::query()->where('business_id', $businessId)->lockForUpdate()->findOrFail($templateId);
            if ($template->status !== 'draft' || $template->provider_template_id !== null) {
                throw new RuntimeException('Only a local template draft can be edited.');
            }

            $before = ['name' => $template->name, 'language' => $template->language, 'purpose' => $template->purpose];
            $variables = $this->componentMapper->variablesIn($attributes['body']);
            $template->update([
                'name' => $attributes['name'],
                'language' => $attributes['language'],
                'category' => $attributes['category'],
                'purpose' => $attributes['purpose'],
                'components' => $this->componentMapper->toMetaComponents($attributes['body'], $attributes['examples'] ?? []),
                'variable_map' => $variables,
            ]);
            $this->audit->execute($businessId, $userId, 'message_template.draft_updated', MessageTemplate::class, $template->getKey(), $before, [
                'name' => $template->name,
                'language' => $template->language,
                'purpose' => $template->purpose,
            ]);

            return $template->refresh();
        });
    }
}
