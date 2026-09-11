<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Farbe;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Kontrastgarantie der eigenen Akzentfarbe (App\Support\Farbe, Profil «Darstellung»): reiner
 * PHP-Test wie ThemeKontrastTest, keine DB/App nötig. Prüft für viele Hex-Farben (Extremfälle,
 * Grundfarben, Zufallsfarben mit festem Seed) je hell und dunkel:
 * - --accent auf --bg/--card/--surface-2 jedes Nicht-Kontrast-Themes ≥ 3:1 (UI-Komponenten)
 * - --accent-contrast (Text) auf --accent ≥ 4.5:1
 * - --accent-text auf --bg/--card/--surface-2 jedes Nicht-Kontrast-Themes ≥ 4.5:1
 */
class FarbeKontrastTest extends TestCase
{
    /** @return array<string, array{0:int,1:int,2:int}> */
    private static function flaechen(string $modus): array
    {
        $pfad = dirname(__DIR__, 2).'/resources/css/theme.css';
        $css = file_get_contents($pfad);
        self::assertNotFalse($css, "theme.css nicht lesbar: {$pfad}");

        $flaechen = [];
        preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $css, $bloecke, PREG_SET_ORDER);
        foreach ($bloecke as $block) {
            $selektor = trim(preg_replace('#/\*.*?\*/#s', '', $block[1]));
            if (! preg_match("/\\[data-theme='([a-z]+)'\\]/", $selektor, $m)) {
                continue;
            }
            if ($m[1] === 'kontrast') {
                continue;
            }
            $dunkel = str_contains($selektor, '.dark');
            if (($dunkel ? 'dunkel' : 'hell') !== $modus) {
                continue;
            }
            foreach (['bg', 'card', 'surface-2'] as $token) {
                if (preg_match('/--'.$token.':\s*(\d{1,3})\s+(\d{1,3})\s+(\d{1,3})\s*;/', $block[2], $t)) {
                    $flaechen[] = [(int) $t[1], (int) $t[2], (int) $t[3]];
                }
            }
        }

        self::assertNotEmpty($flaechen, "Keine {$modus}-Flächen in theme.css gefunden.");

        return $flaechen;
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    private static function luminanz(array $rgb): float
    {
        $lin = array_map(static function (int $c): float {
            $c /= 255;

            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
    }

    /**
     * @param  array{0:int,1:int,2:int}  $a
     * @param  array{0:int,1:int,2:int}  $b
     */
    private static function kontrast(array $a, array $b): float
    {
        $la = self::luminanz($a);
        $lb = self::luminanz($b);
        [$hell, $dunkel] = $la >= $lb ? [$la, $lb] : [$lb, $la];

        return ($hell + 0.05) / ($dunkel + 0.05);
    }

    /** @return array<string, array{0:string}> */
    public static function farben(): array
    {
        $farben = [
            '#ffff00', '#000000', '#ffffff', '#7f7f7f', '#00ff00', '#0000ff', '#ff0000',
            '#00ffff', '#ff00ff', '#123456', '#abcdef', '#f0e68c', '#800080',
        ];

        mt_srand(20260911);
        for ($i = 0; $i < 20; $i++) {
            $farben[] = sprintf('#%02x%02x%02x', mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
        }

        $out = [];
        foreach ($farben as $hex) {
            $out[$hex] = [$hex];
        }

        return $out;
    }

    #[Test]
    #[DataProvider('farben')]
    public function eigene_farbe_erfuellt_die_kontrastziele_hell(string $hex): void
    {
        $this->pruefeModus($hex, 'hell');
    }

    #[Test]
    #[DataProvider('farben')]
    public function eigene_farbe_erfuellt_die_kontrastziele_dunkel(string $hex): void
    {
        $this->pruefeModus($hex, 'dunkel');
    }

    private function pruefeModus(string $hex, string $modus): void
    {
        $flaechen = self::flaechen($modus);
        $t = Farbe::tokens($hex, $modus);

        $textKontrast = self::kontrast($t['accent-contrast'], $t['accent']);
        self::assertGreaterThanOrEqual(
            4.5, $textKontrast,
            sprintf('Farbe %s (%s): accent-contrast auf accent = %.2f:1', $hex, $modus, $textKontrast)
        );

        foreach ($flaechen as $flaeche) {
            $ratioAccent = self::kontrast($t['accent'], $flaeche);
            self::assertGreaterThanOrEqual(
                3.0, $ratioAccent,
                sprintf('Farbe %s (%s): accent auf Fläche %s = %.2f:1', $hex, $modus, implode(',', $flaeche), $ratioAccent)
            );

            $ratioText = self::kontrast($t['accent-text'], $flaeche);
            self::assertGreaterThanOrEqual(
                4.5, $ratioText,
                sprintf('Farbe %s (%s): accent-text auf Fläche %s = %.2f:1', $hex, $modus, implode(',', $flaeche), $ratioText)
            );
        }
    }

    #[Test]
    public function ungueltiger_hex_wird_erkannt(): void
    {
        foreach (['#fff', '#gggggg', 'rot', '#12345', '', null, '#1234567'] as $wert) {
            self::assertFalse(Farbe::istGueltigerHex($wert));
        }
        foreach (['#ffffff', '#000000', '#AbCdEf'] as $wert) {
            self::assertTrue(Farbe::istGueltigerHex($wert));
        }
    }

    #[Test]
    public function styleblock_ist_leer_bei_ungueltigem_hex(): void
    {
        self::assertSame('', Farbe::styleBlock('nicht-hex'));
        self::assertSame('', Farbe::styleBlock(null));
    }

    #[Test]
    public function styleblock_enthaelt_beide_selektoren_und_keinen_rohen_hex(): void
    {
        $block = Farbe::styleBlock('#abcdef');

        self::assertStringContainsString(":root[data-akzent='eigen']", $block);
        self::assertStringContainsString(".dark[data-akzent='eigen']", $block);
        self::assertStringNotContainsString('#abcdef', $block);
        self::assertStringNotContainsString('#ABCDEF', $block);
    }
}
