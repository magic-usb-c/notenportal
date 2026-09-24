<?php

namespace Tests\Feature;

use App\Models\Betreuung;
use App\Models\Feedback;
use App\Models\Note;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PilotVorbereitenTest extends TestCase
{
    private function testdaten(): User
    {
        Storage::fake('local');
        $lernender = User::factory()->lernender()->create();
        Betreuung::factory()->create(['lernender_id' => $lernender->lernender->lernender_id]);
        $note = Note::factory()->create(['lernender_id' => $lernender->lernender->lernender_id]);
        DB::table('noten_kommentare')->insert(['note_id' => $note->note_id, 'autor_benutzer_id' => $lernender->benutzer_id, 'kommentar_text' => 'Test']);
        DB::table('noten_gesehen')->insert(['note_id' => $note->note_id, 'viewer_benutzer_id' => $lernender->benutzer_id]);
        Storage::disk('local')->put('feedback/2026/test.jpg', 'bild');
        Feedback::factory()->create(['benutzer_id' => $lernender->benutzer_id, 'screenshot_pfad' => 'feedback/2026/test.jpg']);

        return $lernender;
    }

    #[Test]
    public function vorschau_aendert_nichts(): void
    {
        $this->testdaten();

        $this->artisan('notenportal:pilot-vorbereiten')->assertSuccessful();

        $this->assertSame(1, DB::table('noten')->count());
        $this->assertSame(0, DB::table('benutzer')->where('passwort_wechsel_noetig', true)->count());
    }

    #[Test]
    public function ausfuehren_loescht_testnoten_und_behaelt_konten(): void
    {
        $lernender = $this->testdaten();
        $admin = User::factory()->admin()->create();

        $this->artisan('notenportal:pilot-vorbereiten', ['--ausfuehren' => true])->assertSuccessful();

        foreach (['noten', 'noten_kommentare', 'noten_gesehen', 'feedback'] as $tabelle) {
            $this->assertSame(0, DB::table($tabelle)->count(), $tabelle);
        }
        Storage::disk('local')->assertMissing('feedback/2026/test.jpg');
        $this->assertSame(1, DB::table('betreuungen')->count());
        $this->assertNotNull($lernender->lernender->fresh());
        $this->assertTrue($lernender->fresh()->passwort_wechsel_noetig);
        $this->assertTrue($admin->fresh()->passwort_wechsel_noetig);
    }
}
