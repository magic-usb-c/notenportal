<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Auswertung\Notenbaum\BaumVorlage;
use App\Services\Stammdaten\StammdatenVorlage;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Einrichtungsschritt «Lehrberufe & Fächer» mit Stammdaten-Vorlagen (resources/vorlagen/stammdaten):
 * keine Listen im Code, Bündner Realität als Standard, echte Pflege verlinkt (LAGE.md Lücke 8).
 */
class EinrichtungVorlagenTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $this->admin = User::factory()->admin()->create();
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
}
