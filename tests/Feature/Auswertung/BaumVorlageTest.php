<?php

declare(strict_types=1);

namespace Tests\Feature\Auswertung;

use App\Models\NotenbaumPosition;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Notenbaum\BaumErgebnis;
use App\Services\Auswertung\Notenbaum\BaumVorlage;
use App\Services\Auswertung\NotenQuelle;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Notenbaum-Vorlagen (resources/vorlagen/notenbaeume): prüfen, importieren, exportieren – und die
 * Abnahmefälle aus NOTENBAUM.md §3 über den importierten Baum statt über einen von Hand gebauten.
 */
class BaumVorlageTest extends TestCase
{
    private int $lehrberuf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $this->lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'VLT', 'name' => 'Vorlagen-Test EFZ']);
        Konfiguration::vergessen();
    }

    #[Test]
    public function mitgelieferte_vorlagen_sind_gueltig(): void
    {
        $liste = BaumVorlage::mitgeliefert();

        $this->assertArrayHasKey('informatiker-efz-bivo2020', $liste);
        $this->assertArrayHasKey('bm-tals1-bmv2025', $liste);
        foreach (array_keys($liste) as $schluessel) {
            $this->assertSame([], BaumVorlage::pruefen(BaumVorlage::laden($schluessel)), $schluessel);
        }
    }

    /** @return array<string, array{0: callable(array<string, mixed>): array<string, mixed>, 1: string}> */
    public static function kaputteVorlagen(): array
    {
        return [
            'falsches Format' => [fn ($d) => ['format' => 'etwas'] + $d, 'keine Notenbaum-Vorlage'],
            'doppelter Code' => [function ($d) {
                $d['wurzel']['kinder'][1]['code'] = 'ipa';

                return $d;
            }, 'mehrfach'],
            'Gruppe ohne Kinder' => [function ($d) {
                $d['wurzel']['kinder'][1]['kinder'] = [];

                return $d;
            }, 'keine Unterknoten'],
            'unbekannte Kategorie' => [function ($d) {
                $d['wurzel']['kinder'][3]['kinder'][0]['kategorie'] = 'XYZ';

                return $d;
            }, 'XYZ'],
            'Rundung 0.25' => [function ($d) {
                $d['wurzel']['rundung'] = 0.25;

                return $d;
            }, 'Rundung'],
            'manuell mit Kindern' => [function ($d) {
                $d['wurzel']['kinder'][0]['kinder'] = [['code' => 'x', 'typ' => 'manuell']];

                return $d;
            }, 'Nur Gruppen'],
            'Fächerknoten ohne Fach' => [function ($d) {
                $d['wurzel']['kinder'][2]['faecher'] = [];

                return $d;
            }, 'mindestens ein Fach'],
            'Bildungsgang ohne Track' => [fn ($d) => ['bezug' => 'bildungsgang'] + $d, 'Track'],
            'Code mit Leerzeichen' => [function ($d) {
                $d['wurzel']['code'] = 'q v';

                return $d;
            }, 'Code nur aus'],
            'ohne Wurzel' => [function ($d) {
                unset($d['wurzel']);

                return $d;
            }, 'Wurzelknoten'],
            // Typen und Grenzen der Spalten: früher 500 statt Meldung
            'Name als Liste' => [fn ($d) => ['name' => ['x']] + $d, 'Namen mit höchstens 150'],
            'Name zu lang' => [fn ($d) => ['name' => str_repeat('x', 151)] + $d, 'Namen mit höchstens 150'],
            'Beschreibung als Liste' => [fn ($d) => ['beschreibung' => ['x']] + $d, 'Beschreibung'],
            'Code als Liste' => [function ($d) {
                $d['wurzel']['kinder'][0]['code'] = ['ipa'];

                return $d;
            }, 'Code nur aus'],
            'Knotenname als Liste' => [function ($d) {
                $d['wurzel']['kinder'][0]['name'] = ['IPA'];

                return $d;
            }, 'kein Text'],
            'max_ungenuegend 300' => [function ($d) {
                $d['wurzel']['max_ungenuegend'] = 300;

                return $d;
            }, 'ungenügender Noten'],
            'max_minuspunkte negativ' => [function ($d) {
                $d['wurzel']['max_minuspunkte'] = -1;

                return $d;
            }, 'Minuspunkte'],
            'Gewicht 1e7' => [function ($d) {
                $d['wurzel']['kinder'][0]['gewicht'] = 1e7;

                return $d;
            }, 'Gewicht'],
            'Rundung als Liste' => [function ($d) {
                $d['wurzel']['rundung'] = [0.1];

                return $d;
            }, 'Rundung'],
            'zählt als Text' => [function ($d) {
                $d['wurzel']['kinder'][0]['zaehlt'] = 'ja';

                return $d;
            }, 'zählt'],
            'Fachname als Liste' => [function ($d) {
                $d['wurzel']['kinder'][2]['faecher'][0]['name'] = ['Sport'];

                return $d;
            }, 'Fach ohne Namen'],
            'Kinder als Text' => [function ($d) {
                $d['wurzel']['kinder'][1]['kinder'] = 'x';

                return $d;
            }, 'keine Unterknoten'],
        ];
    }

    #[Test]
    #[DataProvider('kaputteVorlagen')]
    public function kaputte_vorlagen_werden_mit_grund_abgelehnt(callable $kaputt, string $erwartet): void
    {
        $fehler = BaumVorlage::pruefen($kaputt(BaumVorlage::laden('informatiker-efz-bivo2020')));

        $this->assertNotSame([], $fehler);
        $this->assertStringContainsString($erwartet, implode(' | ', $fehler));
    }

    #[Test]
    public function kein_json_und_fremdes_json_werfen_verstaendlich(): void
    {
        $this->assertThrows(fn () => BaumVorlage::lesen('{kaputt'), RuntimeException::class, 'kein gültiges JSON');
        $this->assertThrows(fn () => BaumVorlage::lesen('{"format":"x"}'), RuntimeException::class, 'keine Notenbaum-Vorlage');
        $this->assertThrows(fn () => BaumVorlage::laden('gibt-es-nicht'), RuntimeException::class, 'Unbekannte Vorlage');
    }

    #[Test]
    public function import_legt_baum_an_gibt_faecher_frei_und_loest_den_alten_baum_ab(): void
    {
        $englisch = DB::table('faecher')->insertGetId(['name' => 'englisch', 'kurzname' => 'E', 'kategorie_id' => $this->kat('FACH'), 'aktiv' => 1]);
        $vorlage = BaumVorlage::laden('informatiker-efz-bivo2020');

        $erster = app(BaumVorlage::class)->importieren($vorlage, $this->lehrberuf, 'informatiker-efz-bivo2020');
        $zweiter = app(BaumVorlage::class)->importieren($vorlage, $this->lehrberuf, 'informatiker-efz-bivo2020');

        $this->assertFalse((bool) DB::table('notenbaeume')->where('baum_id', $erster)->value('aktiv'));
        $this->assertTrue((bool) DB::table('notenbaeume')->where('baum_id', $zweiter)->value('aktiv'));
        $this->assertSame(10, DB::table('notenbaum_knoten')->where('baum_id', $zweiter)->count());

        // Englisch wird unabhängig von Gross-/Kleinschreibung wiederverwendet, Mathematik einmal angelegt
        $this->assertSame(1, DB::table('faecher')->whereRaw('LOWER(name) = ?', ['englisch'])->count());
        $this->assertSame(1, DB::table('faecher')->where('name', 'Mathematik')->count());
        $mathe = (int) DB::table('faecher')->where('name', 'Mathematik')->value('fach_id');
        $freigegeben = DB::table('lehrberuf_faecher')->where('lehrberuf_id', $this->lehrberuf)->pluck('fach_id')->map(fn ($v) => (int) $v)->all();
        $this->assertEqualsCanonicalizing([$englisch, $mathe], $freigegeben);

        $egk = DB::table('notenbaum_knoten')->where('baum_id', $zweiter)->where('code', 'egk')->value('knoten_id');
        $this->assertEqualsCanonicalizing([$englisch, $mathe],
            DB::table('notenbaum_knoten_faecher')->where('knoten_id', $egk)->pluck('fach_id')->map(fn ($v) => (int) $v)->all());
    }

    #[Test]
    public function lehrberuf_vorlage_ohne_lehrberuf_wird_abgelehnt(): void
    {
        $this->assertThrows(
            fn () => app(BaumVorlage::class)->importieren(BaumVorlage::laden('informatiker-efz-bivo2020')),
            RuntimeException::class,
            'Lehrberuf',
        );
        $this->assertSame(0, DB::table('notenbaeume')->count());
    }

    #[Test]
    public function fall_a_ueber_den_importierten_efz_baum(): void
    {
        [$user, $id, $m] = $this->efzLernender();
        $this->noten($user, [['modul', $m['schule'], 5.0], ['modul', $m['uek'], 4.0], ['fach', $m['englisch'], 4.5], ['fach', $m['abu'], 4.5]]);
        foreach (['ipa' => 5.0, 'ab_schlussarbeit' => 5.0, 'ab_schlusspruefung' => 4.0] as $code => $wert) {
            NotenbaumPosition::create([
                'lernender_id' => $id,
                'knoten_id' => DB::table('notenbaum_knoten')->where('code', $code)->value('knoten_id'),
                'note_wert' => $wert,
                'erfasst_von_benutzer_id' => $user->benutzer_id,
            ]);
        }

        $a = app(NotenQuelle::class)->auswertung($id);
        $baum = array_values($a->baeume)[0];

        $this->assertEqualsWithDelta(4.8, $baum->knoten('ik')->note, 1e-9);
        $this->assertEqualsWithDelta(4.5, $baum->knoten('egk')->note, 1e-9);
        $this->assertEqualsWithDelta(4.5, $baum->knoten('ab')->note, 1e-9);
        $this->assertEqualsWithDelta(4.8, $a->gesamtNote, 1e-9);
        $this->assertSame(BaumErgebnis::BESTANDEN, $baum->status);
    }

    #[Test]
    public function efz_vorlage_rundet_die_teilmittel_der_informatikkompetenzen_auf_halbe_noten(): void
    {
        [$user, $id, $m] = $this->efzLernender();
        $zweites = DB::table('module')->insertGetId(['modul_nummer' => '902', 'titel' => 'Testmodul Schule 2']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $this->lehrberuf, 'modul_id' => $zweites, 'kategorie_id' => $this->kat('FACH')]);
        Konfiguration::vergessen();
        $this->noten($user, [['modul', $m['schule'], 4.5], ['modul', $zweites, 5.0], ['modul', $m['uek'], 4.0]]);

        $baum = array_values(app(NotenQuelle::class)->auswertung($id)->baeume)[0];

        // Schulmodule 4.75 → 5.0 (LAGE §2: Teilmittel auf ganze/halbe Note); ungerundet wäre IK 4.6
        $this->assertEqualsWithDelta(5.0, $baum->knoten('ik_bfs')->note, 1e-9);
        $this->assertEqualsWithDelta(4.8, $baum->knoten('ik')->note, 1e-9);
    }

    #[Test]
    public function bm_vorlage_haengt_am_track_und_idaf_zaehlt_nicht(): void
    {
        $efzEnglisch = DB::table('faecher')->insertGetId(['name' => 'Englisch', 'kurzname' => 'EN', 'kategorie_id' => $this->kat('FACH'), 'aktiv' => 1]);

        $id = app(BaumVorlage::class)->importieren(BaumVorlage::laden('bm-tals1-bmv2025'), null, 'bm-tals1-bmv2025');

        $bmEnglisch = DB::table('faecher')->where('name', 'Englisch')->where('track_typ', 'BMS')->value('fach_id');
        $this->assertNotNull($bmEnglisch, 'BM-Englisch ist ein eigenes Fach im Track BMS');
        $this->assertNotEquals($efzEnglisch, $bmEnglisch);

        $baum = DB::table('notenbaeume')->where('baum_id', $id)->first();
        $this->assertSame('bildungsgang', $baum->bezug);
        $this->assertSame('BMS', $baum->track_typ);
        $this->assertNull($baum->lehrberuf_id);

        $idaf = DB::table('faecher')->where('kurzname', 'IDAF')->first();
        $this->assertNotNull($idaf);
        $this->assertFalse((bool) $idaf->zaehlt);
        $this->assertSame('BMS', $idaf->track_typ);
        $this->assertSame(1, DB::table('faecher')->where('name', 'Italienisch')->count());
        $this->assertSame(0, DB::table('faecher')->where('name', 'Französisch')->count());
        $this->assertSame(0, DB::table('faecher')->where('track_typ', 'BMS')->where('kategorie_id', '!=', $this->kat('BMS'))->count());
        $this->assertSame(0, DB::table('lehrberuf_faecher')->count(), 'Track-Fächer laufen über den Track, nicht über den Lehrberuf');
    }

    #[Test]
    public function export_und_reimport_ergeben_denselben_baum(): void
    {
        foreach (['informatiker-efz-bivo2020' => $this->lehrberuf, 'bm-tals1-bmv2025' => null] as $schluessel => $lehrberuf) {
            $vorlage = BaumVorlage::laden($schluessel);
            $id = app(BaumVorlage::class)->importieren($vorlage, $lehrberuf);
            $export = app(BaumVorlage::class)->exportieren($id);

            $this->assertSame([], BaumVorlage::pruefen($export));
            unset($vorlage['quellen'], $vorlage['offen']);
            $this->assertEquals($vorlage, $export, $schluessel);

            $faecherVorher = DB::table('faecher')->count();
            $zweiter = app(BaumVorlage::class)->importieren(BaumVorlage::lesen((string) json_encode($export)), $lehrberuf);
            $this->assertEquals($export, app(BaumVorlage::class)->exportieren($zweiter));
            $this->assertSame($faecherVorher, DB::table('faecher')->count(), 'Reimport legt keine Fächer doppelt an');
        }
    }

    #[Test]
    public function artisan_befehl_listet_laedt_und_exportiert(): void
    {
        $this->artisan('notenportal:notenbaum', ['aktion' => 'liste'])
            ->expectsOutputToContain('informatiker-efz-bivo2020')
            ->assertSuccessful();

        $this->artisan('notenportal:notenbaum', ['aktion' => 'laden', 'ziel' => 'informatiker-efz-bivo2020'])
            ->assertFailed();

        $this->artisan('notenportal:notenbaum', ['aktion' => 'laden', 'ziel' => 'informatiker-efz-bivo2020', '--lehrberuf' => 'VLT'])
            ->assertSuccessful();
        $id = (int) DB::table('notenbaeume')->where('lehrberuf_id', $this->lehrberuf)->value('baum_id');

        $datei = sys_get_temp_dir().'/notenbaum-'.getmypid().'.json';
        $this->artisan('notenportal:notenbaum', ['aktion' => 'export', 'ziel' => $datei, '--baum' => $id])->assertSuccessful();
        $this->assertSame([], BaumVorlage::pruefen(json_decode((string) file_get_contents($datei), true)));

        $this->artisan('notenportal:notenbaum', ['aktion' => 'import', 'ziel' => $datei, '--lehrberuf' => 'Vorlagen-Test EFZ'])->assertSuccessful();
        @unlink($datei);
        $this->assertSame(2, DB::table('notenbaeume')->where('lehrberuf_id', $this->lehrberuf)->count());
        $this->assertSame(1, DB::table('notenbaeume')->where('lehrberuf_id', $this->lehrberuf)->where('aktiv', true)->count());
    }

    /** @return array{0: User, 1: int, 2: array{schule: int, uek: int, englisch: int, abu: int}} Lernender mit importiertem EFZ-Baum */
    private function efzLernender(): array
    {
        app(BaumVorlage::class)->importieren(BaumVorlage::laden('informatiker-efz-bivo2020'), $this->lehrberuf);
        $schule = DB::table('module')->insertGetId(['modul_nummer' => '901', 'titel' => 'Testmodul Schule']);
        $uek = DB::table('module')->insertGetId(['modul_nummer' => '991', 'titel' => 'Testmodul ÜK']);
        DB::table('lehrberuf_module')->insert([
            ['lehrberuf_id' => $this->lehrberuf, 'modul_id' => $schule, 'kategorie_id' => $this->kat('FACH')],
            ['lehrberuf_id' => $this->lehrberuf, 'modul_id' => $uek, 'kategorie_id' => $this->kat('UEK')],
        ]);
        $abu = DB::table('faecher')->insertGetId(['name' => 'Allgemeinbildung', 'kurzname' => 'ABU', 'kategorie_id' => $this->kat('ABU'), 'aktiv' => 1]);
        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $this->lehrberuf, 'fach_id' => $abu]);
        DB::table('semester')->insert(['bezeichnung' => 'VL-1', 'start_datum' => '2024-08-01', 'end_datum' => now()->addYears(3)->toDateString(), 'sortierung' => 1]);
        Konfiguration::vergessen();

        $user = User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create();
        $englisch = (int) DB::table('faecher')->where('name', 'Englisch')->value('fach_id');

        return [$user, (int) $user->lernender->lernender_id, ['schule' => $schule, 'uek' => $uek, 'englisch' => $englisch, 'abu' => $abu]];
    }

    /** @param  list<array{0: string, 1: int, 2: float}>  $noten */
    private function noten(User $user, array $noten): void
    {
        $this->actingAs($user);
        foreach ($noten as [$typ, $bezug, $wert]) {
            $this->post(route('learner.grades.store'), [
                'typ' => $typ, $typ.'_id' => $bezug, 'pruefungsdatum' => now()->toDateString(), 'note_wert' => (string) $wert,
            ])->assertSessionHasNoErrors();
        }
    }

    private function kat(string $code): int
    {
        return (int) DB::table('kategorien')->where('code', $code)->value('kategorie_id');
    }
}
