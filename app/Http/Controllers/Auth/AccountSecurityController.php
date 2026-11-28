<?php

namespace App\Http\Controllers\Auth;

use App\Configs\WebPushConfig;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use App\Services\Auth\Commands\RevokeAccountSessionCommand;
use App\Services\Auth\Commands\RevokeOtherAccountSessionsCommand;
use App\Services\Auth\Queries\ListAccountSessionsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountSecurityController extends Controller
{
    public function show(Request $request, ListAccountSessionsQuery $sessions, WebPushConfig $webPush): Response
    {
        return Inertia::render('settings/security', [
            'twoFactor' => [
                'enabled' => $request->user()->hasEnabledTwoFactorAuthentication(),
                'pendingConfirmation' => $request->user()->two_factor_secret !== null
                    && $request->user()->two_factor_confirmed_at === null,
            ],
            'passwordConfirmed' => (int) $request->session()->get('auth.password_confirmed_at', 0)
                >= Date::now()->subSeconds((int) config('auth.password_timeout', 10800))->timestamp,
            'sessions' => $sessions->execute(
                (int) $request->user()->getKey(),
                $request->session()->getId(),
            ),
            'securityNotice' => $request->session()->get('security_notice'),
            'webPush' => [
                'configured' => $webPush->isConfigured(),
                'publicKey' => $webPush->publicKey(),
                'subscriptionCount' => $request->user()->webPushSubscriptions()->count(),
            ],
        ]);
    }

    public function confirmPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'current_password:web'],
        ]);

        $request->session()->put('auth.password_confirmed_at', Date::now()->timestamp);

        return back()->with('status', 'Password confirmed. You can now update authentication settings.');
    }

    public function revokeSession(
        Request $request,
        string $sessionId,
        RevokeAccountSessionCommand $command,
        RecordAuditLogCommand $audit,
    ): RedirectResponse {
        $revoked = $command->execute(
            (int) $request->user()->getKey(),
            $sessionId,
            $request->session()->getId(),
        );

        if ($revoked) {
            $audit->execute($this->businessId((int) $request->user()->getKey()), (int) $request->user()->getKey(), 'account.session_revoked');
        }

        return back()->with('status', $revoked ? 'Session revoked.' : 'That session is no longer available.');
    }

    public function revokeOtherSessions(
        Request $request,
        RevokeOtherAccountSessionsCommand $command,
        RecordAuditLogCommand $audit,
    ): RedirectResponse {
        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->string('password')->toString(), $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
        }

        $revokedCount = $command->execute(
            (int) $request->user()->getKey(),
            $request->session()->getId(),
        );

        if ($revokedCount > 0) {
            $audit->execute(
                $this->businessId((int) $request->user()->getKey()),
                (int) $request->user()->getKey(),
                'account.sessions_revoked',
                after: ['count' => $revokedCount],
            );
        }

        return back()->with('status', "Revoked {$revokedCount} other session(s).");
    }

    private function businessId(int $userId): ?int
    {
        $business = Business::query()->where('is_active', true)->first();

        if ($business === null) {
            return null;
        }

        return $business->members()->where('users.id', $userId)->exists()
            ? (int) $business->getKey()
            : null;
    }
}
