<?php

declare(strict_types=1);

namespace Tests\Feature\Auswertung;

use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Lernender;
use App\Models\LernenderTrack;
use App\Models\Modul;
use App\Models\Note;
use App\Models\Pruefung;
use App\Models\Semester;
use App\Models\User;
use App\Models\Ziel;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\LernstandRechner;
use App\Services\Auswertung\Notenbaum\BaumVorlage;
use App\Services\Auswertung\Rechner;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

class RechnerTest extends TestCase
{
    use VerwaltungTestHilfen;

    private User $user;

    private Lernender $lernender;

    private Semester $semester;

    private Modul $modul;

    private Fach $fach;

    protected function setUp(): void
    {
        parent::setUp();
        Konfiguration::vergessen();

        $this->user = User::factory()->lernender()->create();
        $this->lernender = $this->user->lernender;
        $this->semester = Semester::factory()->create();
        $this->modul = Modul::factory()->create();
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $this->lernender->lehrberuf_id,
            'modul_id' => $this->modul->modul_id,
            'kategorie_id' => Kategorie::where('code', 'FACH')->value('kategorie_id'),
        ]);
        $this->fach = Fach::factory()->create(['track_typ' => 'BMS']);
        LernenderTrack::create([
            'lernender_id' => $this->lernender->lernender_id, 'track_typ' => 'BMS',
            'start_datum' => now()->subYear()->toDateString(), 'start_semester_id' => $this->semester->semester_id,
        ]);
    }

    private function modulNote(float $wert, float $gewicht): void
    {
        $this->actingAs($this->user)->post(route('learner.grades.store'), [
            'typ' => 'modul', 'modul_id' => $this->modul->modul_id, 'pruefungsdatum' => now()->toDateString(),
            'note_wert' => $wert, 'gewichtung_prozent' => $gewicht,
        ])->assertSessionHasNoErrors();
    }

    #[Test]
    public function kategorie_der_note_folgt_aus_dem_modul_nicht_aus_dem_formular(): void
    {
        $this->actingAs($this->user)->post(route('learner.grades.store'), [
            'kategorie_id' => Kategorie::where('code', 'BMS')->value('kategorie_id'),
            'typ' => 'modul', 'modul_id' => $this->modul->modul_id, 'pruefungsdatum' => now()->toDateString(), 'note_wert' => 5,
        ])->assertSessionHasNoErrors();

        $this->assertSame(Kategorie::where('code', 'FACH')->value('kategorie_id'), Note::sole()->kategorie_id);
    }

    #[Test]
    public function rechner_seite_laedt_fuer_lernende(): void
    {
        $this->modulNote(4.0, 50);

        $this->actingAs($this->user)->get(route('learner.grades.calculator'))
            ->assertOk()
            ->assertSee('Offene Prüfungen')
            ->assertSee($this->modul->titel);
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function rechner_spricht_in_der_verwaltung_die_person_an_statt_du(string $bereich): void
    {
        $verwalter = $this->verwalter($bereich, $this->lernender);

        $html = (string) $this->actingAs($verwalter)->get(route("{$bereich}.learners.calculator", $this->lernender->lernender_id))->assertOk()->getContent();
        $this->assertStringContainsString(' braucht mindestens :note', $html);
        $this->assertStringNotContainsString('Du brauchst', $html);
        $this->assertStringNotContainsString('Was brauchst du', $html);

        $this->actingAs($this->user)->get(route('learner.grades.calculator'))->assertOk()->assertSee('Du brauchst mindestens :note', false);
    }

    #[Test]
    public function berechnet_benoetigte_note_und_schlaegt_restgewicht_vor(): void
    {
        $this->modulNote(4.0, 50);

        $antwort = $this->actingAs($this->user)->postJson(route('learner.grades.calculator.calculate'), [
            'ziel' => 'modul:'.$this->modul->modul_id,
            'zielwert' => 5.0,
            'zeilen' => [['element' => 'modul:'.$this->modul->modul_id, 'gewicht' => 50, 'wert' => null]],
        ])->assertOk();

        $this->assertSame('benoetigt', $antwort->json('loesung.status'));
        $this->assertEquals(5.5, $antwort->json('loesung.note'));
        $this->assertNotEmpty($antwort->json('kurve'));

        $seite = app(Rechner::class)->seite($this->lernender, false);
        $this->assertSame([['element' => 'modul:'.$this->modul->modul_id, 'gewicht' => 50.0]],
            array_map(fn ($z) => ['element' => $z['element'], 'gewicht' => $z['gewicht']], $seite['vorschlaege']));
    }

    #[Test]
    public function was_waere_wenn_fuer_fach_im_semester(): void
    {
        $antwort = $this->actingAs($this->user)->postJson(route('learner.grades.calculator.calculate'), [
            'ziel' => 'gesamt',
            'zielwert' => 4.0,
            'zeilen' => [['element' => "fach:{$this->fach->fach_id}@semester:{$this->semester->semester_id}", 'gewicht' => 100, 'wert' => 4.5]],
        ])->assertOk();

        $this->assertSame('keine_unbekannten', $antwort->json('loesung.status'));
        $this->assertEquals(4.5, $antwort->json('loesung.resultat'));
        $this->assertContains('gesamt', array_column($antwort->json('vergleich'), 'text'));
    }

    #[Test]
    public function fremde_module_und_ungueltige_ziele_werden_abgewiesen(): void
    {
        $fremd = Modul::factory()->create();

        $this->actingAs($this->user)->postJson(route('learner.grades.calculator.calculate'), [
            'ziel' => 'gesamt', 'zielwert' => 4,
            'zeilen' => [['element' => 'modul:'.$fremd->modul_id, 'gewicht' => 100, 'wert' => null]],
        ])->assertJsonValidationErrors('zeilen.0.element');

        $this->actingAs($this->user)->postJson(route('learner.grades.calculator.calculate'), [
            'ziel' => 'modul:1@semester:2', 'zielwert' => 4, 'zeilen' => [],
        ])->assertJsonValidationErrors('ziel');
    }

    #[Test]
    public function ersetzt_rechnet_ohne_die_bearbeitete_note(): void
    {
        $this->modulNote(2.0, 100);
        $note = Note::sole();

        $antwort = $this->actingAs($this->user)->postJson(route('learner.grades.calculator.calculate'), [
            'ziel' => 'modul:'.$this->modul->modul_id, 'zielwert' => 4, 'ersetzt' => $note->note_id,
            'zeilen' => [['element' => 'modul:'.$this->modul->modul_id, 'gewicht' => 100, 'wert' => 6]],
        ])->assertOk();

        $this->assertEquals(6.0, $antwort->json('loesung.resultat'));
    }

    #[Test]
    public function ersetzt_laesst_eine_notenbaum_position_mit_gleicher_id_stehen(): void
    {
        app(BaumVorlage::class)->importieren(BaumVorlage::laden('informatiker-efz-bivo2020'), (int) $this->lernender->lehrberuf_id);
        $this->modulNote(2.0, 100);
        $note = Note::sole();
        $ipa = (int) DB::table('notenbaum_knoten')->where('code', 'ipa')->value('knoten_id');
        // Positionen und Noten zählen ihre IDs getrennt; gleiche Zahlen kommen im Betrieb vor
        DB::table('notenbaum_positionen')->insert(['position_id' => $note->note_id, 'lernender_id' => $this->lernender->lernender_id,
            'knoten_id' => $ipa, 'note_wert' => 5.0, 'erfasst_von_benutzer_id' => $this->user->benutzer_id]);
        Konfiguration::vergessen();

        $antwort = $this->actingAs($this->user)->postJson(route('learner.grades.calculator.calculate'), [
            'ziel' => 'gesamt', 'zielwert' => 4, 'ersetzt' => $note->note_id,
            'zeilen' => [['element' => 'modul:'.$this->modul->modul_id, 'gewicht' => 100, 'wert' => 6]],
        ])->assertOk();

        // IPA 5,0 (40) und Informatikkompetenzen 6,0 (30): 380 / 70 = 5,43 → 5,4; ohne IPA wären es 6,0
        $this->assertEquals(5.4, $antwort->json('loesung.resultat'));
    }

    #[Test]
    public function editformular_vorschau_fuer_note_ausserhalb_der_lehrzeit_schlaegt_nicht_fehl(): void
    {
        // Bug: eine Note datiert nach «lehrende» (nachträgliche Korrektur, Lehrzeitverlängerung o.ä.) referenziert
        // ein Semester, das der Rechner-Katalog (bis dahin begrenzt auf lehrbeginn..lehrende) nicht kannte – die
        // Live-Vorschau im Bearbeiten-Formular (resources/js/rechner.js, npNotenFormular.laden()) bekam dafür 422.
        $this->lernender->update(['lehrende' => now()->subYear()->toDateString()]);
        $note = Note::factory()->create([
            'lernender_id' => $this->lernender->lernender_id,
            'kategorie_id' => Kategorie::where('code', 'BMS')->value('kategorie_id'),
            'semester_id' => $this->semester->semester_id,
            'fach_id' => $this->fach->fach_id,
            'modul_belegung_id' => null,
            'pruefungsdatum' => now()->toDateString(),
            'note_wert' => 5.0,
            'gewichtung_prozent' => 100,
            'erfasst_von_benutzer_id' => $this->user->benutzer_id,
        ]);

        $element = "fach:{$this->fach->fach_id}@semester:{$this->semester->semester_id}";

        // Exakte Nutzlast, die npNotenFormular.laden() beim Öffnen des Bearbeiten-Formulars schickt.
        $antwort = $this->actingAs($this->user)->postJson(route('learner.grades.calculator.calculate'), [
            'ziel' => $element,
            'zielwert' => 4,
            'ersetzt' => $note->note_id,
            'zeilen' => [['element' => $element, 'gewicht' => 100, 'wert' => 5.0, 'datum' => $note->pruefungsdatum->toDateString()]],
        ])->assertOk();

        $this->assertEquals(5.0, $antwort->json('loesung.resultat'));
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function editformular_vorschau_im_verwaltungsbereich_fuer_note_ausserhalb_der_lehrzeit(string $bereich): void
    {
        $this->lernender->update(['lehrende' => now()->subYear()->toDateString()]);
        $verwalter = $this->verwalter($bereich, $this->lernender);

        $note = Note::factory()->create([
            'lernender_id' => $this->lernender->lernender_id,
            'kategorie_id' => Kategorie::where('code', 'BMS')->value('kategorie_id'),
            'semester_id' => $this->semester->semester_id,
            'fach_id' => $this->fach->fach_id,
            'modul_belegung_id' => null,
            'pruefungsdatum' => now()->toDateString(),
            'note_wert' => 5.0,
            'gewichtung_prozent' => 100,
            'erfasst_von_benutzer_id' => $this->user->benutzer_id,
        ]);

        $element = "fach:{$this->fach->fach_id}@semester:{$this->semester->semester_id}";

        $antwort = $this->actingAs($verwalter)->postJson(route("{$bereich}.learners.calculator.calculate", $this->lernender->lernender_id), [
            'ziel' => $element,
            'zielwert' => 4,
            'ersetzt' => $note->note_id,
            'zeilen' => [['element' => $element, 'gewicht' => 100, 'wert' => 5.0, 'datum' => $note->pruefungsdatum->toDateString()]],
        ])->assertOk();

        $this->assertEquals(5.0, $antwort->json('loesung.resultat'));
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function berechnen_im_verwaltungsbereich_weist_fremde_module_ab(string $bereich): void
    {
        $fremd = Modul::factory()->create();
        $verwalter = $this->verwalter($bereich, $this->lernender);

        $this->actingAs($verwalter)->postJson(route("{$bereich}.learners.calculator.calculate", $this->lernender->lernender_id), [
            'ziel' => 'gesamt', 'zielwert' => 4,
            'zeilen' => [['element' => 'modul:'.$fremd->modul_id, 'gewicht' => 100, 'wert' => null]],
        ])->assertJsonValidationErrors('zeilen.0.element');
    }

    #[Test]
    public function ziele_speichern_aktualisieren_und_nur_eigene_loeschen(): void
    {
        $this->actingAs($this->user)->post(route('learner.goals.store'), ['ziel' => 'gesamt', 'zielwert' => 4.5])->assertSessionHasNoErrors();
        $this->actingAs($this->user)->post(route('learner.goals.store'), ['ziel' => 'gesamt', 'zielwert' => 5])->assertSessionHasNoErrors();
        $this->assertEquals(5.0, Ziel::sole()->zielwert);

        $this->actingAs($this->user)->post(route('learner.goals.store'), ['ziel' => 'fach:'.$this->fach->fach_id.'@semester:1', 'zielwert' => 5])
            ->assertSessionHasErrors('ziel');

        $andere = User::factory()->lernender()->create();
        $this->actingAs($andere)->delete(route('learner.goals.destroy', Ziel::sole()->ziel_id))->assertNotFound();
        $this->actingAs($this->user)->delete(route('learner.goals.destroy', Ziel::sole()->ziel_id))->assertRedirect();
        $this->assertDatabaseCount('ziele', 0);
    }

    #[Test]
    public function ueberfaellig_zaehlt_nur_offene_pruefungen_nicht_benotete_oder_abgesagte(): void
    {
        $this->modulNote(4.5, 100);
        $basis = [
            'lernender_id' => $this->lernender->lernender_id, 'fach_id' => $this->fach->fach_id,
            'datum' => now()->subWeek()->toDateString(), 'gewichtung_prozent' => 100, 'quelle' => Pruefung::MANUELL,
        ];
        Pruefung::create($basis + ['note_id' => Note::sole()->note_id]);
        Pruefung::create($basis + ['abgesagt_am' => now()]);

        $this->assertSame(0, app(LernstandRechner::class)->fuer([$this->lernender->lernender_id])[$this->lernender->lernender_id]->ueberfaellig);

        Pruefung::create($basis);

        $this->assertSame(1, app(LernstandRechner::class)->fuer([$this->lernender->lernender_id])[$this->lernender->lernender_id]->ueberfaellig);
    }

    #[Test]
    public function standard_waehlt_das_fach_der_naechsten_pruefung_vor_zuletzt_benotetem_modul(): void
    {
        // Rückmeldung #10: der Rechner soll standardmässig mit dem Fach/Modul der nächsten anstehenden
        // Prüfung starten, nicht mit «Gesamt» – auch wenn bereits ein anderes Modul benotet ist.
        $this->modulNote(4.5, 100);
        Pruefung::create([
            'lernender_id' => $this->lernender->lernender_id, 'fach_id' => $this->fach->fach_id,
            'datum' => now()->addWeek()->toDateString(), 'gewichtung_prozent' => 100, 'quelle' => Pruefung::MANUELL,
        ]);

        $seite = app(Rechner::class)->seite($this->lernender, false);

        $this->assertSame("fach:{$this->fach->fach_id}@semester:{$this->semester->semester_id}", $seite['standard']);
    }

    #[Test]
    public function standard_faellt_ohne_geplante_pruefung_auf_das_zuletzt_benotete_modul_zurueck(): void
    {
        $this->modulNote(4.5, 100);

        $seite = app(Rechner::class)->seite($this->lernender, false);

        $this->assertSame('modul:'.$this->modul->modul_id, $seite['standard']);
    }

    #[Test]
    public function standard_faellt_ohne_noten_und_pruefungen_auf_das_erste_fach_im_katalog_zurueck(): void
    {
        $seite = app(Rechner::class)->seite($this->lernender, false);

        $this->assertSame("fach:{$this->fach->fach_id}@semester:{$this->semester->semester_id}", $seite['standard']);
        $this->assertNotSame('gesamt', $seite['standard']);
    }

    #[Test]
    public function rechner_seite_startet_mit_dem_standard_ziel_und_nicht_mit_gesamt(): void
    {
        $this->modulNote(4.5, 100);

        // @js() liefert die Daten unicode-escaped (JSON.parse('...\u0022ziel\u0022:...')), nicht als rohes JSON.
        $this->actingAs($this->user)->get(route('learner.grades.calculator'))
            ->assertOk()
            ->assertDontSee('\u0022ziel\u0022:\u0022gesamt\u0022', false)
            ->assertSee('\u0022ziel\u0022:\u0022modul:'.$this->modul->modul_id.'\u0022', false);
    }

    #[Test]
    public function ziel_parameter_in_der_url_uebersteuert_den_standard_tab(): void
    {
        $this->modulNote(4.5, 100);

        $this->actingAs($this->user)
            ->get(route('learner.grades.calculator', ['ziel' => 'gesamt', 'zielwert' => 5]))
            ->assertOk()
            ->assertSee('\u0022ziel\u0022:\u0022gesamt\u0022', false)
            ->assertSee('\u0022zielwert\u0022:\u00225\u0022', false);
    }

    #[Test]
    public function auswirkung_setzt_die_gesuchte_note_nur_in_pruefungen_mit_einfluss_auf_das_ziel(): void
    {
        $fachB = Fach::factory()->create(['track_typ' => 'BMS']);
        $a = "fach:{$this->fach->fach_id}@semester:{$this->semester->semester_id}";
        $b = "fach:{$fachB->fach_id}@semester:{$this->semester->semester_id}";
        $zeilen = [['element' => $a, 'gewicht' => 100, 'wert' => null], ['element' => $b, 'gewicht' => 100, 'wert' => null]];
        $url = route('learner.grades.calculator.calculate');

        $antwort = $this->actingAs($this->user)->postJson($url, ['ziel' => $a, 'zielwert' => 4.5, 'zeilen' => $zeilen])->assertOk();

        $this->assertSame('benoetigt', $antwort->json('loesung.status'));
        $this->assertSame(1, $antwort->json('loesung.unbekannte'));
        $vergleich = collect($antwort->json('vergleich'))->keyBy('text');
        $this->assertEquals($antwort->json('loesung.resultat'), $vergleich[$a]['nachher']);
        $this->assertGreaterThanOrEqual(4.5, $vergleich[$a]['nachher']);
        $this->assertFalse($vergleich->has($b), 'Fach B fliesst nicht in das Ziel Fach A ein.');

        // Gegenprobe: auf den Gesamtschnitt wirken beide, die Note steckt in beiden.
        $gesamt = $this->actingAs($this->user)->postJson($url, ['ziel' => 'gesamt', 'zielwert' => 4.5, 'zeilen' => $zeilen])->assertOk();

        $this->assertSame(2, $gesamt->json('loesung.unbekannte'));
        $vergleich = collect($gesamt->json('vergleich'))->keyBy('text');
        $this->assertNotNull($vergleich[$a]['nachher']);
        $this->assertEquals($vergleich[$a]['nachher'], $vergleich[$b]['nachher']);
    }

    #[Test]
    public function rechner_hat_fuer_lernende_keinen_zurueck_pfeil_und_fuer_verwalter_den_weg_zur_person(): void
    {
        $this->actingAs($this->user)->get(route('learner.grades.calculator'))
            ->assertOk()->assertViewHas('zurueck', null);

        $bb = User::factory()->berufsbildner()->create();
        $this->betreue($bb, $this->lernender);
        $this->actingAs($bb)->get(route('trainer.learners.calculator', $this->lernender->lernender_id))
            ->assertOk()->assertViewHas('zurueck', route('trainer.learners.show', $this->lernender->lernender_id));
    }

    #[Test]
    public function berufsbildner_nutzt_rechner_nur_fuer_betreute(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $url = route('trainer.learners.calculator', $this->lernender->lernender_id);

        $this->actingAs($bb)->get($url)->assertNotFound();
        $this->actingAs($bb)->postJson(route('trainer.learners.calculator.calculate', $this->lernender->lernender_id), [
            'ziel' => 'gesamt', 'zielwert' => 4, 'zeilen' => [],
        ])->assertNotFound();

        $this->betreue($bb, $this->lernender);
        $this->actingAs($bb)->get($url)->assertOk()->assertDontSee('Als Ziel speichern');
    }
}
