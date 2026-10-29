<?php

declare(strict_types=1);

namespace Tests\Feature\Statistik;

use App\Models\Betreuung;
use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Lehrberuf;
use App\Models\Lernender;
use App\Models\Semester;
use App\Models\User;
use App\Services\Auswertung\LernstandRechner;
use App\Support\Einrichtung;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Die JSON-Antworten der Statistikseiten haben genau die Berechtigungen der HTML-Seiten: Lernende nur eigene Werte
 * (ID aus der Session, nie aus dem Request), Berufsbildner nur aktiv betreute Personen (Lernender::sichtbarFuer).
 */
class StatistikIsolationTest extends TestCase
{
    private int $kategorieId;

    private Lehrberuf $lehrberuf;

    /** @var list<Semester> */
    private array $semester = [];

    protected function setUp(): void
    {
        parent::setUp();
        Einrichtung::abschliessen();

        $this->kategorieId = (int) Kategorie::where('code', 'FACH')->value('kategorie_id');
        $this->lehrberuf = Lehrberuf::factory()->create();

        $start = Carbon::parse('2020-08-01');
        foreach ([1, 2] as $i) {
            $ende = $start->copy()->addMonths(6)->subDay();
            $this->semester[] = Semester::factory()->create([
                'bezeichnung' => 'S'.$i.'-'.fake()->unique()->numberBetween(1, 99999),
                'start_datum' => $start->toDateString(),
                'end_datum' => $ende->toDateString(),
                'sortierung' => $i,
            ]);
            $start = $ende->copy()->addDay();
        }
    }

    /** Lernender mit einer Note je Semester in einem eigenen Fach. */
    #[Test]
    public function lernstand_rundet_das_delta_bevor_die_schwelle_greift(): void
    {
        // Zeugnisnoten 4.5/4.0/4.5 → Semesterschnitt 4.3 (Rundung 0.1), danach 4.0: 4.0 − 4.3 ist in Fliesskomma −0.2999…
        // Hantel, Lernstand::delta() und der Grund «−0.3 zum Vorsemester» müssen dasselbe sagen
        $bb = User::factory()->berufsbildner()->create();
        $a = $this->lernender('Rundungsfach', 4.5, 4.0, $bb);
        $this->fachMitNoten($a, 'Rundungsfach zwei', 4.0, 4.0);
        $this->fachMitNoten($a, 'Rundungsfach drei', 4.5, 4.0);

        $stand = app(LernstandRechner::class)->fuer([$a->lernender->lernender_id])[$a->lernender->lernender_id];
        $this->assertSame(-0.3, $stand->delta());
        $this->assertContains(__(':delta zum Vorsemester', ['delta' => '-0.3']), $stand->gruende);

        $hantel = $this->actingAs($bb)->getJson(route('trainer.dashboard'))->assertOk()->json('diagramm.hantel.zeilen');
        $this->assertSame(-0.3, $hantel[0]['delta']);
    }

    private function lernender(string $fachName, float $vorher, float $jetzt, ?User $bb = null): User
    {
        $user = User::factory()->lernender([
            'lehrberuf_id' => $this->lehrberuf->lehrberuf_id,
            'lehrbeginn' => now()->subMonths(18)->toDateString(),
            'lehrende' => now()->addMonths(30)->toDateString(),
        ])->create();

        if ($bb !== null) {
            Betreuung::factory()->create(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $user->lernender->lernender_id]);
        }
        $this->fachMitNoten($user, $fachName, $vorher, $jetzt);

