<?php

namespace App\Services\Settings\Commands;

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
    ) {}

    /** @param list<string> $keywords */
    public function execute(int $businessId, int $userId, array $keywords): BusinessSetting
    {
        if (! User::query()->findOrFail($userId)->hasBusinessPermission($businessId, 'settings.update')) {
            throw new AuthorizationException('You are not allowed to update messaging settings.');
        }

        $normalized = array_values(array_unique(array_map(
            static fn (string $keyword): string => mb_strtoupper(trim($keyword)),
            $keywords,
        )));

        return DB::transaction(function () use ($businessId, $userId, $normalized): BusinessSetting {
            $before = $this->settings->whatsappOptOutKeywords($businessId, true);
            $setting = $this->settings->saveWhatsAppOptOutKeywords($businessId, $normalized);
            $this->audit->execute(
                $businessId,
                $userId,
                'business.messaging_settings_updated',
                BusinessSetting::class,
                (int) $setting->getKey(),
                ['opt_out_keywords' => $before],
                ['opt_out_keywords' => $normalized],
            );

            return $setting;
        });
    }
}
