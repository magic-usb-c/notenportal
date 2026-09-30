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
        'fjord' => 'Fjord',
        'bernstein' => 'Bernstein',
        'schiefer' => 'Schiefer',
        'kontrast' => 'Kontrast',
    ];

    /**
     * Seitenhintergrund je Theme (hell/dunkel) als Hex – Quelle: resources/css/theme.css (--bg).
     * Für Manifest (background_color/theme_color, nur hell) und <meta name="theme-color"> (hell+dunkel).
     */
    private const array HINTERGRUND = [
        'gletscher' => ['hell' => '#F2F2F7', 'dunkel' => '#1C1C1E'],
        'sandstein' => ['hell' => '#F8F5EF', 'dunkel' => '#14110E'],
        'pflaume' => ['hell' => '#F7F6FA', 'dunkel' => '#110D17'],
        'graphit' => ['hell' => '#FAFAFA', 'dunkel' => '#0D0D0D'],
        'wald' => ['hell' => '#F3F7F4', 'dunkel' => '#09120B'],
        'abendrot' => ['hell' => '#FDF5F2', 'dunkel' => '#160D0B'],
        'papier' => ['hell' => '#F7EFE3', 'dunkel' => '#1C140E'],
        'mitternacht' => ['hell' => '#F5F6FC', 'dunkel' => '#010103'],
        'fjord' => ['hell' => '#F1F8F8', 'dunkel' => '#051213'],
        'bernstein' => ['hell' => '#FBF5ED', 'dunkel' => '#1B1209'],
        'schiefer' => ['hell' => '#F4F7FA', 'dunkel' => '#090E12'],
        'kontrast' => ['hell' => '#FFFFFF', 'dunkel' => '#070707'],
    ];

    private static ?bool $spalteVorhanden = null;

    /** Seitenhintergrund (hell) als Hex, für Manifest und <meta name="theme-color">. */
    public static function hintergrundHell(string $theme): string
    {
        return self::HINTERGRUND[$theme]['hell'] ?? self::HINTERGRUND[self::STANDARD]['hell'];
    }

    /** Seitenhintergrund (dunkel) als Hex, für <meta name="theme-color"> im Dunkelmodus. */
    public static function hintergrundDunkel(string $theme): string
    {
        return self::HINTERGRUND[$theme]['dunkel'] ?? self::HINTERGRUND[self::STANDARD]['dunkel'];
    }

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
