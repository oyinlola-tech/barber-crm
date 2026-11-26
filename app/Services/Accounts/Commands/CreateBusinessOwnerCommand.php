<?php

namespace App\Services\Accounts\Commands;

use App\Dtos\Accounts\CreateBusinessOwnerDto;
use App\Interfaces\Repositories\BusinessRepositoryInterface;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

class CreateBusinessOwnerCommand
{
    public function __construct(public BusinessRepositoryInterface $businessRepository) {}

    public function execute(CreateBusinessOwnerDto $data): User
    {
        $owner = $this->businessRepository->createWithOwner($data);

        event(new Registered($owner));

        return $owner;
    }
}
