<?php

declare(strict_types=1);

namespace Tests\Feature\Benachrichtigungen;

use App\Models\Betreuung;
use App\Models\Fach;
use App\Models\Feedback;
use App\Models\Lernender;
use App\Models\Note;
use App\Models\Semester;
use App\Models\User;
use App\Notifications\PortalMail;
use App\Services\Auswertung\Konfiguration;
use App\Services\Notifications\NotificationCatalog;
use Database\Seeders\BasisSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

/**
 * Auslöser der ereignisbasierten Benachrichtigungen: Kommentar, Note gesehen/korrigiert/erfasst,
 * Schnitt unter Grenzwert, neue Betreuung, Feedback, fehlgeschlagene Sicherung.
 * Testadressen enden bewusst auf «.ch», nicht auf «.example/.com/.org/.net» (Notifier::UNZUSTELLBAR).
 */
class AusloeserTest extends TestCase
{
    use VerwaltungTestHilfen;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    #[Test]
    public function kommentar_von_lernender_geht_an_aktive_betreuer_nicht_an_autor_oder_ehemalige(): void
    {
        Notification::fake();
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        $lernender = $lernenderUser->lernender;
        $note = Note::factory()->create(['lernender_id' => $lernender->lernender_id]);

        $aktiverBb = User::factory()->berufsbildner()->create(['email' => 'aktiv@firma.ch']);
        $this->betreue($aktiverBb, $lernender);

        $ehemaligerBb = User::factory()->berufsbildner()->create(['email' => 'ehemalig@firma.ch']);
        Betreuung::factory()->abgelaufen()->create([
            'berufsbildner_id' => $ehemaligerBb->berufsbildner->berufsbildner_id,
            'lernender_id' => $lernender->lernender_id,
        ]);

        $this->actingAs($lernenderUser)
            ->post(route('comments.store', $note->note_id), ['kommentar_text' => 'Bitte anschauen, danke.'])
            ->assertSessionHas('success');

        Notification::assertSentTo($aktiverBb, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::COMMENT_ADDED);
        Notification::assertNotSentTo($lernenderUser, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::COMMENT_ADDED);
        Notification::assertNotSentTo($ehemaligerBb, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::COMMENT_ADDED);
    }

    #[Test]
    public function kommentar_von_berufsbildner_geht_an_lernenden_nicht_an_autor(): void
    {
        Notification::fake();
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        $lernender = $lernenderUser->lernender;
        $note = Note::factory()->create(['lernender_id' => $lernender->lernender_id]);
        $bb = User::factory()->berufsbildner()->create(['email' => 'bb@firma.ch']);
        $this->betreue($bb, $lernender);

        $this->actingAs($bb)
            ->post(route('comments.store', $note->note_id), ['kommentar_text' => 'Gut gemacht!'])
            ->assertSessionHas('success');

        Notification::assertSentTo($lernenderUser, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::COMMENT_ADDED);
        Notification::assertNotSentTo($bb, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::COMMENT_ADDED);
    }

