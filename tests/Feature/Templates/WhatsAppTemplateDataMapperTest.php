<?php

use App\Mappers\Templates\WhatsAppTemplateDataMapper;
use App\Models\Business;
use App\Models\Customer;
use App\Models\MessageTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses the business profile review link in personalized WhatsApp templates', function (): void {
    $business = Business::factory()->create([
        'public_review_url' => 'https://reviews.example.test/barbershop',
        'settings' => ['review_link' => 'https://old.example.test/review'],
    ]);
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $template = MessageTemplate::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'review_request',
        'language' => 'en',
        'category' => 'UTILITY',
        'status' => 'synchronized',
        'provider_status' => 'APPROVED',
        'provider_template_id' => 'meta-review-request',
        'is_enabled' => true,
        'components' => [['type' => 'BODY', 'text' => 'Please review your visit: {{1}}']],
        'variable_map' => ['review_link'],
    ]);

    $mapped = app(WhatsAppTemplateDataMapper::class)->map($template, $customer, $business);

    expect($mapped['body'])->toBe('Please review your visit: https://reviews.example.test/barbershop')
        ->and($mapped['components'][0]['parameters'][0]['text'])->toBe('https://reviews.example.test/barbershop');
});
