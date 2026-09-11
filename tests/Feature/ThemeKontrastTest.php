<?php

namespace Tests\Feature;

use App\Support\Darstellung;
use App\Support\Theme;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regressionstest: parst resources/css/theme.css (reines PHP, keine DB/App nötig) und prüft
 * die WCAG-2.x-Kontraste (sRGB-Luminanz, wie ~/tools/kontrast/kontrast.mjs) aller Themes und
 * Akzentfarben gegen die Ziele aus dem GUI-Konzept: Text 4.5:1 (Theme Kontrast: text/muted
 * 7:1), UI/Grafik 3:1.
 */
class ThemeKontrastTest extends TestCase
{
    /** @var array<string, array<string, array<string, array{0:int,1:int,2:int}>>>|null */
    private static ?array $themes = null;

    /** @var array<string, array<string, array<string, array{0:int,1:int,2:int}>>>|null */
    private static ?array $akzente = null;

    /** @return array{0: array<string, array<string, array<string, array{0:int,1:int,2:int}>>>, 1: array<string, array<string, array<string, array{0:int,1:int,2:int}>>>} */
    private static function geparst(): array
    {
        if (self::$themes !== null && self::$akzente !== null) {
            return [self::$themes, self::$akzente];
        }

        $pfad = dirname(__DIR__, 2).'/resources/css/theme.css';
        $css = file_get_contents($pfad);
        self::assertNotFalse($css, "theme.css nicht lesbar: {$pfad}");

        $themes = [];
        $akzente = [];

        preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $css, $bloecke, PREG_SET_ORDER);
        foreach ($bloecke as $block) {
            // Der Selektor-Teil des Regex-Treffers schliesst vorangehende CSS-Kommentare ein
            // (z. B. den Datei-Kopfkommentar, der selbst die Zeichenkette ".dark" enthält) –
            // erst die Kommentare entfernen, dann den eigentlichen Selektor auswerten.
            $selektor = trim(preg_replace('#/\*.*?\*/#s', '', $block[1]));
            $body = $block[2];

            preg_match_all('/--([a-z0-9-]+):\s*(\d{1,3})\s+(\d{1,3})\s+(\d{1,3})\s*;/', $body, $tokenTreffer, PREG_SET_ORDER);
            if ($tokenTreffer === []) {
                continue;
            }
            $tokens = [];
            foreach ($tokenTreffer as $t) {
                $tokens[$t[1]] = [(int) $t[2], (int) $t[3], (int) $t[4]];
            }

            $dunkel = str_contains($selektor, '.dark');
            $modus = $dunkel ? 'dunkel' : 'hell';

            if (preg_match("/\\[data-theme='([a-z]+)'\\]/", $selektor, $m)) {
                $themes[$m[1]][$modus] = array_merge($themes[$m[1]][$modus] ?? [], $tokens);
            }
            if (preg_match("/\\[data-akzent='([a-z]+)'\\]/", $selektor, $m)) {
                $akzente[$m[1]][$modus] = array_merge($akzente[$m[1]][$modus] ?? [], $tokens);
            }
        }

        self::assertNotEmpty($themes, 'Keine Themes in theme.css gefunden.');
        self::assertNotEmpty($akzente, 'Keine Akzentfarben in theme.css gefunden.');

        return [self::$themes = $themes, self::$akzente = $akzente];
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

    /** @return array<string, array{0: string, 1: string}> */
    public static function themeUndModus(): array
    {
        $out = [];
        foreach (array_keys(Theme::THEMES) as $theme) {
            foreach (['hell', 'dunkel'] as $modus) {
                $out["{$theme} {$modus}"] = [$theme, $modus];
            }
        }

        return $out;
    }

