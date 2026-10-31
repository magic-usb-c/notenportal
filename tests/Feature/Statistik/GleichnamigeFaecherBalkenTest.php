<?php

declare(strict_types=1);

namespace Tests\Feature\Statistik;

use App\Services\Auswertung\Auswertung;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Leistung;
use App\Services\Auswertung\NotenQuelle;
use App\Services\Auswertung\Rechenkern;
use App\Services\Statistik\Zeugnisbalken;
use App\Services\Uebersicht;
use App\Support\StatistikDaten;
use App\Support\StatistikFilter;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

/** Gleichnamige Fächer in verschiedenen Kategorien (Englisch im Fachunterricht und in der BMS) bleiben zwei Balken. */
class GleichnamigeFaecherBalkenTest extends TestCase
{
    private function regel(string $name, int $sortierung): array
    {
        return ['code' => $name, 'name' => $name, 'sortierung' => $sortierung, 'rundung_element' => 0.5, 'rundung_schnitt' => 0.1, 'gewicht_gesamt' => 0.5,
            'promotion_min_schnitt' => null, 'promotion_max_ungenuegend' => null, 'promotion_max_minuspunkte' => null];
    }

    private function auswertung(): Auswertung
    {
        $semester = [1 => ['bezeichnung' => 'S', 'sortierung' => 1, 'start' => now()->subMonths(2)->toDateString(), 'ende' => now()->addMonths(2)->toDateString()]];
        $k = new Konfiguration([1 => $this->regel('Fachunterricht', 1), 2 => $this->regel('BMS', 2)], $semester, [10 => 'Englisch', 11 => 'Englisch', 12 => 'Mathematik'], [], 4.0, 0.1, []);
        $leistungen = [
            new Leistung(1, 10, null, 1, now()->subDays(5)->toDateString(), 3.0),
            new Leistung(2, 11, null, 1, now()->subDays(4)->toDateString(), 5.5),
            new Leistung(1, 12, null, 1, now()->subDays(3)->toDateString(), 4.5),
        ];

        return (new Rechenkern)->auswerten($leistungen, $k);
    }

    public function test_lehrzeit_balken_der_uebersicht_fuehrt_gleichnamige_faecher_getrennt_mit_kategorie(): void
    {
        $a = $this->auswertung();

        $r = (new ReflectionMethod(Uebersicht::class, 'balkenDiagramm'))->invoke(app(Uebersicht::class), $a, 1);

        $this->assertSame(['Englisch · Fachunterricht', 'Mathematik', 'Englisch · BMS'], $r['lehrzeit']['labels']);
        $this->assertSame([3.0, 4.5, 5.5], $r['lehrzeit']['werte']);
    }

    public function test_semester_balken_der_uebersicht_unterscheidet_gleichnamige_faecher(): void
    {
        $a = $this->auswertung();

        $r = (new ReflectionMethod(Uebersicht::class, 'balkenDiagramm'))->invoke(app(Uebersicht::class), $a, 1);

        $this->assertSame(['Englisch · Fachunterricht', 'Mathematik', 'Englisch · BMS'], $r['semester']['labels']);
    }

    public function test_eindeutige_namen_bleiben_ohne_kategorieanhang(): void
    {
        $a = $this->auswertung();

        $zeilen = Zeugnisbalken::lehrzeit($a, 1);

        $this->assertSame(['Englisch', 'Mathematik'], array_column($zeilen, 'label'));
    }

    public function test_wo_stehe_ich_fuehrt_beide_englisch_noten(): void
    {
        $a = $this->auswertung();
        $daten = new StatistikDaten(new NotenQuelle);
        $filter = StatistikFilter::aus(Request::create('/x', 'GET', ['modus' => 'lehrzeit']), $daten->regelnLernender($a), StatistikDaten::STANDARD_LERNENDER);

        $r = $daten->wostehe($filter, $a);

        $this->assertSame(['Englisch · Fachunterricht', 'Mathematik', 'Englisch · BMS'], $r['diagramm']['lehrzeit']['labels']);
        $this->assertSame([3.0, 4.5, 5.5], $r['diagramm']['lehrzeit']['werte']);
        $this->assertStringContainsString('Englisch · Fachunterricht', $r['zusammenfassung']);
    }

    public function test_wo_stehe_ich_mit_kategoriefilter_zeigt_nur_diese_kategorie_ohne_anhang(): void
    {
        $a = $this->auswertung();
        $daten = new StatistikDaten(new NotenQuelle);
        $filter = StatistikFilter::aus(Request::create('/x', 'GET', ['modus' => 'lehrzeit', 'kategorie' => '2']), $daten->regelnLernender($a), StatistikDaten::STANDARD_LERNENDER);

        $r = $daten->wostehe($filter, $a);

        $this->assertSame(['Englisch'], $r['diagramm']['lehrzeit']['labels']);
        $this->assertSame([5.5], $r['diagramm']['lehrzeit']['werte']);
    }
}
