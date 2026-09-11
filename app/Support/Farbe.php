<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Eigene Akzentfarbe (Profil «Darstellung», Feld benutzer.praeferenzen.akzent_eigen). Aus einem
 * vom Benutzer gewählten Hex-Wert werden Tokens für hell UND dunkel abgeleitet – gleiche
 * Tokennamen/Formate (RGB-Tripel) wie die festen [data-akzent]-Blöcke in resources/css/theme.css.
 *
 * Kontrastgarantie (schrittweise Helligkeit in HSL angepasst, Farbton/Sättigung bleiben):
 * - --accent auf --bg/--card/--surface-2 jedes Nicht-Kontrast-Themes ≥ 3:1 (UI-Komponenten)
 * - --accent-contrast (Text) auf --accent ≥ 4.5:1
 * - --accent-text (Textfarbe) auf --bg/--card/--surface-2 jedes Nicht-Kontrast-Themes ≥ 4.5:1
 * Da beide Ziele mit zunehmender Verdunklung (hell) bzw. Aufhellung (dunkel) monoton leichter
 * erfüllbar werden (Extremwert Schwarz/Weiss erfüllt sie immer), terminiert die Suche garantiert.
 */
final class Farbe
{
    private const string HEX_MUSTER = '/^#[0-9a-f]{6}$/i';

    /** Textfarbe auf der Akzentfläche je Modus – wie bei den festen Akzenten (hell: Weiss, dunkel: nahezu Schwarz). */
    private const array KONTRASTFARBE = [
        'hell' => [255, 255, 255],
        'dunkel' => [12, 15, 22],
    ];

    private const float SCHRITT = 0.01;

    /** @var array<string, list<array{0:int,1:int,2:int}>> */
    private static array $flaechenCache = [];

    public static function istGueltigerHex(?string $wert): bool
    {
        return is_string($wert) && preg_match(self::HEX_MUSTER, $wert) === 1;
    }

    /**
     * @return array{accent: array{0:int,1:int,2:int}, 'accent-contrast': array{0:int,1:int,2:int}, 'accent-text': array{0:int,1:int,2:int}, ring: array{0:int,1:int,2:int}, 'chart-1': array{0:int,1:int,2:int}}
     */
    public static function tokens(string $hex, string $modus): array
    {
        $modus = $modus === 'dunkel' ? 'dunkel' : 'hell';
        [$h, $s, $l] = self::rgbZuHsl(self::hexZuRgb($hex));
        $flaechen = self::flaechen($modus);
        $kontrastfarbe = self::KONTRASTFARBE[$modus];

        $accentL = self::passendeHelligkeit($h, $s, $l, $modus, $flaechen, 3.0, $kontrastfarbe);
        $accent = self::hslZuRgb($h, $s, $accentL);

        $textL = self::passendeHelligkeit($h, $s, $l, $modus, $flaechen, 4.5, null);
        $accentText = self::hslZuRgb($h, $s, $textL);

        return [
            'accent' => $accent,
            'accent-contrast' => $kontrastfarbe,
            'accent-text' => $accentText,
            'ring' => $accentText,
            'chart-1' => $accent,
        ];
    }

