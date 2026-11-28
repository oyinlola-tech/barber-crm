<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\Staff\Commands\AcceptStaffInvitationCommand;
use App\Validators\Staff\AcceptStaffInvitationRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StaffInvitationController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('staff/accept-invitation');
    }

    public function accept(AcceptStaffInvitationRequest $request, AcceptStaffInvitationCommand $command): RedirectResponse
    {
        $command->execute($request->validated('token'));

        return to_route('login')->with('status', 'Your account is ready. Sign in with the temporary password in your invitation email, then choose a new password.');
    }
}
