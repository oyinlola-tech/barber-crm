<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolveBarbershop
{
    public function handle(Request $request, Closure $next): Response
    {
        $barbershops = Business::query()->where('is_active', true)->limit(2)->get();

        abort_if($barbershops->count() !== 1, 503, 'The barbershop profile is not configured.');

        $barbershop = $barbershops->first();
        $activeMembership = $request->user()?->businesses()->whereKey($barbershop->getKey())
            ->wherePivot('status', 'active')->exists();

        if (! $activeMembership) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'This account is not active. Contact the barbershop owner.']);
        }

        $request->attributes->set('currentBusiness', $barbershop);

        return $next($request);
    }
}
