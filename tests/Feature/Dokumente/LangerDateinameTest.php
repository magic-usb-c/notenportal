<?php

declare(strict_types=1);

namespace Tests\Feature\Dokumente;

use App\Models\Dokument;
use App\Models\ModulDokument;
use App\Models\User;
use Database\Seeders\BasisSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Ohne Titel dient der Dateiname als Titel. Die Spalte fasst 150 Zeichen, ein Dateiname bis zu 255:
 * der Titel wird gekürzt, und scheitert das Anlegen trotzdem, bleibt keine verwaiste Datei auf der Platte.
 */
class LangerDateinameTest extends TestCase
{
    private int $modulId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        Storage::fake('local');
        $this->modulId = (int) DB::table('module')->insertGetId(['modul_nummer' => 'M780', 'titel' => 'Unterlagen']);
    }

    private function langeDatei(): UploadedFile
    {
        return UploadedFile::fake()->create(str_repeat('ä', 196).'.pdf', 20, 'application/pdf');
    }

    #[Test]
    public function dokument_mit_langem_dateinamen_ohne_titel_wird_mit_gekuerztem_titel_gespeichert(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->post(route('learner.documents.store'), [
            'datei' => $this->langeDatei(), 'art' => 'zeugnis',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $dokument = Dokument::query()->firstOrFail();
        $this->assertSame(150, mb_strlen($dokument->titel));
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    #[Test]
    public function modulunterlage_mit_langem_dateinamen_ohne_titel_wird_mit_gekuerztem_titel_gespeichert(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->post(route('modules.documents.store', $this->modulId), [
            'datei' => $this->langeDatei(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $unterlage = ModulDokument::query()->firstOrFail();
        $this->assertSame(150, mb_strlen($unterlage->titel));
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    #[Test]
    public function scheitert_das_anlegen_eines_dokuments_bleibt_keine_datei_zurueck(): void
    {
        $user = User::factory()->lernender()->create();
        Dokument::creating(static fn () => throw new RuntimeException('Insert scheitert'));

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)->post(route('learner.documents.store'), [
                'datei' => $this->langeDatei(), 'art' => 'zeugnis',
            ]);
            $this->fail('Ausnahme erwartet');
        } catch (RuntimeException $e) {
            $this->assertSame('Insert scheitert', $e->getMessage());
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    #[Test]
    public function scheitert_das_anlegen_einer_modulunterlage_bleibt_keine_datei_zurueck(): void
    {
        $user = User::factory()->lernender()->create();
        ModulDokument::creating(static fn () => throw new RuntimeException('Insert scheitert'));

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)->post(route('modules.documents.store', $this->modulId), [
                'datei' => $this->langeDatei(),
            ]);
            $this->fail('Ausnahme erwartet');
        } catch (RuntimeException $e) {
            $this->assertSame('Insert scheitert', $e->getMessage());
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
