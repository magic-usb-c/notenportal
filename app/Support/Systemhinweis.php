<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\NotificationMark;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Systemhinweis-Banner (Block AE): Text/Art/Zielgruppe/Zeitfenster liegen in `einstellungen`
 * (gecacht über Einstellungen::alle(), also ohne zusätzliche Abfrage), das Wegklicken pro
 * Benutzer in `notification_marks` (gleiches Muster wie der Feedback-Hinweis).
 */
final class Systemhinweis
{
    public const string TYP = 'systemhinweis';

    /**
     * @return array{text: string, art: string, version: string}|null
     *
     * Reihenfolge (je früh raus, desto weniger Abfragen): Text leer → null ohne Abfrage;
     * ausserhalb des Zeitfensters → null ohne Abfrage; Zielgruppe ≠ alle → 1 Abfrage
     * (Rollen); danach 1 Abfrage, ob schon weggeklickt.
     */
    public static function aktuell(?User $user): ?array
    {
        $text = trim((string) Einstellungen::get(Einstellungen::HINWEIS_TEXT, ''));
        if ($text === '') {
            return null;
        }

        if (! self::imZeitfenster()) {
            return null;
        }

        $zielgruppe = Einstellungen::get(Einstellungen::HINWEIS_ZIELGRUPPE, 'alle');
        if ($zielgruppe !== 'alle' && ! self::passtZielgruppe($zielgruppe, $user)) {
            return null;
        }

        $version = (string) Einstellungen::get(Einstellungen::HINWEIS_VERSION, '');
        if ($user && self::weggeklickt($user, $version)) {
            return null;
        }

        return [
            'text' => $text,
            'art' => Einstellungen::get(Einstellungen::HINWEIS_ART, 'info') ?: 'info',
            'version' => $version,
        ];
    }

    /** Eigener Hinweis auf der Login-Seite: nur wenn dafür aktiviert und für alle bestimmt, nicht wegklickbar. */
    public static function fuerLogin(): ?array
    {
        if (Einstellungen::get(Einstellungen::HINWEIS_LOGIN, '0') !== '1') {
            return null;
        }

        if ((Einstellungen::get(Einstellungen::HINWEIS_ZIELGRUPPE, 'alle') ?: 'alle') !== 'alle') {
            return null;
        }

        return self::aktuell(null);
    }

    /** Normalisiert Zeilenumbrüche/Leerzeilen; dieselbe Normalisierung fürs Speichern wie fürs Hashen. */
    public static function normalisiert(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /** Erste 16 Zeichen von sha1 über den normalisierten Text – ändert sich nur bei neuem Text. */
    public static function version(string $normalisierterText): string
    {
        return substr(sha1($normalisierterText), 0, 16);
    }

    private static function imZeitfenster(): bool
    {
        $jetzt = Carbon::now('UTC');

        $beginn = Einstellungen::get(Einstellungen::HINWEIS_BEGINN);
        if ($beginn && $jetzt->lt(Carbon::parse($beginn, 'UTC'))) {
            return false;
        }

        $ende = Einstellungen::get(Einstellungen::HINWEIS_ENDE);
        if ($ende && $jetzt->gt(Carbon::parse($ende, 'UTC'))) {
            return false;
        }

        return true;
    }

    private static function passtZielgruppe(string $zielgruppe, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $erforderlich = match ($zielgruppe) {
            'lernende' => 'Lernender',
            'berufsbildner' => 'Berufsbildner',
            'admins' => 'Admin',
            default => null,
        };

        if ($erforderlich === null) {
            return false;
        }

        // Einmal laden statt hasRole() je Aufruf abzufragen (siehe Klassenkommentar der Spezifikation).
        return $user->rollen->contains(fn ($rolle) => strcasecmp((string) $rolle->name, $erforderlich) === 0);
    }

    private static function weggeklickt(User $user, string $version): bool
    {
        if ($version === '') {
            return false;
        }

        return NotificationMark::query()
            ->where('user_id', (int) $user->benutzer_id)
            ->where('type', self::TYP)
            ->where('subject_key', $version)
            ->exists();
    }
}
