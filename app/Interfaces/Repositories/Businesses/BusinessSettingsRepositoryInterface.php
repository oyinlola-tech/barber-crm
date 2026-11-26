<?php

namespace App\Interfaces\Repositories\Businesses;

use App\Models\BusinessSetting;

interface BusinessSettingsRepositoryInterface
{
    /** @return list<string>|null */
    public function whatsappOptOutKeywords(int $businessId, bool $lock = false): ?array;

    /** @param list<string> $keywords */
    public function saveWhatsAppOptOutKeywords(int $businessId, array $keywords): BusinessSetting;
}
