<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Auswertung\Notenbaum\BaumVorlage;
use App\Services\Stammdaten\StammdatenVorlage;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Einrichtungsschritt «Lehrberufe & Fächer» mit Stammdaten-Vorlagen (resources/vorlagen/stammdaten):
 * keine Listen im Code, Bündner Realität als Standard, echte Pflege verlinkt (LAGE.md Lücke 8).
 */
class EinrichtungVorlagenTest extends TestCase
{
    private const string STANDARD = 'graubuenden-gbchur';

    private User $admin;

    /** @var list<string> */
    private array $tempVerzeichnisse = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempVerzeichnisse as $dir) {
            array_map('unlink', glob($dir.'/*') ?: []);
            @rmdir($dir);
        }
        parent::tearDown();
    }

    /** Eigenes Vorlagenverzeichnis: Name => Inhalt (Text oder Array). Die echten Vorlagen bleiben unberührt. */
    private function vorlagenverzeichnis(array $dateien): string
    {
        $dir = sys_get_temp_dir().'/np-vorlagen-'.bin2hex(random_bytes(4));
        mkdir($dir);
        $this->tempVerzeichnisse[] = $dir;
        foreach ($dateien as $name => $inhalt) {
            file_put_contents("$dir/$name.json", is_string($inhalt) ? $inhalt : json_encode($inhalt, JSON_UNESCAPED_UNICODE));
        }
        config(['notenportal.stammdaten_verzeichnis' => $dir]);

        return $dir;
    }

    /** @return list<string> Fachschlüssel, die das Formular der Standardvorlage vorausgewählt anbietet */
    private function vorauswahlFaecher(): array
    {
        return collect(StammdatenVorlage::laden(self::STANDARD)['faecher'])->where('vorauswahl', true)
            ->map(fn ($f) => StammdatenVorlage::fachSchluessel($f))->values()->all();
    }

    private function lehrberufId(string $kuerzel): int
    {
        return (int) DB::table('lehrberufe')->where('kuerzel', $kuerzel)->value('lehrberuf_id');
    }

    /** @return list<string> Namen der Fächer, die dem Lehrberuf freigegeben sind */
    private function freigegeben(string $kuerzel): array
    {
        return DB::table('lehrberuf_faecher as lf')->join('faecher as f', 'f.fach_id', '=', 'lf.fach_id')
            ->where('lf.lehrberuf_id', $this->lehrberufId($kuerzel))->orderBy('f.name')->pluck('f.name')->all();
    }

    #[Test]
    public function mitgelieferte_vorlagen_sind_gueltig_und_verweisen_auf_vorhandene_baeume_und_kategorien(): void
    {
        $vorlagen = StammdatenVorlage::mitgeliefert();
        $this->assertSame('graubuenden-gbchur', array_key_first($vorlagen), 'Graubünden ist die Standardvorlage');
        $this->assertArrayHasKey('schweiz-ict', $vorlagen);

        $baeume = BaumVorlage::mitgeliefert();
        $kategorien = DB::table('kategorien')->pluck('code')->all();
        foreach ($vorlagen as $schluessel => $v) {
            foreach ($v['lehrberufe'] as $l) {
                if (isset($l['notenbaum'])) {
                    $this->assertArrayHasKey($l['notenbaum'], $baeume, "$schluessel: {$l['kuerzel']}");
                }
            }
            foreach ($v['notenbaeume'] ?? [] as $b) {
                $this->assertArrayHasKey($b['vorlage'], $baeume, $schluessel);
            }
            foreach ($v['faecher'] as $f) {
                $this->assertContains($f['kategorie'] ?? ($f['track'] ?? 'FACH'), $kategorien, "$schluessel: {$f['name']}");
            }
        }
    }

    #[Test]
    public function standard_ist_italienisch_und_die_echte_pflege_ist_verlinkt(): void
    {
        $this->actingAs($this->admin)->get(route('admin.setup', 'professions'))->assertOk()
            ->assertSee('Italienisch')->assertDontSee('Französisch')
            ->assertSee('Sport')
            ->assertSee(route('admin.master-data.subjects.index'))
            ->assertSee(route('admin.master-data.grade-trees.index'))
            ->assertSee('Informatiker/in EFZ (BiVo 2020)');

        $this->get(route('admin.setup', ['schritt' => 'professions', 'vorlage' => 'schweiz-ict']))->assertOk()
            ->assertSee('Französisch')->assertSee('Mediamatiker/in EFZ');
        // Unbekannte Vorlage fällt still auf den Standard zurück
        $this->get(route('admin.setup', ['schritt' => 'professions', 'vorlage' => '../../etc/passwd']))->assertOk()->assertSee('Italienisch');
    }

    #[Test]
    public function vorauswahl_legt_berufe_faecher_und_notenbaeume_an_und_ist_wiederholbar(): void
    {
        $vorlage = StammdatenVorlage::laden('graubuenden-gbchur');
        $post = [
            'vorlage' => 'graubuenden-gbchur',
            'berufe' => ['INAP', 'INPE'],
            'faecher' => collect($vorlage['faecher'])->where('vorauswahl', true)->map(fn ($f) => StammdatenVorlage::fachSchluessel($f))->values()->all(),
            'notenbaeume' => '1',
        ];

        $this->actingAs($this->admin)->post(route('admin.setup.professions'), $post)
            ->assertRedirect(route('admin.setup', 'modules'))->assertSessionHas('success');

        $this->assertEqualsCanonicalizing(['INAP', 'INPE'], DB::table('lehrberufe')->pluck('kuerzel')->all());
        $this->assertSame('BMS', DB::table('faecher')->where('name', 'Italienisch')->value('track_typ'));
        $this->assertSame(0, DB::table('faecher')->where('name', 'Französisch')->count());
        $idaf = DB::table('faecher')->where('kurzname', 'IDAF')->first();
        $this->assertFalse((bool) $idaf->zaehlt);
        $sport = DB::table('faecher')->where('name', 'Sport')->first();
        $this->assertSame('stufe', $sport->skala);
        $this->assertFalse((bool) $sport->zaehlt);
        $this->assertNull($sport->track_typ);
        $this->assertSame(2, DB::table('lehrberuf_faecher')->where('fach_id', $sport->fach_id)->count(), 'Sport für beide Lehrberufe freigegeben');

        // Zwei EFZ-Bäume (INAP, INPE) und der BM-Baum; Fächer der Bäume werden wiederverwendet
        $this->assertSame(3, DB::table('notenbaeume')->where('aktiv', true)->count());
        $this->assertSame(1, DB::table('faecher')->where('name', 'Englisch')->whereNull('track_typ')->count());
        $this->assertSame(1, DB::table('faecher')->where('name', 'Englisch')->where('track_typ', 'BMS')->count());

        $faecher = DB::table('faecher')->count();
        $this->post(route('admin.setup.professions'), $post)->assertSessionHas('success');
        $this->assertSame($faecher, DB::table('faecher')->count());
        $this->assertSame(3, DB::table('notenbaeume')->count(), 'Zweiter Durchlauf lädt keine Bäume doppelt');
    }

    #[Test]
    public function ohne_haken_werden_keine_notenbaeume_geladen(): void
    {
        $this->actingAs($this->admin)->post(route('admin.setup.professions'), [
            'vorlage' => 'graubuenden-gbchur', 'berufe' => ['INAP'], 'faecher' => ['BMS:D'], 'notenbaeume' => '0',
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('notenbaeume')->count());
        $this->assertSame(1, DB::table('lehrberufe')->count());
    }

    #[Test]
    public function fach_oder_beruf_ausserhalb_der_vorlage_wird_abgelehnt(): void
    {
        $this->actingAs($this->admin)->post(route('admin.setup.professions'), [
            'vorlage' => 'graubuenden-gbchur', 'faecher' => ['BMS:F'],
        ])->assertSessionHasErrors('faecher.0');
        $this->post(route('admin.setup.professions'), [
            'vorlage' => 'graubuenden-gbchur', 'berufe' => ['MEDI'],
        ])->assertSessionHasErrors('berufe.0');
        $this->post(route('admin.setup.professions'), ['vorlage' => 'gibt-es-nicht'])->assertSessionHasErrors('vorlage');

        $this->assertSame(0, DB::table('faecher')->count());
        $this->assertSame(0, DB::table('lehrberufe')->count());
    }

    #[Test]
    public function vorhandener_beruf_bekommt_neue_faecher_und_fehlenden_baum(): void
    {
        DB::table('lehrberufe')->insert(['kuerzel' => 'INAP', 'name' => 'Informatiker/in EFZ Applikationsentwicklung', 'aktiv' => 1]);

        // Der vorhandene Beruf ist im Formular deaktiviert und wird nicht mitgesendet
        $this->actingAs($this->admin)->post(route('admin.setup.professions'), [
            'vorlage' => 'graubuenden-gbchur', 'faecher' => ['BERUF:SP'],
        ])->assertSessionHasNoErrors();

        $inap = (int) DB::table('lehrberufe')->where('kuerzel', 'INAP')->value('lehrberuf_id');
        $sport = (int) DB::table('faecher')->where('name', 'Sport')->value('fach_id');
        $this->assertTrue(DB::table('lehrberuf_faecher')->where(['lehrberuf_id' => $inap, 'fach_id' => $sport])->exists());
        $this->assertTrue(DB::table('notenbaeume')->where(['lehrberuf_id' => $inap, 'aktiv' => true])->exists());
    }

    #[Test]
    public function neuer_beruf_im_zweiten_durchlauf_bekommt_vorhandene_faecher_ohne_track(): void
    {
        $this->actingAs($this->admin)->post(route('admin.setup.professions'), [
            'vorlage' => self::STANDARD, 'berufe' => ['INAP', 'INPE'], 'faecher' => ['BERUF:EN', 'BERUF:MA', 'BERUF:SP'], 'notenbaeume' => '0',
        ])->assertSessionHasNoErrors();
        // Entscheid des Admins: INPE hat Sport nicht mehr
        DB::table('lehrberuf_faecher')->where('lehrberuf_id', $this->lehrberufId('INPE'))
            ->where('fach_id', DB::table('faecher')->where('name', 'Sport')->value('fach_id'))->delete();

        // Zweiter Durchlauf wie das Formular: Vorhandenes ist deaktiviert und fehlt im POST
        $this->post(route('admin.setup.professions'), ['vorlage' => self::STANDARD, 'berufe' => ['INBE'], 'notenbaeume' => '0'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['Englisch', 'Mathematik', 'Sport'], $this->freigegeben('INBE'));
        $this->assertSame(['Englisch', 'Mathematik', 'Sport'], $this->freigegeben('INAP'));
        $this->assertSame(['Englisch', 'Mathematik'], $this->freigegeben('INPE'), 'Vorhandene Lehrberufe bleiben unangetastet');
    }

    #[Test]
    public function vorhandener_beruf_mit_gleichem_namen_und_anderem_kuerzel_wird_wiedererkannt(): void
    {
        DB::table('lehrberufe')->insert(['kuerzel' => 'IAP', 'name' => 'informatiker/in EFZ applikationsentwicklung', 'aktiv' => 1]);
        $iap = $this->lehrberufId('IAP');

        // Das Formular zeigt ihn als vorhanden (Name ohne Gross-/Kleinschreibung), nicht als weiteren Lehrberuf
        $this->actingAs($this->admin)->get(route('admin.setup', 'professions'))
            ->assertOk()->assertViewHas('vorhandeneBerufe', fn ($v) => ($v['INAP'] ?? null) === $iap);

        $this->post(route('admin.setup.professions'), [
            'vorlage' => self::STANDARD, 'berufe' => ['INPE'], 'faecher' => $this->vorauswahlFaecher(), 'notenbaeume' => '1',
        ])->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_starts_with($m, '1 Lehrberuf und 14 Fächer angelegt.'));

        $this->assertSame(0, DB::table('lehrberufe')->where('kuerzel', 'INAP')->count(), 'Kein zweiter INAP unter anderem Kürzel');
        $this->assertSame(['Englisch', 'Mathematik', 'Sport'], $this->freigegeben('IAP'));
        $this->assertTrue(DB::table('notenbaeume')->where(['lehrberuf_id' => $iap, 'aktiv' => true])->exists(), 'EFZ-Baum für IAP');
    }

    #[Test]
    public function zweiter_durchlauf_laedt_den_bm_baum_aus_vorhandenen_faechern_nach(): void
    {
        $this->actingAs($this->admin)->post(route('admin.setup.professions'), [
            'vorlage' => self::STANDARD, 'berufe' => ['INAP', 'INPE'], 'faecher' => $this->vorauswahlFaecher(), 'notenbaeume' => '0',
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, DB::table('notenbaeume')->count());

        // Zweiter Durchlauf wie das Formular: Berufe und Fächer sind vorhanden und deaktiviert, die Bäume angehakt
        $this->post(route('admin.setup.professions'), ['vorlage' => self::STANDARD, 'notenbaeume' => '1'])
            ->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, '3 Notenbäume geladen'));

        $this->assertTrue(DB::table('notenbaeume')->where(['bezug' => 'bildungsgang', 'track_typ' => 'BMS', 'aktiv' => true])->exists(), 'BM-Baum');
        $this->assertSame(3, DB::table('notenbaeume')->where('aktiv', true)->count());
    }

    #[Test]
    public function geaenderter_kategorie_code_bricht_den_schritt_ganz_ab_und_meldet_das_fach(): void
    {
        DB::table('kategorien')->where('code', 'ABU')->update(['code' => 'ALLG']);

        $this->actingAs($this->admin)->post(route('admin.setup.professions'), [
            'vorlage' => self::STANDARD, 'berufe' => ['INAP', 'INPE'], 'faecher' => $this->vorauswahlFaecher(), 'notenbaeume' => '1',
        ])->assertRedirect(route('admin.setup', 'professions'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Gesellschaft') && str_contains($m, 'ABU'));

        $this->assertSame(0, DB::table('lehrberufe')->count());
        $this->assertSame(0, DB::table('faecher')->count());
        $this->assertSame(0, DB::table('lehrberuf_faecher')->count());
        $this->assertSame(0, DB::table('notenbaeume')->count());

        // Der Schritt zeigt die Meldung, und die Eingaben sind noch angehakt
        $this->get(route('admin.setup', 'professions'))->assertOk()->assertSee('Kategorie «ABU» gibt es nicht.');
    }

    #[Test]
    public function scheitert_ein_notenbaum_wird_nichts_gespeichert(): void
    {
        // Der EFZ-Baum nutzt die Kategorie UEK; die gewählten Fächer brauchen sie nicht und werden vorher angelegt
        DB::table('kategorien')->where('code', 'UEK')->update(['code' => 'UEK2']);

        $this->actingAs($this->admin)->post(route('admin.setup.professions'), [
            'vorlage' => self::STANDARD, 'berufe' => ['INAP'], 'faecher' => ['BERUF:EN', 'BERUF:SP'], 'notenbaeume' => '1',
        ])->assertRedirect(route('admin.setup', 'professions'))->assertSessionHas('error');

        $this->assertSame(0, DB::table('lehrberufe')->count());
        $this->assertSame(0, DB::table('faecher')->count());
        $this->assertSame(0, DB::table('notenbaeume')->count());
    }

    #[Test]
    public function vorlage_als_liste_im_aufruf_ergibt_keinen_500(): void
    {
        $this->actingAs($this->admin)->get(route('admin.setup', ['schritt' => 'professions', 'vorlage' => ['x']]))
            ->assertOk()->assertSee('Italienisch');
        $this->post(route('admin.setup.professions'), ['vorlage' => ['x']])->assertSessionHasErrors('vorlage');
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function kaputteVorlagen(): array
    {
        $basis = [
            'format' => StammdatenVorlage::FORMAT, 'version' => StammdatenVorlage::VERSION, 'name' => 'Muster',
            'lehrberufe' => [['kuerzel' => 'AB', 'name' => 'Alpha']],
            'faecher' => [['name' => 'Deutsch', 'kurzname' => 'D']],
            'notenbaeume' => [['vorlage' => 'x', 'track' => 'BMS']],
        ];
        $mit = fn (array $ersatz) => [[...$basis, ...$ersatz]];
        $lehrberuf = fn (array $ersatz) => $mit(['lehrberufe' => [[...$basis['lehrberufe'][0], ...$ersatz]]]);
        $fach = fn (array $ersatz) => $mit(['faecher' => [[...$basis['faecher'][0], ...$ersatz]]]);

        return [
            'Version als Liste' => $mit(['version' => ['x']]),
            'Name als Liste' => $mit(['name' => ['x']]),
            'Beschreibung als Liste' => $mit(['beschreibung' => ['x']]),
            'Standard als Text' => $mit(['standard' => 'ja']),
            'Lehrberufe als Text' => $mit(['lehrberufe' => 'x']),
            'Lehrberufe als Objekt' => $mit(['lehrberufe' => ['a' => ['kuerzel' => 'AB', 'name' => 'Alpha']]]),
            'Lehrberuf als Text' => $mit(['lehrberufe' => ['x']]),
            'Lehrberuf-Kürzel als Liste' => $lehrberuf(['kuerzel' => ['AB']]),
            'Lehrberuf-Name als Liste' => $lehrberuf(['name' => ['x']]),
            'Lehrberuf-Notenbaum als Liste' => $lehrberuf(['notenbaum' => ['x']]),
            'Lehrberuf-Vorauswahl als Text' => $lehrberuf(['vorauswahl' => 'ja']),
            'Fächer als Text' => $mit(['faecher' => 'x']),
            'Fach als Text' => $mit(['faecher' => ['x']]),
            'Fach-Name als Liste' => $fach(['name' => ['x']]),
            'Fach-Kurzname als Liste' => $fach(['kurzname' => ['D']]),
            'Fach-Track als Liste' => $fach(['track' => ['BMS']]),
            'Fach-Skala als Liste' => $fach(['skala' => ['note']]),
            'Fach-Kategorie als Liste' => $fach(['kategorie' => ['FACH']]),
            'Fach-zählt als Text' => $fach(['zaehlt' => 'nein']),
            'Fach-Vorauswahl als Liste' => $fach(['vorauswahl' => ['x']]),
            'Notenbäume als Text' => $mit(['notenbaeume' => 'x']),
            'Notenbaum als Text' => $mit(['notenbaeume' => ['x']]),
            'Notenbaum-Vorlage als Liste' => $mit(['notenbaeume' => [['vorlage' => ['x'], 'track' => 'BMS']]]),
            'Notenbaum-Track unbekannt' => $mit(['notenbaeume' => [['vorlage' => 'x', 'track' => 'XYZ']]]),
        ];
    }

    /** @param  array<string, mixed>  $d */
    #[Test]
    #[DataProvider('kaputteVorlagen')]
    public function pruefen_meldet_falsche_typen_als_fehler_statt_zu_werfen(array $d): void
    {
        $this->assertNotSame([], StammdatenVorlage::pruefen($d));
    }

    #[Test]
    public function pruefen_laesst_eine_vollstaendige_vorlage_gelten(): void
    {
        $this->assertSame([], StammdatenVorlage::pruefen([...self::kaputteVorlagen()['Version als Liste'][0], 'version' => 1]));
        $this->assertSame([], StammdatenVorlage::pruefen(['format' => StammdatenVorlage::FORMAT, 'version' => 1, 'name' => 'Leer']));
    }

    #[Test]
    public function kaputte_vorlagendatei_wird_uebersprungen_und_gueltige_bleiben_nutzbar(): void
    {
        $kopf = ['format' => StammdatenVorlage::FORMAT, 'version' => 1];
        $this->vorlagenverzeichnis([
            'gut' => file_get_contents(resource_path('vorlagen/stammdaten/'.self::STANDARD.'.json')),
            'kaputt-lehrberufe' => [...$kopf, 'name' => 'Kaputt A', 'lehrberufe' => 'x', 'faecher' => []],
            'kaputt-faecher' => [...$kopf, 'name' => 'Kaputt B', 'lehrberufe' => [], 'faecher' => ['x']],
            'kaputt-name' => [...$kopf, 'name' => ['x'], 'lehrberufe' => [], 'faecher' => []],
        ]);
        $this->assertSame(['gut'], array_keys(StammdatenVorlage::mitgeliefert()));

        $this->actingAs($this->admin)->get(route('admin.setup', 'professions'))->assertOk()->assertSee('Italienisch');
        $this->get(route('admin.setup', ['schritt' => 'professions', 'vorlage' => 'kaputt-lehrberufe']))->assertOk()->assertSee('Italienisch');
        $this->post(route('admin.setup.professions'), ['vorlage' => 'gut', 'berufe' => ['INAP'], 'faecher' => ['BERUF:SP'], 'notenbaeume' => '0'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->post(route('admin.setup.professions'), ['vorlage' => 'kaputt-name'])->assertSessionHasErrors('vorlage');
        $this->assertSame(1, DB::table('lehrberufe')->count());
    }

    #[Test]
    public function ohne_gueltige_vorlage_gibt_es_einen_validierungsfehler_statt_500(): void
    {
        $this->vorlagenverzeichnis(['kaputt' => ['format' => StammdatenVorlage::FORMAT, 'version' => 1, 'name' => 'Kaputt', 'lehrberufe' => 'x']]);

        $this->actingAs($this->admin)->get(route('admin.setup', 'professions'))->assertOk();
        $this->post(route('admin.setup.professions'), ['eigene' => [['kuerzel' => 'lab', 'name' => 'Laborant/in EFZ']]])
            ->assertSessionHasErrors('vorlage');
        $this->assertSame(0, DB::table('lehrberufe')->count());

        $this->vorlagenverzeichnis([]);
        $this->post(route('admin.setup.professions'), [])->assertSessionHasErrors('vorlage');
    }

    #[Test]
    public function weiterer_lehrberuf_kuerzel_ist_ohne_gross_und_kleinschreibung_eindeutig(): void
    {
        $this->actingAs($this->admin)->post(route('admin.setup.professions'), [
            'vorlage' => self::STANDARD, 'eigene' => [['kuerzel' => 'ab', 'name' => 'Alpha'], ['kuerzel' => 'AB', 'name' => 'Beta']],
        ])->assertSessionHasErrors('eigene.1.kuerzel');
        $this->assertSame(0, DB::table('lehrberufe')->count());

        $this->post(route('admin.setup.professions'), [
            'vorlage' => self::STANDARD, 'eigene' => [['kuerzel' => 'ab', 'name' => 'Alpha'], ['kuerzel' => 'cd', 'name' => 'alpha']],
        ])->assertSessionHasErrors('eigene.1.name');

        // Leere Zeilen des Formulars bleiben erlaubt
        $this->post(route('admin.setup.professions'), [
            'vorlage' => self::STANDARD, 'eigene' => [['kuerzel' => '', 'name' => ''], ['kuerzel' => '', 'name' => '']],
        ])->assertSessionHasNoErrors();
    }

    #[Test]
    public function weiterer_lehrberuf_kuerzel_wird_mit_umlauten_gross_gespeichert(): void
    {
        $this->actingAs($this->admin)->post(route('admin.setup.professions'), [
            'vorlage' => self::STANDARD, 'eigene' => [['kuerzel' => 'äbc', 'name' => 'Ärmel-Näher/in EFZ'], ['kuerzel' => '123', 'name' => 'Nummer Drei']],
        ])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['ÄBC', '123'], DB::table('lehrberufe')->pluck('kuerzel')->all());
    }

    #[Test]
    public function weiterer_lehrberuf_darf_kein_vorlagenkuerzel_unter_fremdem_namen_anlegen(): void
    {
        $this->actingAs($this->admin)->post(route('admin.setup.professions'), [
            'vorlage' => self::STANDARD, 'eigene' => [['kuerzel' => 'inap', 'name' => 'Laborant/in EFZ']], 'notenbaeume' => '1',
        ])->assertSessionHasErrors(['eigene.0.kuerzel' => 'Kürzel gehört zu einem Lehrberuf der Vorlage']);

        $this->assertSame(0, DB::table('lehrberufe')->count());
        $this->assertSame(0, DB::table('notenbaeume')->count());

        // Mit dem Namen der Vorlage ist es derselbe Lehrberuf und damit unschädlich
        $this->post(route('admin.setup.professions'), [
            'vorlage' => self::STANDARD, 'eigene' => [['kuerzel' => 'inap', 'name' => 'Informatiker/in EFZ Applikationsentwicklung']],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['INAP'], DB::table('lehrberufe')->pluck('kuerzel')->all());
    }

    #[Test]
    public function vorhandene_faecher_zeigen_die_werte_der_datenbank_und_bleiben_unveraendert(): void
    {
        $kat = (int) DB::table('kategorien')->where('code', 'FACH')->value('kategorie_id');
        $bms = (int) DB::table('kategorien')->where('code', 'BMS')->value('kategorie_id');
        DB::table('faecher')->insert([
            ['name' => 'Sport', 'kurzname' => 'SPO', 'kategorie_id' => $kat, 'track_typ' => null, 'skala' => 'note', 'zaehlt' => 1, 'aktiv' => 1],
            ['name' => 'Deutsch', 'kurzname' => 'DEU', 'kategorie_id' => $bms, 'track_typ' => 'BMS', 'skala' => 'stufe', 'zaehlt' => 0, 'aktiv' => 1],
        ]);

        $html = $this->actingAs($this->admin)->get(route('admin.setup', 'professions'))->assertOk()->getContent();
        $zeile = fn (string $schluessel) => explode('</label>', (string) collect(explode('<label', $html))->first(fn ($t) => str_contains($t, 'value="'.$schluessel.'"')))[0];

        $sport = $zeile('BERUF:SP');
        $this->assertStringNotContainsString('Stufe', $sport);
        $this->assertStringNotContainsString('zählt nicht', $sport);
        $deutsch = $zeile('BMS:D');
        $this->assertStringContainsString('Stufe', $deutsch);
        $this->assertStringContainsString('zählt nicht', $deutsch);
        $this->assertStringContainsString('zählt nicht', $zeile('BMS:IDAF'), 'Nicht vorhandene Fächer zeigen die Werte der Vorlage');

        // Die Datenbank bleibt Sache des Admins, auch wenn ein Fach dennoch mitgeschickt wird
        $this->post(route('admin.setup.professions'), ['vorlage' => self::STANDARD, 'berufe' => ['INAP'], 'faecher' => ['BERUF:SP', 'BMS:D'], 'notenbaeume' => '0'])
            ->assertSessionHasNoErrors();
        $sport = DB::table('faecher')->where('name', 'Sport')->first();
        $this->assertSame('note', $sport->skala);
        $this->assertTrue((bool) $sport->zaehlt);
        $this->assertSame(1, DB::table('faecher')->where('name', 'Sport')->count());
    }
}
