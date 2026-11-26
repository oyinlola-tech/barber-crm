<?php

namespace App\Services\Staff\Commands;

use App\Models\Business;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevokeStaffInvitationCommand
{
    public function __construct(public RecordAuditLogCommand $audit) {}

    public function execute(User $actor, Business $business, int $invitationId): StaffInvitation
    {
        $businessId = (int) $business->getKey();
        abort_unless($actor->hasBusinessPermission($businessId, 'staff.invite'), 403);

        return DB::transaction(function () use ($actor, $businessId, $invitationId): StaffInvitation {
            $invitation = StaffInvitation::query()
                ->where('business_id', $businessId)
                ->lockForUpdate()
                ->findOrFail($invitationId);

            if ($invitation->accepted_at !== null || $invitation->revoked_at !== null) {
                throw ValidationException::withMessages(['invitation' => 'This invitation is no longer pending.']);
            }

            $invitation->forceFill(['revoked_at' => now()])->save();
            $this->audit->execute(
                $businessId,
                (int) $actor->getKey(),
                'staff.invitation.revoked',
                StaffInvitation::class,
                (int) $invitation->getKey(),
                ['role' => $invitation->role_slug, 'expires_at' => $invitation->expires_at->toIso8601String()],
                ['revoked_at' => $invitation->revoked_at?->toIso8601String()],
            );

            return $invitation->refresh();
        });
    }
}
