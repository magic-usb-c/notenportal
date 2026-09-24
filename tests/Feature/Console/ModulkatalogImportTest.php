<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * app/Console/Commands/ModulkatalogImport.php: liest eine Ernte des Modulbaukastens ein.
 * Alle Nummern, Titel und Berufe hier sind erfunden – der echte Katalog gehört ICT-Berufsbildung
 * Schweiz und liegt deshalb nie im Repo (docs/modulkatalog.md).
 */
class ModulkatalogImportTest extends TestCase
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

    private function ernte(array $ersetzen = []): string
    {
        $daten = $ersetzen + [
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
                    ['bezeichnung' => 'LBV 987-2 - Element 2', 'gewichtung_prozent' => 40],
                ]],
            ]],
        ];

        $pfad = tempnam(sys_get_temp_dir(), 'np-katalog').'.json';
        file_put_contents($pfad, (string) json_encode($daten));
        $this->dateien[] = $pfad;

        return $pfad;
    }

    #[Test]
    public function die_vorschau_schreibt_nichts(): void
    {
        $this->artisan('notenportal:modulkatalog', ['datei' => $this->ernte()])
            ->expectsOutputToContain('Nur Vorschau')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('module')->where('modul_nummer', 'M987')->count());
        $this->assertSame(0, DB::table('lehrberufe')->count());
    }

    #[Test]
    public function eine_fehlende_datei_meldet_und_scheitert(): void
    {
        $this->artisan('notenportal:modulkatalog', ['datei' => '/tmp/gibt-es-nicht-987.json'])
            ->expectsOutputToContain('Datei nicht lesbar')
            ->assertFailed();
    }

    #[Test]
    public function anwenden_schreibt_module_ziele_lbv_beruf_und_zuordnung(): void
    {
        $this->artisan('notenportal:modulkatalog', ['datei' => $this->ernte(), '--anwenden' => true])
            ->assertSuccessful();

        $modul = DB::table('module')->where('modul_nummer', 'M987')->first();
        $this->assertNotNull($modul);
        $this->assertSame('Musterobjekte prüfen', $modul->titel);
        $this->assertSame('2', $modul->version);
        $this->assertSame('Musterfeld', $modul->kompetenzfeld);
        $this->assertSame('modulbaukasten', $modul->quelle);
        $this->assertSame('2026-09-01 08:30:00', $modul->quelle_stand);

        $this->assertSame(2, DB::table('modul_handlungsziele')->where('modul_id', $modul->modul_id)->count());
        $lbv = DB::table('modul_lbv_elemente')->where('modul_id', $modul->modul_id)->orderBy('sortierung')->get();
        $this->assertCount(2, $lbv);
        $this->assertSame('60.00', (string) $lbv[0]->gewichtung_prozent);
        $this->assertSame('Einzelarbeit', $lbv[0]->sozialform);

        $beruf = DB::table('lehrberufe')->first();
        $this->assertSame('Prüfberuf/in EFZ Musterrichtung (2026)', $beruf->name);
        $this->assertSame(2026, (int) $beruf->bivo_jahr);
        $this->assertSame('abschluss-kennung', $beruf->quelle_kennung);

        $pflicht = DB::table('lehrberuf_module')
            ->where('lehrberuf_id', $beruf->lehrberuf_id)->where('modul_id', $modul->modul_id)->first();
        $this->assertSame(1, (int) $pflicht->pflicht);
        $this->assertSame('pfl', $pflicht->pflichtgrad);
        $this->assertSame(3, (int) $pflicht->empfohlenes_lehrsemester_nr, 'Lehrjahr 2 ist das 3. Semester');
        $this->assertSame(
            (int) DB::table('kategorien')->where('code', 'FACH')->value('kategorie_id'),
            (int) $pflicht->kategorie_id
        );

        $wahl = DB::table('lehrberuf_module as lbm')->join('module as m', 'm.modul_id', '=', 'lbm.modul_id')
            ->where('m.modul_nummer', 'M654')->first();
        $this->assertSame(0, (int) $wahl->pflicht);
        $this->assertSame('wm', $wahl->pflichtgrad);
    }

    #[Test]
    public function die_zuordnung_traegt_die_version_ihres_abschlusses(): void
    {
        // Dasselbe Modul ist in zwei Jahrgängen gleichzeitig gültig. Die Modulzeile kann nur EINE
        // Version halten (die höhere, weil `modul_nummer` eindeutig ist) – die Zuordnung hält
        // deshalb die Version ihres Abschlusses. Sonst verwiese der ältere Jahrgang auf die
        // neuere Modulseite im Modulbaukasten.
        $datei = $this->ernte([
            'abschluesse' => [
                ['name' => 'Altberuf EFZ Muster (2021)', 'kennung' => 'alt', 'module' => [
                    ['nummer' => '987', 'version' => '1', 'titel' => 'Musterobjekte prüfen', 'pflichtgrad' => 'pfl', 'lehrjahr' => 1],
                ]],
                ['name' => 'Neuberuf EFZ Muster (2026)', 'kennung' => 'neu', 'module' => [
                    ['nummer' => '987', 'version' => '2', 'titel' => 'Musterobjekte prüfen', 'pflichtgrad' => 'pfl', 'lehrjahr' => 1],
                ]],
            ],
            'module' => [
                ['nummer' => '987', 'version' => '1', 'titel' => 'Musterobjekte prüfen'],
                ['nummer' => '987', 'version' => '2', 'titel' => 'Musterobjekte prüfen'],
            ],
        ]);

        $this->artisan('notenportal:modulkatalog', ['datei' => $datei, '--anwenden' => true])->assertSuccessful();

        $modul = DB::table('module')->where('modul_nummer', 'M987')->first();
        $this->assertSame('2', $modul->version, 'Die Modulzeile führt die höhere Version.');

        $version = fn (string $kennung) => DB::table('lehrberuf_module as lbm')
            ->join('lehrberufe as lb', 'lb.lehrberuf_id', '=', 'lbm.lehrberuf_id')
            ->where('lb.quelle_kennung', $kennung)
            ->where('lbm.modul_id', $modul->modul_id)
            ->value('lbm.version');

        $this->assertSame('1', $version('alt'), 'Der ältere Jahrgang behält seine Version.');
        $this->assertSame('2', $version('neu'));
    }

    #[Test]
    public function ein_zweiter_lauf_aktualisiert_statt_zu_verdoppeln(): void
    {
        $datei = $this->ernte();
        $this->artisan('notenportal:modulkatalog', ['datei' => $datei, '--anwenden' => true])->assertSuccessful();
        $this->artisan('notenportal:modulkatalog', ['datei' => $datei, '--anwenden' => true])->assertSuccessful();

        $this->assertSame(2, DB::table('module')->count());
        $this->assertSame(1, DB::table('lehrberufe')->count());
        $this->assertSame(2, DB::table('lehrberuf_module')->count());
        $this->assertSame(2, DB::table('modul_handlungsziele')->count());
        $this->assertSame(2, DB::table('modul_lbv_elemente')->count());
    }

    #[Test]
    public function eigene_titel_und_lernorte_bleiben_erhalten(): void
    {
        $kategorien = DB::table('kategorien')->pluck('kategorie_id', 'code');
        $beruf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'PRF', 'name' => 'Prüfberuf Musterrichtung']);
        $modul = DB::table('module')->insertGetId(['modul_nummer' => 'M987', 'titel' => 'ÜK: Eigene Bezeichnung']);
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $beruf, 'modul_id' => $modul,
            'kategorie_id' => $kategorien['UEK'], 'pflicht' => 0, 'empfohlenes_lehrsemester_nr' => 5,
        ]);

        $this->artisan('notenportal:modulkatalog', ['datei' => $this->ernte(), '--anwenden' => true])->assertSuccessful();

        $nachher = DB::table('module')->where('modul_id', $modul)->first();
        $this->assertSame('ÜK: Eigene Bezeichnung', $nachher->titel, 'ein Import wirft eigene Titel nicht weg');
        $this->assertSame('2', $nachher->version, 'Katalogangaben kommen trotzdem dazu');

        // Derselbe Beruf, nur anders geschrieben: kein zweiter Lehrberuf.
        $this->assertSame(1, DB::table('lehrberufe')->count());
        $zuordnung = DB::table('lehrberuf_module')->where('modul_id', $modul)->first();
        $this->assertSame((int) $kategorien['UEK'], (int) $zuordnung->kategorie_id, 'Lernort bleibt beim Betrieb');
        $this->assertSame(5, (int) $zuordnung->empfohlenes_lehrsemester_nr, 'gesetztes Semester bleibt');
        $this->assertSame(1, (int) $zuordnung->pflicht, 'der Pflichtgrad kommt aus dem Katalog');
    }

    /** Ein eigenes Modul, dessen Nummer der Katalog bei einem ganz anderen Beruf führt. */
    private function eigenesModulUnterKatalognummer(): int
    {
        $fremd = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'ABU', 'name' => 'Allgemeinbildung']);
        $modul = DB::table('module')->insertGetId(['modul_nummer' => 'M654', 'titel' => 'ABU: Gesellschaft']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $fremd, 'modul_id' => $modul, 'pflicht' => 1]);

        return $modul;
    }

    #[Test]
    public function eine_fremd_gemeinte_katalognummer_bleibt_unberuehrt(): void
    {
        $modul = $this->eigenesModulUnterKatalognummer();

        $this->artisan('notenportal:modulkatalog', ['datei' => $this->ernte(), '--anwenden' => true])
            ->expectsOutputToContain('654: ABU: Gesellschaft')
            ->assertSuccessful();

        $nachher = DB::table('module')->where('modul_id', $modul)->first();
        $this->assertSame('ABU: Gesellschaft', $nachher->titel);
        $this->assertNull($nachher->quelle, 'das Modul gehört weiterhin dem Betrieb');
        $this->assertNull($nachher->version, 'ohne Version entsteht kein Link auf ein fremdes Modul');

        // Der Katalogberuf erbt die fremd gemeinte Nummer nicht.
        $this->assertSame(1, DB::table('lehrberuf_module')->where('modul_id', $modul)->count());
        $this->assertSame(2, DB::table('module')->count(), 'nur M987 kommt dazu');
    }

    #[Test]
    public function eigene_uebernehmen_holt_das_modul_doch_in_den_katalog(): void
    {
        $modul = $this->eigenesModulUnterKatalognummer();

        $this->artisan('notenportal:modulkatalog', [
            'datei' => $this->ernte(), '--anwenden' => true, '--eigene-uebernehmen' => true,
        ])->assertSuccessful();

        $nachher = DB::table('module')->where('modul_id', $modul)->first();
        $this->assertSame('ABU: Gesellschaft', $nachher->titel, 'der eigene Titel bleibt auch dann');
        $this->assertSame('modulbaukasten', $nachher->quelle);
        $this->assertSame('1', $nachher->version);
        $this->assertSame(2, DB::table('lehrberuf_module')->where('modul_id', $modul)->count());
    }

    #[Test]
    public function ein_eigenes_modul_ohne_jede_zuordnung_gilt_als_konflikt(): void
    {
        // Ohne Zuordnung fehlt jeder Hinweis, dass dieselbe Nummer dasselbe Modul meint. Der Import
        // lässt sie dann aus, statt zu raten – hier festgeschrieben, damit es dabei bleibt.
        $modul = DB::table('module')->insertGetId(['modul_nummer' => '987', 'titel' => 'Eigenes ohne Beruf']);

        $this->artisan('notenportal:modulkatalog', ['datei' => $this->ernte(), '--anwenden' => true])
            ->expectsOutputToContain('987: Eigenes ohne Beruf')
            ->assertSuccessful();

        $nachher = DB::table('module')->where('modul_id', $modul)->first();
        $this->assertSame('Eigenes ohne Beruf', $nachher->titel);
        $this->assertNull($nachher->quelle);
        $this->assertNull($nachher->version);
        $this->assertSame(0, DB::table('lehrberuf_module')->where('modul_id', $modul)->count());
    }

    #[Test]
    public function zwei_jahrgaenge_desselben_berufs_bleiben_getrennt(): void
    {
        $bestehend = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'PRF', 'name' => 'Prüfberuf Musterrichtung']);
        $datei = $this->ernte(['abschluesse' => [
            [
                'name' => 'Prüfberuf/in EFZ Musterrichtung (2014)',
                'kennung' => 'kennung-2014',
                'module' => [['nummer' => '654', 'version' => '1', 'titel' => 'Alte Verordnung', 'pflichtgrad' => 'pfl', 'lehrjahr' => 1]],
            ],
            [
                'name' => 'Prüfberuf/in EFZ Musterrichtung (2026)',
                'kennung' => 'kennung-2026',
                'module' => [['nummer' => '987', 'version' => '2', 'titel' => 'Neue Verordnung', 'pflichtgrad' => 'pfl', 'lehrjahr' => 1]],
            ],
        ]]);

        $this->artisan('notenportal:modulkatalog', ['datei' => $datei, '--anwenden' => true])->assertSuccessful();

        $this->assertSame(2, DB::table('lehrberufe')->count(), 'der ältere Jahrgang bekommt einen eigenen Lehrberuf');
        $alt = DB::table('lehrberufe')->where('quelle_kennung', 'kennung-2014')->first();
        $neu = DB::table('lehrberufe')->where('quelle_kennung', 'kennung-2026')->first();
        $this->assertSame($bestehend, (int) $neu->lehrberuf_id, 'der jüngste Jahrgang übernimmt den bestehenden Beruf');
        $this->assertNotSame($bestehend, (int) $alt->lehrberuf_id);
        $this->assertSame(2014, (int) $alt->bivo_jahr);

        $nummern = fn (int $id) => DB::table('lehrberuf_module as lbm')
            ->join('module as m', 'm.modul_id', '=', 'lbm.modul_id')
            ->where('lbm.lehrberuf_id', $id)->orderBy('m.modul_nummer')->pluck('m.modul_nummer')->all();
        $this->assertSame(['M654'], $nummern((int) $alt->lehrberuf_id));
        $this->assertSame(['M987'], $nummern((int) $neu->lehrberuf_id), 'die Verordnungen vermischen sich nicht');

        // Zweiter Lauf: die Kennung findet beide wieder, nichts verdoppelt sich.
        $this->artisan('notenportal:modulkatalog', ['datei' => $datei, '--anwenden' => true])->assertSuccessful();
        $this->assertSame(2, DB::table('lehrberufe')->count());
        $this->assertSame(2, DB::table('lehrberuf_module')->count());
    }

    #[Test]
    public function ohne_berufe_bleiben_die_lehrberufe_unberuehrt(): void
    {
        $this->artisan('notenportal:modulkatalog', ['datei' => $this->ernte(), '--anwenden' => true, '--ohne-berufe' => true])
            ->assertSuccessful();

        $this->assertSame(2, DB::table('module')->count());
        $this->assertSame(0, DB::table('lehrberufe')->count());
        $this->assertSame(0, DB::table('lehrberuf_module')->count());
    }
}
