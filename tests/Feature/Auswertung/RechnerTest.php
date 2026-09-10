<?php

declare(strict_types=1);

namespace Tests\Feature\Auswertung;

use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Lernender;
use App\Models\LernenderTrack;
use App\Models\Modul;
use App\Models\Note;
use App\Models\Semester;
use App\Models\User;
use App\Models\Ziel;
use App\Services\Auswertung\Konfiguration;
use Illuminate\Support\Facades\DB;
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
        $this->actingAs($this->user)->post(route('lernender.noten.store'), [
            'typ' => 'modul', 'modul_id' => $this->modul->modul_id, 'pruefungsdatum' => now()->toDateString(),
            'note_wert' => $wert, 'gewichtung_prozent' => $gewicht,
        ])->assertSessionHasNoErrors();
    }

    #[Test]
    public function kategorie_der_note_folgt_aus_dem_modul_nicht_aus_dem_formular(): void
    {
        $this->actingAs($this->user)->post(route('lernender.noten.store'), [
            'kategorie_id' => Kategorie::where('code', 'BMS')->value('kategorie_id'),
            'typ' => 'modul', 'modul_id' => $this->modul->modul_id, 'pruefungsdatum' => now()->toDateString(), 'note_wert' => 5,
        ])->assertSessionHasNoErrors();

        $this->assertSame(Kategorie::where('code', 'FACH')->value('kategorie_id'), Note::sole()->kategorie_id);
    }

    #[Test]
    public function rechner_seite_laedt_fuer_lernende(): void
    {
        $this->modulNote(4.0, 50);

        $this->actingAs($this->user)->get(route('lernender.noten.rechner'))
            ->assertOk()
            ->assertSee('Offene Prüfungen')
            ->assertSee($this->modul->titel);
    }

    #[Test]
    public function berechnet_benoetigte_note_und_schlaegt_restgewicht_vor(): void
    {
        $this->modulNote(4.0, 50);

        $antwort = $this->actingAs($this->user)->postJson(route('lernender.noten.rechner.berechnen'), [
            'ziel' => 'modul:'.$this->modul->modul_id,
            'zielwert' => 5.0,
            'zeilen' => [['element' => 'modul:'.$this->modul->modul_id, 'gewicht' => 50, 'wert' => null]],
        ])->assertOk();

        $this->assertSame('benoetigt', $antwort->json('loesung.status'));
        $this->assertEquals(5.5, $antwort->json('loesung.note'));
        $this->assertNotEmpty($antwort->json('kurve'));

        $seite = app(\App\Services\Auswertung\Rechner::class)->seite($this->lernender, false);
        $this->assertSame([['element' => 'modul:'.$this->modul->modul_id, 'gewicht' => 50.0]],
            array_map(fn ($z) => ['element' => $z['element'], 'gewicht' => $z['gewicht']], $seite['vorschlaege']));
    }

    #[Test]
    public function was_waere_wenn_fuer_fach_im_semester(): void
    {
        $antwort = $this->actingAs($this->user)->postJson(route('lernender.noten.rechner.berechnen'), [
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

        $this->actingAs($this->user)->postJson(route('lernender.noten.rechner.berechnen'), [
            'ziel' => 'gesamt', 'zielwert' => 4,
            'zeilen' => [['element' => 'modul:'.$fremd->modul_id, 'gewicht' => 100, 'wert' => null]],
        ])->assertJsonValidationErrors('zeilen.0.element');

        $this->actingAs($this->user)->postJson(route('lernender.noten.rechner.berechnen'), [
            'ziel' => 'modul:1@semester:2', 'zielwert' => 4, 'zeilen' => [],
        ])->assertJsonValidationErrors('ziel');
    }

    #[Test]
    public function ersetzt_rechnet_ohne_die_bearbeitete_note(): void
    {
        $this->modulNote(2.0, 100);
        $note = Note::sole();

        $antwort = $this->actingAs($this->user)->postJson(route('lernender.noten.rechner.berechnen'), [
            'ziel' => 'modul:'.$this->modul->modul_id, 'zielwert' => 4, 'ersetzt' => $note->note_id,
            'zeilen' => [['element' => 'modul:'.$this->modul->modul_id, 'gewicht' => 100, 'wert' => 6]],
        ])->assertOk();

        $this->assertEquals(6.0, $antwort->json('loesung.resultat'));
    }

    #[Test]
    public function ziele_speichern_aktualisieren_und_nur_eigene_loeschen(): void
    {
        $this->actingAs($this->user)->post(route('lernender.ziele.store'), ['ziel' => 'gesamt', 'zielwert' => 4.5])->assertSessionHasNoErrors();
        $this->actingAs($this->user)->post(route('lernender.ziele.store'), ['ziel' => 'gesamt', 'zielwert' => 5])->assertSessionHasNoErrors();
        $this->assertEquals(5.0, Ziel::sole()->zielwert);

        $this->actingAs($this->user)->post(route('lernender.ziele.store'), ['ziel' => 'fach:'.$this->fach->fach_id.'@semester:1', 'zielwert' => 5])
            ->assertSessionHasErrors('ziel');

        $andere = User::factory()->lernender()->create();
        $this->actingAs($andere)->delete(route('lernender.ziele.destroy', Ziel::sole()->ziel_id))->assertNotFound();
        $this->actingAs($this->user)->delete(route('lernender.ziele.destroy', Ziel::sole()->ziel_id))->assertRedirect();
        $this->assertDatabaseCount('ziele', 0);
    }

    #[Test]
    public function berufsbildner_nutzt_rechner_nur_fuer_betreute(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $url = route('berufsbildner.lernende.rechner', $this->lernender->lernender_id);

        $this->actingAs($bb)->get($url)->assertNotFound();
        $this->actingAs($bb)->postJson(route('berufsbildner.lernende.rechner.berechnen', $this->lernender->lernender_id), [
            'ziel' => 'gesamt', 'zielwert' => 4, 'zeilen' => [],
        ])->assertNotFound();

        $this->betreue($bb, $this->lernender);
        $this->actingAs($bb)->get($url)->assertOk()->assertDontSee('Als Ziel speichern');
    }
}
