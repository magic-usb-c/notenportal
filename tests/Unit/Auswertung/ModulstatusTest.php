<?php

declare(strict_types=1);

namespace Tests\Unit\Auswertung;

use App\Models\Pruefung;
use App\Services\Auswertung\Element;
use App\Services\Auswertung\Modulstatus;
use App\Services\Auswertung\Rechenkern;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * App\Services\Auswertung\Modulstatus::status() (Rückmeldung #14): reine Berechnung aus
 * bereits geladenem Element, Terminen, Beginn und «heute» – ohne DB, deterministisch.
 */
class ModulstatusTest extends TestCase
{
    use AuswertungTestHilfen;

    private Modulstatus $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new Modulstatus;
    }

    private function element(string $schluessel): Element
    {
        $k = $this->testKonfiguration();
        $a = (new Rechenkern)->auswerten([
            $this->modul(100, 5.0, 40, '2024-09-01'),
            $this->modul(100, null, 60, '2024-11-01'),
        ], $k);

        return $a->elemente[$schluessel];
    }

    #[Test]
    public function kein_termin_liefert_weder_naechsten_noch_letzten_und_keinen_fortschritt(): void
    {
        $heute = CarbonImmutable::parse('2024-10-01');
        $r = $this->service->status($this->element('m100'), [], '2024-09-01', $heute, $this->testKonfiguration());

        $this->assertNull($r['naechster_termin']);
        $this->assertNull($r['letzter_termin']);
        $this->assertNull($r['fortschritt_prozent']);
        // 2024-09-01 -> 2024-10-01 = 30 Tage: Wochen-Staffel (> 14 Tage), round(30/7) = 4.
        $this->assertSame('seit 4 Wochen', $r['dauer_seit_beginn']);
    }

    #[Test]
    public function nur_vergangene_termine_ergeben_keinen_naechsten_und_fortschritt_100(): void
    {
        $heute = CarbonImmutable::parse('2024-12-01');
        $termine = [
            ['datum' => '2024-09-15', 'titel' => 'Test 1', 'art' => Pruefung::ART_PRUEFUNG],
            ['datum' => '2024-10-15', 'titel' => 'Test 2', 'art' => Pruefung::ART_ABGABE],
        ];
        $r = $this->service->status($this->element('m100'), $termine, '2024-09-01', $heute, $this->testKonfiguration());

        $this->assertNull($r['naechster_termin']);
        $this->assertSame('Test 2', $r['letzter_termin']['titel']);
        $this->assertSame(100.0, $r['fortschritt_prozent']);
    }

    #[Test]
    public function termin_heute_zaehlt_als_naechster_termin(): void
    {
        $heute = CarbonImmutable::parse('2024-10-01');
        $termine = [['datum' => '2024-10-01', 'titel' => 'Abgabe heute', 'art' => Pruefung::ART_ABGABE]];
        $r = $this->service->status($this->element('m100'), $termine, '2024-09-01', $heute, $this->testKonfiguration());

        $this->assertNotNull($r['naechster_termin']);
        $this->assertSame('Abgabe heute', $r['naechster_termin']['titel']);
        $this->assertSame('endet heute', $r['naechster_termin']['restdauer']);
        $this->assertSame($r['naechster_termin']['titel'], $r['letzter_termin']['titel']);
    }

    #[Test]
    public function null_prozent_bewertet_ohne_benotete_leistung(): void
    {
        $k = $this->testKonfiguration();
        $a = (new Rechenkern)->auswerten([$this->modul(100, null, 60, '2024-11-01')], $k);
        $heute = CarbonImmutable::parse('2024-10-01');

        $r = $this->service->status($a->elemente['m100'], [], '2024-09-01', $heute, $k);

        $this->assertSame(0.0, $r['bewerteter_anteil_prozent']);
    }

    #[Test]
    public function hundert_prozent_bewertet_wenn_alles_benotet_ist(): void
    {
        $k = $this->testKonfiguration();
        // Fach ohne Zielgewicht: Gesamtgewicht fällt auf bewertet + offen zurück, hier nur bewertet.
        $a = (new Rechenkern)->auswerten([$this->fach(self::MATHE, 1, 5.0, 50)], $k);
        $heute = CarbonImmutable::parse('2024-10-01');

        $r = $this->service->status($a->elemente['f'.self::MATHE.'s1'], [], '2024-09-01', $heute, $k);

        $this->assertSame(100.0, $r['bewerteter_anteil_prozent']);
    }

    #[Test]
    public function fehlendes_beginn_datum_liefert_keinen_fortschritt_und_keine_dauer(): void
    {
        $heute = CarbonImmutable::parse('2024-12-01');
        $termine = [['datum' => '2024-11-01', 'titel' => 'Test', 'art' => Pruefung::ART_PRUEFUNG]];

        $r = $this->service->status($this->element('m100'), $termine, null, $heute, $this->testKonfiguration());

        $this->assertNull($r['beginn']);
        $this->assertNull($r['dauer_seit_beginn']);
        $this->assertNull($r['fortschritt_prozent']);
        $this->assertNotNull($r['letzter_termin']);
    }
}
