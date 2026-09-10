<?php

declare(strict_types=1);

namespace Tests\Unit\Auswertung;

use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Leistung;

trait AuswertungTestHilfen
{
    protected const int FACH = 1;

    protected const int UEK = 2;

    protected const int BMS = 3;

    protected const int MATHE = 10;

    protected const int DEUTSCH = 11;

    protected const int ENGLISCH = 12;

    protected function testKonfiguration(float $gewichtBms = 1.0): Konfiguration
    {
        $regel = fn (string $code, int $sort, float $gewicht = 1.0, ?float $min = null, ?int $maxUng = null, ?float $maxMinus = null) => [
            'code' => $code, 'name' => $code, 'sortierung' => $sort, 'rundung_element' => 0.5, 'rundung_schnitt' => 0.1,
            'gewicht_gesamt' => $gewicht, 'promotion_min_schnitt' => $min, 'promotion_max_ungenuegend' => $maxUng,
            'promotion_max_minuspunkte' => $maxMinus,
        ];

        return new Konfiguration(
            kategorien: [
                self::FACH => $regel('FACH', 10),
                self::UEK => $regel('UEK', 20),
                self::BMS => $regel('BMS', 30, $gewichtBms, 4.0, 2, 2.0),
            ],
            semester: [
                1 => ['bezeichnung' => '24/25-1', 'sortierung' => 1, 'start' => '2024-08-01', 'ende' => '2025-01-31'],
                2 => ['bezeichnung' => '24/25-2', 'sortierung' => 2, 'start' => '2025-02-01', 'ende' => '2025-07-31'],
            ],
            faecher: [self::MATHE => 'Mathematik', self::DEUTSCH => 'Deutsch', self::ENGLISCH => 'Englisch'],
            module: [100 => ['nummer' => '100', 'titel' => 'Daten', 'ziel' => 100.0], 101 => ['nummer' => '101', 'titel' => 'Web', 'ziel' => 100.0]],
        );
    }

    protected function fach(int $fachId, int $semesterId, ?float $wert, float $gewicht = 100.0): Leistung
    {
        return new Leistung(self::BMS, $fachId, null, $semesterId, null, $wert, $gewicht);
    }

    protected function modul(int $modulId, ?float $wert, float $gewicht = 100.0, string $datum = '2024-10-01', int $kategorie = self::FACH): Leistung
    {
        return new Leistung($kategorie, null, $modulId, null, $datum, $wert, $gewicht);
    }
}
