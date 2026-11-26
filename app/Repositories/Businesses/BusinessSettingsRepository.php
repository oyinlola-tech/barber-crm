<?php

namespace App\Repositories\Businesses;

use App\Interfaces\Repositories\Businesses\BusinessSettingsRepositoryInterface;
use App\Models\BusinessSetting;

class BusinessSettingsRepository implements BusinessSettingsRepositoryInterface
{
    public function whatsappOptOutKeywords(int $businessId, bool $lock = false): ?array
    {
        $query = BusinessSetting::query()
            ->where('business_id', $businessId)
            ->where('key', 'whatsapp.opt_out_keywords');

        if ($lock) {
            $query->lockForUpdate();
        }

        $value = $query->value('value');

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : null;
    }

    public function saveWhatsAppOptOutKeywords(int $businessId, array $keywords): BusinessSetting
    {
        return BusinessSetting::query()->updateOrCreate(
            ['business_id' => $businessId, 'key' => 'whatsapp.opt_out_keywords'],
            ['value' => $keywords],
        );
    }
}
