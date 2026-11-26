<?php

use App\Utils\PhoneNumberNormalizer;

it('normalizes a domestic Nigerian number to E.164', function (): void {
    config(['crm.phone_default_region' => 'NG']);

    expect(app(PhoneNumberNormalizer::class)->normalize('0803 123 4567'))
        ->toBe('+2348031234567');
});

it('preserves valid international numbers regardless of the default region', function (): void {
    config(['crm.phone_default_region' => 'NG']);

    expect(app(PhoneNumberNormalizer::class)->normalize('+1 415 555 2671'))
        ->toBe('+14155552671');
});

it('rejects invalid phone numbers', function (): void {
    expect(app(PhoneNumberNormalizer::class)->normalize('1234'))
        ->toBeNull();
});
