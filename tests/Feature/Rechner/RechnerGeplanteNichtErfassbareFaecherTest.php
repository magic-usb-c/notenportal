<?php

declare(strict_types=1);

namespace Tests\Feature\Rechner;

use App\Models\Betreuung;
use App\Models\Fach;
use App\Models\Lernender;
use App\Models\LernenderTrack;
use App\Models\Pruefung;
use App\Models\Semester;
use App\Models\User;
use App\Models\Ziel;
use App\Services\Auswertung\Auswertung;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\NotenQuelle;
use App\Services\Auswertung\Rechner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Geplante Prüfung in einem Fach, das heute nicht mehr erfassbar ist (BMS-Track beendet): Katalog und
 * Vorschläge müssen zusammenpassen, sonst lehnt der Rechner die eigenen Vorschläge als «Ungültige Auswahl.» ab.
 */
class RechnerGeplanteNichtErfassbareFaecherTest extends TestCase
{
    private User $user;

    private Lernender $lernender;

    private Fach $fachBms;

    private Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->lernender()->create();
        $this->lernender = $this->user->lernender;
        $this->semester = Semester::factory()->create([
            'start_datum' => now()->subMonths(3)->toDateString(),
            'end_datum' => now()->addMonths(3)->toDateString(),
            'sortierung' => 2002,
        ]);
        $this->fachBms = Fach::factory()->create(['track_typ' => 'BMS']);
        LernenderTrack::create([
            'lernender_id' => $this->lernender->lernender_id, 'track_typ' => 'BMS',
            'start_datum' => now()->subYear()->toDateString(), 'start_semester_id' => $this->semester->semester_id,
            'end_datum' => now()->subMonth()->toDateString(),
        ]);
        Pruefung::create([
            'lernender_id' => $this->lernender->lernender_id, 'fach_id' => $this->fachBms->fach_id,
            'titel' => 'LB', 'datum' => now()->addDays(10)->toDateString(), 'gewichtung_prozent' => 100,
        ]);
        Ziel::create(['lernender_id' => $this->lernender->lernender_id, 'ebene' => 'gesamt', 'zielwert' => 4.5]);
        Konfiguration::vergessen();
    }

    private function auswertung(): Auswertung
    {
        return app(NotenQuelle::class)->auswertung((int) $this->lernender->lernender_id);
    }

    #[Test]
    public function geplantes_fach_ausserhalb_des_tracks_steht_nicht_erfassbar_im_katalog(): void
    {
        $katalog = app(Rechner::class)->katalog($this->lernender);

        $eintrag = collect($katalog['faecher'])->firstWhere('id', (int) $this->fachBms->fach_id);
        $this->assertNotNull($eintrag, 'Fach mit geplanter Prüfung bleibt im Katalog');
        $this->assertFalse($eintrag['erfassbar']);
        $this->assertSame((int) $this->fachBms->kategorie_id, $eintrag['kategorie_id']);
        $this->assertTrue(collect($katalog['semester'])->contains('id', (int) $this->semester->semester_id));
    }

    #[Test]
    public function lernenden_dashboard_mit_ziel_laedt_ohne_umleitung(): void
    {
        $this->actingAs($this->user)->get(route('learner.dashboard'))->assertOk();
    }

    #[Test]
    public function rechner_seite_und_standard_waehlt_kein_nicht_erfassbares_fach(): void
    {
        $this->actingAs($this->user)->get(route('learner.grades.calculator'))->assertOk();

        $erfassbar = Fach::factory()->create(['track_typ' => null, 'name' => 'Zzz Berufsfach']);
        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $this->lernender->lehrberuf_id, 'fach_id' => $erfassbar->fach_id]);
        Konfiguration::vergessen();

        $seite = app(Rechner::class)->seite($this->lernender, false);
        $katalog = collect($seite['katalog']['faecher']);
        if (! $katalog->firstWhere('erfassbar', true)) {
            $this->markTestSkipped('Kein erfassbares Fach im Testaufbau.');
        }
        $this->assertStringStartsNotWith('fach:'.$this->fachBms->fach_id.'@', (string) $seite['standard']);
    }

    #[Test]
    public function berechnen_mit_den_vorschlaegen_liefert_in_allen_fuenf_modi_200(): void
    {
        $seite = app(Rechner::class)->seite($this->lernender, false);
        $this->assertNotEmpty($seite['vorschlaege']);
        $sid = (int) $this->semester->semester_id;
        $fid = (int) $this->fachBms->fach_id;
        $kid = (int) $this->fachBms->kategorie_id;
        $modul = DB::table('module')->insertGetId(['modul_nummer' => '777', 'titel' => 'Testmodul']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $this->lernender->lehrberuf_id, 'modul_id' => $modul, 'kategorie_id' => $kid]);
        Konfiguration::vergessen();

        foreach (['gesamt', "kategorie:{$kid}", "semester:{$sid}", "fach:{$fid}@semester:{$sid}", "modul:{$modul}"] as $ziel) {
            // ohne eigene Zeilen und mit den Vorschlägen der Seite
            foreach ([[], $seite['vorschlaege']] as $zeilen) {
                $this->actingAs($this->user)->postJson(route('learner.grades.calculator.calculate'), [
                    'ziel' => $ziel, 'zielwert' => 4.5,
                    'zeilen' => array_map(fn ($z) => ['element' => $z['element'], 'gewicht' => $z['gewicht'], 'wert' => null, 'datum' => $z['datum']], $zeilen),
                ])->assertOk();
            }
        }
    }

    #[Test]
    public function berechnen_mit_simulierter_note_fuer_das_geplante_fach_ist_gueltig(): void
    {
        $sid = (int) $this->semester->semester_id;
        $this->actingAs($this->user)->postJson(route('learner.grades.calculator.calculate'), [
            'ziel' => 'gesamt', 'zielwert' => 4.5,
            'zeilen' => [['element' => "fach:{$this->fachBms->fach_id}@semester:{$sid}", 'gewicht' => 100, 'wert' => 5, 'datum' => null]],
        ])->assertOk();
    }

    #[Test]
    public function pruefung_ausserhalb_der_lehrzeit_haengt_ihr_semester_im_katalog_an(): void
    {
        $this->lernender->forceFill(['lehrende' => now()->subMonths(2)->toDateString()])->save();
        Konfiguration::vergessen();

        $this->assertTrue(collect(app(Rechner::class)->katalog($this->lernender->fresh())['semester'])->contains('id', (int) $this->semester->semester_id));
        $this->actingAs($this->user)->get(route('learner.dashboard'))->assertOk();
        $this->actingAs($this->user)->postJson(route('learner.grades.calculator.calculate'), [
            'ziel' => 'gesamt', 'zielwert' => 4.5, 'zeilen' => [],
        ])->assertOk();
    }

    #[Test]
    public function admin_und_berufsbildner_detail_laden(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.learners.show', $this->lernender->lernender_id))->assertOk();

        $bb = User::factory()->berufsbildner()->create();
        Betreuung::factory()->create(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $this->lernender->lernender_id]);
        $this->actingAs($bb)->get(route('trainer.learners.show', $this->lernender->lernender_id))->assertOk();
    }

    #[Test]
    public function unbekannter_vorschlag_bricht_offene_leistungen_nicht_ab_und_wird_protokolliert(): void
    {
        // Sicherheitsnetz: Vorschläge stammen aus einer Auswertung mit älterer Konfiguration, das Semester fehlt dem Katalog.
        $rechner = app(Rechner::class);
        $a = $this->auswertung();
        $this->assertNotEmpty($rechner->vorschlaege($this->lernender, $a));

        DB::table('semester')->where('semester_id', $this->semester->semester_id)->update(['start_datum' => '2000-01-01', 'end_datum' => '2000-06-30']);
        Konfiguration::vergessen();

        Log::spy();
        $this->assertSame([], $rechner->offeneLeistungen($this->lernender, $a));
        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $meldung, array $kontext) => ($kontext['lernender_id'] ?? null) === (int) $this->lernender->lernender_id
                && str_contains((string) ($kontext['grund'] ?? ''), 'Ungültige Auswahl'),
        );
    }
}
