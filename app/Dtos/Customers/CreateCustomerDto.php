<?php

namespace App\Dtos\Customers;

final readonly class CreateCustomerDto
{
    public function __construct(
        public string $firstName,
        public ?string $lastName,
        public string $phone,
        public ?string $email,
        public ?string $dateOfBirth,
        public ?string $intendedReturnDate,
        public ?string $notes,
    ) {}
}
