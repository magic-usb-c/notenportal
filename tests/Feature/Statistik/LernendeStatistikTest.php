<?php

declare(strict_types=1);

namespace Tests\Feature\Statistik;

use App\Models\Betreuung;
use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Lehrberuf;
use App\Models\Modul;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Die Statistikdarstellungen der Lernenden-Seiten (S1–S6, docs/auftrag/GUI-R6.md R6-08): jede rendert eine
 * Zusammenfassung mit Zahl und die Tabelle in <details>; eine Person ohne Noten sieht Leerzustand statt leerem Diagramm.
 */
class LernendeStatistikTest extends TestCase
{
    private User $mitNoten;

    private User $ohneNoten;

    private int $kategorieId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kategorieId = (int) Kategorie::where('code', 'FACH')->value('kategorie_id');
        $lehrberuf = Lehrberuf::factory()->create();
        $fach = Fach::factory()->create(['track_typ' => null, 'kategorie_id' => $this->kategorieId]);
        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $lehrberuf->lehrberuf_id, 'fach_id' => $fach->fach_id, 'aktiv' => 1]);

        $start = Carbon::parse('2020-08-01');
        $semester = [];
        foreach ([1, 2] as $i) {
            $ende = $start->copy()->addMonths(6)->subDay();
            $semester[] = Semester::factory()->create([
                'bezeichnung' => 'S'.$i.'-'.fake()->unique()->numberBetween(1, 99999),
                'start_datum' => $start->toDateString(),
                'end_datum' => $ende->toDateString(),
                'sortierung' => $i,
            ]);
            $start = $ende->copy()->addDay();
        }
        Semester::factory()->create();

        $lernende = fn () => User::factory()->lernender([
            'lehrberuf_id' => $lehrberuf->lehrberuf_id,
            'lehrbeginn' => now()->subMonths(18)->toDateString(),
            'lehrende' => now()->addMonths(30)->toDateString(),
        ])->create();
        $this->mitNoten = $lernende();
        $this->ohneNoten = $lernende();

        foreach ([[$semester[0], 4.0], [$semester[1], 5.0]] as [$s, $wert]) {
            DB::table('noten')->insert([
                'lernender_id' => $this->mitNoten->lernender->lernender_id,
                'kategorie_id' => $this->kategorieId,
                'semester_id' => $s->semester_id,
                'fach_id' => $fach->fach_id,
                'modul_belegung_id' => null,
                'titel' => 'Note',
                'pruefungsdatum' => Carbon::parse($s->start_datum)->addDays(10)->toDateString(),
                'note_wert' => $wert,
                'gewichtung_prozent' => 100,
                'erfasst_von_benutzer_id' => $this->mitNoten->benutzer_id,
            ]);
        }

        $modul = Modul::factory()->create();
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $lehrberuf->lehrberuf_id, 'modul_id' => $modul->modul_id,
            'kategorie_id' => Kategorie::where('code', 'UEK')->value('kategorie_id')]);
        $this->actingAs($this->mitNoten)->post(route('learner.exams.store'), [
            'bezug' => 'modul:'.$modul->modul_id, 'datum' => now()->addWeeks(2)->toDateString(), 'gewichtung_prozent' => 40, 'titel' => 'LB2',
        ])->assertSessionHasNoErrors();
        auth()->logout();
    }

    #[Test]
    public function die_tests_laufen_gegen_eine_test_datenbank(): void
    {
        $this->assertStringEndsWith('_test', DB::connection()->getDatabaseName());
    }

    #[Test]
    public function s1_s2_dashboard_rendert_verlauf_und_wo_stehe_ich_mit_satz_und_tabelle(): void
    {
        $html = $this->actingAs($this->mitNoten)->get(route('learner.dashboard'))->assertOk()->getContent();

        // ein Filterformular mit den Filtern des Verlaufs und dem Modus von «Wo stehe ich»
        foreach (['name="zeitraum"', 'name="achse"', 'name="modus"', 'data-np-verlauf-chart', 'data-np-satz', 'data-np-tabelle'] as $teil) {
            $this->assertStringContainsString($teil, $html, $teil);
        }
        // Zusammenfassung mit Zahl und Tabelle in <details>
        $this->assertMatchesRegularExpression('/Schnitt von \d\.\d auf \d\.\d, 2 Prüfungen im Zeitraum\./', $html);
        $this->assertSame(2, substr_count($html, 'Als Tabelle'), 'Verlauf und Wo stehe ich haben je eine Tabelle in <details>');
        $this->assertStringContainsString('<details', $html);
        // keine alte Verlaufskarte mit eigenem Alpine-Zustand
        $this->assertStringNotContainsString("modus: 'kategorien'", $html);
    }

    #[Test]
    public function s1_zeitraum_lehrjahr_steht_in_der_adresse_und_im_formular(): void
    {
        $html = $this->actingAs($this->mitNoten)->get(route('learner.dashboard', ['zeitraum' => 'lehrjahr', 'achse' => 'semester', 'modus' => 'lehrzeit']))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<option value="lehrjahr"\s+selected/', $html);
        $this->assertMatchesRegularExpression('/value="semester"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/value="lehrzeit"[^>]*checked/', $html);
    }

    #[Test]
    public function s3_noten_rendert_die_hantel_als_baustein_mit_satz_und_tabelle(): void
    {
        $html = $this->actingAs($this->mitNoten)->get(route('learner.grades.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-np-baustein="hantel"', $html);
        $this->assertStringContainsString('id="np-hantel"', $html);
        $this->assertStringContainsString('name="sort"', $html);
        $this->assertStringContainsString('data-np-semester-wechsel', $html);
        $this->assertMatchesRegularExpression('/(\d+ besser, \d+ schlechter als im Vorsemester\.|Kein Vorsemester zum Vergleich\.)/', $html);
        $this->assertStringContainsString('Vorsemester gegen aktuell', $html);
        $this->assertStringContainsString('<details', $html);

        $this->actingAs($this->mitNoten)->get(route('learner.grades.index', ['sort' => 'fach']))
            ->assertOk()->assertSee('value="fach" class="sr-only" checked', false);
    }

    #[Test]
    public function s4_rechner_rendert_kurve_mit_satz_und_tabelle(): void
    {
        $html = $this->actingAs($this->mitNoten)->get(route('learner.grades.calculator'))->assertOk()->getContent();

        $this->assertStringContainsString("npChart('kurve')", $html);
        $this->assertStringContainsString('Ergebnis je Note in den offenen Prüfungen', $html);
        $this->assertStringContainsString('schlechteste Endnote', $html);
        $this->assertStringContainsString('beste Endnote', $html);
        $this->assertStringContainsString('<details', $html);
    }

    #[Test]
    public function s5_qualifikation_rendert_die_seite_ohne_fehler_und_ohne_paket_nicht_fuer_die_verwaltung(): void
    {
        $antwort = $this->actingAs($this->mitNoten)->get(route('learner.qualification.index'))->assertOk();
        $paket = $antwort->viewData('statistik');
        $zeilen = $paket['diagramm']['positionen']['zeilen'];

        if ($zeilen === []) {
            $antwort->assertDontSee('Was fehlt zum Bestehen');
        } else {
            $antwort->assertSee('Was fehlt zum Bestehen')->assertSee($paket['zusammenfassung']['positionen'])->assertSee('<details', false);
        }
    }

    #[Test]
    public function s6_agenda_rendert_die_zeitleiste_mit_nötiger_note_satz_und_tabelle(): void
    {
        $html = $this->actingAs($this->mitNoten)->get(route('learner.exams.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-np-baustein="zeitleiste"', $html);
        $this->assertStringContainsString('id="np-zeitleiste"', $html);
        $this->assertStringContainsString('name="zeitraum"', $html);
        $this->assertStringContainsString('LB2', $html);
        $this->assertMatchesRegularExpression('/Nächste Prüfung am \d\d\.\d\d\.\d{4}/', $html);
        $this->assertStringContainsString('<details', $html);

        // 4w bis 8w: die Prüfung in zwei Wochen ist in jedem Zeitraum drin; die Auswahl bleibt gesetzt
        $this->actingAs($this->mitNoten)->get(route('learner.exams.index', ['zeitraum' => '4w']))
            ->assertOk()->assertSee('value="4w" class="sr-only" checked', false);
    }

    #[Test]
    public function ohne_noten_gibt_es_leerzustand_statt_leerem_diagramm(): void
    {
        $dashboard = $this->actingAs($this->ohneNoten)->get(route('learner.dashboard'))->assertOk();
        $dashboard->assertSee('Noch keine Noten')->assertDontSee('<canvas', false);

        $noten = $this->actingAs($this->ohneNoten)->get(route('learner.grades.index'))->assertOk();
        $noten->assertDontSee('data-np-baustein="hantel"', false)->assertDontSee('<canvas', false);

        $this->actingAs($this->ohneNoten)->get(route('learner.qualification.index'))->assertOk();

        // die Zeitleiste bleibt als Baustein stehen (npFilter ersetzt sie), mit Satz statt leerer Achse
        $this->actingAs($this->ohneNoten)->get(route('learner.exams.index'))
            ->assertOk()->assertSee('Keine Prüfungen im gewählten Zeitraum.')->assertDontSee('<svg class="w-full"', false);
    }

    #[Test]
    public function verwaltung_sieht_den_rechner_und_den_abschluss_ohne_statistikpaket(): void
    {
        $berufsbildner = User::factory()->berufsbildner()->create();
        Betreuung::factory()->create(['berufsbildner_id' => $berufsbildner->berufsbildner->berufsbildner_id, 'lernender_id' => $this->mitNoten->lernender->lernender_id]);

        $id = $this->mitNoten->lernender->lernender_id;
        $this->actingAs($berufsbildner)->get(route('trainer.learners.qualification', $id))->assertOk();
        $this->actingAs($berufsbildner)->get(route('trainer.learners.calculator', $id))->assertOk();
    }
}
