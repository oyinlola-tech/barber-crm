<?php

namespace App\Services\Settings\Queries;

use App\Configs\WhatsAppConfig;
use App\Interfaces\Repositories\Businesses\BusinessSettingsRepositoryInterface;

class GetWhatsAppMessagingSettingsQuery
{
    public function __construct(
        public BusinessSettingsRepositoryInterface $settings,
        public WhatsAppConfig $configuration,
    ) {}

    /** @return list<string> */
    public function execute(int $businessId): array
    {
        $keywords = array_merge(
            $this->configuration->optOutKeywords(),
            $this->additionalPhrases($businessId),
        );

        return $this->normalize($keywords);
    }

    /** @return list<string> */
    public function additionalPhrases(int $businessId): array
    {
        return $this->normalize($this->settings->whatsappOptOutKeywords($businessId) ?? []);
    }

    /** @param list<string> $keywords @return list<string> */
    private function normalize(array $keywords): array
    {
        return array_values(array_unique(array_map(
            static fn (string $keyword): string => mb_strtoupper(trim($keyword)),
            array_filter($keywords, static fn (string $keyword): bool => trim($keyword) !== ''),
        )));
    }
}
