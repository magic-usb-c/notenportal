<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Quelltext-Test (keine DB): Befehlspalette und Eingabefelder halten die Kontrast- und Fokusregeln
 * aus notenportal-dunkelmodus ein (Text auf bg-accent deckend, Fokusring bei Feldern).
 */
class PalettenFokusKontrastTest extends TestCase
{
    private static function quelle(string $pfad): string
    {
        $inhalt = file_get_contents(dirname(__DIR__, 2).'/'.$pfad);
        self::assertNotFalse($inhalt, "{$pfad} nicht lesbar.");

        return $inhalt;
    }

    /** Inhalt des @utility-Blocks np-feld (bis zur nächsten @utility-Zeile). */
    private static function npFeld(): string
    {
        $css = self::quelle('resources/css/app.css');
        self::assertSame(1, preg_match('/@utility np-feld \{(.*?)\n\}\n/s', $css, $treffer), 'np-feld nicht gefunden.');

        return $treffer[1];
    }

    #[Test]
    public function gewaehlte_palettenzeile_nutzt_den_nebentext_ohne_transparenz_auf_dem_akzent(): void
    {
        $navigation = self::quelle('resources/views/layouts/navigation.blade.php');
        $css = self::quelle('resources/css/app.css');

        self::assertStringNotContainsString('text-accent-contrast/', $navigation, 'Halbtransparenter Text auf bg-accent unterschreitet 4.5:1.');
        self::assertStringNotContainsString('text-accent-contrast/', $css, 'Die erzwungene Alpha-Klasse gehört nicht mehr in @source inline.');
        self::assertStringContainsString("i === index ? 'text-accent-contrast' : 'text-muted'", $navigation);
    }

    #[Test]
    public function eingabefeld_zeigt_im_fokus_den_ring_statt_nur_eines_akzentscheins(): void
    {
        $feld = self::npFeld();

        self::assertSame(1, preg_match('/&:focus,\s*&:focus-visible \{(.*?)\}/s', $feld, $fokus), 'Fokusregel von np-feld fehlt.');
        self::assertStringContainsString('outline: 2px solid rgb(var(--ring))', $fokus[1]);
        self::assertStringContainsString('outline-offset: 2px', $fokus[1]);
        self::assertStringContainsString('border-color: rgb(var(--ring))', $fokus[1]);
        self::assertStringNotContainsString('outline: none', $fokus[1]);
        self::assertStringNotContainsString('rgb(var(--accent)', $fokus[1], 'Der Akzent allein ist im Dunkeln kaum vom Ruherand zu unterscheiden.');
    }

    #[Test]
    public function ungueltiges_feld_behaelt_den_fehlerrand_und_zeigt_trotzdem_den_ring(): void
    {
        $feld = self::npFeld();

        self::assertSame(1, preg_match("/&\\[aria-invalid='true'\\] \\{(.*?)\\}/s", $feld, $ungueltig));
        self::assertStringContainsString('border-color: rgb(var(--note-ungenuegend))', $ungueltig[1]);
        // Fehlerrand muss nach der Fokusregel stehen, sonst überstimmt der Ring-Rand ihn.
        self::assertGreaterThan(strpos($feld, '&:focus,'), strpos($feld, "&[aria-invalid='true'] {"));
        self::assertStringNotContainsString('outline: none', $feld);
    }
}
