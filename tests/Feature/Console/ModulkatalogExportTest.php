<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * app/Console/Commands/ModulkatalogExport.php: schreibt den Katalog dieser Instanz in das Format,
 * das der Import liest – damit eine zweite Instanz ihn übernehmen kann, ohne neu zu ernten.
 *
 * Geprüft wird die Rundreise: einlesen, ausgeben, wieder einlesen. Bleibt dabei nichts liegen,
 * ist die Datei vollständig. Alle Nummern, Titel und Berufe sind erfunden (docs/modulkatalog.md).
 */
class ModulkatalogExportTest extends TestCase
{
    /** @var list<string> */
    private array $dateien = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->dateien as $pfad) {
            @unlink($pfad);
        }
        parent::tearDown();
    }

    private function ziel(): string
    {
        $pfad = tempnam(sys_get_temp_dir(), 'np-export').'.json';
        $this->dateien[] = $pfad;

        return $pfad;
    }

    /** Legt denselben Katalog an, den der Import-Test einliest. */
    private function katalogEinlesen(): void
    {
        $quelle = tempnam(sys_get_temp_dir(), 'np-ernte').'.json';
        $this->dateien[] = $quelle;
        file_put_contents($quelle, (string) json_encode([
            'format' => 1,
            'geerntet_am' => '2026-09-01T08:30:00Z',
            'abschluesse' => [[
                'name' => 'Prüfberuf/in EFZ Musterrichtung (2026)',
                'kennung' => 'abschluss-kennung',
                'module' => [
                    ['nummer' => '987', 'version' => '2', 'titel' => 'Musterobjekte prüfen', 'pflichtgrad' => 'pfl', 'lehrjahr' => 2],
                    ['nummer' => '654', 'version' => '1', 'titel' => 'Wahlweise vertiefen', 'pflichtgrad' => 'wm', 'lehrjahr' => 3],
                ],
            ]],
            'module' => [[
                'nummer' => '987',
                'version' => '2',
                'titel' => 'Musterobjekte prüfen',
                'kompetenzfeld' => 'Musterfeld',
                'kompetenz' => 'Prüft Musterobjekte selbstständig.',
                'objekt' => 'Musterobjekt',
                'publiziert_am' => '2026-01-15',
                'handlungsziele' => [
                    ['nummer' => '1', 'text' => 'Erstes Ziel.'],
                    ['nummer' => '2', 'text' => 'Zweites Ziel.'],
                ],
                'lbv' => ['elemente' => [
                    ['bezeichnung' => 'LBV 987-2 - Element 1', 'gewichtung_prozent' => 60, 'richtzeit' => 1.5,
                        'pruefungsform' => 'Praktisch am Objekt', 'sozialform' => 'Einzelarbeit'],
                ]],
            ]],
        ]));

        $this->artisan('notenportal:modulkatalog', ['datei' => $quelle, '--anwenden' => true])->assertSuccessful();
    }

    #[Test]
    public function ohne_module_meldet_der_export_und_scheitert(): void
    {
        $this->artisan('notenportal:modulkatalog-export', ['datei' => $this->ziel()])
            ->expectsOutputToContain('keine Module')
            ->assertFailed();
    }

    #[Test]
    public function der_export_gibt_module_ziele_lbv_und_zuordnungen_aus(): void
    {
        $this->katalogEinlesen();
        $ziel = $this->ziel();

        $this->artisan('notenportal:modulkatalog-export', ['datei' => $ziel])->assertSuccessful();

        $daten = json_decode((string) file_get_contents($ziel), true);
        $this->assertSame(1, $daten['format']);
        $this->assertCount(2, $daten['module']);

        $modul = collect($daten['module'])->firstWhere('nummer', 'M987');
        $this->assertSame('Musterobjekte prüfen', $modul['titel']);
        $this->assertSame('2', $modul['version']);
        $this->assertSame('Musterfeld', $modul['kompetenzfeld']);
        $this->assertCount(2, $modul['handlungsziele']);
        $this->assertSame('Einzelarbeit', $modul['lbv']['elemente'][0]['sozialform']);
        // 60.00 aus der Spalte wird als Zahl ausgegeben, JSON schreibt sie ohne Nachkommastelle.
        $this->assertEquals(60, $modul['lbv']['elemente'][0]['gewichtung_prozent']);
        $this->assertEquals(1.5, $modul['lbv']['elemente'][0]['richtzeit']);

        $abschluss = $daten['abschluesse'][0];
        $this->assertSame('Prüfberuf/in EFZ Musterrichtung (2026)', $abschluss['name']);
        $this->assertSame('abschluss-kennung', $abschluss['kennung']);

        $pflicht = collect($abschluss['module'])->firstWhere('nummer', 'M987');
        $this->assertSame('pfl', $pflicht['pflichtgrad']);
        // Der Import rechnet Lehrjahr 2 in Semester 3 um, der Export muss zurückrechnen.
        $this->assertSame(2, $pflicht['lehrjahr']);
        $this->assertSame('wm', collect($abschluss['module'])->firstWhere('nummer', 'M654')['pflichtgrad']);
    }

    #[Test]
    public function die_ausgegebene_datei_laesst_sich_ohne_verlust_wieder_einlesen(): void
    {
        $this->katalogEinlesen();
        $ziel = $this->ziel();
        $this->artisan('notenportal:modulkatalog-export', ['datei' => $ziel])->assertSuccessful();

        $vorher = [
            'module' => DB::table('module')->count(),
            'ziele' => DB::table('modul_handlungsziele')->count(),
            'lbv' => DB::table('modul_lbv_elemente')->count(),
            'berufe' => DB::table('lehrberufe')->count(),
            'zuordnungen' => DB::table('lehrberuf_module')->count(),
        ];

        // Dieselbe Instanz noch einmal mit der eigenen Ausgabe füttern: nichts darf doppelt
        // entstehen und nichts verschwinden.
        $this->artisan('notenportal:modulkatalog', ['datei' => $ziel, '--anwenden' => true])->assertSuccessful();

        $this->assertSame($vorher['module'], DB::table('module')->count());
        $this->assertSame($vorher['ziele'], DB::table('modul_handlungsziele')->count());
        $this->assertSame($vorher['lbv'], DB::table('modul_lbv_elemente')->count());
        $this->assertSame($vorher['berufe'], DB::table('lehrberufe')->count());
        $this->assertSame($vorher['zuordnungen'], DB::table('lehrberuf_module')->count());

        $lbv = DB::table('modul_lbv_elemente')->first();
        $this->assertSame('Praktisch am Objekt', $lbv->pruefungsform);
        $this->assertSame('1.50', (string) $lbv->richtzeit);
    }

    #[Test]
    public function ein_von_hand_gesetztes_semester_ueberlebt_die_rundreise(): void
    {
        $this->katalogEinlesen();
        // Das Lehrsemester ist in den Stammdaten frei wählbar (1–12) und muss nicht zum Lehrjahr
        // passen. Aus dem Lehrjahr allein liesse sich die 4 nicht zurückholen.
        DB::table('lehrberuf_module')->update(['empfohlenes_lehrsemester_nr' => 4]);

        $ziel = $this->ziel();
        $this->artisan('notenportal:modulkatalog-export', ['datei' => $ziel])->assertSuccessful();

        $daten = json_decode((string) file_get_contents($ziel), true);
        $zuordnung = collect($daten['abschluesse'][0]['module'])->firstWhere('nummer', 'M987');
        $this->assertSame(4, $zuordnung['semester']);
        $this->assertSame(2, $zuordnung['lehrjahr']);

        // Auf einer frischen Instanz gäbe es die Zuordnung noch nicht – hier nachgestellt.
        DB::table('lehrberuf_module')->delete();
        $this->artisan('notenportal:modulkatalog', ['datei' => $ziel, '--anwenden' => true])->assertSuccessful();

        $modulId = DB::table('module')->where('modul_nummer', 'M987')->value('modul_id');
        $this->assertSame(4, (int) DB::table('lehrberuf_module')->where('modul_id', $modulId)
            ->value('empfohlenes_lehrsemester_nr'));
    }

    #[Test]
    public function eigene_module_bleiben_ohne_schalter_draussen(): void
    {
        $this->katalogEinlesen();
        DB::table('module')->insert(['modul_nummer' => 'UEK01', 'titel' => 'Eigener ÜK', 'aktiv' => 1]);

        $ohne = $this->ziel();
        $this->artisan('notenportal:modulkatalog-export', ['datei' => $ohne])->assertSuccessful();
        $nummern = array_column(json_decode((string) file_get_contents($ohne), true)['module'], 'nummer');
        $this->assertNotContains('UEK01', $nummern);

        $mit = $this->ziel();
        $this->artisan('notenportal:modulkatalog-export', ['datei' => $mit, '--mit-eigenen' => true])->assertSuccessful();
        $nummern = array_column(json_decode((string) file_get_contents($mit), true)['module'], 'nummer');
        $this->assertContains('UEK01', $nummern);
    }

    #[Test]
    public function nur_grenzt_auf_einen_lehrberuf_ein(): void
    {
        $this->katalogEinlesen();
        $ziel = $this->ziel();

        $this->artisan('notenportal:modulkatalog-export', ['datei' => $ziel, '--nur' => ['Gibt es nicht EFZ']])
            ->assertSuccessful();

        $daten = json_decode((string) file_get_contents($ziel), true);
        $this->assertSame([], $daten['abschluesse']);
        // Die Module bleiben trotzdem in der Datei: sie sind der Katalog, nicht die Zuordnung.
        $this->assertCount(2, $daten['module']);
    }
}
