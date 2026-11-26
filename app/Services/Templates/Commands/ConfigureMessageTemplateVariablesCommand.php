<?php

namespace App\Services\Templates\Commands;

use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfigureMessageTemplateVariablesCommand
{
    public function __construct(public RecordAuditLogCommand $audit) {}

    /** @param list<string> $variables */
    public function execute(int $businessId, int $templateId, int $userId, array $variables): MessageTemplate
    {
        return DB::transaction(function () use ($businessId, $templateId, $userId, $variables): MessageTemplate {
            if (! User::query()->findOrFail($userId)->hasBusinessPermission($businessId, 'templates.update')) {
                throw new AuthorizationException('You are not allowed to configure message template variables.');
            }

            $template = MessageTemplate::query()->where('business_id', $businessId)->lockForUpdate()->findOrFail($templateId);
            $allowed = config('automation.allowed_template_variables', []);
            if (array_diff($variables, $allowed) !== []) {
                throw ValidationException::withMessages(['variables' => 'One or more template variables are not supported.']);
            }

            $text = '';
            foreach ($template->components ?? [] as $component) {
                if (($component['type'] ?? null) === 'BODY' && is_string($component['text'] ?? null)) {
                    $text = $component['text'];
                    break;
                }
            }
            preg_match_all('/{{\s*(\d+)\s*}}/', $text, $matches);
            $indexes = array_unique(array_map(static fn (string $index): int => (int) $index, $matches[1]));
            sort($indexes);
            if (count($indexes) !== count($variables) || ($indexes !== [] && $indexes !== range(1, count($indexes)))) {
                throw ValidationException::withMessages(['variables' => 'Provide one mapping for each positional placeholder in the approved template.']);
            }

            $before = ['variable_map' => $template->variable_map ?? []];
            $template->update(['variable_map' => $variables]);
            $this->audit->execute($businessId, $userId, 'message_template.variables_updated', MessageTemplate::class, $template->getKey(), $before, ['variable_map' => $variables]);

            return $template->refresh();
        });
    }
}
