<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Deaktivierte Benutzer sofort aussperren — nicht erst beim nächsten Login.
     * Das aktiv-Flag wird sonst nur in LoginRequest::credentials() geprüft,
     * eine bestehende Session bliebe nach Deaktivierung voll handlungsfähig.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->aktiv) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Dein Konto wurde deaktiviert.');
        }

        return $next($request);
    }
}
