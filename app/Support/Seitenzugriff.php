<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Darf ein Konto eine URL laut Rollen-Middleware («role:Admin,…») öffnen? Genutzt nach dem Login:
 * Eine vorgemerkte Ziel-URL einer anderen Rolle (z. B. Mail-Link an eine Admin-Adresse, geöffnet
 * mit dem Lernendenkonto) führt sonst direkt auf eine 403-Seite.
 */
class Seitenzugriff
{
    public static function erlaubt(User $user, string $url): bool
    {
        try {
            $route = Route::getRoutes()->match(Request::create($url, 'GET'));
        } catch (Throwable) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware) || ! str_starts_with($middleware, 'role:')) {
                continue;
            }
            $rollen = array_filter(array_map(trim(...), explode(',', substr($middleware, 5))));
            if (! collect($rollen)->contains(fn (string $rolle) => $user->hasRole($rolle))) {
                return false;
            }
        }

        return true;
    }
}
