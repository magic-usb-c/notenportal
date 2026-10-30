<?php

declare(strict_types=1);

namespace Tests\Feature\Statistik;

use App\Services\Auswertung\Auswertung;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Leistung;
use App\Services\Auswertung\NotenQuelle;
use App\Services\Auswertung\Rechenkern;
use App\Services\Uebersicht;
use App\Support\StatistikDaten;
use App\Support\StatistikFilter;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

class NichtZaehlendeFaecherAnzeigeTest extends TestCase
{
    private function auswertung(): Auswertung
    {
        $regel = ['code' => 'A', 'name' => 'A', 'sortierung' => 1, 'rundung_element' => 0.5, 'rundung_schnitt' => 0.1, 'gewicht_gesamt' => 1.0,
            'promotion_min_schnitt' => null, 'promotion_max_ungenuegend' => null, 'promotion_max_minuspunkte' => null];
        $semester = [1 => ['bezeichnung' => 'S', 'sortierung' => 1, 'start' => now()->subMonths(2)->toDateString(), 'ende' => now()->addMonths(2)->toDateString()]];
        $k = new Konfiguration([1 => $regel], $semester, [10 => 'Fach A', 11 => 'Fach B'], [], 4.0, 0.1, [11]);
        $leistungen = [
            new Leistung(1, 10, null, 1, now()->subDays(5)->toDateString(), 5.0),
            new Leistung(1, 11, null, 1, now()->subDays(4)->toDateString(), 2.5),
        ];

        return (new Rechenkern)->auswerten($leistungen, $k);
    }

    public function test_wo_stehe_ich_fuehrt_nicht_zaehlende_faecher_auch_ueber_die_lehrzeit_nicht(): void
    {
        $a = $this->auswertung();
        $daten = new StatistikDaten(new NotenQuelle);
        $filter = StatistikFilter::aus(Request::create('/x', 'GET', ['modus' => 'lehrzeit']), $daten->regelnLernender($a), StatistikDaten::STANDARD_LERNENDER);

        $r = $daten->wostehe($filter, $a);

        $this->assertSame(['Fach A'], $r['diagramm']['lehrzeit']['labels']);
        $this->assertStringNotContainsString('Fach B', $r['zusammenfassung']);
        $this->assertSame(['Fach A'], array_column($r['tabelle']['zeilen'], 0));
    }

    public function test_balkendiagramm_der_uebersicht_fuehrt_nicht_zaehlende_faecher_nicht_in_der_lehrzeit(): void
    {
        $a = $this->auswertung();
        $m = new ReflectionMethod(Uebersicht::class, 'balkenDiagramm');

        $r = $m->invoke(app(Uebersicht::class), $a, 1);

        $this->assertSame(['Fach A'], $r['lehrzeit']['labels']);
        $this->assertSame(['Fach A'], $r['semester']['labels']);
    }
}
