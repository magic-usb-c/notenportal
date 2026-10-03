<?php

declare(strict_types=1);

namespace Tests\Unit\Auswertung;

use App\Models\Note;
use App\Models\Semester;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Leistung;
use App\Services\Auswertung\NotenQuelle;
use App\Services\Auswertung\Rechenkern;
use App\Services\Auswertung\Statistik;
use App\Services\Auswertung\Zielgroesse;
use App\Services\Auswertung\Zielrechner;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StatistikTest extends TestCase
{
    use AuswertungTestHilfen;

    private function note(int $fachId, string $datum, ?float $wert, float $gewicht = 100.0, int $kategorie = self::FACH, ?int $id = null): Leistung
    {
        return new Leistung($kategorie, $fachId, null, null, $datum, $wert, $gewicht, id: $id);
    }

    private function gesamt(): Zielgroesse
    {
        return new Zielgroesse('gesamt');
    }

    #[Test]
    public function stichtagsreihe_rechnet_an_monatsenden_mit_dem_stand_dieses_tages(): void
    {
        $leistungen = [
            $this->note(self::MATHE, '2024-09-15', 4.0, id: 1),
            $this->note(self::MATHE, '2024-11-10', 5.0, id: 2),
            $this->note(self::DEUTSCH, '2025-03-01', 3.0, id: 3),
        ];

        $r = (new Statistik)->stichtagsreihe($leistungen, $this->testKonfiguration(), $this->gesamt(), Carbon::parse('2024-09-01'), Carbon::parse('2025-03-31'));

        $this->assertSame(['2024-09-30', '2024-10-31', '2024-11-30', '2024-12-31', '2025-01-31', '2025-02-28', '2025-03-31'], array_column($r['reihe'], 'stichtag'));
        $this->assertSame([4.0, 4.0, 4.5, 4.5, 4.5, 4.5, 3.8], array_column($r['reihe'], 'wert'));
        $this->assertFalse($r['meta']['vergroebert']);
        $this->assertSame(7, $r['meta']['stichtage']);
        $this->assertSame([[1, 4.0, 'Mathematik'], [2, 5.0, 'Mathematik'], [3, 3.0, 'Deutsch']], array_map(fn ($p) => [$p['id'], $p['note'], $p['fach']], $r['punkte']));
    }

    #[Test]
    public function stichtag_vor_der_ersten_note_hat_keinen_wert(): void
    {
        $r = (new Statistik)->stichtagsreihe([$this->note(self::MATHE, '2024-10-20', 4.0)], $this->testKonfiguration(), $this->gesamt(), Carbon::parse('2024-09-01'), Carbon::parse('2024-10-31'));

        $this->assertSame([null, 4.0], array_column($r['reihe'], 'wert'));
    }

    #[Test]
    public function der_letzte_stichtag_ist_bis_wenn_es_kein_periodenende_ist(): void
    {
        $r = (new Statistik)->stichtagsreihe([], $this->testKonfiguration(), $this->gesamt(), Carbon::parse('2025-01-10'), Carbon::parse('2025-02-15'));
        $this->assertSame(['2025-01-31', '2025-02-15'], array_column($r['reihe'], 'stichtag'));

        $kurz = (new Statistik)->stichtagsreihe([], $this->testKonfiguration(), $this->gesamt(), Carbon::parse('2025-01-05'), Carbon::parse('2025-01-20'));
        $this->assertSame(['2025-01-20'], array_column($kurz['reihe'], 'stichtag'));

        $umgekehrt = (new Statistik)->stichtagsreihe([], $this->testKonfiguration(), $this->gesamt(), Carbon::parse('2025-02-01'), Carbon::parse('2025-01-01'));
        $this->assertSame([], $umgekehrt['reihe']);
    }

    #[Test]
    public function sechzig_monate_bleiben_monatlich_siebzig_werden_quartale(): void
    {
        $statistik = new Statistik;
        $k = $this->testKonfiguration();

        $sechzig = $statistik->stichtagsreihe([], $k, $this->gesamt(), Carbon::parse('2021-01-01'), Carbon::parse('2025-12-31'));
        $this->assertSame(60, $sechzig['meta']['stichtage']);
        $this->assertFalse($sechzig['meta']['vergroebert']);

        $siebzig = $statistik->stichtagsreihe([], $k, $this->gesamt(), Carbon::parse('2020-01-01'), Carbon::parse('2025-10-31'));
        $this->assertTrue($siebzig['meta']['vergroebert']);
        $tage = array_column($siebzig['reihe'], 'stichtag');
        $this->assertSame('2020-03-31', $tage[0]);
        $this->assertSame('2025-10-31', end($tage));
        $this->assertCount(24, $tage);
        foreach (array_slice($tage, 0, -1) as $tag) {
            $this->assertContains((int) substr($tag, 5, 2), [3, 6, 9, 12]);
        }
    }

    #[Test]
    public function leistungen_ohne_datum_stehen_in_meta_und_nicht_in_der_reihe(): void
    {
        $ohne = new Leistung(self::FACH, self::DEUTSCH, null, 1, null, 6.0, id: 9, titel: 'Schlussprüfung');
        $position = Leistung::position(5, 5.5);
        $offen = Leistung::position(6, null);

        $r = (new Statistik)->stichtagsreihe(
            [$this->note(self::MATHE, '2024-09-15', 4.0, id: 1), $ohne, $position, $offen],
            $this->testKonfiguration(), $this->gesamt(), Carbon::parse('2024-09-01'), Carbon::parse('2024-10-31'),
        );

        $this->assertSame([4.0, 4.0], array_column($r['reihe'], 'wert'), 'ohne Datum zählt in keinem Stichtag');
        $this->assertCount(1, $r['punkte']);
        $this->assertSame(
            [['id' => 9, 'titel' => 'Schlussprüfung', 'knotenId' => null, 'wert' => 6.0], ['id' => null, 'titel' => null, 'knotenId' => 5, 'wert' => 5.5]],
            $r['meta']['ohneDatum'],
        );
    }

    #[Test]
    public function punkte_gehoeren_zur_zielgroesse_und_in_den_zeitraum(): void
    {
        $leistungen = [
            $this->note(self::MATHE, '2024-09-15', 4.0, id: 1),
            $this->note(self::DEUTSCH, '2024-09-20', 5.0, id: 2),
            $this->note(self::MATHE, '2025-04-01', 3.0, id: 3),
            $this->note(self::MATHE, '2024-12-01', null, id: 4),
        ];

        $r = (new Statistik)->stichtagsreihe($leistungen, $this->testKonfiguration(), new Zielgroesse('fach', self::MATHE), Carbon::parse('2024-09-01'), Carbon::parse('2025-01-31'));

        $this->assertSame([1], array_column($r['punkte'], 'id'), 'andere Fächer, spätere und offene Prüfungen sind keine Punkte');
    }

    #[Test]
    public function hanteln_sortieren_den_groessten_rueckgang_zuerst_und_stellen_unvollstaendige_ans_ende(): void
    {
        $k = $this->testKonfiguration();
        $a = (new Rechenkern)->auswerten([
            new Leistung(self::FACH, self::MATHE, null, 1, null, 4.0),
            new Leistung(self::FACH, self::MATHE, null, 2, null, 5.0),
            new Leistung(self::FACH, self::DEUTSCH, null, 1, null, 5.0),
            new Leistung(self::FACH, self::DEUTSCH, null, 2, null, 4.0),
            new Leistung(self::FACH, self::ENGLISCH, null, 2, null, 4.5),
        ], $k);

        $h = (new Statistik)->hanteln($a, 2, 1, null);

        $this->assertSame(['Deutsch', 'Mathematik', 'Englisch'], array_column($h, 'fach'));
        $this->assertSame([-1.0, 1.0, null], array_column($h, 'delta'));
        $this->assertSame([5.0, 4.0, null], array_column($h, 'vorher'));
        $this->assertSame([4.0, 5.0, 4.5], array_column($h, 'jetzt'));

        $this->assertSame([], (new Statistik)->hanteln($a, 2, 1, self::UEK));
        $ohneVor = (new Statistik)->hanteln($a, 2, null);
        $this->assertSame([null, null, null], array_column($ohneVor, 'delta'));
        $this->assertSame([4.0, 4.5, 5.0], array_column($ohneVor, 'jetzt'), 'ohne Delta nach Fachname');
    }

    #[Test]
    public function benoetigt_liefert_die_noetige_note_fuer_genuegend_aus_der_konfiguration(): void
    {
        $k = $this->testKonfiguration();
        $leistungen = [
            $this->note(self::MATHE, '2024-09-01', 3.0, id: 1),
            new Leistung(self::FACH, self::MATHE, null, null, '2024-12-01', null, 100.0, Leistung::GEPLANT, id: 7, titel: 'Prüfung 2'),
            Leistung::position(5, null),
        ];

        $r = (new Statistik)->benoetigt($leistungen, $k);

        $this->assertCount(2, $r);
        $this->assertSame(7, $r[0]['id'], 'datierte Prüfung vor der Position');
        $this->assertSame('fach:10@semester:1', $r[0]['ziel']);
        $this->assertSame(Zielrechner::BENOETIGT, $r[0]['status']);
        $this->assertSame(4.5, $r[0]['note']);
        $this->assertSame(4.0, $r[0]['grenze']);
        $this->assertSame(5, $r[1]['knotenId']);
        $this->assertSame('gesamt', $r[1]['ziel']);

        // Die Grenze kommt aus der Konfiguration: bei «genügend» = 4.5 braucht dieselbe Prüfung eine 5.5
        $streng = new Konfiguration($k->kategorien, $k->semester, $k->faecher, $k->module, genuegend: 4.5);
        $this->assertSame(5.5, (new Statistik)->benoetigt($leistungen, $streng)[0]['note']);
        $this->assertSame(4.5, (new Statistik)->benoetigt($leistungen, $streng)[0]['grenze']);
    }

    #[Test]
    public function benoetigt_ohne_offene_leistungen_ist_leer(): void
    {
        $this->assertSame([], (new Statistik)->benoetigt([$this->note(self::MATHE, '2024-09-01', 3.0)], $this->testKonfiguration()));
    }

    #[Test]
    public function geloeschte_noten_zaehlen_in_keiner_reihe(): void
    {
        $lernender = User::factory()->lernender()->create()->lernender;
        $semester = Semester::factory()->create(['start_datum' => '2024-08-01', 'end_datum' => '2025-01-31', 'sortierung' => 1]);
        $behalten = Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'semester_id' => $semester->semester_id, 'pruefungsdatum' => '2024-09-10', 'note_wert' => 5.0]);
        $geloescht = Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'semester_id' => $semester->semester_id, 'pruefungsdatum' => '2024-09-12', 'note_wert' => 2.0]);
        $geloescht->delete();
        Konfiguration::vergessen();

        $leistungen = app(NotenQuelle::class)->fuerLernenden((int) $lernender->lernender_id);
        $r = (new Statistik)->stichtagsreihe($leistungen, Konfiguration::ausDb(), $this->gesamt(), Carbon::parse('2024-09-01'), Carbon::parse('2024-09-30'));

        $this->assertSame([$behalten->note_id], array_column($r['punkte'], 'id'));
        $this->assertSame(5.0, $r['reihe'][0]['wert']);
    }
}
