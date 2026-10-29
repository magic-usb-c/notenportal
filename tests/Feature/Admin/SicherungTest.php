<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Betrieb\Sicherung;
use App\Support\Einstellungen;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class SicherungTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function dumpGelingt(): void
    {
        Process::fake(function (PendingProcess $prozess) {
            foreach ((array) $prozess->command as $teil) {
                if (str_starts_with((string) $teil, '--result-file=')) {
                    file_put_contents(substr((string) $teil, 14), str_repeat("-- Dump\n", 30));
                }
            }

            return Process::result();
        });
    }

    #[Test]
    public function sicherung_enthaelt_datenbank_und_dokumente_und_ist_nur_fuer_admins(): void
    {
        $this->dumpGelingt();
        Storage::disk('local')->put('lernende/7/dokumente/2026/abc.pdf', '%PDF-1.4 Test');
        Storage::disk('local')->put('module/5/unterlagen/skript.pdf', '%PDF-1.4 Modul');
        Storage::disk('local')->put('modulkatalog/katalog.xlsx', 'xlsx');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.operations.backups.store'))->assertSessionHas('success');

        $liste = app(Sicherung::class)->liste();
        $this->assertCount(1, $liste);
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path('sicherungen/'.$liste[0]['name']));
        $this->assertNotFalse($zip->locateName('datenbank.sql'));
        $this->assertNotFalse($zip->locateName('dateien/lernende/7/dokumente/2026/abc.pdf'));
        // Modulunterlagen gehören in die Sicherung, die flüchtige Zwischenablage des Katalogimports (24 h) nicht
        $this->assertNotFalse($zip->locateName('dateien/module/5/unterlagen/skript.pdf'));
        $this->assertFalse($zip->locateName('dateien/modulkatalog/katalog.xlsx'));
        $zip->close();
        Process::assertRan(fn (PendingProcess $p) => ! str_contains(implode(' ', (array) $p->command), 'password'));

        $this->get(route('admin.operations.edit'))->assertOk()->assertSee('Letzte Sicherung');
        $this->get(route('admin.operations.backups.show', $liste[0]['name']))->assertOk()->assertDownload($liste[0]['name']);
        $this->get(route('admin.operations.backups.show', '..%2F.env'))->assertNotFound();

        $this->actingAs(User::factory()->berufsbildner()->create())
            ->get(route('admin.operations.backups.show', $liste[0]['name']))->assertForbidden();

        $this->actingAs($admin)->delete(route('admin.operations.backups.destroy', $liste[0]['name']))->assertSessionHas('success');
        $this->assertSame([], app(Sicherung::class)->liste());
    }

    #[Test]
    public function alte_sicherungen_werden_aufgeraeumt(): void
    {
        $this->dumpGelingt();
        for ($i = 1; $i <= Sicherung::BEHALTEN + 2; $i++) {
            Storage::disk('local')->put(sprintf('sicherungen/notenportal-202601%02d-020000.zip', $i), 'alt');
        }

        app(Sicherung::class)->erstellen();

        $namen = array_column(app(Sicherung::class)->liste(), 'name');
        $this->assertCount(Sicherung::BEHALTEN, $namen);
        $this->assertNotContains('notenportal-20260101-020000.zip', $namen);
    }

    #[Test]
    public function fehlgeschlagener_export_wird_gemeldet(): void
    {
        Process::fake(fn () => Process::result(errorOutput: 'Access denied', exitCode: 2));

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.operations.backups.store'))
            ->assertSessionHas('error');

        $this->assertStringContainsString('Access denied', (string) Einstellungen::get(Sicherung::FEHLER));
        $this->assertSame([], app(Sicherung::class)->liste());
        $this->artisan('notenportal:sicherung')->assertFailed();
    }
}
