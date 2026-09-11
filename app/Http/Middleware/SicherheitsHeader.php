<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sicherheits-Header für alle Web-Antworten. Setzt nur, was die Antwort nicht selbst setzt
 * (z. B. liefert die Dokument-Ablage eine eigene Sandbox-CSP). Die CSP schränkt bewusst keine
 * Skripte/Styles ein (Alpine braucht 'unsafe-eval', Views haben Inline-Skripte), sondern nur
 * Einbettung, <base>, Plugins und Formularziele.
 *
 * Für angemeldete Benutzer zusätzlich Cache-Control: no-store, private – Antworten mit
 * Personendaten (Noten, Dashboards, ...) dürfen weder vom Browser noch von einem
 * zwischengeschalteten Proxy nach dem Abmelden weiter angezeigt werden (z. B. Zurück-Button
 * auf einem gemeinsam genutzten Gerät). Symfony setzt in Response::prepare() immer einen
 * Standard-Cache-Control («no-cache, private»); darum Direktiven gezielt setzen/entfernen statt
 * has()/set() – ein has()-Check wäre immer wahr. Downloads (BinaryFileResponse/StreamedResponse,
 * z. B. Sicherungen, CSV-Exporte, Dokumente) bleiben unverändert, ebenso Manifest, Offline-Seite
 * und der token-basierte .ics-Kalenderabo-Link (kein Login, sollen wie bisher cachebar bleiben).
 */
class SicherheitsHeader
{
    public const HEADER = [
        'Content-Security-Policy' => "base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; worker-src 'self'",
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
    ];

    /** Öffentliche Routen ohne Login, die trotz angemeldetem Browser (Session-Cookie) cachebar bleiben sollen. */
    private const KEIN_NO_STORE = ['manifest', 'offline', 'calendar.export', 'branding.logo'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADER as $name => $wert) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $wert);
            }
        }

        if ($request->user() && ! in_array($request->route()?->getName(), self::KEIN_NO_STORE, true)
            && ! $response instanceof BinaryFileResponse && ! $response instanceof StreamedResponse) {
            $response->headers->addCacheControlDirective('no-store');
            $response->headers->addCacheControlDirective('private');
            $response->headers->removeCacheControlDirective('public');
            $response->headers->removeCacheControlDirective('max-age');
            $response->headers->removeCacheControlDirective('s-maxage');
        }

        return $response;
    }
}
