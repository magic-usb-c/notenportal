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
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Notenbaum\BaumVorlage;
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
    public function s5_qualifikation_rechnet_je_offener_position_des_notenbaums_die_noetige_note(): void
    {
        // Ohne Notenbaum gibt es keine Positionen: Seite ohne den Block, Paket ohne Zeilen
        $antwort = $this->actingAs($this->mitNoten)->get(route('learner.qualification.index'))->assertOk();
        $this->assertSame([], $antwort->viewData('statistik')['diagramm']['positionen']['zeilen']);
        $antwort->assertDontSee('Was fehlt zum Bestehen');

        // Mit dem Baum des Lehrberufs sind alle Positionsblätter (IPA …) offen, solange niemand eine Note eingetragen hat
        app(BaumVorlage::class)->importieren(BaumVorlage::laden('informatiker-efz-bivo2020'), (int) $this->mitNoten->lernender->lehrberuf_id);
        Konfiguration::vergessen();

        $json = $this->actingAs($this->mitNoten)->getJson(route('learner.qualification.index'))->assertOk()->json();
        $zeilen = $json['diagramm']['positionen']['zeilen'];
        $this->assertNotEmpty($zeilen);
        $ipa = null;
        foreach ($zeilen as $z) {
            $this->assertNotNull($z['knotenId']);
            $this->assertNotSame('', $z['titel']);
            // Ohne eine einzige erfasste Position ist jede offen und hat eine konkrete nötige Note
            $this->assertSame('benoetigt', $z['status'], $z['titel']);
            $this->assertNotNull($z['note'], $z['titel']);
            $this->assertStringContainsString('benötigt', $z['text']);
            if (str_contains($z['titel'], 'IPA')) {
                $ipa = $z;
            }
        }
        $this->assertNotNull($ipa, 'die IPA ist ein offenes Positionsblatt');
        // Die IPA trägt die Fallnote 4.0: die nötige Note liegt nie darunter, auch wenn der Gesamtschnitt weniger verlangt
        $this->assertGreaterThanOrEqual(4.0, $ipa['note']);
        $this->assertSame(count($zeilen).' offene Positionen, 0 davon bestanden.', $json['zusammenfassung']['positionen']);

        $html = $this->actingAs($this->mitNoten)->get(route('learner.qualification.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Was fehlt zum Bestehen', $html);
        $this->assertStringContainsString($json['zusammenfassung']['positionen'], $html);
        $this->assertSame(count($zeilen), substr_count($html, 'viewBox="0 0 100 8"'), 'ein Mini-Bullet je Position');
        $this->assertStringContainsString('<details', $html);

        // Eine erfasste IPA unter der Fallnote macht den Baum unbestehbar: keine andere Position kann das noch retten
        $this->actingAs($this->mitNoten)->put(route('learner.qualification.update'), ['werte' => [$ipa['knotenId'] => '3.5']])->assertSessionHas('success');
        $vorher = count($zeilen);
        $json = $this->actingAs($this->mitNoten)->getJson(route('learner.qualification.index'))->assertOk()->json();
        $zeilen = $json['diagramm']['positionen']['zeilen'];
        $this->assertCount($vorher - 1, $zeilen, 'die IPA ist nicht mehr offen');
        $this->assertNotContains($ipa['titel'], array_column($zeilen, 'titel'));
        $this->assertNotEmpty($zeilen);
        foreach ($zeilen as $z) {
            $this->assertSame('unerreichbar', $z['status'], $z['titel']);
            $this->assertSame('nicht erreichbar', $z['text']);
        }

        // Verwaltung sieht die Seite ohne Statistikpaket, und die Lernenden-ID kommt nur aus der Session
        $this->actingAs(User::factory()->admin()->create())->get(route('learner.qualification.index'))->assertForbidden();
    }

    #[Test]
    public function s5_ignoriert_die_fallnote_einer_position_die_fuer_den_track_der_person_entfaellt(): void
    {
        $lernender = $this->mitNoten->lernender;
        app(BaumVorlage::class)->importieren(BaumVorlage::laden('informatiker-efz-bivo2020'), (int) $lernender->lehrberuf_id);
        DB::table('notenbaum_knoten')->where('code', 'ipa')->update(['entfaellt_mit_track' => 'BMS']);
        Konfiguration::vergessen();
        $ipaId = (int) DB::table('notenbaum_knoten')->where('code', 'ipa')->value('knoten_id');

        // Die IPA wurde erfasst, bevor die Person in den BMS-Track wechselte: sie entfällt jetzt und darf
        // mit ihrer Fallnote die übrigen Positionen nicht mehr auf «nicht erreichbar» setzen
        DB::table('notenbaum_positionen')->insert([
            'lernender_id' => $lernender->lernender_id, 'knoten_id' => $ipaId, 'note_wert' => 3.5, 'datum' => null,
            'erfasst_von_benutzer_id' => $this->mitNoten->benutzer_id, 'erstellt_am' => now(), 'aktualisiert_am' => now(),
        ]);
        DB::table('lernender_tracks')->insert([
            'lernender_id' => $lernender->lernender_id, 'track_typ' => 'BMS',
            'start_semester_id' => Semester::factory()->create()->semester_id,
            'start_datum' => now()->subMonths(12)->toDateString(), 'end_datum' => null,
        ]);

        $zeilen = $this->actingAs($this->mitNoten)->getJson(route('learner.qualification.index'))->assertOk()
            ->json('diagramm.positionen.zeilen');
        $this->assertNotEmpty($zeilen);
        foreach ($zeilen as $z) {
            $this->assertSame('benoetigt', $z['status'], $z['titel']);
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
