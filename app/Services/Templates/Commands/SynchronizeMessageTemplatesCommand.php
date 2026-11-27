<?php

namespace App\Services\Templates\Commands;

use App\Apis\WhatsApp\WhatsAppTemplateApi;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\WhatsAppConnection;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SynchronizeMessageTemplatesCommand
{
    public function __construct(
        public WhatsAppTemplateApi $templateApi,
        public RecordAuditLogCommand $audit,
    ) {}

    public function execute(int $businessId, int $userId): int
    {
        if (! User::query()->findOrFail($userId)->hasBusinessPermission($businessId, 'templates.sync')) {
            throw new AuthorizationException('You are not allowed to synchronize WhatsApp templates.');
        }

        $connection = WhatsAppConnection::query()
            ->where('business_id', $businessId)
            ->where('status', 'connected')
            ->first();
        if ($connection === null || $connection->business_account_id === null || $connection->access_token === null) {
            throw new RuntimeException('A connected WhatsApp Business account is required to synchronize templates.');
        }

        $providerTemplates = $this->templateApi->listTemplates($connection);

        return DB::transaction(function () use ($businessId, $userId, $providerTemplates): int {
            $synchronizedCount = 0;
            $providerIds = [];
            foreach ($providerTemplates as $providerTemplate) {
                $providerId = $providerTemplate['id'] ?? null;
                $name = $providerTemplate['name'] ?? null;
                $language = $providerTemplate['language'] ?? null;
                $status = $providerTemplate['status'] ?? null;

                if (! is_string($providerId) || ! is_string($name) || ! is_string($language) || ! is_string($status)) {
                    continue;
                }

                $providerIds[] = $providerId;
                $template = MessageTemplate::query()
                    ->where('business_id', $businessId)
                    ->where('provider_template_id', $providerId)
                    ->lockForUpdate()
                    ->first()
                    ?? MessageTemplate::query()
                        ->where('business_id', $businessId)
                        ->whereIn('status', ['submitted', 'submission_uncertain', 'synchronized', 'unavailable'])
                        ->where('name', $name)
                        ->where('language', $language)
                        ->lockForUpdate()
                        ->first();

                $existingStatus = $template?->status;
                $providerIdentityChanged = $template !== null
                    && $template->provider_template_id !== null
                    && $template->provider_template_id !== $providerId;
                $attributes = [
                    'provider_template_id' => $providerId,
                    'provider_status' => strtoupper($status),
                    'status' => in_array($existingStatus, ['submission_uncertain', 'unavailable'], true)
                        ? 'synchronized'
                        : ($existingStatus ?? 'synchronized'),
                    'category' => is_string($providerTemplate['category'] ?? null) ? strtolower($providerTemplate['category']) : $template?->category,
                    'components' => is_array($providerTemplate['components'] ?? null) ? $providerTemplate['components'] : [],
                    'is_enabled' => strtoupper($status) === 'APPROVED'
                        && ! $providerIdentityChanged
                            ? (bool) $template?->is_enabled
                            : false,
                ];

                if ($template === null) {
                    MessageTemplate::query()->create([
                        ...$attributes,
                        'business_id' => $businessId,
                        'name' => $name,
                        'language' => $language,
                        'purpose' => null,
                        'variable_map' => [],
                    ]);
                } else {
                    $template->update($attributes);
                }

                $synchronizedCount++;
            }

            $missingTemplates = MessageTemplate::query()
                ->where('business_id', $businessId)
                ->whereNotNull('provider_template_id')
                ->whereIn('status', ['synchronized', 'unavailable'])
                ->when($providerIds !== [], fn ($query) => $query->whereNotIn('provider_template_id', array_unique($providerIds)))
                ->lockForUpdate()
                ->get();

            foreach ($missingTemplates as $missingTemplate) {
                $missingTemplate->update([
                    'status' => 'unavailable',
                    'provider_status' => 'DELETED',
                    'is_enabled' => false,
                ]);
            }

            $this->audit->execute($businessId, $userId, 'message_templates.synchronized', MessageTemplate::class, null, null, [
                'count' => $synchronizedCount,
                'unavailable' => $missingTemplates->count(),
            ]);

            return $synchronizedCount;
        });
    }
}
