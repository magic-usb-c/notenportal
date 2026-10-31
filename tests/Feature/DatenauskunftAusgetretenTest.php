<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Berufsbildner;
use App\Models\Betreuung;
use App\Models\Lernender;
use App\Models\Note;
use App\Models\User;
use App\Support\Datenauskunft;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

/** Datenauskunft für ausgetretene Personen: soft-gelöschte Berufsbildner- und Lernenden-Datensätze gehören weiterhin zur Auskunft. */
class DatenauskunftAusgetretenTest extends TestCase
{
    /** @var list<string> */
    private array $aufraeumen = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        foreach ($this->aufraeumen as $pfad) {
            @unlink($pfad);
        }
        parent::tearDown();
    }

    private function oeffnen(User $user): ZipArchive
    {
        $pfad = app(Datenauskunft::class)->erzeugen($user);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($pfad) === true);
        $this->aufraeumen[] = $pfad;

        return $zip;
    }

    #[Test]
    public function auskunft_eines_deaktivierten_berufsbildners_enthaelt_seine_betreuungen(): void
    {
        $admin = User::factory()->admin()->create();
        $bbUser = User::factory()->berufsbildner()->create();
        $bb = Berufsbildner::where('benutzer_id', $bbUser->benutzer_id)->firstOrFail();
        $lernender = User::factory()->lernender()->create(['nachname' => 'Zehnder', 'vorname' => 'Mia']);
        Betreuung::create([
            'lernender_id' => $lernender->lernender->lernender_id,
            'berufsbildner_id' => $bb->berufsbildner_id,
            'gueltig_von' => '2025-01-01',
            'gueltig_bis' => '2025-12-31',
        ]);

        $this->actingAs($admin)->post(route('admin.users.toggle-active', $bbUser->benutzer_id), ['aktiv' => 0])->assertRedirect();
        $this->assertTrue(Berufsbildner::withTrashed()->findOrFail($bb->berufsbildner_id)->trashed());

        $zip = $this->oeffnen(User::withTrashed()->findOrFail($bbUser->benutzer_id));

        $csv = (string) $zip->getFromName('betreuungen.csv');
        $this->assertStringContainsString('Zehnder Mia', $csv);
        $this->assertStringContainsString('01.01.2025', $csv);
        $zip->close();
    }

    #[Test]
    public function betreuungsliste_nennt_auch_einen_soft_geloeschten_lernenden(): void
    {
        $bbUser = User::factory()->berufsbildner()->create();
        $bb = Berufsbildner::where('benutzer_id', $bbUser->benutzer_id)->firstOrFail();
        $lernender = User::factory()->lernender()->create(['nachname' => 'Zehnder', 'vorname' => 'Mia']);
        Betreuung::create([
            'lernender_id' => $lernender->lernender->lernender_id,
            'berufsbildner_id' => $bb->berufsbildner_id,
            'gueltig_von' => '2025-01-01',
        ]);
        $lernender->lernender->delete();

        $zip = $this->oeffnen($bbUser);

        $this->assertStringContainsString('Zehnder Mia', (string) $zip->getFromName('betreuungen.csv'));
        $zip->close();
    }

    #[Test]
    public function auskunft_eines_soft_geloeschten_lernenden_enthaelt_seine_noten(): void
    {
        $user = User::factory()->lernender()->create();
        Note::factory()->create([
            'lernender_id' => $user->lernender->lernender_id,
            'titel' => 'Eigene Note',
            'note_wert' => 5.0,
        ]);
        Lernender::findOrFail($user->lernender->lernender_id)->delete();

        $ausgetreten = User::findOrFail($user->benutzer_id);
        $this->assertNull($ausgetreten->lernender);

        $zip = $this->oeffnen($ausgetreten);

        $this->assertNotFalse($zip->locateName('noten.json'));
        $this->assertStringContainsString('Eigene Note', (string) $zip->getFromName('noten.csv'));
        $this->assertFalse($zip->locateName('betreuungen.csv'));
        $zip->close();
    }
}
