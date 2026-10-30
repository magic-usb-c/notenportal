<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Bremst das Raten des aktuellen Passworts bei übernommener Sitzung (Passwort ändern, Passwort bestätigen,
 * E-Mail ändern). Wie LoginRequest zählen nur Fehlversuche; die Sperre kommt als Formularfehler mit
 * Restzeit statt als 429-Seite. Ein Zähler je Benutzer für alle drei Stellen: die Sitzung ist es, die der
 * Angreifer hält, eine andere IP hebt die Sperre nicht auf.
 */
final class PasswortOrakelSperre
{
    private const MAX_VERSUCHE = 5;

    /** @throws ValidationException wenn der Zähler voll ist – auch das richtige Passwort wird dann nicht geprüft */
    public static function pruefen(Request $request, string $feld, string $bag = 'default'): void
    {
        $schluessel = self::schluessel($request);

        if (! RateLimiter::tooManyAttempts($schluessel, self::MAX_VERSUCHE)) {
            return;
        }

        throw ValidationException::withMessages([
            $feld => __('Zu viele Versuche. Bitte :seconds Sekunden warten.', ['seconds' => RateLimiter::availableIn($schluessel)]),
        ])->errorBag($bag);
    }

    public static function fehlversuch(Request $request): void
    {
        RateLimiter::hit(self::schluessel($request));
    }

    public static function zuruecksetzen(Request $request): void
    {
        RateLimiter::clear(self::schluessel($request));
    }

    private static function schluessel(Request $request): string
    {
        return 'passwort-pruefung|'.$request->user()->getAuthIdentifier();
    }
}
