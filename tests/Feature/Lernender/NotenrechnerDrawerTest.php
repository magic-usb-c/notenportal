<?php

declare(strict_types=1);

namespace Tests\Feature\Lernender;

use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Lernender;
use App\Models\LernenderTrack;
use App\Models\MailLog;
use App\Models\Modul;
use App\Models\Note;
use App\Models\Semester;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\NotenQuelle;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

/**
 * Notenrechner-Drawer der Notenseite (Block AF): mehrere hypothetische Noten simulieren, ohne zu speichern.
 * Rechnet über RechnerController::simulieren() → Rechner::berechne() (dieselbe Engine wie die echte Anzeige).
 */
class NotenrechnerDrawerTest extends TestCase
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

    private function zeile(array $overrides = []): array
    {
        return array_merge([
            'typ' => 'fach',
            'fach_id' => $this->fach->fach_id,
            'pruefungsdatum' => $this->semester->start_datum->toDateString(),
            'note_wert' => 5.5,
            'gewichtung_prozent' => 100,
        ], $overrides);
    }

    #[Test]
    public function rechnet_dasselbe_wie_das_echte_speichern(): void
    {
        $this->modulNote(4.0, 100);

        $antwort = $this->actingAs($this->user)->postJson(route('learner.grades.calculator.simulate'), [
            'zeilen' => [$this->zeile(['note_wert' => 5.5])],
        ])->assertOk();

        $gesamtSimuliert = collect($antwort->json('vergleich'))->firstWhere('text', 'gesamt')['nachher'];

        // Dieselbe Note jetzt wirklich speichern und über die Rechenkern-Auswertung (echte Anzeige) vergleichen.
        $this->actingAs($this->user)->post(route('learner.grades.store'), [
            'typ' => 'fach', 'fach_id' => $this->fach->fach_id,
            'pruefungsdatum' => $this->semester->start_datum->toDateString(),
            'note_wert' => 5.5, 'gewichtung_prozent' => 100,
        ])->assertSessionHasNoErrors();

        $echt = app(NotenQuelle::class)->auswertung($this->lernender->lernender_id)->gesamtNote;

        $this->assertNotNull($gesamtSimuliert);
        $this->assertEquals($echt, $gesamtSimuliert);
    }

    #[Test]
    public function speichert_nichts(): void
    {
        $this->modulNote(4.0, 100);
        $notenVorher = Note::count();
        $belegungenVorher = DB::table('modul_belegungen')->count();
        $mailsVorher = MailLog::count();

        $this->actingAs($this->user)->postJson(route('learner.grades.calculator.simulate'), [
            'zeilen' => [
                $this->zeile(),
                $this->zeile(['typ' => 'modul', 'fach_id' => null, 'modul_id' => $this->modul->modul_id, 'note_wert' => 3.0]),
            ],
        ])->assertOk();

        $this->assertSame($notenVorher, Note::count());
        $this->assertSame($belegungenVorher, DB::table('modul_belegungen')->count());
        $this->assertSame($mailsVorher, MailLog::count());
    }

    #[Test]
    public function weist_auswirkung_und_promotion_aus(): void
    {
        $antwort = $this->actingAs($this->user)->postJson(route('learner.grades.calculator.simulate'), [
            'zeilen' => [$this->zeile()],
        ])->assertOk();

        $antwort->assertJsonStructure(['vergleich' => ['*' => ['text', 'label', 'vorher', 'nachher']], 'promotion']);
        $this->assertContains('gesamt', array_column($antwort->json('vergleich'), 'text'));
    }

    #[Test]
    public function weist_notenbereich_und_maximalzahl_ab(): void
    {
        $this->actingAs($this->user)->postJson(route('learner.grades.calculator.simulate'), [
            'zeilen' => [$this->zeile(['note_wert' => 6.5])],
        ])->assertJsonValidationErrors('zeilen.0.note_wert');

        $this->actingAs($this->user)->postJson(route('learner.grades.calculator.simulate'), [
            'zeilen' => [$this->zeile(['note_wert' => 4.03])],
        ])->assertJsonValidationErrors('zeilen.0.note_wert');

        $this->actingAs($this->user)->postJson(route('learner.grades.calculator.simulate'), [
            'zeilen' => array_fill(0, 11, $this->zeile()),
        ])->assertJsonValidationErrors('zeilen');
    }

    #[Test]
    public function weist_fremde_oder_ungueltige_eingaben_ab(): void
    {
        $fremdesModul = Modul::factory()->create();

        $this->actingAs($this->user)->postJson(route('learner.grades.calculator.simulate'), [
            'zeilen' => [$this->zeile(['typ' => 'modul', 'fach_id' => null, 'modul_id' => $fremdesModul->modul_id])],
        ])->assertStatus(422);

        $this->actingAs($this->user)->postJson(route('learner.grades.calculator.simulate'), [
            'zeilen' => [$this->zeile(['typ' => 'fach', 'fach_id' => null])],
        ])->assertJsonValidationErrors('zeilen.0.fach_id');

        $this->actingAs($this->user)->postJson(route('learner.grades.calculator.simulate'), [
            'zeilen' => [],
        ])->assertJsonValidationErrors('zeilen');
    }

    #[Test]
    public function nur_lernende_haben_zugriff(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $admin = User::factory()->admin()->create();

        // Gast zuerst prüfen: actingAs() bleibt sonst für spätere Requests in diesem Test aktiv.
        $this->postJson(route('learner.grades.calculator.simulate'), ['zeilen' => [$this->zeile()]])
            ->assertUnauthorized();
        $this->actingAs($bb)->postJson(route('learner.grades.calculator.simulate'), ['zeilen' => [$this->zeile()]])
            ->assertForbidden();
        $this->actingAs($admin)->postJson(route('learner.grades.calculator.simulate'), ['zeilen' => [$this->zeile()]])
            ->assertForbidden();
    }

    #[Test]
    public function drawer_oeffnet_per_parameter_serverseitig(): void
    {
        $this->actingAs($this->user)->get(route('learner.grades.index'))
            ->assertOk()->assertSee(__('Notenrechner'))->assertDontSee('offen: true', false);

        $this->actingAs($this->user)->get(route('learner.grades.index', ['rechner' => 1]))
            ->assertOk()->assertSee('offen: true', false);
    }
}
