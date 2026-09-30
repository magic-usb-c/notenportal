<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Note;
use App\Models\User;
use App\Services\Bericht;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BerichtTest extends TestCase
{
    #[Test]
    public function bericht_laedt_fuer_alle_zeitraeume_und_sortierungen(): void
    {
        $admin = User::factory()->admin()->create();
        $lernender = User::factory()->lernender()->create(['nachname' => 'Berichtmann']);

        foreach ([[], ['semester' => 'alle'], ['sort' => 'gesamt', 'dir' => 'desc'], ['sort' => 'unbekannt']] as $parameter) {
            $this->actingAs($admin)
                ->get(route('admin.reports.grades', $parameter))
                ->assertOk()
                ->assertSee('Berichtmann');
        }
    }

    #[Test]
    public function export_enthaelt_lernende_und_status(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->lernender()->create(['nachname' => 'Berichtmann']);

        $csv = $this->actingAs($admin)->get(route('admin.reports.grades.export', ['semester' => 'alle']))->assertOk()->streamedContent();

        $this->assertStringContainsString('Berichtmann', $csv);
        $this->assertStringContainsString('Gesamtnote', $csv);
    }

    #[Test]
    public function verteilung_der_zeugnisnoten_enthaelt_tabellenalternative(): void
    {
        $admin = User::factory()->admin()->create();
        $lernender = User::factory()->lernender()->create();
        Note::factory()->create(['lernender_id' => $lernender->lernender->lernender_id, 'note_wert' => 4.5]);

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.grades', ['semester' => 'alle']))
            ->assertOk()
            ->assertSee('Verteilung der Zeugnisnoten')
            ->assertSee('Als Tabelle');

        $response->assertSeeInOrder(['Note', 'Zeugnisnoten', 'Stufe', '4.5', '1', 'genügend']);
    }

    #[Test]
    public function verteilung_der_zeugnisnoten_zeigt_stufenlegende(): void
    {
        $admin = User::factory()->admin()->create();
        $lernender = User::factory()->lernender()->create();
        Note::factory()->create(['lernender_id' => $lernender->lernender->lernender_id, 'note_wert' => 4.5]);

        $this->actingAs($admin)
            ->get(route('admin.reports.grades', ['semester' => 'alle']))
            ->assertOk()
            ->assertSeeInOrder(['ungenügend', 'knapp', 'genügend', 'gut']);
    }

    /**
     * Das Histogramm färbt jede Klasse nach ihrer Untergrenze (charts.js). Mit Rundung 0.1 fiel 3.8 vorher per
     * round() in die Klasse 4.0 und stand damit neutral im genügenden Bereich, obwohl das Fazit sie als ungenügend zählt.
     */
    #[Test]
    public function histogramm_legt_eine_zeugnisnote_in_die_klasse_ihrer_untergrenze(): void
    {
        $lernender = User::factory()->lernender()->create()->lernender;
        $note = Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'note_wert' => 3.8]);
        Kategorie::query()->whereKey($note->fach->kategorie_id)->update(['rundung_element' => 0.1]);

        $verteilung = app(Bericht::class)->noten(['semester_id' => null, 'lehrberuf_id' => null, 'berufsbildner_id' => null])['verteilung'];
        $klassen = array_combine($verteilung['labels'], $verteilung['werte']);

        $this->assertSame(1, $klassen['3.5']);
        $this->assertSame(0, $klassen['4.0']);
        $this->assertSame(10, count($klassen) - 1, 'Klassen 1.0 bis 6.0 in Halbnotenschritten');
    }

    /** @return array<string, array{0: float, 1: string}> */
    public static function klassen(): array
    {
        return [
            'Untergrenze 1.0' => [1.0, '1.0'],
            '3.8 als 38 × 0.1' => [38 * 0.1, '3.5'],
            'genau 4.0' => [4.0, '4.0'],
            '4.0 knapp darunter wie NotenSkala::stufe' => [3.9999999993, '4.0'],
            '4.49999 aus einem Schnitt' => [4.49999, '4.0'],
            '4.5 knapp darunter' => [4.4999999996, '4.5'],
            'genau 6.0' => [6.0, '6.0'],
            '6.0 als 60 × 0.1' => [60 * 0.1, '6.0'],
        ];
    }

    #[Test]
    #[DataProvider('klassen')]
    public function histogrammklasse_ist_die_untergrenze_mit_derselben_toleranz_wie_die_notenstufe(float $note, string $klasse): void
    {
        $this->assertSame($klasse, Bericht::klasse($note));
    }

    #[Test]
    public function nicht_zaehlendes_fach_gilt_im_bericht_nicht_als_ungenuegend(): void
    {
        $lernender = User::factory()->lernender()->create()->lernender;
        $idaf = Fach::factory()->create(['zaehlt' => false]);
        Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'fach_id' => $idaf->fach_id, 'note_wert' => 2.0]);
        Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'note_wert' => 5.0]);

        $zeile = app(Bericht::class)->noten(['semester_id' => null, 'lehrberuf_id' => null, 'berufsbildner_id' => null])['zeilen']
            ->firstWhere('id', (int) $lernender->lernender_id);

        $this->assertSame(0, $zeile->ungenuegend);
    }

    #[Test]
    public function gesamtschnitt_nach_lehrjahr_gruppiert_und_zaehlt_lernende(): void
    {
        Carbon::setTestNow('2026-09-11 10:00:00');

        $admin = User::factory()->admin()->create();

        // Lehrjahr 1: zwei Lernende mit Noten 4.0 und 6.0 → Schnitt 5.0, 2 Lernende.
        $a = User::factory()->lernender(['lehrbeginn' => '2026-08-01'])->create();
        Note::factory()->create(['lernender_id' => $a->lernender->lernender_id, 'note_wert' => 4.0]);
        $b = User::factory()->lernender(['lehrbeginn' => '2026-08-15'])->create();
        Note::factory()->create(['lernender_id' => $b->lernender->lernender_id, 'note_wert' => 6.0]);

        // Lehrjahr 2: eine Lernende mit Note 5.0 → Schnitt 5.0, 1 Lernende.
        $c = User::factory()->lernender(['lehrbeginn' => '2025-08-01'])->create();
        Note::factory()->create(['lernender_id' => $c->lernender->lernender_id, 'note_wert' => 5.0]);

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.grades', ['semester' => 'alle']))
            ->assertOk()
            ->assertSee('Gesamtschnitt nach Lehrjahr')
            ->assertSee('1. Lehrjahr')
            ->assertSee('2. Lehrjahr');

        $response->assertSeeInOrder(['1. Lehrjahr', '5.0', '2', '2. Lehrjahr', '5.0', '1']);

        Carbon::setTestNow();
    }

    /**
     * Schmal (Handy, Seitenleiste) blendet die Tabelle Status, Ungenügend, Prüfungen und Letzte Note aus. Die Werte
     * bleiben in der Namenszelle stehen – vorher sah man dort nur den Status, die Gründe fehlten ganz.
     */
    #[Test]
    public function namenszelle_nennt_die_werte_der_schmal_ausgeblendeten_spalten(): void
    {
        $admin = User::factory()->admin()->create();
        $lernender = User::factory()->lernender()->create(['nachname' => 'Schmalmann'])->lernender;
        $note = Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'note_wert' => 3.0]);

        // Mit Semesterfilter, damit auch die Semesterspalte vorkommt
        $html = (string) $this->actingAs($admin)->get(route('admin.reports.grades', ['semester_id' => $note->semester_id]))->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8">'.$html, LIBXML_NOERROR);
        $xpath = new \DOMXPath($dom);
        $zeile = $xpath->query('//tr[td/a[contains(., "Schmalmann")]]')->item(0);
        $this->assertNotNull($zeile);
        $text = fn (\DOMNode $n) => trim((string) preg_replace('/\s+/u', ' ', $n->textContent));
        $namenszelle = $text($xpath->query('td[1]', $zeile)->item(0));

        $this->assertStringContainsString(__(':anzahl ungenügend', ['anzahl' => 1]), $namenszelle);
        $this->assertStringContainsString(__('1 Prüfung'), $namenszelle);

        // Jede schmal ausgeblendete Spalte: jeder ihrer Werte (Status, Gründe, Semester, Ungenügend, Prüfungen, letzte Note) steht in der Namenszelle
        $spalten = $xpath->query('td[contains(@class, "hidden") and contains(@class, ":table-cell")]', $zeile);
        $this->assertSame(5, $spalten->length, 'Status, Semester, Ungenügend, Prüfungen, letzte Note');
        foreach ($spalten as $td) {
            $teile = $xpath->query('*', $td)->length ? iterator_to_array($xpath->query('*', $td)) : [$td];
            foreach ($teile as $teil) {
                $wert = $text($teil);
                $this->assertNotSame('', $wert);
                $this->assertStringContainsString($wert, $namenszelle, "Schmal fehlt «{$wert}»");
            }
        }
    }
}
