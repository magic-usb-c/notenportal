<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Modulkatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * app/Support/Modulkatalog.php: prüft und normalisiert die Ernte, bevor sie in die Datenbank geht.
 * Alle Daten hier sind erfunden (docs/modulkatalog.md, Abschnitt Rechtslage).
 */
class ModulkatalogTest extends TestCase
{
    private function ernte(array $daten): string
    {
        return (string) json_encode($daten);
    }

    #[Test]
    public function kaputtes_json_ergibt_einen_fehler_und_keine_module(): void
    {
        $raus = Modulkatalog::ausJson('{nicht wirklich json');

        $this->assertSame([], $raus['module']);
        $this->assertNotEmpty($raus['fehler']);
    }

    #[Test]
    public function ein_unbekanntes_format_wird_abgelehnt(): void
    {
        $raus = Modulkatalog::ausJson($this->ernte(['format' => 99, 'module' => [['nummer' => '987', 'titel' => 'X']]]));

        $this->assertSame([], $raus['module']);
        $this->assertNotEmpty($raus['fehler']);
    }

    #[Test]
    public function ein_modul_wird_mit_zielen_und_lbv_uebernommen(): void
    {
        $raus = Modulkatalog::ausJson($this->ernte([
            'format' => Modulkatalog::FORMAT,
            'geerntet_am' => '2026-09-01T08:30:00Z',
            'module' => [[
                'nummer' => 'M987',
                'version' => '2',
                'titel' => "Musterobjekte\n  prüfen",
                'kompetenzfeld' => 'Musterfeld',
                'publiziert_am' => '2026-01-15',
                'handlungsziele' => [
                    ['nummer' => '1', 'text' => 'Erstes Ziel.'],
                    ['nummer' => '2', 'text' => ''],
                    ['nummer' => 'x', 'text' => 'Ohne gültige Nummer.'],
                ],
                'lbv' => ['elemente' => [
                    ['bezeichnung' => 'LBV 987-2 - Element 1', 'gewichtung_prozent' => 60, 'richtzeit' => 1.5,
                        'pruefungsform' => 'Praktisch am Objekt', 'sozialform' => 'Einzelarbeit'],
                    ['bezeichnung' => '', 'gewichtung_prozent' => 40],
                ]],
            ]],
        ]));

        $this->assertCount(1, $raus['module']);
        $modul = $raus['module'][0];
        $this->assertSame('987', $modul['nummer']);
        $this->assertSame('2', $modul['version']);
        $this->assertSame('Musterobjekte prüfen', $modul['titel']);
        $this->assertSame('2026-01-15', $modul['publiziert_am']);
        $this->assertSame('2026-09-01 08:30:00', $raus['stand']);

        // Ziele ohne Text und mit unbrauchbarer Nummer fallen weg, ebenso LBV ohne Bezeichnung.
        $this->assertCount(1, $modul['handlungsziele']);
        $this->assertSame('Erstes Ziel.', $modul['handlungsziele'][0]['text']);
        $this->assertCount(1, $modul['lbv_elemente']);
        $this->assertSame(60.0, $modul['lbv_elemente'][0]['gewichtung_prozent']);
        $this->assertSame('Einzelarbeit', $modul['lbv_elemente'][0]['sozialform']);
    }

    #[Test]
    public function unbrauchbare_werte_werden_verworfen_statt_uebernommen(): void
    {
        $raus = Modulkatalog::ausJson($this->ernte([
            'format' => Modulkatalog::FORMAT,
            'module' => [
                ['nummer' => 'Projektarbeit', 'titel' => 'Ohne Katalognummer'],
                ['nummer' => '987', 'titel' => ''],
                ['nummer' => '654', 'titel' => 'Mit krummen Werten', 'version' => 'V1', 'publiziert_am' => '15.01.2026',
                    'lbv' => ['elemente' => [['bezeichnung' => 'E1', 'gewichtung_prozent' => 250, 'richtzeit' => 'viel']]]],
            ],
        ]));

        $this->assertCount(1, $raus['module']);
        $modul = $raus['module'][0];
        $this->assertSame('654', $modul['nummer']);
        $this->assertNull($modul['version']);
        $this->assertNull($modul['publiziert_am']);
        $this->assertNull($modul['lbv_elemente'][0]['gewichtung_prozent']);
        $this->assertNull($modul['lbv_elemente'][0]['richtzeit']);
        $this->assertCount(2, $raus['fehler']);
    }

    #[Test]
    public function pflichtgrad_und_lehrjahr_eines_abschlusses_werden_ausgewertet(): void
    {
        $raus = Modulkatalog::ausJson($this->ernte([
            'format' => Modulkatalog::FORMAT,
            'abschluesse' => [[
                'name' => 'Prüfberuf/in EFZ Musterrichtung (2026)',
                'kennung' => 'abschluss-kennung',
                'module' => [
                    ['nummer' => '987', 'version' => '2', 'titel' => 'Pflichtmodul', 'pflichtgrad' => 'pfl', 'lehrjahr' => 2],
                    ['nummer' => '654', 'version' => '1', 'titel' => 'Wahlmodul', 'pflichtgrad' => 'wm', 'lehrjahr' => 9],
                    ['nummer' => 'kein Modul', 'titel' => 'wird übersprungen'],
                ],
            ]],
        ]));

        $this->assertCount(1, $raus['abschluesse']);
        $a = $raus['abschluesse'][0];
        $this->assertSame(2026, $a['bivo_jahr']);
        $this->assertSame('abschluss-kennung', $a['kennung']);
        $this->assertCount(2, $a['module']);
        $this->assertSame(1, $a['module'][0]['pflicht']);
        $this->assertSame(2, $a['module'][0]['lehrjahr']);
        $this->assertSame(0, $a['module'][1]['pflicht']);
        $this->assertNull($a['module'][1]['lehrjahr'], 'Lehrjahr 9 gibt es nicht');

        // Eine Ernte ohne --details kennt die Module nur aus den Abschlusslisten: sie zählen trotzdem.
        $this->assertCount(2, $raus['module']);
        $this->assertSame(['654', '987'], collect($raus['module'])->pluck('nummer')->sort()->values()->all());
    }
}
