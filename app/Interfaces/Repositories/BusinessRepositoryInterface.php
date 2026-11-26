<?php

namespace App\Interfaces\Repositories;

use App\Dtos\Accounts\CreateBusinessOwnerDto;
use App\Models\User;

interface BusinessRepositoryInterface
{
    public function createWithOwner(CreateBusinessOwnerDto $data): User;
}
