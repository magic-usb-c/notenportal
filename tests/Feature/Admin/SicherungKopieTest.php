<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Betrieb\Sicherung;
use App\Services\Betrieb\SicherungKopie;
use App\Support\Einstellungen;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class SicherungKopieTest extends TestCase
{
    private string $ziel;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->ziel = sys_get_temp_dir().'/np-kopie-'.uniqid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->ziel);
        parent::tearDown();
    }

    #[Test]
    public function spiegelt_nur_sicherungen_in_einen_ordner(): void
    {
        Storage::disk('local')->put('sicherungen/notenportal-20260910-023000.zip', 'a');
        Storage::disk('local')->put('sicherungen/notenportal-20260911-023000.zip', 'b');
        File::ensureDirectoryExists($this->ziel);
        file_put_contents($this->ziel.'/notenportal-20260801-023000.zip', 'alt');
        file_put_contents($this->ziel.'/fremd.txt', 'bleibt');

        $kopie = app(SicherungKopie::class);
        $kopie->speichern([SicherungKopie::ZIEL => 'ordner', SicherungKopie::PFAD => $this->ziel]);
        $kopie->testen();
        $kopie->kopieren();

        $this->assertFileExists($this->ziel.'/notenportal-20260911-023000.zip');
        $this->assertFileDoesNotExist($this->ziel.'/notenportal-20260801-023000.zip', 'alte Sicherung am Ziel wird gespiegelt entfernt');
        $this->assertFileExists($this->ziel.'/fremd.txt', 'fremde Dateien am Ziel bleiben');
        $this->assertNotNull($kopie->letzte());
        $this->assertNull($kopie->fehler());
    }

    #[Test]
    public function ssh_aufruf_ohne_shell_und_mit_eigenem_schluessel(): void
    {
        $kopie = app(SicherungKopie::class);
        $kopie->speichern([SicherungKopie::ZIEL => 'ssh', SicherungKopie::HOST => 'backup.example.org', SicherungKopie::PORT => '2222',
            SicherungKopie::BENUTZER => 'np', SicherungKopie::PFAD => '/srv/sicherungen/notenportal']);

        $befehl = $kopie->befehl();

        $this->assertSame('rsync', $befehl[0]);
        $this->assertContains('--include=notenportal-*.zip', $befehl);
        $this->assertSame('np@backup.example.org:/srv/sicherungen/notenportal/', end($befehl));
        $ssh = $befehl[array_search('-e', $befehl, true) + 1];
        $this->assertStringContainsString('-p 2222', $ssh);
        $this->assertStringContainsString('BatchMode=yes', $ssh);
        $this->assertStringContainsString('id_ed25519', $ssh);
    }

    #[Test]
    public function fehler_wird_festgehalten_und_ungueltige_ziele_abgelehnt(): void
    {
        $kopie = app(SicherungKopie::class);
        $kopie->speichern([SicherungKopie::ZIEL => 'ordner', SicherungKopie::PFAD => '/proc/nicht-beschreibbar']);

        try {
            $kopie->kopieren();
            $this->fail('Kopie in /proc hätte scheitern müssen');
        } catch (RuntimeException $e) {
            $this->assertStringStartsWith('Kopie fehlgeschlagen', $e->getMessage());
        }
        $this->assertNotNull(Einstellungen::get(SicherungKopie::FEHLER));

        $this->expectException(RuntimeException::class);
        $kopie->speichern([SicherungKopie::ZIEL => 'ordner', SicherungKopie::PFAD => base_path('storage/kopie')]);
    }

    #[Test]
    public function admin_richtet_kopie_auf_betrieb_ein_und_sicherungslauf_kopiert(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->put(route('admin.betrieb.kopie.update'), [SicherungKopie::ZIEL => 'ordner', SicherungKopie::PFAD => base_path('x')])
            ->assertSessionHasErrors(SicherungKopie::PFAD);
        $this->actingAs($admin)->put(route('admin.betrieb.kopie.update'), [SicherungKopie::ZIEL => 'ordner', SicherungKopie::PFAD => $this->ziel])
            ->assertSessionHas('success');
        $this->actingAs($admin)->get(route('admin.betrieb.edit'))->assertOk()->assertSee('Kopie ausser Haus')->assertSee('Jetzt kopieren');
        $this->actingAs($admin)->post(route('admin.betrieb.kopie.run'), ['aktion' => 'testen'])->assertSessionHas('success');
        $this->actingAs(User::factory()->lernender()->create())->post(route('admin.betrieb.kopie.run'))->assertForbidden();

        // Täglicher Lauf: Sicherung erstellen, danach spiegeln
        Process::fake(function (PendingProcess $prozess) {
            foreach ((array) $prozess->command as $teil) {
                if (str_starts_with((string) $teil, '--result-file=')) {
                    file_put_contents(substr((string) $teil, 14), str_repeat("-- Dump\n", 30));
                }
            }

            return Process::result();
        });
        $this->artisan('notenportal:sicherung')->assertSuccessful();
        $name = app(Sicherung::class)->liste()[0]['name'];
        $this->assertFileExists($this->ziel.'/'.$name);
    }

    #[Test]
    public function erzeugt_ssh_schluessel_einmal(): void
    {
        $kopie = app(SicherungKopie::class);
        $a = $kopie->oeffentlicherSchluessel();

        $this->assertStringStartsWith('ssh-ed25519 ', $a);
        $this->assertSame($a, $kopie->oeffentlicherSchluessel());
    }
}
