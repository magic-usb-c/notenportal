<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\Console\Commands\I18nScan;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * app/Console/Commands/I18nScan.php: Kernlogik (I18nScan::funde) ohne echte Views/DB testen –
 * ein artisan-Lauf hinge sonst vom aktuellen, sich ändernden Inhalt von resources/views ab.
 */
class I18nScanTest extends TestCase
{
    #[Test]
    public function findet_textknoten_ausserhalb_von_uebersetzungsfunktionen(): void
    {
        [$knoten] = I18nScan::funde('<div>Guten Tag zusammen</div>');

        $this->assertSame(['Guten Tag zusammen'], $knoten);
    }

    #[Test]
    public function text_in_doppelten_geschweiften_klammern_wird_ignoriert(): void
    {
        [$knoten] = I18nScan::funde('<div>{{ __(\'Übersetzt\') }}</div>');

        $this->assertSame([], $knoten);
    }

    #[Test]
    public function blade_kommentare_php_script_style_und_svg_werden_entfernt(): void
    {
        $blade = '{{-- Notiz auf Deutsch --}}'
            .'<script>var text = "Nicht anzeigen";</script>'
            .'<style>.a::before{content:"Nicht anzeigen"}</style>'
            .'<svg><title>Nicht anzeigen</title></svg>'
            .'@php $x = "Nicht anzeigen"; @endphp'
            .'<div>Sichtbarer Text</div>';

        [$knoten] = I18nScan::funde($blade);

        $this->assertSame(['Sichtbarer Text'], $knoten);
    }

    #[Test]
    public function blade_direktiven_mit_und_ohne_klammern_werden_entfernt(): void
    {
        [$knoten] = I18nScan::funde('@if($bedingung) Bedingter Text @endif @csrf');

        $this->assertSame(['Bedingter Text'], $knoten);
    }

    #[Test]
    public function relevante_attribute_werden_erfasst_andere_nicht(): void
    {
        $blade = '<input placeholder="Suchbegriff eingeben" data-info="Nicht erfasst" title="Weitere Erklärung">';

        [$knoten, $attribute] = I18nScan::funde($blade);

        $this->assertSame([], $knoten);
        $this->assertSame(['[Suchbegriff eingeben]', '[Weitere Erklärung]'], $attribute);
    }

    #[Test]
    public function zahlen_satzzeichen_und_marken_zaehlen_nicht_als_text(): void
    {
        [$knoten] = I18nScan::funde('<span>123</span><span>–</span><span>Notenportal</span><span>PDF</span>');

        $this->assertSame([], $knoten);
    }
}
