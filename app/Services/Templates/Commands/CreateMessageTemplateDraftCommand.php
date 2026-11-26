<?php

namespace App\Services\Templates\Commands;

use App\Mappers\Templates\WhatsAppTemplateComponentMapper;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CreateMessageTemplateDraftCommand
{
    public function __construct(
        public WhatsAppTemplateComponentMapper $componentMapper,
        public RecordAuditLogCommand $audit,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function execute(int $businessId, int $userId, array $attributes): MessageTemplate
    {
        return DB::transaction(function () use ($businessId, $userId, $attributes): MessageTemplate {
            if (! User::query()->findOrFail($userId)->hasBusinessPermission($businessId, 'templates.create')) {
                throw new AuthorizationException('You are not allowed to create message templates.');
            }

            $components = $this->componentMapper->toMetaComponents($attributes['body'], $attributes['examples'] ?? []);
            $variables = $this->componentMapper->variablesIn($attributes['body']);
            $template = MessageTemplate::query()->create([
                'business_id' => $businessId,
                'name' => $attributes['name'],
                'language' => $attributes['language'],
                'category' => $attributes['category'],
                'purpose' => $attributes['purpose'],
                'status' => 'draft',
                'provider_status' => null,
                'is_enabled' => false,
                'components' => $components,
                'variable_map' => $variables,
            ]);
            $this->audit->execute($businessId, $userId, 'message_template.draft_created', MessageTemplate::class, $template->getKey(), null, [
                'name' => $template->name,
                'language' => $template->language,
                'purpose' => $template->purpose,
            ]);

            return $template;
        });
    }
}
