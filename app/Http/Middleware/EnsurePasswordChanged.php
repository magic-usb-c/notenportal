<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Konten mit Startpasswort kommen erst nach dem Passwortwechsel weiter. */
class EnsurePasswordChanged
{
    private const array ERLAUBT = ['password.initial', 'password.initial.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->passwort_wechsel_noetig && ! $request->routeIs(...self::ERLAUBT)) {
            return redirect()->route('password.initial');
        }

        return $next($request);
    }
}