    #[Test]
    #[DataProvider('themeUndModus')]
    public function alle_paare_eines_themes_erfuellen_die_kontrastziele(string $theme, string $modus): void
    {
        [$themes] = self::geparst();
        self::assertArrayHasKey($theme, $themes, "Theme «{$theme}» fehlt in theme.css.");
        self::assertArrayHasKey($modus, $themes[$theme], "Theme «{$theme}» hat keinen {$modus}-Block.");
        $t = $themes[$theme][$modus];

        $paare = [
            ['text', 'bg'], ['text', 'card'], ['text', 'surface-2'],
            ['muted', 'card'], ['muted', 'bg'], ['muted', 'surface-2'],
            ['accent-contrast', 'accent'], ['accent-text', 'card'], ['accent-text', 'bg'],
            ['note-gut', 'card'], ['note-genuegend', 'card'], ['note-knapp', 'card'], ['note-ungenuegend', 'card'],
        ];

        foreach ($paare as [$fg, $bg]) {
            self::assertArrayHasKey($fg, $t, "Theme «{$theme}» ({$modus}): Token --{$fg} fehlt.");
            self::assertArrayHasKey($bg, $t, "Theme «{$theme}» ({$modus}): Token --{$bg} fehlt.");

            $ziel = ($theme === Theme::KONTRAST && in_array($fg, ['text', 'muted'], true)) ? 7.0 : 4.5;
            $ratio = self::kontrast($t[$fg], $t[$bg]);

            self::assertGreaterThanOrEqual(
                $ziel,
                $ratio,
                sprintf('Theme «%s» (%s): --%s auf --%s = %.2f:1, Ziel %.1f:1', $theme, $modus, $fg, $bg, $ratio, $ziel)
            );
        }
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function akzentUndModus(): array
    {
        $out = [];
        foreach (array_keys(Darstellung::AKZENTE) as $akzent) {
            foreach (['hell', 'dunkel'] as $modus) {
                $out["{$akzent} {$modus}"] = [$akzent, $modus];
            }
        }

        return $out;
    }

    #[Test]
    #[DataProvider('akzentUndModus')]
    public function jede_akzentfarbe_erfuellt_die_kontrastziele_auf_allen_nicht_kontrast_themes(string $akzent, string $modus): void
    {
        [$themes, $akzente] = self::geparst();
        self::assertArrayHasKey($akzent, $akzente, "Akzentfarbe «{$akzent}» fehlt in theme.css.");
        self::assertArrayHasKey($modus, $akzente[$akzent], "Akzentfarbe «{$akzent}» hat keinen {$modus}-Block.");
        $a = $akzente[$akzent][$modus];

        foreach (['accent', 'accent-contrast', 'accent-text', 'ring'] as $token) {
            self::assertArrayHasKey($token, $a, "Akzentfarbe «{$akzent}» ({$modus}): Token --{$token} fehlt.");
        }

        $ratioKontrast = self::kontrast($a['accent-contrast'], $a['accent']);
        self::assertGreaterThanOrEqual(4.5, $ratioKontrast, sprintf('Akzent «%s» (%s): --accent-contrast auf --accent = %.2f:1', $akzent, $modus, $ratioKontrast));

        $nichtKontrast = array_diff(array_keys(Theme::THEMES), [Theme::KONTRAST]);
        foreach ($nichtKontrast as $theme) {
            self::assertArrayHasKey($theme, $themes, "Theme «{$theme}» fehlt in theme.css.");
            $t = $themes[$theme][$modus];

            foreach (['bg', 'card', 'surface-2'] as $flaeche) {
                $ratioText = self::kontrast($a['accent-text'], $t[$flaeche]);
                self::assertGreaterThanOrEqual(4.5, $ratioText, sprintf(
                    'Akzent «%s» (%s): --accent-text auf %s.--%s = %.2f:1', $akzent, $modus, $theme, $flaeche, $ratioText
                ));

                $ratioRing = self::kontrast($a['ring'], $t[$flaeche]);
                self::assertGreaterThanOrEqual(3.0, $ratioRing, sprintf(
                    'Akzent «%s» (%s): --ring auf %s.--%s = %.2f:1', $akzent, $modus, $theme, $flaeche, $ratioRing
                ));
            }
        }
    }
}
