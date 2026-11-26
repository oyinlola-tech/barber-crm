<?php

namespace App\Console\Commands;

use App\Dtos\Accounts\CreateBusinessOwnerDto;
use App\Models\Business;
use App\Models\User;
use App\Services\Accounts\Commands\CreateBusinessOwnerCommand;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

#[Signature('app:provision-owner {--name=} {--email=} {--business=} ')]
#[Description('Provision the first barbershop owner account.')]
class ProvisionOwner extends Command
{
    public function handle(CreateBusinessOwnerCommand $command): int
    {
        if (User::query()->exists() || Business::query()->exists()) {
            $this->components->error('An owner or barbershop is already provisioned.');

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?: $this->ask('Owner name'));
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Owner email'))));
        $businessName = (string) ($this->option('business') ?: $this->ask('Barbershop name'));
        $password = (string) $this->secret('Owner password (minimum 12 characters)');

        Validator::make([
            'name' => $name,
            'email' => $email,
            'business_name' => $businessName,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'business_name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:12'],
        ])->validate();

        $user = $command->execute(new CreateBusinessOwnerDto($name, $email, $password, $businessName));

        $this->components->info("Owner account provisioned for {$user->email}.");

        return self::SUCCESS;
    }
}
