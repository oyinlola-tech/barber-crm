<?php

namespace App\Dtos\Accounts;

class CreateBusinessOwnerDto
{
    public function __construct(
        public readonly string $ownerName,
        public readonly string $ownerEmail,
        public readonly string $password,
        public readonly string $businessName,
    ) {}
}
