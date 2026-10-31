<?php

namespace Tests\Feature;

use App\Models\Betreuung;
use App\Models\Feedback;
use App\Models\Note;
use App\Models\NotenbaumPosition;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Notenbaum\BaumVorlage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

class PilotVorbereitenTest extends TestCase
{
    use VerwaltungTestHilfen;

    private function testdaten(): User
    {
        Storage::fake('local');
        $lernender = User::factory()->lernender()->create();
        Betreuung::factory()->create(['lernender_id' => $lernender->lernender->lernender_id]);
        $note = Note::factory()->create(['lernender_id' => $lernender->lernender->lernender_id]);
        DB::table('noten_kommentare')->insert(['note_id' => $note->note_id, 'autor_benutzer_id' => $lernender->benutzer_id, 'kommentar_text' => 'Test']);
        DB::table('noten_gesehen')->insert(['note_id' => $note->note_id, 'viewer_benutzer_id' => $lernender->benutzer_id]);
        DB::table('notification_digest_items')->insert(['user_id' => $lernender->benutzer_id, 'type' => 'grade_created', 'title' => 'Neue Note erfasst', 'url' => '/grades/'.$note->note_id, 'created_at' => now(), 'sent_at' => null]);
        DB::table('notification_marks')->insert(['user_id' => $lernender->benutzer_id, 'type' => 'inactivity', 'subject_key' => 'inaktiv:1:0', 'created_at' => now()]);
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

        foreach (['noten', 'noten_kommentare', 'noten_gesehen', 'feedback', 'notification_digest_items', 'notification_marks'] as $tabelle) {
            $this->assertSame(0, DB::table($tabelle)->count(), $tabelle);
        }
        Storage::disk('local')->assertMissing('feedback/2026/test.jpg');
        $this->assertSame(1, DB::table('betreuungen')->count());
        $this->assertNotNull($lernender->lernender->fresh());
        $this->assertTrue($lernender->fresh()->passwort_wechsel_noetig);
        $this->assertTrue($admin->fresh()->passwort_wechsel_noetig);
    }

    #[Test]
    public function abschlussnoten_im_notenbaum_werden_in_vorschau_gezaehlt_und_ausgefuehrt_geloescht(): void
    {
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'NPT', 'name' => 'Testberuf EFZ']);
        DB::table('semester')->insert(['bezeichnung' => 'T-1', 'start_datum' => '2024-08-01', 'end_datum' => now()->addYears(3)->toDateString(), 'sortierung' => 1]);
        Konfiguration::vergessen();
        $lernender = $this->neuerLernender([], ['lehrberuf_id' => $lehrberuf]);
        app(BaumVorlage::class)->importieren(BaumVorlage::laden('informatiker-efz-bivo2020'), $lehrberuf);
        $knoten = (int) DB::table('notenbaum_knoten')->where('code', 'ipa')->value('knoten_id');
        NotenbaumPosition::create(['lernender_id' => $lernender->lernender_id, 'knoten_id' => $knoten, 'note_wert' => 5.5, 'erfasst_von_benutzer_id' => $lernender->benutzer->benutzer_id]);

        Artisan::call('notenportal:pilot-vorbereiten');
        $this->assertMatchesRegularExpression('/notenbaum_positionen\s*\|\s*1\s*\|/', Artisan::output());
        $this->assertSame(1, DB::table('notenbaum_positionen')->count());

        $this->artisan('notenportal:pilot-vorbereiten', ['--ausfuehren' => true])->assertSuccessful();

        $this->assertSame(0, DB::table('notenbaum_positionen')->count());
        $this->assertGreaterThan(0, DB::table('notenbaum_knoten')->count());
    }
}
