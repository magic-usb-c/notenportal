<?php

declare(strict_types=1);

namespace Tests\Feature\Heatmap;

use App\Support\NotenSkala;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Funktionsprüfung R6: Stufe nie nur als Farbe (knapp trägt eine Form), Heatmap als Tabelle
 * mit Beschriftung, rechtsbündigen Zahlen und ▼ nur für Sehende.
 */
class HeatmapStufenFormTest extends TestCase
{
    private function daten(): array
    {
        return [
            'semester' => [['id' => 1, 'name' => '1. Sem.'], ['id' => 2, 'name' => '2. Sem.']],
            'gruppen' => [[
                'name' => 'Fachunterricht',
                'semester' => [1 => 3.0, 2 => 3.8],
                'note' => 3.4,
                'zeilen' => [[
                    'label' => 'Modul Muster',
                    'offen' => false,
                    'zellen' => [1 => 3.0, 2 => 3.8],
                    'lehrzeit' => 3.4,
                ]],
            ]],
            'semesterschnitt' => [1 => 3.0, 2 => 3.8],
            'gesamt' => 3.4,
        ];
    }

    public function test_knapp_traegt_in_text_und_badge_eine_eigene_form(): void
    {
        foreach (['text', 'badge'] as $art) {
            $knapp = NotenSkala::$art(3.8);
            $ungenuegend = NotenSkala::$art(3.0);

            $this->assertStringContainsString('underline', $knapp, "knapp ($art) braucht eine Form");
            $this->assertStringContainsString('decoration-dotted', $knapp);
            $this->assertStringNotContainsString('decoration-dotted', $ungenuegend);
            $this->assertStringContainsString('underline', $ungenuegend);
            $this->assertNotSame($knapp, $ungenuegend);
        }
    }

    public function test_gut_und_genuegend_bleiben_ohne_form(): void
    {
        foreach ([5.5, 4.5] as $note) {
            $this->assertStringNotContainsString('underline', NotenSkala::text($note));
            $this->assertStringNotContainsString('underline', NotenSkala::badge($note));
        }
    }

    public function test_heatmap_hat_beschriftung_rechtsbuendige_zahlen_und_verborgene_marke(): void
    {
        $html = Blade::render('<x-heatmap :daten="$d" />', ['d' => $this->daten()]);

        $this->assertStringContainsString('<caption class="sr-only">'.__('Zeugnisnoten').'</caption>', $html);
        $this->assertStringNotContainsString('text-center', $html, 'Zahlenspalten gehören rechtsbündig');
        $this->assertStringContainsString('text-right', $html);

        // Jedes ▼ steht in einem aria-hidden-Span, nie lose im vorgelesenen Text.
        $anzahl = substr_count($html, '▼');
        $this->assertGreaterThan(0, $anzahl);
        $this->assertSame($anzahl, substr_count($html, '<span aria-hidden="true">▼ </span>'));
    }
}
