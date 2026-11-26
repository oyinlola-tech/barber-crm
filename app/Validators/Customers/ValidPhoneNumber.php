<?php

namespace App\Validators\Customers;

use App\Utils\PhoneNumberNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPhoneNumber implements ValidationRule
{
    public function __construct(private readonly PhoneNumberNormalizer $normalizer) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $this->normalizer->normalize($value) === null) {
            $fail('Enter a valid international phone number.');
        }
    }
}
