<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $allowed = array_filter(array_map('trim', explode(',', $roles)));

        foreach ($allowed as $roleName) {
            if ($user->hasRole($roleName)) {
                return $next($request);
            }
        }

        abort(403, 'Forbidden');
    }
}
