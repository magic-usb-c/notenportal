<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\App;

/**
 * Datum in der Sprache der Oberfläche (de → de_CH, en → en_GB). Ersetzt harte ->locale('de_CH').
 *
 *   Format::date($tag, 'dddd, D. MMMM YYYY')   // isoFormat oder benannte Vorlage (VORLAGEN)
 *   Format::datum($monat, 'F Y')               // translatedFormat (PHP-Format)
 *
 * Noten werden nicht über Intl formatiert, sie bleiben in beiden Sprachen «5.0».
 */
final class Format
{
    public const array LOCALES = ['de' => 'de_CH', 'en' => 'en_GB'];

    /** Benannte isoFormat-Vorlagen je Sprache, damit Englisch nicht «Friday, 11. September» zeigt. */
    public const array VORLAGEN = [
        'wochentag_datum' => ['de' => 'dddd, D. MMMM YYYY', 'en' => 'dddd D MMMM YYYY'],
        'wochentag_tag' => ['de' => 'dddd, D. MMMM', 'en' => 'dddd D MMMM'],
        'tag_monat' => ['de' => 'D. MMMM', 'en' => 'D MMMM'],
        'monat_jahr' => ['de' => 'MMMM YYYY', 'en' => 'MMMM YYYY'],
    ];

    public static function locale(?string $sprache = null): string
    {
        return self::LOCALES[$sprache ?? App::getLocale()] ?? self::LOCALES['de'];
    }

    /** isoFormat mit Locale; $format ist eine Vorlage aus VORLAGEN oder ein isoFormat-Muster. */
    public static function date(CarbonInterface $datum, string $format = 'wochentag_datum'): string
    {
        $sprache = array_key_exists(App::getLocale(), self::LOCALES) ? App::getLocale() : 'de';
        $muster = self::VORLAGEN[$format][$sprache] ?? $format;

        return $datum->copy()->locale(self::locale($sprache))->isoFormat($muster);
    }

    /** translatedFormat (PHP-Datumsformat) mit Locale, z. B. 'F Y', 'D, d. M'. */
    public static function datum(CarbonInterface $datum, string $format): string
    {
        return $datum->copy()->locale(self::locale())->translatedFormat($format);
    }
}
