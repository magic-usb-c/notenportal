<?php

declare(strict_types=1);

namespace Tests\Feature\Statistik;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Die serverseitig gezeichneten Bausteine Punktstreifen und Zeitleiste: Leerzustand ohne tote Tabelle,
 * Legende mit Zeichen, Tastatur und Stufe nicht nur als Farbe (docs/auftrag/GUI-R6.md §134, Skill ui §11).
 */
class PunktstreifenZeitleisteTest extends TestCase
{
    /** @return array<string, mixed> Paket in der Form von StatistikAntwort::paket mit einem Teil */
    private function teil(string $schluessel, array $diagramm, array $tabelle, string $satz, string $titel): array
    {
        return [
            'filter' => [],
            'diagramm' => [$schluessel => $diagramm],
            'tabelle' => [$schluessel => $tabelle],
            'zusammenfassung' => [$schluessel => $satz],
            'meta' => [$schluessel => ['titel' => $titel]],
        ];
    }

    private function punktstreifen(array $noten, ?float $median): string
    {
        $punkte = [];
        foreach ($noten as $i => $note) {
            $punkte[] = ['id' => $i + 1, 'name' => 'Person '.($i + 1), 'gesamt' => $note, 'status' => 'gruen'];
        }
        $paket = $this->teil(
            'punktstreifen',
            ['punkte' => $punkte, 'median' => $median, 'n' => count($punkte), 'grenze' => 4.0],
            ['spalten' => ['Name', 'Gesamtschnitt', 'Status'], 'zeilen' => []],
            count($punkte) === 0 ? 'Keine Personen mit Note.' : count($punkte).' Personen.',
            'Wer braucht Aufmerksamkeit',
        );

        return $this->blade('<x-punktstreifen :paket="$paket" />', ['paket' => $paket])->__toString();
    }

    #[Test]
    public function zeitleiste_ohne_pruefungen_hat_keine_tote_tabellen_aufklappung(): void
    {
        $leer = $this->teil(
            'zeitleiste',
            ['von' => '2026-10-03', 'bis' => '2026-11-28', 'grenze' => 4.0, 'zeilen' => []],
            ['spalten' => ['Datum', 'Prüfung', 'Nötig'], 'zeilen' => []],
            'Keine Prüfungen im gewählten Zeitraum.',
            'Nächste Prüfungen',
        );
        $html = $this->blade('<x-zeitleiste :paket="$paket" />', ['paket' => $leer])->__toString();

        $this->assertStringContainsString('Keine Prüfungen im gewählten Zeitraum.', $html);
        $this->assertStringNotContainsString('Als Tabelle', $html);
        $this->assertStringNotContainsString('<details', $html);
        $this->assertStringNotContainsString('<table', $html);
    }

    #[Test]
    public function zeitleiste_mit_pruefung_behaelt_die_tabelle(): void
    {
        $paket = $this->teil(
            'zeitleiste',
            ['von' => '2026-10-03', 'bis' => '2026-11-28', 'grenze' => 4.0, 'zeilen' => [['titel' => 'Mathematik', 'datum' => '2026-10-20', 'status' => 'benoetigt', 'note' => 4.5, 'text' => 'benötigt 4.5']]],
            ['spalten' => ['Datum', 'Prüfung', 'Nötig'], 'zeilen' => [['20.10.2026', 'Mathematik', 'benötigt 4.5']]],
            'Nächste Prüfung am 20.10.2026, nötig sind 4.5.',
            'Nächste Prüfungen',
        );
        $html = $this->blade('<x-zeitleiste :paket="$paket" />', ['paket' => $paket])->__toString();

        $this->assertStringContainsString('Als Tabelle', $html);
        $this->assertStringContainsString('<table', $html);
    }

    #[Test]
    public function punktstreifen_ohne_personen_hat_keine_tote_tabellen_aufklappung(): void
    {
        $html = $this->punktstreifen([], null);

        $this->assertStringContainsString('Keine Personen mit Note.', $html);
        $this->assertStringNotContainsString('Als Tabelle', $html);
        $this->assertStringNotContainsString('<table', $html);
    }

    #[Test]
    public function punktstreifen_legende_hat_zeichen_und_die_medianlinie_eine_eigene_farbe(): void
    {
        $html = $this->punktstreifen([3.2, 4.4, 5.1], 4.4);

        $this->assertStringContainsString('<span class="w-4 border-t border-dashed border-muted"></span>', $html);
        $this->assertStringContainsString('<span class="h-3 w-0.5 rounded-full bg-text"></span>', $html);
        $this->assertSame(1, preg_match('/<rect[^>]*class="fill-text"/', $html), 'Medianlinie in Textfarbe');
        $this->assertStringNotContainsString('fill-chart-1"/>', $html, 'Medianlinie nicht in der Punktfarbe');

        $ohneMedian = $this->punktstreifen([4.4, 5.1], null);
        $this->assertStringNotContainsString('bg-text', $ohneMedian);
    }

    #[Test]
    public function punktstreifen_ist_per_tastatur_bedienbar_und_sagt_name_wert_und_stufe_an(): void
    {
        $html = $this->punktstreifen([3.2, 4.4, 5.1], 4.4);

        $this->assertMatchesRegularExpression('/<div[^>]*tabindex="0"[^>]*@keydown\.arrow-right\.prevent/', $html);
        foreach (['@keydown.arrow-left.prevent', '@keydown.home.prevent', '@keydown.end.prevent'] as $taste) {
            $this->assertStringContainsString($taste, $html);
        }
        // Texte der Punkte (der Breite nach sortiert) für die Ansage, mit Stufe
        $this->assertStringContainsString('Person 1, 3.2, ungenügend', html_entity_decode($html));
        $this->assertStringContainsString('Person 3, 5.1, gut', html_entity_decode($html));
        // Der Ansage-Absatz bleibt bei npFilter.baustein() stehen und hängt dann nicht mehr im neuen x-data-Geltungsbereich:
        // eine x-text-Bindung würde nach der ersten Filteränderung verstummen, die Tastatur schreibt den Text deshalb selbst
        $this->assertSame(1, preg_match('/<p[^>]*aria-live="polite"[^>]*data-np-ansage[^>]*>/', $html), 'Ansage-Absatz vorhanden');
        $this->assertDoesNotMatchRegularExpression('/<p[^>]*data-np-ansage[^>]*x-(text|html|bind)/', $html, 'Ansage ohne Alpine-Bindung');
        $this->assertStringContainsString('sag(this.texte[this.i])', html_entity_decode($html), 'Auswahl schreibt die Ansage direkt');
        $this->assertStringContainsString("querySelector('[data-np-ansage]')", html_entity_decode($html));
        $this->assertDoesNotMatchRegularExpression('/@blur="[^"]*sag\(/', $html, 'Verlassen des Streifens sagt nichts neu an');
        $this->assertStringContainsString('x-show="i >= 0"', $html);
    }

    #[Test]
    public function punktstreifen_zeigt_ungenuegende_zusaetzlich_als_hohlen_punkt(): void
    {
        $html = $this->punktstreifen([3.2, 5.1], null);

        $this->assertSame(1, substr_count($html, 'data-form="hohl"'), 'Nur der ungenügende Punkt ist hohl');
        $this->assertSame(1, preg_match('/<circle cx="[\d.]+"[^>]*data-form="hohl"[^>]*>\s*<title>Person 1, 3\.2<\/title>/', $html));
    }
}
