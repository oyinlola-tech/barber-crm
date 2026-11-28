<?php

namespace App\Services\Settings\Queries;

use App\Enums\WhatsAppMessageFeature;
use App\Interfaces\Repositories\Businesses\BusinessSettingsRepositoryInterface;

class GetWhatsAppMessageFeaturesQuery
{
    public function __construct(private readonly BusinessSettingsRepositoryInterface $settings) {}

    /** @return array<string, bool> */
    public function execute(int $businessId): array
    {
        return array_replace(
            WhatsAppMessageFeature::defaults(),
            $this->settings->whatsappMessageFeatures($businessId) ?? [],
        );
    }

    public function isEnabled(int $businessId, WhatsAppMessageFeature $feature): bool
    {
        $features = $this->execute($businessId);

        return ($features[WhatsAppMessageFeature::Outbound->value] ?? false)
            && ($features[$feature->value] ?? false);
    }

    public function allowsAutomation(int $businessId, string $purpose, string $trigger): bool
    {
        if (! $this->isEnabled($businessId, WhatsAppMessageFeature::Outbound)) {
            return false;
        }

        $feature = match ($trigger) {
            'customer_inactive' => WhatsAppMessageFeature::InactiveReengagement,
            'return_date_missed' => WhatsAppMessageFeature::MissedVisitFollowUp,
            default => WhatsAppMessageFeature::forTemplatePurpose($purpose) ?? WhatsAppMessageFeature::ExperienceFollowUp,
        };

        return $this->isEnabled($businessId, $feature);
    }
}
