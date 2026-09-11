<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Betriebslogo;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Öffentliche Auslieferung des Betriebslogos (Block AH): kein Login, damit Login-Seite und
 * Mail-Kopf es zeigen können. Läuft ohne Session-/Cookie-/CSRF-Middleware der web-Gruppe
 * (siehe routes/web.php) – die Antwort trägt darum nie einen Set-Cookie-Header. ETag/
 * Last-Modified erlauben 304; die URL trägt `?v=<Zeitstempel>` (Betriebslogo::url()), darum
 * lange cachebar (public, ein Tag) ohne dass ein neues Logo veraltet angezeigt wird. Eigene,
 * sehr enge CSP (`default-src 'none'; sandbox`) und `Content-Disposition: inline`, falls die
 * Datei je direkt aufgerufen statt eingebettet wird. Ohne Logo oder bei verwaister Einstellung
 * 404 (Betriebslogo::pfad() prüft beides).
 */
class BrandingController extends Controller
{
    public function logo(Request $request): BinaryFileResponse
    {
        $pfad = Betriebslogo::pfad();
        abort_if($pfad === null, 404);

        $response = new BinaryFileResponse($pfad, 200, [], true, null, true, true);
        $response->headers->set('Content-Type', Betriebslogo::mime() ?? 'application/octet-stream');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; sandbox");
        $response->headers->set('Cache-Control', 'public, max-age=86400');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'logo.'.(Betriebslogo::endung() ?? 'bin'));
        $response->isNotModified($request);

        return $response;
    }
}
