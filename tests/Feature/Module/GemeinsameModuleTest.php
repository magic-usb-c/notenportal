<?php

declare(strict_types=1);

namespace Tests\Feature\Module;

use App\Models\Modul;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use Database\Seeders\BasisSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Module sind gemeinsame Stammdaten: ein Datensatz je Modul, den alle sehen und ergänzen dürfen.
 *
 * Geprüft wird genau das, was den Unterschied zu «je Person eine Kopie» ausmacht: was eine Person
 * anlegt oder hochlädt, findet die nächste unverändert vor – und niemand bekommt dabei eine zweite
 * Zeile in der Tabelle. Dazu die Grenzen: die Modulnummer bleibt fest, fremde Unterlagen bleiben
 * stehen, und eigene Noten sind erst nach dem bewussten Hinzufügen möglich.
 */
class GemeinsameModuleTest extends TestCase
{
    private int $lehrberuf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        Storage::fake('local');
        $this->lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        DB::table('semester')->insert(['bezeichnung' => '25/26-2', 'start_datum' => '2026-02-01',
            'end_datum' => '2026-07-31', 'sortierung' => 10]);
        Konfiguration::vergessen();
    }

    private function lernender(): User
    {
        return User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create();
    }

    #[Test]
    public function jede_rolle_darf_ein_fehlendes_modul_anlegen_und_alle_sehen_es(): void
    {
        $this->actingAs($this->lernender())
            ->post(route('modules.store'), [
                'modul_nummer' => 'm777',
                'titel' => 'Erfundenes Testmodul',
                'handlungsziele' => "1 Erstes Ziel\n2.1 Zweites Ziel",
            ])->assertRedirect();

        $modul = DB::table('module')->where('modul_nummer', 'M777')->first();
        $this->assertNotNull($modul, 'Das Modul wurde nicht angelegt.');
        $this->assertSame('benutzer', $modul->quelle);
        $this->assertSame(['1', '2.1'], DB::table('modul_handlungsziele')->where('modul_id', $modul->modul_id)
            ->orderBy('sortierung')->pluck('nummer')->all());

        // Eine zweite Person sieht denselben Datensatz – es entsteht keine Kopie.
        $this->actingAs($this->lernender())
            ->get(route('modules.show', $modul->modul_id))->assertOk()->assertSee('Erfundenes Testmodul');
        $this->assertSame(1, DB::table('module')->where('modul_nummer', 'M777')->count());
    }

    #[Test]
    public function hochgeladene_unterlage_steht_allen_zur_verfuegung_fremde_duerfen_sie_nicht_loeschen(): void
    {
        $modulId = DB::table('module')->insertGetId(['modul_nummer' => 'M778', 'titel' => 'Unterlagen']);
        $erste = $this->lernender();

        $this->actingAs($erste)->post(route('modules.documents.store', $modulId), [
            'datei' => UploadedFile::fake()->create('modulbeschreibung.pdf', 12, 'application/pdf'),
            'titel' => 'Modulbeschreibung',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $dokument = DB::table('modul_dokumente')->where('modul_id', $modulId)->first();
        $this->assertNotNull($dokument);
        Storage::disk('local')->assertExists($dokument->pfad);

        $zweite = $this->lernender();
        $this->actingAs($zweite)->get(route('modules.documents.show', [$modulId, $dokument->modul_dokument_id]))->assertOk();
        $this->actingAs($zweite)->delete(route('modules.documents.destroy', [$modulId, $dokument->modul_dokument_id]))->assertForbidden();
        $this->assertDatabaseCount('modul_dokumente', 1);

        $this->actingAs($erste)->delete(route('modules.documents.destroy', [$modulId, $dokument->modul_dokument_id]))->assertRedirect();
        $this->assertDatabaseCount('modul_dokumente', 0);
        Storage::disk('local')->assertMissing($dokument->pfad);
    }

    #[Test]
    public function die_modulnummer_bleibt_fest_der_rest_ist_ergaenzbar(): void
    {
        $modulId = DB::table('module')->insertGetId(['modul_nummer' => 'M779', 'titel' => 'Alt']);

        $this->actingAs($this->lernender())->put(route('modules.update', $modulId), [
            'modul_nummer' => 'M999',
            'titel' => 'Neu',
            'link' => 'https://example.org/modul',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $modul = DB::table('module')->where('modul_id', $modulId)->first();
        $this->assertSame('M779', $modul->modul_nummer, 'Die Nummer darf ein Lernender nicht ändern.');
        $this->assertSame('Neu', $modul->titel);
        $this->assertSame('https://example.org/modul', $modul->link);
    }

    #[Test]
    public function eigene_noten_sind_erst_nach_dem_hinzufuegen_moeglich(): void
    {
        $modulId = DB::table('module')->insertGetId(['modul_nummer' => 'M780', 'titel' => 'Fremdes Modul']);
        $user = $this->lernender();
        $note = ['typ' => 'modul', 'modul_id' => $modulId, 'pruefungsdatum' => '2026-03-02',
            'note_wert' => '5.0', 'gewichtung_prozent' => 100];

        $this->actingAs($user)->post(route('learner.grades.store'), $note)->assertSessionHasErrors('modul_id');

        $this->actingAs($user)->post(route('modules.enroll', $modulId))->assertRedirect();
        $this->actingAs($user)->post(route('learner.grades.store'), $note)->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('modul_belegungen')->where('modul_id', $modulId)->count());
    }

    #[Test]
    public function ein_veralteter_formularstand_ueberschreibt_fremde_ziele_nicht(): void
    {
        $modulId = DB::table('module')->insertGetId(['modul_nummer' => 'M782', 'titel' => 'Gemeinsam']);
        $stand = (string) Modul::query()->findOrFail($modulId)->aktualisiert_am->getTimestamp();

        // Zweite Person ergänzt ein Ziel, während die erste ihr Formular noch offen hat.
        $this->actingAs($this->lernender())->put(route('modules.update', $modulId), [
            'modul_nummer' => 'M782', 'titel' => 'Gemeinsam', 'handlungsziele' => '1 Ziel der zweiten Person',
        ])->assertSessionHasNoErrors();
        DB::table('module')->where('modul_id', $modulId)->update(['aktualisiert_am' => '2026-09-14 09:00:00']);

        $this->actingAs($this->lernender())->put(route('modules.update', $modulId), [
            'modul_nummer' => 'M782', 'titel' => 'Gemeinsam', 'handlungsziele' => '1 Ziel der ersten Person',
            'stand' => $stand,
        ])->assertSessionHasErrors('handlungsziele');

        $this->assertSame(['Ziel der zweiten Person'],
            DB::table('modul_handlungsziele')->where('modul_id', $modulId)->pluck('text')->all());
    }

    #[Test]
    public function der_verweis_nimmt_die_katalogversion_des_eigenen_lehrberufs(): void
    {
        // Das Modul selbst führt V5 (der Katalog kennt nur eine Zeile je Nummer), der Lehrberuf
        // dieses Lernenden steht aber noch auf V4. Der Verweis muss seiner Version folgen, sonst
        // landet er auf der Modulseite des neueren Jahrgangs.
        $modulId = DB::table('module')->insertGetId([
            'modul_nummer' => 'M117', 'titel' => 'Zwei Jahrgänge', 'version' => '5',
        ]);
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $this->lehrberuf, 'modul_id' => $modulId, 'version' => '4',
        ]);

        $this->actingAs($this->lernender())
            ->get(route('modules.show', $modulId))
            ->assertOk()
            ->assertSee('modulbaukasten.ch/module/117/4/de-DE', false)
            ->assertDontSee('modulbaukasten.ch/module/117/5/de-DE', false);
    }

    #[Test]
    public function ohne_anmeldung_kein_zugriff(): void
    {
        $this->get(route('modules.index'))->assertRedirect(route('login'));
        $this->post(route('modules.store'), ['modul_nummer' => 'M781', 'titel' => 'X'])->assertRedirect(route('login'));
    }
}