    /**
     * <style>-Block fürs <head>, gleiches Format wie die festen [data-akzent]-Blöcke in theme.css
     * (RGB-Tripel). Leer bei ungültigem Hex – der aufrufende View gibt dann nichts aus.
     */
    public static function styleBlock(?string $hex): string
    {
        if (! self::istGueltigerHex($hex)) {
            return '';
        }

        $hell = self::css(self::tokens($hex, 'hell'));
        $dunkel = self::css(self::tokens($hex, 'dunkel'));

        return ":root[data-akzent='eigen']{{$hell}}.dark[data-akzent='eigen']{{$dunkel}}";
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    private static function css(array $tokens): string
    {
        $teile = [];
        foreach ($tokens as $name => $rgb) {
            $teile[] = sprintf('--%s:%d %d %d', $name, $rgb[0], $rgb[1], $rgb[2]);
        }

        return implode(';', $teile).';';
    }

    /**
     * Sucht die nächste Helligkeit (HSL L, 0..1) ausgehend von $lStart in Richtung Schwarz (hell)
     * bzw. Weiss (dunkel), bei der sowohl der Flächenkontrast (--accent auf jede Fläche in
     * $flaechen ≥ $flaechenZiel) als auch – falls $textAufAkzent gesetzt – der Textkontrast
     * (Text auf die Kandidatenfarbe ≥ 4.5:1) erfüllt sind.
     *
     * @param  list<array{0:int,1:int,2:int}>  $flaechen
     * @param  ?array{0:int,1:int,2:int}  $textAufAkzent
     */
    private static function passendeHelligkeit(
        float $h,
        float $s,
        float $lStart,
        string $modus,
        array $flaechen,
        float $flaechenZiel,
        ?array $textAufAkzent
    ): float {
        $richtungAbwaerts = $modus === 'hell';
        $grenze = $richtungAbwaerts ? 0.0 : 1.0;
        $l = max(0.0, min(1.0, $lStart));

        while (true) {
            $rgb = self::hslZuRgb($h, $s, $l);

            $flaechenOk = true;
            foreach ($flaechen as $flaeche) {
                if (self::kontrast($rgb, $flaeche) < $flaechenZiel) {
                    $flaechenOk = false;
                    break;
                }
            }

            $textOk = $textAufAkzent === null || self::kontrast($textAufAkzent, $rgb) >= 4.5;

            if ($flaechenOk && $textOk) {
                return $l;
            }

            if ($l === $grenze) {
                // Schwarz/Weiss erfüllt beide Ziele rechnerisch immer – Sicherheitsnetz gegen Endlosschleifen.
                return $grenze;
            }

            $l = $richtungAbwaerts ? max($grenze, $l - self::SCHRITT) : min($grenze, $l + self::SCHRITT);
        }
    }

    /**
     * --bg/--card/--surface-2 aller Nicht-Kontrast-Themes für einen Modus, aus theme.css gelesen
     * (gleiche Prüfgrundlage wie der Kommentar über den festen [data-akzent]-Blöcken). Ohne
     * lesbare Datei ein konservativer Rückfall (Weiss/Schwarz), niemals ein Fehler.
     *
     * @return list<array{0:int,1:int,2:int}>
     */
    private static function flaechen(string $modus): array
    {
        if (isset(self::$flaechenCache[$modus])) {
            return self::$flaechenCache[$modus];
        }

        $flaechen = [];
        // Kein resource_path(): die Klasse muss auch ohne gebooteten Container lesbar sein
        // (tests/Feature/FarbeKontrastTest.php ist ein reiner PHPUnit-Test wie ThemeKontrastTest).
        $css = @file_get_contents(dirname(__DIR__, 2).'/resources/css/theme.css');

        if ($css !== false) {
            preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $css, $bloecke, PREG_SET_ORDER);
            foreach ($bloecke as $block) {
                $selektor = trim(preg_replace('#/\*.*?\*/#s', '', $block[1]));
                if (! preg_match("/\\[data-theme='([a-z]+)'\\]/", $selektor, $treffer)) {
                    continue;
                }
                if ($treffer[1] === Theme::KONTRAST) {
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
        }

        if ($flaechen === []) {
            $flaechen[] = $modus === 'hell' ? [255, 255, 255] : [0, 0, 0];
        }

        return self::$flaechenCache[$modus] = $flaechen;
    }

    /** @return array{0:int,1:int,2:int} */
    private static function hexZuRgb(string $hex): array
    {
        return [
            (int) hexdec(substr($hex, 1, 2)),
            (int) hexdec(substr($hex, 3, 2)),
            (int) hexdec(substr($hex, 5, 2)),
        ];
    }

    /**
     * @param  array{0:int,1:int,2:int}  $rgb
     * @return array{0:float,1:float,2:float} [Farbton 0..360, Sättigung 0..1, Helligkeit 0..1]
     */
    private static function rgbZuHsl(array $rgb): array
    {
        [$r, $g, $b] = array_map(static fn (int $c): float => $c / 255, $rgb);
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0, $l];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        $h = match ($max) {
            $r => (($g - $b) / $d) + ($g < $b ? 6 : 0),
            $g => (($b - $r) / $d) + 2,
            default => (($r - $g) / $d) + 4,
        };
        $h *= 60;

        return [$h, $s, $l];
    }

    /** @return array{0:int,1:int,2:int} */
    private static function hslZuRgb(float $h, float $s, float $l): array
    {
        if ($s === 0.0) {
            $v = (int) round($l * 255);

            return [$v, $v, $v];
        }

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $hk = $h / 360;

        $kanal = static function (float $p, float $q, float $t): float {
            if ($t < 0) {
                $t += 1;
            }
            if ($t > 1) {
                $t -= 1;
            }
            if ($t < 1 / 6) {
                return $p + ($q - $p) * 6 * $t;
            }
            if ($t < 1 / 2) {
                return $q;
            }
            if ($t < 2 / 3) {
                return $p + ($q - $p) * (2 / 3 - $t) * 6;
            }

            return $p;
        };

        return [
            (int) round($kanal($p, $q, $hk + 1 / 3) * 255),
            (int) round($kanal($p, $q, $hk) * 255),
            (int) round($kanal($p, $q, $hk - 1 / 3) * 255),
        ];
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
}
