<?php

namespace App\Mappers\Templates;

use App\Models\Business;
use App\Models\Customer;
use App\Models\MessageTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class WhatsAppTemplateDataMapper
{
    public function __construct(private readonly WhatsAppTemplateComponentMapper $components) {}

    /** @return array{body:string,components:list<array<string,mixed>>} */
    public function map(MessageTemplate $template, Customer $customer, Business $business): array
    {
        $body = $this->bodyText($template);
        $variables = $template->variable_map ?? [];
        $values = $this->values($customer, $business);
        $components = $this->components->toSendComponents($body, $variables, $values);
        $renderedBody = preg_replace_callback(
            '/{{\s*(\d+)\s*}}/',
            static function (array $matches) use ($variables, $values): string {
                $variable = $variables[(int) $matches[1] - 1] ?? null;
                $value = is_string($variable) ? ($values[$variable] ?? null) : null;
                if (! is_scalar($value) || trim((string) $value) === '') {
                    throw ValidationException::withMessages(['template' => 'A mapped approved template value is unavailable.']);
                }

                return (string) $value;
            },
            $body,
        );

        return ['body' => (string) $renderedBody, 'components' => $components];
    }

    private function bodyText(MessageTemplate $template): string
    {
        foreach ($template->components ?? [] as $component) {
            if (strtoupper((string) ($component['type'] ?? '')) === 'BODY' && is_string($component['text'] ?? null)) {
                return $component['text'];
            }
        }

        throw ValidationException::withMessages(['template' => 'The approved template has no text body.']);
    }

    /** @return array<string, scalar|null> */
    private function values(Customer $customer, Business $business): array
    {
        $lastVisit = $customer->visits()->latest('visited_at')->value('visited_at');
        /** @var array<string, mixed> $businessSettings */
        $businessSettings = $business->settings ?? [];

        return [
            'customer_name' => trim((string) $customer->first_name.' '.(string) $customer->last_name),
            'business_name' => $business->name,
            'business_phone' => $business->phone,
            'last_visit_date' => $lastVisit === null ? null : CarbonImmutable::parse($lastVisit, 'UTC')->setTimezone($business->timezone)->format('Y-m-d'),
            'review_link' => $businessSettings['public_review_link'] ?? $businessSettings['review_link'] ?? null,
        ];
    }
}
