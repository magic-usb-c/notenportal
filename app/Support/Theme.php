<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Farbthema: der Betrieb wählt es auf «Betrieb» (Einstellung), Benutzer können
 * «Kontrast» persönlich einschalten (Spalte benutzer.kontrast). Tokens: resources/css/theme.css.
 */
final class Theme
{
    public const string STANDARD = 'gletscher';

    public const string KONTRAST = 'kontrast';

    /** Wert => Bezeichnung */
    public const array THEMES = [
        'gletscher' => 'Gletscher',
        'sandstein' => 'Sandstein',
        'pflaume' => 'Pflaume',
        'graphit' => 'Graphit',
        'wald' => 'Wald',
        'abendrot' => 'Abendrot',
        'papier' => 'Papier',
        'mitternacht' => 'Mitternacht',
        'kontrast' => 'Kontrast',
    ];

    private static ?bool $spalteVorhanden = null;

    public static function betrieb(): string
    {
        $wert = (string) Einstellungen::get(Einstellungen::THEME, self::STANDARD);

        return array_key_exists($wert, self::THEMES) ? $wert : self::STANDARD;
    }

    /** Theme für die Seite: persönliches Theme > alter Kontrast-Schalter > Betriebs-Theme. */
    public static function fuer(?User $user): string
    {
        $persoenlich = Darstellung::fuer($user)['theme'] ?? null;
        if ($persoenlich !== null) {
            return $persoenlich;
        }

        if ($user && $user->getAttribute('kontrast')) {
            return self::KONTRAST;
        }

        try {
            return self::betrieb();
        } catch (\Throwable) {
            return self::STANDARD;
        }
    }

    /** Die persönliche Option gibt es erst nach der Migration 2026_09_11_000007. */
    public static function kontrastOptionVerfuegbar(): bool
    {
        return self::$spalteVorhanden ??= Schema::hasColumn('benutzer', 'kontrast');
    }
}
