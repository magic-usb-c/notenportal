<?php

declare(strict_types=1);

namespace Tests\Unit\Auswertung;

use App\Services\Auswertung\Element;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Eine Schwelle für «offen»: Reste unter 0.01 % gelten als Rundung, nie «0 % offen» neben «nicht abgeschlossen». */
class OffenGewichtTest extends TestCase
{
    private function modul(float $summe, ?float $ziel = 100.0): Element
    {
        $e = new Element('modul:1', Element::MODUL, 1, null, 1, 1, 'Modul 1', $ziel);
        $e->gewichtSumme = $summe;

        return $e;
    }

    #[Test]
    public function rundungsrest_gilt_als_abgeschlossen(): void
    {
        $e = $this->modul(99.996);

        $this->assertSame(0.0, $e->offenGewicht());
        $this->assertTrue($e->abgeschlossen());
    }

    #[Test]
    public function kleiner_echter_rest_bleibt_offen_und_gerundet(): void
    {
        $e = $this->modul(99.98);

        $this->assertSame(0.02, $e->offenGewicht());
        $this->assertFalse($e->abgeschlossen());
    }

    #[Test]
    public function ueberschrittenes_ziel_ist_abgeschlossen(): void
    {
        $this->assertSame(0.0, $this->modul(120.0)->offenGewicht());
        $this->assertTrue($this->modul(120.0)->abgeschlossen());
    }

    #[Test]
    public function ohne_ziel_gibt_es_kein_offen(): void
    {
        $e = $this->modul(50.0, null);

        $this->assertNull($e->offenGewicht());
        $this->assertFalse($e->abgeschlossen());
    }
}
