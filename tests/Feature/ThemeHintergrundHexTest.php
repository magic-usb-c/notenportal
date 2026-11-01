<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Theme;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Die Hex-Werte für theme-color und Manifest (Theme::HINTERGRUND) sind aus --bg in theme.css abgeleitet
 * und dürfen nicht davon abweichen.
 */
class ThemeHintergrundHexTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function themes(): array
    {
        $fall = [];
        foreach (array_keys(Theme::THEMES) as $theme) {
            foreach (['hell', 'dunkel'] as $modus) {
                $fall["$theme $modus"] = [$theme, $modus];
            }
        }

        return $fall;
    }

    private function bgTripel(string $theme, string $modus): string
    {
        $css = (string) file_get_contents(base_path('resources/css/theme.css'));
        $auswahl = $modus === 'dunkel' ? "[data-theme='$theme'].dark" : "[data-theme='$theme']";
        $start = strpos($css, $auswahl.' {');
        $this->assertNotFalse($start, "Block $auswahl fehlt in theme.css");
        $ende = strpos($css, '}', $start);
        $this->assertSame(1, preg_match('/--bg:\s*(\d+)\s+(\d+)\s+(\d+)\s*;/', substr($css, $start, $ende - $start), $m), "--bg fehlt in $auswahl");

        return sprintf('#%02X%02X%02X', $m[1], $m[2], $m[3]);
    }

    #[Test]
    #[DataProvider('themes')]
    public function hex_stimmt_mit_dem_bg_tripel_aus_theme_css_ueberein(string $theme, string $modus): void
    {
        $hex = $modus === 'dunkel' ? Theme::hintergrundDunkel($theme) : Theme::hintergrundHell($theme);

        $this->assertSame($this->bgTripel($theme, $modus), strtoupper($hex));
    }
}
