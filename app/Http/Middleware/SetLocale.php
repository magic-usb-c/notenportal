<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Einstellungen;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprache der Oberfläche. Solange die Sprachwahl aus ist (Einstellung sprachwahl_aktiv), gilt für alle
 * die Standardsprache des Betriebs. Sonst der Reihe nach: angemeldet benutzer.locale; Gast Session,
 * dann Accept-Language (nur de/en); zuletzt die Einstellung sprache_standard, sonst de.
 */
final class SetLocale
{
    public const array SPRACHEN = ['de', 'en'];

    public const string STANDARD = 'sprache_standard';

    public const string WAHL_AKTIV = 'sprachwahl_aktiv';

    public const string SESSION = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = self::ermitteln($request);
        App::setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }

    public static function ermitteln(Request $request): string
    {
        if (! self::wahlAktiv()) {
            return self::standard();
        }

        $user = $request->user();
        if ($user) {
            return self::gueltig($user->getAttribute('locale')) ?? self::standard();
        }

        $session = $request->hasSession() ? self::gueltig($request->session()->get(self::SESSION)) : null;

        return $session ?? self::ausBrowser($request) ?? self::standard();
    }

    /** Standardsprache des Betriebs (Einstellung), sonst de. */
    public static function standard(): string
    {
        try {
            return self::gueltig(Einstellungen::get(self::STANDARD)) ?? 'de';
        } catch (\Throwable) {
            return 'de';
        }
    }

    /** Umschalter sichtbar und persönliche Sprache wirksam. */
    public static function wahlAktiv(): bool
    {
        try {
            return (bool) Einstellungen::get(self::WAHL_AKTIV);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function gueltig(mixed $wert): ?string
    {
        return is_string($wert) && in_array($wert, self::SPRACHEN, true) ? $wert : null;
    }

    /** Erste unterstützte Sprache aus Accept-Language (nach q sortiert), sonst null. */
    private static function ausBrowser(Request $request): ?string
    {
        if (! $request->headers->has('Accept-Language')) {
            return null;
        }
        foreach ($request->getLanguages() as $sprache) {
            if ($treffer = self::gueltig(strtolower(strtok($sprache, '_-')))) {
                return $treffer;
            }
        }

        return null;
    }
}
