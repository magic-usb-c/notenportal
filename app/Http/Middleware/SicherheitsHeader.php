<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sicherheits-Header für alle Web-Antworten. Setzt nur, was die Antwort nicht selbst setzt
 * (z. B. liefert die Dokument-Ablage eine eigene Sandbox-CSP). Keine CSP hier: Alpine braucht
 * 'unsafe-eval', eine portalweite CSP gehört zur Härtung vor einem Betrieb ausserhalb des Labs.
 */
class SicherheitsHeader
{
    public const HEADER = [
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADER as $name => $wert) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $wert);
            }
        }

        return $response;
    }
}
