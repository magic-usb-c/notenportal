<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Lehrberuf;
use App\Models\Note;
use App\Models\Semester;
use App\Models\User;
use App\Services\Bericht;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
    public function kachel_kritisch_oeffnet_die_lernendenliste_mit_denselben_filtern(): void
    {
        $admin = User::factory()->admin()->create();
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $bb = User::factory()->berufsbildner()->create()->berufsbildner->berufsbildner_id;

        $mitFilter = route('admin.learners.index', ['warnung' => 'kritisch', 'lehrberuf_id' => $lehrberuf, 'berufsbildner_id' => $bb]);
        $this->actingAs($admin)
            ->get(route('admin.reports.grades', ['semester' => 'alle', 'lehrberuf_id' => $lehrberuf, 'berufsbildner_id' => $bb]))
            ->assertOk()
            ->assertSee('href="'.e($mitFilter).'"', false);

        $ohneFilter = route('admin.learners.index', ['warnung' => 'kritisch']);
        $this->get(route('admin.reports.grades', ['semester' => 'alle']))
            ->assertOk()
            ->assertSee('href="'.e($ohneFilter).'"', false);
    }

    #[Test]
    public function kachel_ungenuegend_sortiert_die_lernendentabelle_und_springt_dorthin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.reports.grades', ['semester' => 'alle']))
            ->assertOk()
            ->assertSee('sort=ungenuegend&amp;dir=desc#lernende"', false)
            ->assertSee('id="lernende"', false);

        // Schon so sortiert: nur noch zur Tabelle springen, ohne die Seite neu zu laden
        $this->get(route('admin.reports.grades', ['semester' => 'alle', 'sort' => 'ungenuegend', 'dir' => 'desc']))
            ->assertOk()
            ->assertSee('href="#lernende"', false);
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

        $this->assertSame(1, $klassen['3.75']);
        $this->assertSame(0, $klassen['4.00']);
        $this->assertSame(20, count($klassen), 'Klassen 1.00 bis 5.75 in Viertelnotenschritten, die letzte schliesst 6.0 ein');
    }

    #[Test]
    public function lehrjahresvergleich_behaelt_mit_kategoriefilter_den_lehrzeitbezug(): void
    {
        $kategorieId = (int) Kategorie::where('code', 'FACH')->value('kategorie_id');
        $lehrberuf = Lehrberuf::factory()->create();
        $fach = Fach::factory()->create(['track_typ' => null, 'kategorie_id' => $kategorieId]);
        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $lehrberuf->lehrberuf_id, 'fach_id' => $fach->fach_id, 'aktiv' => 1]);
        $start = Carbon::parse('2020-08-01');
        $semester = [];
        foreach ([1, 2] as $i) {
            $ende = $start->copy()->addMonths(6)->subDay();
            $semester[] = Semester::factory()->create(['bezeichnung' => 'S'.$i.'-lj', 'start_datum' => $start->toDateString(), 'end_datum' => $ende->toDateString(), 'sortierung' => $i]);
            $start = $ende->copy()->addDay();
        }
        foreach ([[4.0, 5.0], [5.0, 4.0], [3.0, 3.5]] as [$erstes, $zweites]) {
            $l = User::factory()->lernender(['lehrberuf_id' => $lehrberuf->lehrberuf_id, 'lehrbeginn' => now()->subMonths(18)->toDateString(), 'lehrende' => now()->addMonths(30)->toDateString()])->create()->lernender;
            foreach ([[$semester[0], $erstes], [$semester[1], $zweites]] as [$s, $wert]) {
                Note::factory()->create(['lernender_id' => $l->lernender_id, 'kategorie_id' => $kategorieId, 'semester_id' => $s->semester_id, 'fach_id' => $fach->fach_id,
                    'pruefungsdatum' => Carbon::parse($s->start_datum)->addDays(10)->toDateString(), 'note_wert' => $wert, 'gewichtung_prozent' => 100]);
            }
        }
        $bericht = app(Bericht::class);
        $sid = (int) $semester[1]->semester_id;
        $ohne = $bericht->noten(['semester_id' => $sid, 'lehrberuf_id' => null, 'berufsbildner_id' => null]);
        $mit = $bericht->noten(['semester_id' => $sid, 'lehrberuf_id' => null, 'berufsbildner_id' => null, 'kategorie_id' => $kategorieId]);

        // Eine einzige Kategorie: Kategorienote der Lehrzeit = Gesamtnote der Lehrzeit, nicht die Semesternote
        $this->assertSame($ohne['nachLehrjahr'], $mit['nachLehrjahr']);
        $this->assertSame([2], array_column($mit['nachLehrjahr'], 'jahr'));
        $this->assertCount(3, $mit['nachLehrjahr'][0]['werte']);
        $this->assertNotNull($mit['nachLehrjahr'][0]['median']);
        $this->assertNotSame([5.0, 4.0, 3.5], $mit['nachLehrjahr'][0]['werte'], 'nicht die Semesternoten');

        $alsText = $bericht->noten(['semester_id' => null, 'lehrberuf_id' => null, 'berufsbildner_id' => null, 'lehrjahr' => '2']);
        $this->assertCount(3, $alsText['zeilen'], 'lehrjahr aus der Query kommt als Text und zählt trotzdem');
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

        // Lehre abgeschlossen: zählt in keinem Lehrjahr, statt als «5. Lehrjahr» aufzutauchen
        $d = User::factory()->lernender(['lehrbeginn' => '2022-08-01', 'lehrende' => '2026-07-31'])->create();
        Note::factory()->create(['lernender_id' => $d->lernender->lernender_id, 'note_wert' => 3.0]);

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.grades', ['semester' => 'alle']))
            ->assertOk()
            ->assertSee('Gesamtschnitt nach Lehrjahr')
            ->assertSee('1. Lehrjahr')
            ->assertSee('2. Lehrjahr')
            ->assertDontSee('5. Lehrjahr');

        $response->assertSeeInOrder(['1. Lehrjahr', '2', '5.0', '2. Lehrjahr', '1', '5.0']);
        $this->assertNull($d->lernender->fresh()->lehrjahr());

        Carbon::setTestNow();
    }

    /** Jeder Wert steht in seiner eigenen Spalte, die Zeile führt ins Profil, «Noten» in die Notenliste des Zeitraums. */
    #[Test]
    public function jede_spalte_steht_in_eigener_zelle_und_die_zeile_oeffnet_das_profil(): void
    {
        $admin = User::factory()->admin()->create();
        $lernender = User::factory()->lernender()->create(['nachname' => 'Spaltmann'])->lernender;
        $note = Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'note_wert' => 3.0]);

        $html = (string) $this->actingAs($admin)->get(route('admin.reports.grades', ['semester' => $note->semester_id]))->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8">'.$html, LIBXML_NOERROR);
        $xpath = new \DOMXPath($dom);
        $zeile = $xpath->query('//tr[td//a[contains(., "Spaltmann")]]')->item(0);
        $this->assertNotNull($zeile);
        $this->assertSame(route('admin.learners.show', $lernender->lernender_id), $zeile->getAttribute('data-href'));

        $text = fn (\DOMNode $n) => trim((string) preg_replace('/\s+/u', ' ', $n->textContent));
        $zellen = array_map($text, iterator_to_array($xpath->query('td', $zeile)));
        $this->assertCount(8, $zellen, 'Name, Status, Gesamt, Semester, Ungenügend, Prüfungen, Letzte Note, Aktion');
        $this->assertSame('3.0', $zellen[2]);
        $this->assertSame('3.0', $zellen[3]);
        $this->assertSame('1', $zellen[4]);
        $this->assertSame(0, $xpath->query('td[contains(concat(" ", normalize-space(@class), " "), " hidden ")]', $zeile)->length);

        $noten = $xpath->query('td[last()]//a', $zeile)->item(0);
        $this->assertSame(route('admin.learners.grades.index', ['lernender_id' => $lernender->lernender_id, 'semester_id' => $note->semester_id]), $noten->getAttribute('href'));
    }
}
