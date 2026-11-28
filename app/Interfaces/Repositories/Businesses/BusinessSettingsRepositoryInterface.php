<?php

namespace App\Interfaces\Repositories\Businesses;

use App\Models\BusinessSetting;

interface BusinessSettingsRepositoryInterface
{
    /** @return list<string>|null */
    public function whatsappOptOutKeywords(int $businessId, bool $lock = false): ?array;

    /** @param list<string> $keywords */
    public function saveWhatsAppOptOutKeywords(int $businessId, array $keywords): BusinessSetting;

    /** @return array<string, bool>|null */
    public function whatsappMessageFeatures(int $businessId, bool $lock = false): ?array;

    /** @param array<string, bool> $features */
    public function saveWhatsAppMessageFeatures(int $businessId, array $features): BusinessSetting;
}
