<?php

namespace App\Services\Settings\Commands;

use App\Enums\WhatsAppMessageFeature;
use App\Interfaces\Repositories\Businesses\BusinessSettingsRepositoryInterface;
use App\Models\BusinessSetting;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class UpdateWhatsAppMessagingSettingsCommand
{
    public function __construct(
        public BusinessSettingsRepositoryInterface $settings,
        public RecordAuditLogCommand $audit,
        private readonly CancelScheduledMessagesForDisabledFeaturesCommand $cancelDisabledMessages,
    ) {}

    /** @param list<string> $keywords @param array<string, bool> $features */
    public function execute(int $businessId, int $userId, array $keywords, array $features): BusinessSetting
    {
        if (! User::query()->findOrFail($userId)->hasBusinessPermission($businessId, 'settings.update')) {
            throw new AuthorizationException('You are not allowed to update messaging settings.');
        }

        $normalized = array_values(array_unique(array_map(
            static fn (string $keyword): string => mb_strtoupper(trim($keyword)),
            $keywords,
        )));
        $normalizedFeatures = array_replace(WhatsAppMessageFeature::defaults(), $features);

        return DB::transaction(function () use ($businessId, $userId, $normalized, $normalizedFeatures): BusinessSetting {
            $before = $this->settings->whatsappOptOutKeywords($businessId, true);
            $beforeFeatures = $this->settings->whatsappMessageFeatures($businessId, true) ?? WhatsAppMessageFeature::defaults();
            $this->settings->saveWhatsAppOptOutKeywords($businessId, $normalized);
            $setting = $this->settings->saveWhatsAppMessageFeatures($businessId, $normalizedFeatures);
            $cancelledMessages = $this->cancelDisabledMessages->execute($businessId);
            $this->audit->execute(
                $businessId,
                $userId,
                'business.messaging_settings_updated',
                BusinessSetting::class,
                (int) $setting->getKey(),
                ['opt_out_keywords' => $before, 'features' => $beforeFeatures],
                ['opt_out_keywords' => $normalized, 'features' => $normalizedFeatures, 'cancelled_scheduled_messages' => $cancelledMessages],
            );

            return $setting;
        });
    }
}