    #[Test]
    public function note_einzeln_gesehen_erzeugt_tageszusammenfassung_beim_lernenden(): void
    {
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        $lernender = $lernenderUser->lernender;
        $note = Note::factory()->create(['lernender_id' => $lernender->lernender_id]);
        $bb = User::factory()->berufsbildner()->create(['email' => 'bb@firma.ch']);
        $this->betreue($bb, $lernender);

        $this->actingAs($bb)
            ->post(route('trainer.learners.grades.seen', [$lernender->lernender_id, $note->note_id]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('notification_digest_items', [
            'user_id' => $lernenderUser->benutzer_id,
            'type' => NotificationCatalog::GRADE_SEEN,
        ]);
    }

    #[Test]
    public function alle_gesehen_ohne_treffer_erzeugt_keine_meldung(): void
    {
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        $lernender = $lernenderUser->lernender;
        $bb = User::factory()->berufsbildner()->create(['email' => 'bb@firma.ch']);
        $this->betreue($bb, $lernender);

        $this->actingAs($bb)
            ->post(route('trainer.learners.grades.seen_all', $lernender->lernender_id))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('notification_digest_items', 0);
    }

    #[Test]
    public function note_korrektur_meldet_lernenden_mit_alt_und_neu(): void
    {
        Notification::fake();
        [$lernenderUser, $lernender, $bb, $note] = $this->korrekturSetup(4.5);

        $this->actingAs($bb)
            ->put(route('trainer.learners.grades.update', [$lernender->lernender_id, $note->note_id]), [
                'kategorie_id' => $note->kategorie_id,
                'typ' => 'fach',
                'fach_id' => $note->fach_id,
                'titel' => 'Korrigiert',
                'pruefungsdatum' => now()->toDateString(),
                'note_wert' => 5.25,
                'gewichtung_prozent' => 100,
            ])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($lernenderUser, PortalMail::class, function (PortalMail $m) {
            return $m->type === NotificationCatalog::GRADE_CORRECTED
                && $m->content->facts['Bisherige Note'] === '4.5'
                && $m->content->facts['Neue Note'] === '5.25';
        });
    }

    #[Test]
    public function unveraenderter_notenwert_meldet_keine_korrektur(): void
    {
        Notification::fake();
        [$lernenderUser, $lernender, $bb, $note] = $this->korrekturSetup(4.5);

        $this->actingAs($bb)
            ->put(route('trainer.learners.grades.update', [$lernender->lernender_id, $note->note_id]), [
                'kategorie_id' => $note->kategorie_id,
                'typ' => 'fach',
                'fach_id' => $note->fach_id,
                'titel' => 'Nur die Notiz geändert',
                'pruefungsdatum' => now()->toDateString(),
                'note_wert' => 4.5,
                'gewichtung_prozent' => 100,
            ])
            ->assertSessionHasNoErrors();

        Notification::assertNotSentTo($lernenderUser, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::GRADE_CORRECTED);
    }

    /** @return array{0: User, 1: Lernender, 2: User, 3: Note} */
    private function korrekturSetup(float $noteWert): array
    {
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        $lernender = $lernenderUser->lernender;
        $semester = Semester::factory()->create();
        $this->bmsTrack($lernender, $semester->semester_id);
        $note = Note::factory()->create([
            'lernender_id' => $lernender->lernender_id,
            'semester_id' => $semester->semester_id,
            'fach_id' => Fach::factory()->create(['track_typ' => 'BMS'])->fach_id,
            'note_wert' => $noteWert,
        ]);
        $bb = User::factory()->berufsbildner()->create(['email' => 'bb@firma.ch']);
        $this->betreue($bb, $lernender);

        return [$lernenderUser, $lernender, $bb, $note];
    }

    #[Test]
    public function neue_note_meldet_aktive_betreuer_als_tageszusammenfassung_nicht_ehemalige(): void
    {
        [$lernenderUser, $lernender, $fach, $bb, $ehemaligerBb] = $this->eigeneNoteSetup();

        $this->actingAs($lernenderUser)
            ->post(route('learner.grades.store'), [
                'typ' => 'fach',
                'fach_id' => $fach->fach_id,
                'titel' => 'Test',
                'pruefungsdatum' => now()->toDateString(),
                'note_wert' => 5.0,
                'gewichtung_prozent' => 100,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('notification_digest_items', [
            'user_id' => $bb->benutzer_id,
            'type' => NotificationCatalog::GRADE_ADDED,
        ]);
        $this->assertDatabaseMissing('notification_digest_items', [
            'user_id' => $ehemaligerBb->benutzer_id,
            'type' => NotificationCatalog::GRADE_ADDED,
        ]);
    }

    #[Test]
    public function neue_note_unter_grenze_meldet_lernenden_und_betreuer(): void
    {
        Notification::fake();
        [$lernenderUser, $lernender, $fach, $bb] = $this->eigeneNoteSetup();

        $this->actingAs($lernenderUser)
            ->post(route('learner.grades.store'), [
                'typ' => 'fach',
                'fach_id' => $fach->fach_id,
                'titel' => 'Test',
                'pruefungsdatum' => now()->toDateString(),
                'note_wert' => 3.5,
                'gewichtung_prozent' => 100,
            ])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($lernenderUser, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::BELOW_THRESHOLD);
        Notification::assertSentTo($bb, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::BELOW_THRESHOLD);
    }

    #[Test]
    public function note_ueber_grenze_meldet_kein_unterschreiten(): void
    {
        Notification::fake();
        [$lernenderUser, $lernender, $fach] = $this->eigeneNoteSetup();

        $this->actingAs($lernenderUser)
            ->post(route('learner.grades.store'), [
                'typ' => 'fach',
                'fach_id' => $fach->fach_id,
                'titel' => 'Test',
                'pruefungsdatum' => now()->toDateString(),
                'note_wert' => 5.0,
                'gewichtung_prozent' => 100,
            ])
            ->assertSessionHasNoErrors();

        Notification::assertNotSentTo($lernenderUser, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::BELOW_THRESHOLD);
    }

    /** @return array{0: User, 1: Lernender, 2: Fach, 3: User, 4: User} Lernender, Fach, aktive und ehemalige Betreuung */
    private function eigeneNoteSetup(): array
    {
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        $lernender = $lernenderUser->lernender;
        $semester = Semester::factory()->create();
        $this->bmsTrack($lernender, $semester->semester_id);
        $fach = Fach::factory()->create(['track_typ' => 'BMS']);

        $bb = User::factory()->berufsbildner()->create(['email' => 'bb@firma.ch']);
        $this->betreue($bb, $lernender);

        $ehemaligerBb = User::factory()->berufsbildner()->create(['email' => 'ehemalig@firma.ch']);
        Betreuung::factory()->abgelaufen()->create([
            'berufsbildner_id' => $ehemaligerBb->berufsbildner->berufsbildner_id,
            'lernender_id' => $lernender->lernender_id,
        ]);

        return [$lernenderUser, $lernender, $fach, $bb, $ehemaligerBb];
    }

    #[Test]
    public function import_erzeugt_sammelmeldung_fuer_aktive_betreuer(): void
    {
        $this->seed(BasisSeeder::class);
        $kategorie = DB::table('kategorien')->pluck('kategorie_id', 'code');
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $fach = DB::table('faecher')->insertGetId(['kategorie_id' => $kategorie['ABU'], 'name' => 'Sprache', 'kurzname' => 'SK']);
        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $lehrberuf, 'fach_id' => $fach]);
        DB::table('semester')->insert(['bezeichnung' => '25/26-2', 'start_datum' => '2026-02-01', 'end_datum' => '2026-07-31', 'sortierung' => 10]);
        Konfiguration::vergessen();

        $lernenderUser = User::factory()
            ->lernender(['lehrberuf_id' => $lehrberuf, 'lehrbeginn' => '2024-08-01'])
            ->create(['email' => 'lernender@firma.ch']);
        $lernender = $lernenderUser->lernender;
        $bb = User::factory()->berufsbildner()->create(['email' => 'bb@firma.ch']);
        $this->betreue($bb, $lernender);

        $csv = "Datum;Fach/Modul;Titel;Note;Gewicht\n02.03.2026;Sprache;LB1;4,5;\n09.03.2026;Sprache;LB2;5.0;\n";
        $this->actingAs($lernenderUser)
            ->post(route('learner.grades.import.read'), ['datei' => UploadedFile::fake()->createWithContent('noten.csv', $csv)]);

        $vorschau = session('notenimport.'.$lernender->lernender_id);

        $this->actingAs($lernenderUser)
            ->post(route('learner.grades.import.apply'), ['zeilen' => json_encode($vorschau['zeilen']), 'token' => $vorschau['token']])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('notification_digest_items', [
            'user_id' => $bb->benutzer_id,
            'type' => NotificationCatalog::GRADE_ADDED,
        ]);
    }

    #[Test]
    public function neue_betreuung_meldet_dem_neuen_berufsbildner(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create(['email' => 'admin@firma.ch']);
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        $lernender = $lernenderUser->lernender;
        $neuerBb = User::factory()->berufsbildner()->create(['email' => 'neu@firma.ch']);

        $this->actingAs($admin)
            ->post(route('admin.learners.supervision.store', $lernender->lernender_id), [
                'berufsbildner_id' => $neuerBb->berufsbildner->berufsbildner_id,
                'gueltig_von' => now()->toDateString(),
            ])
            ->assertSessionHas('success');

        Notification::assertSentTo($neuerBb, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::LEARNER_ASSIGNED);
    }

    #[Test]
    public function feedback_erstellen_meldet_aktiven_admins(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create(['email' => 'admin@firma.ch']);
        $melder = User::factory()->lernender()->create(['email' => 'melder@firma.ch']);

        $this->actingAs($melder)
            ->postJson(route('feedback.store'), ['kategorie' => 'idee', 'text' => 'Es wäre toll, wenn...'])
            ->assertCreated();

        Notification::assertSentTo($admin, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::FEEDBACK_RECEIVED);
    }

    #[Test]
    public function feedback_erstellen_geht_nicht_an_inaktive_admins(): void
    {
        Notification::fake();
        $inaktiverAdmin = User::factory()->admin()->create(['email' => 'inaktiv@firma.ch', 'aktiv' => false]);
        $melder = User::factory()->lernender()->create(['email' => 'melder@firma.ch']);

        $this->actingAs($melder)
            ->postJson(route('feedback.store'), ['kategorie' => 'fehler', 'text' => 'Es funktioniert nicht...'])
            ->assertCreated();

        Notification::assertNotSentTo($inaktiverAdmin, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::FEEDBACK_RECEIVED);
    }

    #[Test]
    public function feedback_status_aenderung_meldet_melder(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create(['email' => 'admin@firma.ch']);
        $melder = User::factory()->lernender()->create(['email' => 'melder@firma.ch']);
        $feedback = Feedback::factory()->create(['benutzer_id' => $melder->benutzer_id, 'status' => Feedback::STATUS_OFFEN, 'admin_notiz' => null]);

        $this->actingAs($admin)
            ->patchJson(route('admin.feedback.update', $feedback->feedback_id), [
                'status' => Feedback::STATUS_ERLEDIGT,
                'admin_notiz' => 'Danke für den Hinweis.',
            ])
            ->assertOk();

        Notification::assertSentTo($melder, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::FEEDBACK_ANSWERED);
    }

    #[Test]
    public function unveraenderte_meldung_meldet_dem_melder_nichts(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create(['email' => 'admin@firma.ch']);
        $melder = User::factory()->lernender()->create(['email' => 'melder@firma.ch']);
        $feedback = Feedback::factory()->create(['benutzer_id' => $melder->benutzer_id, 'status' => Feedback::STATUS_OFFEN, 'admin_notiz' => null]);

        $this->actingAs($admin)
            ->patchJson(route('admin.feedback.update', $feedback->feedback_id), ['status' => Feedback::STATUS_OFFEN])
            ->assertOk();

        Notification::assertNotSentTo($melder, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::FEEDBACK_ANSWERED);
    }

    #[Test]
    public function fehlgeschlagene_sicherung_meldet_aktiven_admins(): void
    {
        Notification::fake();
        Process::fake(fn () => Process::result(errorOutput: 'Zugriff verweigert', exitCode: 2));
        $admin = User::factory()->admin()->create(['email' => 'admin@firma.ch']);

        $this->artisan('notenportal:sicherung')->assertFailed();

        Notification::assertSentTo($admin, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::BACKUP_FAILED);
    }
}