        return $user;
    }

    /** Eigenes Fach des Lehrberufs mit einer Note je Semester. */
    private function fachMitNoten(User $user, string $fachName, float $vorher, float $jetzt): void
    {
        $fach = Fach::factory()->create(['name' => $fachName, 'track_typ' => null, 'kategorie_id' => $this->kategorieId]);
        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $this->lehrberuf->lehrberuf_id, 'fach_id' => $fach->fach_id, 'aktiv' => 1]);
        foreach ([[$this->semester[0], $vorher], [$this->semester[1], $jetzt]] as [$semester, $wert]) {
            DB::table('noten')->insert([
                'lernender_id' => $user->lernender->lernender_id,
                'kategorie_id' => $this->kategorieId,
                'semester_id' => $semester->semester_id,
                'fach_id' => $fach->fach_id,
                'modul_belegung_id' => null,
                'titel' => 'Note',
                'pruefungsdatum' => Carbon::parse($semester->start_datum)->addDays(10)->toDateString(),
                'note_wert' => $wert,
                'gewichtung_prozent' => 100,
                'erfasst_von_benutzer_id' => $user->benutzer_id,
            ]);
        }
    }

    #[Test]
    public function lernende_sehen_mit_fremder_id_im_request_nur_eigene_werte(): void
    {
        $a = $this->lernender('Eigenfach', 4.0, 5.0);
        $b = $this->lernender('Fremdfach', 6.0, 6.0);

        // B hat eine geplante Prüfung: ein Leck in der Zeitleiste (/exams) wäre sonst unsichtbar
        $b->lernender->pruefungen()->create(['fach_id' => Fach::where('name', 'Fremdfach')->value('fach_id'), 'titel' => 'Plan Fremdfach', 'datum' => now()->addDays(3)->toDateString()]);

        $routen = ['learner.dashboard', 'learner.grades.index', 'learner.qualification.index', 'learner.exams.index'];
        $ohne = [];
        foreach ($routen as $route) {
            $ohne[$route] = $this->actingAs($a)->getJson(route($route))->assertOk()->getContent();
        }
        $fremd = [
            ['lernender' => $b->lernender->lernender_id], ['lernender_id' => $b->lernender->lernender_id], ['id' => $b->lernender->lernender_id],
            ['benutzer_id' => $b->benutzer_id], ['user' => $b->benutzer_id],
        ];
        foreach ($fremd as $abfrage) {
            foreach ($routen as $route) {
                // byte-gleich mit der Antwort ohne Parameter: der Parameter wird nirgends gelesen
                $this->assertSame($ohne[$route], $this->actingAs($a)->getJson(route($route, $abfrage))->assertOk()->getContent(), $route.' '.key($abfrage));
            }
        }
        foreach ($ohne as $route => $inhalt) {
            $this->assertStringNotContainsString('Fremdfach', $inhalt, $route);
        }
        $this->assertStringContainsString('Eigenfach', $ohne['learner.dashboard']);
        $this->assertEqualsCanonicalizing([4.0, 5.0], array_column(json_decode($ohne['learner.dashboard'], true)['diagramm']['verlauf']['punkte'], 'note'));
    }

    #[Test]
    public function berufsbildner_auf_nicht_betreuter_person_bekommt_denselben_status_wie_die_html_seite(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $fremderBb = User::factory()->berufsbildner()->create();
        $fremd = $this->lernender('Fremdfach', 5.0, 5.0, $fremderBb);

        $html = $this->actingAs($bb)->get(route('trainer.learners.show', $fremd->lernender->lernender_id));
        $json = $this->actingAs($bb)->getJson(route('trainer.learners.show', $fremd->lernender->lernender_id));

        $this->assertNotSame(200, $html->getStatusCode());
        $this->assertSame($html->getStatusCode(), $json->getStatusCode());
        $this->assertStringNotContainsString('Fremdfach', $json->getContent());
    }

    #[Test]
    public function betreute_person_ist_fuer_den_berufsbildner_sichtbar_nicht_aber_fuer_einen_anderen(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $andererBb = User::factory()->berufsbildner()->create();
        $eigen = $this->lernender('Eigenfach', 4.0, 4.5, $bb);

        $this->actingAs($bb)->getJson(route('trainer.learners.show', $eigen->lernender->lernender_id))->assertOk()->assertSee('Eigenfach');
        $this->actingAs($andererBb)->getJson(route('trainer.learners.show', $eigen->lernender->lernender_id))->assertNotFound();
    }

    #[Test]
    public function median_ueber_sichtbare_personen_aendert_sich_nicht_durch_fremde_extremnote(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $fremderBb = User::factory()->berufsbildner()->create();
        foreach ([4.0, 4.5, 5.0] as $note) {
            $this->lernender('Fach'.$note, $note, $note, $bb);
        }

        foreach (['trainer.learners.index'] as $route) {
            $vorher = $this->actingAs($bb)->getJson(route($route))->assertOk();
            $this->assertEqualsWithDelta(4.5, $vorher->json('diagramm.punktstreifen.median'), 1e-9);
            $this->assertSame(3, $vorher->json('diagramm.punktstreifen.n'));
        }

        $this->lernender('Extremfach', 6.0, 6.0, $fremderBb);

        $nachher = $this->actingAs($bb)->getJson(route('trainer.learners.index'))->assertOk();
        $this->assertEqualsWithDelta(4.5, $nachher->json('diagramm.punktstreifen.median'), 1e-9);
        $this->assertSame(3, $nachher->json('diagramm.punktstreifen.n'));
        $this->assertStringNotContainsString('Extremfach', $nachher->getContent());

        // das Berufsbildner-Dashboard sieht ebenfalls nur die eigenen drei
        $dashboard = $this->actingAs($bb)->getJson(route('trainer.dashboard'))->assertOk();
        $this->assertCount(3, $dashboard->json('diagramm.hantel.zeilen'));
    }

    #[Test]
    public function median_ist_bei_zwei_personen_null(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $this->lernender('FachA', 4.0, 4.0, $bb);
        $this->lernender('FachB', 6.0, 6.0, $bb);

        $antwort = $this->actingAs($bb)->getJson(route('trainer.learners.index'))->assertOk();

        $this->assertNull($antwort->json('diagramm.punktstreifen.median'));
        $this->assertSame(2, $antwort->json('diagramm.punktstreifen.n'));
    }

    #[Test]
    public function admin_sieht_alle_berufsbildner_nur_die_eigenen(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $this->lernender('Eigenfach', 4.0, 4.0, $bb);
        $this->lernender('Fremdfach', 5.0, 5.0, User::factory()->berufsbildner()->create());

        $this->assertSame(1, $this->actingAs($bb)->getJson(route('trainer.learners.index'))->json('diagramm.punktstreifen.n'));
        $this->assertSame(2, $this->actingAs(User::factory()->admin()->create())->getJson(route('admin.learners.index'))->json('diagramm.punktstreifen.n'));
    }

    #[Test]
    public function lernende_haben_keinen_zugriff_auf_fremde_statistikrouten_per_json(): void
    {
        $a = $this->lernender('Eigenfach', 4.0, 4.0);

        foreach (['trainer.dashboard', 'trainer.learners.index', 'admin.dashboard', 'admin.learners.index', 'admin.reports.grades'] as $route) {
            $this->actingAs($a)->getJson(route($route))->assertForbidden();
        }
    }
}
