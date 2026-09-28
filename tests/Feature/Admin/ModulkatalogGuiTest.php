<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\BasisSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * app/Http/Controllers/Admin/ModulkatalogController.php: Katalog über die Oberfläche einlesen.
 * Die Importregeln selbst prüft Tests\Feature\Console\ModulkatalogImportTest – hier geht es um den
 * Weg Upload → Vorschau → Übernahme und darum, dass eine Vorschau nur einmal wirkt.
 *
 * Alle Nummern, Titel und Berufe sind erfunden: der echte Katalog gehört ICT-Berufsbildung Schweiz
 * und liegt nie im Repo (docs/modulkatalog.md).
 */
class ModulkatalogGuiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        // Die hochgeladene Ernte landet auf `local`; im Test in einer Attrappe, damit nichts liegen bleibt.
        Storage::fake('local');
    }

    private function ernte(): UploadedFile
    {
        $daten = [
            'format' => 1,
            'geerntet_am' => '2026-09-01T08:30:00Z',
            'abschluesse' => [[
                'name' => 'Prüfberuf/in EFZ Musterrichtung (2026)',
                'kennung' => 'abschluss-kennung',
                'module' => [
                    ['nummer' => '987', 'version' => '2', 'titel' => 'Musterobjekte prüfen', 'pflichtgrad' => 'pfl', 'lehrjahr' => 2],
                ],
            ]],
            'module' => [[
                'nummer' => '987',
                'version' => '2',
                'titel' => 'Musterobjekte prüfen',
                'kompetenzfeld' => 'Musterfeld',
                'handlungsziele' => [['nummer' => '1', 'text' => 'Erstes Ziel.']],
            ]],
        ];

        return UploadedFile::fake()->createWithContent('katalog.json', (string) json_encode($daten));
    }

    #[Test]
    public function die_vorschau_schreibt_nichts_und_zeigt_die_zahlen(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.master-data.modules.catalog.read'), ['datei' => $this->ernte()])
            ->assertRedirect(route('admin.master-data.modules.catalog'));

        $this->assertSame(0, DB::table('module')->where('modul_nummer', 'M987')->count());
        $this->assertSame(0, DB::table('lehrberufe')->count());

        $this->actingAs($admin)->get(route('admin.master-data.modules.catalog'))
            ->assertOk()
            ->assertSee(__('Katalog übernehmen'))
            ->assertSee('katalog.json');
    }

    #[Test]
    public function uebernehmen_schreibt_modul_beruf_und_zuordnung(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.master-data.modules.catalog.read'), ['datei' => $this->ernte()]);
        $vorschau = session('modulkatalog.vorschau');

        $this->actingAs($admin)
            ->post(route('admin.master-data.modules.catalog.apply'), ['token' => $vorschau['token']])
            ->assertRedirect(route('admin.master-data.modules.index'))
            ->assertSessionHas('success');

        $modul = DB::table('module')->where('modul_nummer', 'M987')->first();
        $this->assertNotNull($modul);
        $this->assertSame('Musterobjekte prüfen', $modul->titel);
        $this->assertSame(1, DB::table('modul_handlungsziele')->where('modul_id', $modul->modul_id)->count());
        $this->assertSame(1, DB::table('lehrberufe')->count());
        $this->assertSame(1, DB::table('lehrberuf_module')->count());
        // Die Ernte bleibt nicht liegen, sobald sie übernommen ist.
        $this->assertSame([], Storage::disk('local')->files('modulkatalog'));
    }

    #[Test]
    public function dieselbe_vorschau_laesst_sich_nicht_zweimal_uebernehmen(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.master-data.modules.catalog.read'), ['datei' => $this->ernte()]);
        $token = session('modulkatalog.vorschau')['token'];

        $this->actingAs($admin)->post(route('admin.master-data.modules.catalog.apply'), ['token' => $token]);
        $this->actingAs($admin)
            ->post(route('admin.master-data.modules.catalog.apply'), ['token' => $token])
            ->assertSessionHas('error');

        $this->assertSame(1, DB::table('module')->where('modul_nummer', 'M987')->count());
        $this->assertSame(1, DB::table('lehrberuf_module')->count());
    }

    #[Test]
    public function eine_datei_ohne_verwertbare_module_wird_abgewiesen(): void
    {
        $admin = User::factory()->admin()->create();
        $leer = UploadedFile::fake()->createWithContent('leer.json', '{"format":1,"module":[],"abschluesse":[]}');

        $this->actingAs($admin)
            ->post(route('admin.master-data.modules.catalog.read'), ['datei' => $leer])
            ->assertSessionHasErrors('datei');

        $this->assertNull(session('modulkatalog.vorschau'));
    }

    #[Test]
    public function ohne_adminrolle_bleibt_die_seite_verschlossen(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.master-data.modules.catalog'))
            ->assertForbidden();
    }

    #[Test]
    public function der_knopf_zum_weitergeben_erscheint_erst_mit_katalog(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.master-data.modules.catalog'))
            ->assertOk()
            ->assertDontSee(__('Katalog herunterladen'));

        $this->actingAs($admin)->post(route('admin.master-data.modules.catalog.read'), ['datei' => $this->ernte()]);
        $this->actingAs($admin)->post(route('admin.master-data.modules.catalog.apply'), [
            'token' => session('modulkatalog.vorschau')['token'],
        ]);

        $this->actingAs($admin)->get(route('admin.master-data.modules.catalog'))
            ->assertOk()
            ->assertSee(__('Katalog herunterladen'));
    }

    #[Test]
    public function der_download_liefert_die_datei_fuer_die_zweite_instanz(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.master-data.modules.catalog.read'), ['datei' => $this->ernte()]);
        $this->actingAs($admin)->post(route('admin.master-data.modules.catalog.apply'), [
            'token' => session('modulkatalog.vorschau')['token'],
        ]);

        $antwort = $this->actingAs($admin)->get(route('admin.master-data.modules.catalog.export'))->assertOk();
        $antwort->assertHeader('Content-Type', 'application/json');
        $this->assertStringContainsString('modulkatalog-', (string) $antwort->headers->get('Content-Disposition'));

        // Dieselbe Datei, die der Upload oben wieder annimmt: Module samt Zielen und Zuordnung.
        $daten = json_decode($antwort->streamedContent(), true);
        $this->assertSame('M987', $daten['module'][0]['nummer']);
        $this->assertSame('Erstes Ziel.', $daten['module'][0]['handlungsziele'][0]['text']);
        $this->assertSame('Prüfberuf/in EFZ Musterrichtung (2026)', $daten['abschluesse'][0]['name']);
    }

    #[Test]
    public function ohne_adminrolle_gibt_es_den_katalog_nicht_zum_herunterladen(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.master-data.modules.catalog.export'))
            ->assertForbidden();
    }
}
