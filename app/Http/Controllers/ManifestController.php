<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Einstellungen;
use App\Support\Theme;
use Illuminate\Http\JsonResponse;

/**
 * Web-App-Manifest (installierbare PWA). Öffentlich, 1 Tag cachebar – enthält nur den
 * Betriebsnamen und die Farben des Betriebs-Theme (hell), keine Personendaten.
 */
class ManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $betrieb = Einstellungen::get(Einstellungen::BETRIEB_NAME);
        $farbe = Theme::hintergrundHell(Theme::betrieb());

        $manifest = [
            'name' => 'Notenportal'.($betrieb ? ' · '.$betrieb : ''),
            'short_name' => 'Notenportal',
            'start_url' => '/dashboard',
            'scope' => '/',
            'display' => 'standalone',
            'lang' => 'de-CH',
            'background_color' => $farbe,
            'theme_color' => $farbe,
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ];

        return response()->json($manifest, 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
