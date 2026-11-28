<?php

namespace App\Console\Commands;

use App\Dtos\Accounts\CreateBusinessOwnerDto;
use App\Mail\OwnerTemporaryAccess;
use App\Models\Business;
use App\Models\User;
use App\Services\Accounts\Commands\CreateBusinessOwnerCommand;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
        if (! in_array(config('mail.default'), ['smtp', 'sendmail', 'mailgun', 'ses', 'ses-v2', 'postmark', 'resend'], true)) {
            $this->components->error('Configure a private email delivery service before provisioning the owner.');

            return self::FAILURE;
        }

        $password = Str::password(32);

        Validator::make([
            'name' => $name,
            'email' => $email,
            'business_name' => $businessName,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'business_name' => ['required', 'string', 'max:255'],
        ])->validate();

        $user = $command->execute(new CreateBusinessOwnerDto($name, $email, $password, $businessName));

        try {
            Mail::to($email)->send(new OwnerTemporaryAccess($businessName, $password));
        } catch (\Throwable) {
            $this->components->error('The owner account was created, but the temporary credential could not be delivered. Configure email, then use password recovery.');

            return self::FAILURE;
        }

        $this->components->info("Owner account provisioned for {$user->email}. A temporary sign-in was sent by email; it expires in 24 hours.");

        return self::SUCCESS;
    }
}
