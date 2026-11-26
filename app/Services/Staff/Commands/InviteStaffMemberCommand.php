<?php

namespace App\Services\Staff\Commands;

use App\Enums\BusinessRole;
use App\Mail\StaffInvitation as StaffInvitationMail;
use App\Models\Business;
use App\Models\Role;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteStaffMemberCommand
{
    /** @param array{name: string, email: string, role_slug: string} $attributes */
    public function execute(User $actor, Business $business, array $attributes): StaffInvitation
    {
        abort_unless($actor->hasBusinessPermission((int) $business->getKey(), 'staff.invite'), 403);

        if (! in_array(config('mail.default'), ['smtp', 'sendmail', 'mailgun', 'ses', 'ses-v2', 'postmark', 'resend'], true)) {
            throw ValidationException::withMessages([
                'email' => 'Configure a private email delivery service before sending staff invitations.',
            ]);
        }

        $email = mb_strtolower(trim($attributes['email']));
        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => 'An account already uses this email address.']);
        }

        $role = Role::query()
            ->where('slug', $attributes['role_slug'])
            ->where(fn ($query) => $query->whereNull('business_id')->orWhere('business_id', $business->getKey()))
            ->orderByRaw('business_id is null asc')
            ->firstOrFail();

        abort_if($role->slug === BusinessRole::Owner->value, 403, 'Ownership cannot be granted through staff invitations.');
        $this->assertActorCanDelegate($actor, (int) $business->getKey(), $role->permissions ?? []);

        $rawToken = Str::random(64);
        $invitation = DB::transaction(function () use ($actor, $business, $attributes, $email, $role, $rawToken): StaffInvitation {
            StaffInvitation::query()
                ->where('business_id', $business->getKey())
                ->where('email', $email)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $invitation = StaffInvitation::query()->create([
                'business_id' => $business->getKey(),
                'invited_by' => $actor->getKey(),
                'name' => trim($attributes['name']),
                'email' => $email,
                'role_slug' => $role->slug,
                'token_hash' => hash('sha256', $rawToken),
                'expires_at' => now()->addHours(72),
            ]);

            app(RecordAuditLogCommand::class)->execute(
                (int) $business->getKey(), (int) $actor->getKey(), 'staff.invited',
                StaffInvitation::class, (int) $invitation->getKey(), null,
                ['email' => $email, 'role' => $role->slug, 'expires_at' => $invitation->expires_at->toIso8601String()],
            );

            return $invitation;
        });

        $url = rtrim((string) config('app.url'), '/').'/staff/invitations#'.$rawToken;
        try {
            Mail::to($email)->send(new StaffInvitationMail($url, (string) $business->name));
        } catch (\Throwable $exception) {
            $invitation->forceFill(['revoked_at' => now()])->save();

            throw $exception;
        }

        return $invitation;
    }

    /** @param list<string> $permissions */
    private function assertActorCanDelegate(User $actor, int $businessId, array $permissions): void
    {
        foreach ($permissions as $permission) {
            abort_unless($actor->hasBusinessPermission($businessId, $permission), 403, 'You cannot delegate permissions you do not hold.');
        }
    }
}
