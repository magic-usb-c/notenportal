<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\BasisSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModulkatalogMeldungTest extends TestCase
{
    #[Test]
    public function die_uebernahmemeldung_nennt_keine_zahl_mit_falschem_plural(): void
    {
        $this->seed(BasisSeeder::class);
        Storage::fake('local');
        $daten = [
            'format' => 1, 'geerntet_am' => '2026-09-01T08:30:00Z',
            'abschluesse' => [['name' => 'Prüfberuf/in EFZ Musterrichtung (2026)', 'kennung' => 'k', 'module' => [
                ['nummer' => '987', 'version' => '2', 'titel' => 'Musterobjekte prüfen', 'pflichtgrad' => 'pfl', 'lehrjahr' => 2],
            ]]],
            'module' => [['nummer' => '987', 'version' => '2', 'titel' => 'Musterobjekte prüfen', 'kompetenzfeld' => 'F', 'handlungsziele' => [['nummer' => '1', 'text' => 'Z.']]]],
        ];
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.master-data.modules.catalog.read'), [
            'datei' => UploadedFile::fake()->createWithContent('katalog.json', json_encode($daten)),
        ]);
        $token = session('modulkatalog.vorschau')['token'];

        $this->actingAs($admin)->post(route('admin.master-data.modules.catalog.apply'), ['token' => $token])
            ->assertRedirect(route('admin.master-data.modules.index'));

        $meldung = session('success');
        $this->assertMatchesRegularExpression('/^Übernommen – Module: \d+, Lehrberufe: 1, Zuordnungen: \d+\.$/u', $meldung);
        $this->assertStringNotContainsString('1 Lehrberufe', $meldung);
    }

    #[Test]
    public function die_englische_uebernahmemeldung_ist_uebersetzt(): void
    {
        app()->setLocale('en');

        $this->assertSame(
            'Imported – modules: 2, occupations: 1, assignments: 2.',
            __('Übernommen – Module: :module, Lehrberufe: :berufe, Zuordnungen: :zuordnungen.', ['module' => 2, 'berufe' => 1, 'zuordnungen' => 2]),
        );
    }
}
