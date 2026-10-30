<?php

declare(strict_types=1);

namespace Tests\Feature\Auswertung;

use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Leistung;
use App\Services\Auswertung\LernstandRechner;
use App\Services\Auswertung\NotenQuelle;
use App\Services\Auswertung\Rechenkern;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Fächer, die in keinem Schnitt zählen (IDAF, Sport), melden im Lernstand keinen Einbruch (docs/notenlogik.md). */
class LernstandNichtZaehlendTest extends TestCase
{
    private function konfiguration(array $nichtZaehlend): Konfiguration
    {
        return new Konfiguration(
            kategorien: [1 => [
                'code' => 'FACH', 'name' => 'FACH', 'sortierung' => 10, 'rundung_element' => 0.5, 'rundung_schnitt' => 0.1,
                'gewicht_gesamt' => 1.0, 'promotion_min_schnitt' => null, 'promotion_max_ungenuegend' => null, 'promotion_max_minuspunkte' => null,
            ]],
            semester: [
                1 => ['bezeichnung' => 'S1', 'sortierung' => 1, 'start' => '2024-08-01', 'ende' => '2025-01-31'],
                2 => ['bezeichnung' => 'S2', 'sortierung' => 2, 'start' => '2025-02-01', 'ende' => '2025-07-31'],
            ],
            faecher: [10 => 'Mathe', 12 => 'IDAF'],
            nichtZaehlend: $nichtZaehlend,
        );
    }

    private function lernstand(Konfiguration $k): object
    {
        $leistungen = [
            new Leistung(1, 12, null, 1, '2024-10-01', 5.0),
            new Leistung(1, 12, null, 2, '2025-03-01', 3.5),
            new Leistung(1, 10, null, 1, '2024-10-01', 5.0),
            new Leistung(1, 10, null, 2, '2025-03-01', 5.0),
        ];
        $a = (new Rechenkern)->auswerten($leistungen, $k);
        $a->lernenderId = 1;

        return (new LernstandRechner(app(NotenQuelle::class)))->berechne(1, $a, null, 0, null);
    }

    #[Test]
    public function nicht_zaehlendes_fach_meldet_keinen_einbruch(): void
    {
        $stand = $this->lernstand($this->konfiguration([12]));

        $this->assertSame([], $stand->einbrueche);
        $this->assertSame([], $stand->gruende);
    }

    #[Test]
    public function zaehlendes_fach_meldet_den_einbruch_weiterhin(): void
    {
        $stand = $this->lernstand($this->konfiguration([]));

        $this->assertCount(1, $stand->einbrueche);
        $this->assertStringContainsString('IDAF', $stand->einbrueche[0]['label']);
    }
}
