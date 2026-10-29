<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Models\Betreuung;
use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Lehrberuf;
use App\Models\Lernender;
use App\Models\Semester;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Bericht;
use App\Services\Uebersicht;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Die Daten der Statistikseiten (Punktstreifen, Hantel, Sparkline, Verteilung, Lehrjahresvergleich) dürfen die
 * Abfragenzahl nicht mit der Zahl der Lernenden wachsen lassen. Muster: AbfragenAnzahlTest – zuerst 2, dann 8 Lernende.
 */
class StatistikAbfragenTest extends TestCase
{
    private const int TOLERANZ = 2;

    private object $betrieb;

    private Collection $semester;

    protected function setUp(): void
    {
        parent::setUp();

        $kategorieId = Kategorie::where('code', 'FACH')->value('kategorie_id');
        $lehrberuf = Lehrberuf::factory()->create();
        $fach = Fach::factory()->create(['track_typ' => null, 'kategorie_id' => $kategorieId]);
        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $lehrberuf->lehrberuf_id, 'fach_id' => $fach->fach_id, 'aktiv' => 1]);
        $this->betrieb = (object) ['lehrberuf' => $lehrberuf, 'fach' => $fach, 'kategorieId' => $kategorieId];

        $start = Carbon::parse('2020-08-01');
        $this->semester = collect();
        for ($i = 1; $i <= 2; $i++) {
            $ende = $start->copy()->addMonths(6)->subDay();
            $this->semester->push(Semester::factory()->create([
                'bezeichnung' => 'S'.$i.'-'.fake()->unique()->numberBetween(1, 99999),
                'start_datum' => $start->toDateString(),
                'end_datum' => $ende->toDateString(),
                'sortierung' => $i,
            ]));
            $start = $ende->copy()->addDay();
        }
    }

    private function abfragenFuer(\Closure $aktion): int
    {
        Konfiguration::vergessen();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $aktion();
        $anzahl = count(DB::getQueryLog());
        DB::flushQueryLog();

        return $anzahl;
    }

    private function lernender(?User $bb = null, float $vorher = 4.0, float $jetzt = 5.0, bool $laufend = false): Lernender
    {
        $lernender = User::factory()->lernender([
            'lehrberuf_id' => $this->betrieb->lehrberuf->lehrberuf_id,
            'lehrbeginn' => $laufend ? now()->subMonths(18)->toDateString() : $this->semester->first()->start_datum,
            'lehrende' => $laufend ? now()->addMonths(30)->toDateString() : $this->semester->last()->end_datum,
        ])->create()->lernender;

        if ($bb !== null) {
            Betreuung::factory()->create(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $lernender->lernender_id]);
        }
        foreach ([[$this->semester[0], $vorher], [$this->semester[1], $jetzt]] as [$semester, $wert]) {
            DB::table('noten')->insert([
                'lernender_id' => $lernender->lernender_id,
                'kategorie_id' => $this->betrieb->kategorieId,
                'semester_id' => $semester->semester_id,
                'fach_id' => $this->betrieb->fach->fach_id,
                'modul_belegung_id' => null,
                'titel' => 'Note',
                'pruefungsdatum' => Carbon::parse($semester->start_datum)->addDays(10)->toDateString(),
                'note_wert' => $wert,
                'gewichtung_prozent' => 100,
                'erfasst_von_benutzer_id' => $lernender->benutzer_id,
            ]);
        }

        return $lernender;
    }

    private function assertWaechstNicht(int $klein, int $gross, string $seite): void
    {
        $this->assertLessThanOrEqual($klein + self::TOLERANZ, $gross, "{$seite}: Abfragenzahl wächst mit der Zahl der Lernenden ({$klein} -> {$gross}).");
    }

    #[Test]
    public function berufsbildner_dashboard_bleibt_bei_8_statt_2_lernenden_im_rahmen(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        for ($i = 0; $i < 2; $i++) {
            $this->lernender($bb);
        }
        $klein = $this->abfragenFuer(fn () => $this->actingAs($bb)->get(route('trainer.dashboard'))->assertOk());

        for ($i = 0; $i < 6; $i++) {
            $this->lernender($bb);
        }
        $gross = $this->abfragenFuer(fn () => $this->actingAs($bb)->get(route('trainer.dashboard'))->assertOk());

        $this->assertWaechstNicht($klein, $gross, 'Berufsbildner-Dashboard');
    }

    #[Test]
    public function berufsbildner_statistikdaten_kosten_keine_abfrage_je_person(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        for ($i = 0; $i < 2; $i++) {
            $this->lernender($bb);
        }
        $klein = $this->abfragenFuer(fn () => app(Uebersicht::class)->berufsbildner($bb));

        for ($i = 0; $i < 6; $i++) {
            $this->lernender($bb);
        }
        $gross = $this->abfragenFuer(fn () => app(Uebersicht::class)->berufsbildner($bb));

        $this->assertWaechstNicht($klein, $gross, 'Uebersicht::berufsbildner');
    }

    #[Test]
    public function bericht_bleibt_bei_8_statt_2_lernenden_im_rahmen(): void
    {
        $admin = User::factory()->admin()->create();
        for ($i = 0; $i < 2; $i++) {
            $this->lernender();
        }
        $klein = $this->abfragenFuer(fn () => $this->actingAs($admin)->get(route('admin.reports.grades'))->assertOk());
        $kleinFilter = $this->abfragenFuer(fn () => app(Bericht::class)->noten(['semester_id' => null, 'lehrberuf_id' => null, 'berufsbildner_id' => null, 'lehrjahr' => 1, 'kategorie_id' => $this->betrieb->kategorieId]));

        for ($i = 0; $i < 6; $i++) {
            $this->lernender();
        }
        $gross = $this->abfragenFuer(fn () => $this->actingAs($admin)->get(route('admin.reports.grades'))->assertOk());
        $grossFilter = $this->abfragenFuer(fn () => app(Bericht::class)->noten(['semester_id' => null, 'lehrberuf_id' => null, 'berufsbildner_id' => null, 'lehrjahr' => 1, 'kategorie_id' => $this->betrieb->kategorieId]));

        $this->assertWaechstNicht($klein, $gross, 'Bericht');
        $this->assertWaechstNicht($kleinFilter, $grossFilter, 'Bericht mit Filtern');
    }

    #[Test]
    public function admin_aktivitaet_kostet_fuer_jeden_zeitraum_gleich_viele_abfragen(): void
    {
        $this->lernender();
        app(Uebersicht::class)->admin();
        $zahlen = [];
        foreach ([12, 26, 52] as $wochen) {
            $zahlen[$wochen] = $this->abfragenFuer(fn () => app(Uebersicht::class)->admin($wochen));
        }

        $this->assertSame(1, count(array_unique($zahlen)), 'Abfragen je Zeitraum: '.json_encode($zahlen));
    }

    #[Test]
    public function berufsbildner_sieht_delta_semesterschnitte_und_punktstreifen_nur_seiner_lernenden(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $fremderBb = User::factory()->berufsbildner()->create();
        $a = $this->lernender($bb, 4.0, 5.0);
        $b = $this->lernender($bb, 5.0, 4.0);
        $c = $this->lernender($bb, 3.0, 3.5);
        $fremd = $this->lernender($fremderBb, 6.0, 6.0);

        $daten = app(Uebersicht::class)->berufsbildner($bb);

        $zeilen = $daten['zeilen']->keyBy(fn ($z) => (int) $z->lernender->lernender_id);
        $this->assertCount(3, $zeilen);
        $this->assertFalse($zeilen->has((int) $fremd->lernender_id));
        $this->assertSame(1.0, $zeilen[(int) $a->lernender_id]->delta);
        $this->assertSame(-1.0, $zeilen[(int) $b->lernender_id]->delta);
        $this->assertSame([4.0, 5.0], $zeilen[(int) $a->lernender_id]->semesterschnitte);
        $this->assertSame(4.5, $zeilen[(int) $a->lernender_id]->gesamt);

        $streifen = $daten['punktstreifen'];
        $this->assertSame(3, $streifen['n']);
        $this->assertSame([3.3, 4.5, 4.5], array_column($streifen['punkte'], 'gesamt'));
        $this->assertSame(4.5, $streifen['median']);
        $this->assertNotContains((int) $fremd->lernender_id, array_column($streifen['punkte'], 'id'));
    }

    #[Test]
    public function punktstreifen_hat_unter_drei_personen_keinen_median(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $this->lernender($bb);
        $this->lernender($bb);

        $streifen = app(Uebersicht::class)->berufsbildner($bb)['punktstreifen'];

        $this->assertSame(2, $streifen['n']);
        $this->assertNull($streifen['median']);
    }

    #[Test]
    public function aktivitaet_erlaubt_nur_12_26_und_52_wochen(): void
    {
        $uebersicht = app(Uebersicht::class);

        $this->assertSame(12, $uebersicht->admin()['aktivitaet']['wochen']);
        $this->assertSame(12, $uebersicht->admin(7)['aktivitaet']['wochen']);
        $this->assertSame(26, count($uebersicht->admin(26)['aktivitaet']['werte']));
        $this->assertSame(52, count($uebersicht->admin(52)['aktivitaet']['labels']));
        $this->assertSame(12, count($uebersicht->admin(1000)['aktivitaet']['werte']));
    }

    #[Test]
    public function aktivitaet_zaehlt_nur_nicht_geloeschte_noten_und_liefert_den_median(): void
    {
        $lernender = $this->lernender();
        DB::table('noten')->where('lernender_id', $lernender->lernender_id)->update(['erstellt_am' => now()]);
        DB::table('noten')->where('lernender_id', $lernender->lernender_id)->limit(1)->update(['geloescht_am' => now()]);

        $aktivitaet = app(Uebersicht::class)->admin()['aktivitaet'];

        $this->assertSame(1, array_sum($aktivitaet['werte']));
        $this->assertSame(0.0, $aktivitaet['median']);
    }

    #[Test]
    public function bericht_liefert_viertelnoten_median_und_personenwerte_je_lehrjahr(): void
    {
        foreach ([[4.0, 5.0], [4.0, 4.0], [5.0, 5.5]] as [$vorher, $jetzt]) {
            $this->lernender(null, $vorher, $jetzt, laufend: true);
        }

        $r = app(Bericht::class)->noten(['semester_id' => null, 'lehrberuf_id' => null, 'berufsbildner_id' => null]);

        $this->assertCount(20, $r['verteilung']['labels']);
        $this->assertSame(0.25, $r['verteilung']['breite']);
        $this->assertSame(6, $r['verteilung']['n'], 'zwei Zeugnisnoten je Lernende (ein Fach, zwei Semester)');
        $this->assertNotNull($r['verteilung']['median']);
        $this->assertSame(6, array_sum($r['verteilung']['werte']));

        $this->assertNotEmpty($r['nachLehrjahr']);
        $jahr = $r['nachLehrjahr'][0];
        $this->assertSame(3, $jahr['anzahl']);
        $this->assertCount(3, $jahr['werte']);
        $this->assertNotNull($jahr['median']);

        $this->assertSame(2, $jahr['jahr']);

        $passend = app(Bericht::class)->noten(['semester_id' => null, 'lehrberuf_id' => null, 'berufsbildner_id' => null, 'lehrjahr' => 2, 'kategorie_id' => $this->betrieb->kategorieId]);
        $this->assertCount(3, $passend['zeilen']);
        $this->assertSame(6, $passend['verteilung']['n']);
        $this->assertCount(1, $passend['nachLehrjahr']);

        $fremdeKategorie = app(Bericht::class)->noten(['semester_id' => null, 'lehrberuf_id' => null, 'berufsbildner_id' => null, 'kategorie_id' => $this->betrieb->kategorieId + 999]);
        $this->assertSame(0, $fremdeKategorie['verteilung']['n']);
        $this->assertSame([], $fremdeKategorie['nachLehrjahr']);

        $gefiltert = app(Bericht::class)->noten(['semester_id' => null, 'lehrberuf_id' => null, 'berufsbildner_id' => null, 'lehrjahr' => 4, 'kategorie_id' => $this->betrieb->kategorieId]);
        $this->assertCount(0, $gefiltert['zeilen']);
        $this->assertSame(0, $gefiltert['verteilung']['n']);
        $this->assertNull($gefiltert['verteilung']['median']);
    }
}
