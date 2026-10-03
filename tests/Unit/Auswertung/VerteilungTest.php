<?php

declare(strict_types=1);

namespace Tests\Unit\Auswertung;

use App\Services\Auswertung\Verteilung;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class VerteilungTest extends TestCase
{
    /** @return array<string, array{list<float>}> */
    public static function zuWenige(): array
    {
        return ['keine Werte' => [[]], 'ein Wert' => [[4.0]], 'zwei Werte' => [[4.0, 5.0]]];
    }

    /** @param  list<float>  $werte */
    #[Test]
    #[DataProvider('zuWenige')]
    public function unter_drei_werten_gibt_es_weder_median_noch_quartile(array $werte): void
    {
        $this->assertNull(Verteilung::median($werte));
        $this->assertNull(Verteilung::quartile($werte));
        $this->assertNull(Verteilung::histogramm($werte)['median']);
    }

    #[Test]
    public function quartile_folgen_dem_quantil_typ_7(): void
    {
        $this->assertSame(['q1' => 1.75, 'q2' => 2.5, 'q3' => 3.25], Verteilung::quartile([1, 2, 3, 4]));
        $this->assertSame(2.5, Verteilung::median([4, 1, 3, 2]), 'Reihenfolge der Eingabe spielt keine Rolle');
        $this->assertSame(4.5, Verteilung::median([3.5, 4.5, 5.5]));
        $this->assertSame(['q1' => 4.0, 'q2' => 4.5, 'q3' => 5.0], Verteilung::quartile([3.5, 4.0, 4.5, 5.0, 5.5]));
    }

    #[Test]
    public function median_ist_auf_hundertstel_gerundet(): void
    {
        // (4.0 + 4.3333) / 2 = 4.16665
        $this->assertSame(4.17, Verteilung::median([4.0, 4.0, 4.3333, 4.3333]));
    }

    #[Test]
    public function histogramm_zaehlt_in_viertelnoten_und_schliesst_sechs_in_der_letzten_klasse_ein(): void
    {
        $h = Verteilung::histogramm([1.0, 3.8, 4.0, 4.0, 5.75, 6.0]);

        $this->assertCount(20, $h['labels']);
        $this->assertSame('1.00', $h['labels'][0]);
        $this->assertSame('5.75', $h['labels'][19]);
        $klassen = array_combine($h['labels'], $h['werte']);
        $this->assertSame(1, $klassen['1.00']);
        $this->assertSame(1, $klassen['3.75'], '3.8 liegt in [3.75, 4.00)');
        $this->assertSame(2, $klassen['4.00'], 'ein Vierer fällt nicht in die Klasse darunter');
        $this->assertSame(2, $klassen['5.75'], '5.75 und 6.0 stehen in der letzten Klasse');
        $this->assertSame(6, $h['n']);
        $this->assertSame(6, array_sum($h['werte']));
        $this->assertSame(0.25, $h['breite']);
    }

    #[Test]
    public function histogramm_vertraegt_float_rauschen_an_der_klassengrenze(): void
    {
        // 40 × 0.1 und 3.9999999993 gelten wie in Bericht::klasse und NotenSkala::stufe als 4.0
        $h = Verteilung::histogramm([40 * 0.1, 3.9999999993, 38 * 0.1]);
        $klassen = array_combine($h['labels'], $h['werte']);

        $this->assertSame(2, $klassen['4.00']);
        $this->assertSame(1, $klassen['3.75']);
    }

    #[Test]
    public function histogramm_ignoriert_werte_ausserhalb_und_liefert_median_ab_drei(): void
    {
        $h = Verteilung::histogramm([0.5, 4.0, 5.0, 6.5, 6.0]);

        $this->assertSame(3, $h['n']);
        $this->assertSame(5.0, $h['median']);
        $this->assertSame(['q1' => 4.5, 'q2' => 5.0, 'q3' => 5.5], $h['quartile']);

        $leer = Verteilung::histogramm([]);
        $this->assertSame(0, $leer['n']);
        $this->assertSame(0, array_sum($leer['werte']));
        $this->assertNull($leer['median']);
    }

    #[Test]
    public function histogramm_mit_anderer_breite_und_bereich(): void
    {
        $h = Verteilung::histogramm([1, 2, 2.5, 3], 0.5, 1.0, 3.0);

        $this->assertSame(['1.00', '1.50', '2.00', '2.50'], $h['labels']);
        $this->assertSame([1, 0, 1, 2], $h['werte']);
    }
}
