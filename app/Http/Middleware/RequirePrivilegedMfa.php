<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePrivilegedMfa
{
    /** @var list<string> */
    private const MFA_SETUP_PATHS = [
        'settings/security',
        'settings/security/*',
        'email/verify',
        'email/verify/*',
        'email/verification-notification',
        'user/confirm-password',
        'user/two-factor-authentication',
        'user/two-factor-confirmation',
        'user/confirmed-two-factor-authentication',
        'user/two-factor-qr-code',
        'user/two-factor-secret-key',
        'user/two-factor-recovery-codes',
        'logout',
    ];

    /** @var list<string> */
    private const PRIVILEGED_PERMISSIONS = [
        'staff.invite',
        'staff.suspend',
        'roles.create',
        'roles.update',
        'roles.delete',
        'roles.assign',
        'settings.update',
        'whatsapp.configure',
        'whatsapp.disconnect',
    ];

    /** @var list<string> */
    private const PRIVILEGED_PERMISSION_GROUPS = [
        'staff.*',
        'roles.*',
        'settings.*',
        'whatsapp.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $request->is(...self::MFA_SETUP_PATHS)) {
            return $next($request);
        }

        $businessId = Business::query()->where('is_active', true)->value('id');

        if ($businessId === null) {
            return $next($request);
        }

        $membership = $user->businesses()
            ->whereKey((int) $businessId)
            ->wherePivot('status', 'active')
            ->first();

        if ($membership === null) {
            return $next($request);
        }

        $isOwner = $membership->pivot->role === 'owner';
        $permissions = $user->businessPermissions((int) $businessId);
        $isPrivileged = array_intersect(self::PRIVILEGED_PERMISSIONS, $permissions) !== []
            || in_array('*', $permissions, true)
            || array_intersect(self::PRIVILEGED_PERMISSION_GROUPS, $permissions) !== [];

        if (($isOwner || $isPrivileged) && ! $user->hasEnabledTwoFactorAuthentication()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Two-factor authentication is required for this account.'], 403);
            }

            return redirect()->route('account.security')
                ->with('security_notice', 'Enable and confirm two-factor authentication before continuing.');
        }

        return $next($request);
    }
}
