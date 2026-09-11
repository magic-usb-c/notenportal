<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Services\Betrieb\SicherungKopie;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** app/Console/Commands/SicherungKopieren.php: läuft nach der täglichen Sicherung, spiegelt ausser Haus. */
class SicherungKopierenCommandTest extends TestCase
{
    private string $ziel;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->ziel = sys_get_temp_dir().'/np-kopie-cmd-'.uniqid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->ziel);
        parent::tearDown();
    }

    #[Test]
    public function ohne_eingerichtetes_ziel_meldet_und_ist_erfolgreich(): void
    {
        $this->artisan('notenportal:sicherung-kopieren')
            ->expectsOutputToContain('Keine Kopie ausser Haus eingerichtet (Admin → Betrieb).')
            ->assertSuccessful();
    }

    #[Test]
    public function mit_ordner_ziel_werden_die_sicherungen_gespiegelt(): void
    {
        Storage::disk('local')->put('sicherungen/notenportal-20260910-023000.zip', 'a');
        app(SicherungKopie::class)->speichern([SicherungKopie::ZIEL => 'ordner', SicherungKopie::PFAD => $this->ziel]);

        $this->artisan('notenportal:sicherung-kopieren')
            ->expectsOutputToContain('Sicherungen kopiert.')
            ->assertSuccessful();

        $this->assertFileExists($this->ziel.'/notenportal-20260910-023000.zip');
    }

    #[Test]
    public function fehler_beim_kopieren_meldet_und_scheitert(): void
    {
        app(SicherungKopie::class)->speichern([SicherungKopie::ZIEL => 'ordner', SicherungKopie::PFAD => '/proc/nicht-beschreibbar']);

        $this->artisan('notenportal:sicherung-kopieren')
            ->expectsOutputToContain('Kopie fehlgeschlagen')
            ->assertFailed();
    }
}
