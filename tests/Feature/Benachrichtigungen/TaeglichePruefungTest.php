<?php

declare(strict_types=1);

namespace Tests\Feature\Benachrichtigungen;

use App\Models\Fach;
use App\Models\Note;
use App\Models\NotificationMark;
use App\Models\Pruefung;
use App\Models\Semester;
use App\Models\User;
use App\Notifications\PortalMail;
use App\Services\Notifications\NotificationCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

/**
 * Tägliche Prüfung (notifications:check): Prüfungserinnerung, Inaktivität, Semesterende/-abschluss,
 * Lernstand kritisch. Testadressen enden auf «.ch» (Notifier::UNZUSTELLBAR blockiert .example/.com/.org/.net).
 */
class TaeglichePruefungTest extends TestCase
{
    use VerwaltungTestHilfen;

    #[Test]
    public function pruefungserinnerung_wird_innerhalb_der_frist_gemeldet_und_nicht_doppelt(): void
    {
        Notification::fake();
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        $fach = Fach::factory()->create();
        Pruefung::create([
            'lernender_id' => $lernenderUser->lernender->lernender_id,
            'fach_id' => $fach->fach_id,
            'titel' => 'Testat',
            'datum' => today()->addDays(3)->toDateString(),
            'gewichtung_prozent' => 50,
        ]);

        $this->artisan('notifications:check');
        $this->artisan('notifications:check'); // zweiter Lauf am selben Tag darf nicht doppelt melden

        Notification::assertSentTo($lernenderUser, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::EXAM_REMINDER);
        $this->assertSame(1, DB::table('mail_log')->where('type', NotificationCatalog::EXAM_REMINDER)->count());
        $this->assertSame(1, DB::table('notification_marks')->where('type', NotificationCatalog::EXAM_REMINDER)->count());
    }

    #[Test]
    public function pruefung_ausserhalb_der_frist_wird_nicht_gemeldet(): void
    {
        Notification::fake();
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        $fach = Fach::factory()->create();
        Pruefung::create([
            'lernender_id' => $lernenderUser->lernender->lernender_id,
            'fach_id' => $fach->fach_id,
            'titel' => 'Zu weit weg',
            'datum' => today()->addDays(10)->toDateString(),
            'gewichtung_prozent' => 50,
        ]);

        $this->artisan('notifications:check');

        Notification::assertNotSentTo($lernenderUser, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::EXAM_REMINDER);
    }

    #[Test]
    public function inaktiver_lernender_ohne_note_wird_gemeldet(): void
    {
        Notification::fake();
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);

        $this->artisan('notifications:check');

        Notification::assertSentTo($lernenderUser, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::INACTIVITY);
    }

    #[Test]
    public function lernender_mit_frischer_note_wird_nicht_als_inaktiv_gemeldet(): void
    {
        Notification::fake();
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        Note::factory()->create(['lernender_id' => $lernenderUser->lernender->lernender_id]);

        $this->artisan('notifications:check');

        Notification::assertNotSentTo($lernenderUser, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::INACTIVITY);
    }

    #[Test]
    public function semesterende_mit_offener_pruefung_wird_gemeldet(): void
    {
        Notification::fake();
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        $fach = Fach::factory()->create();
        $semester = Semester::factory()->create([
            'start_datum' => today()->subMonths(2)->toDateString(),
            'end_datum' => today()->addDays(14)->toDateString(),
        ]);
        Pruefung::create([
            'lernender_id' => $lernenderUser->lernender->lernender_id,
            'fach_id' => $fach->fach_id,
            'titel' => 'Semesterprüfung',
            'datum' => today()->addDays(5)->toDateString(),
            'gewichtung_prozent' => 50,
        ]);

        $this->artisan('notifications:check');

        Notification::assertSentTo($lernenderUser, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::SEMESTER_ENDING);
    }

    #[Test]
    public function semesterende_wird_nach_ausgefallenem_lauf_genau_einmal_nachgeholt(): void
    {
        Notification::fake();
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        Semester::factory()->create([
            'start_datum' => today()->subMonths(2)->toDateString(),
            'end_datum' => today()->addDays(13)->toDateString(),
        ]);
        Pruefung::create([
            'lernender_id' => $lernenderUser->lernender->lernender_id, 'fach_id' => Fach::factory()->create()->fach_id,
            'titel' => 'Semesterprüfung', 'datum' => today()->addDays(5)->toDateString(), 'gewichtung_prozent' => 50,
        ]);

        $this->artisan('notifications:check');
        $this->artisan('notifications:check');

        $this->assertCount(1, Notification::sent($lernenderUser, PortalMail::class)->filter(fn (PortalMail $m) => $m->type === NotificationCatalog::SEMESTER_ENDING));
    }

    #[Test]
    public function semester_ohne_offene_punkte_wird_nicht_gemeldet(): void
    {
        Notification::fake();
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        Semester::factory()->create([
            'start_datum' => today()->subMonths(2)->toDateString(),
            'end_datum' => today()->addDays(14)->toDateString(),
        ]);

        $this->artisan('notifications:check');

        Notification::assertNotSentTo($lernenderUser, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::SEMESTER_ENDING);
    }

    #[Test]
    public function semesterabschluss_meldet_lernenden_und_betreuer_nicht_doppelt(): void
    {
        Notification::fake();
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        $lernender = $lernenderUser->lernender;
        $bb = User::factory()->berufsbildner()->create(['email' => 'bb@firma.ch']);
        $this->betreue($bb, $lernender);

        $semester = Semester::factory()->create([
            'start_datum' => today()->subMonths(4)->toDateString(),
            'end_datum' => today()->subDay()->toDateString(),
        ]);
        Note::factory()->create([
            'lernender_id' => $lernender->lernender_id,
            'semester_id' => $semester->semester_id,
            'fach_id' => Fach::factory()->create()->fach_id,
            'note_wert' => 3.5,
        ]);

        $this->artisan('notifications:check');
        $this->artisan('notifications:check');

        Notification::assertSentTo($lernenderUser, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::SEMESTER_CLOSED);
        Notification::assertSentTo($bb, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::SEMESTER_CLOSED);
        $this->assertSame(2, DB::table('mail_log')->where('type', NotificationCatalog::SEMESTER_CLOSED)->count());
    }

    #[Test]
    public function lernstand_rot_meldet_betreuer_und_merker_wird_bei_gruen_geloescht(): void
    {
        Notification::fake();
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        $lernender = $lernenderUser->lernender;
        $bb = User::factory()->berufsbildner()->create(['email' => 'bb@firma.ch']);
        $this->betreue($bb, $lernender);

        $semester = Semester::factory()->create();
        $this->bmsTrack($lernender, $semester->semester_id);
        $note = Note::factory()->create([
            'lernender_id' => $lernender->lernender_id,
            'semester_id' => $semester->semester_id,
            'fach_id' => Fach::factory()->create(['track_typ' => 'BMS'])->fach_id,
            'note_wert' => 3.0,
        ]);

        $this->artisan('notifications:check');

        Notification::assertSentTo($bb, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::LEARNER_AT_RISK);
        $this->assertSame(1, DB::table('mail_log')->where('type', NotificationCatalog::LEARNER_AT_RISK)->count());
        $this->assertTrue(NotificationMark::where('type', NotificationCatalog::LEARNER_AT_RISK)->where('subject_key', 'rot:'.$lernender->lernender_id)->exists());

        // Schnitt verbessert sich auf Gut → Merker wird gelöscht
        $note->update(['note_wert' => 5.5]);
        $this->artisan('notifications:check');

        $this->assertFalse(NotificationMark::where('type', NotificationCatalog::LEARNER_AT_RISK)->where('subject_key', 'rot:'.$lernender->lernender_id)->exists());
        $this->assertSame(1, DB::table('mail_log')->where('type', NotificationCatalog::LEARNER_AT_RISK)->count());

        // Wieder Rot → erneute Meldung, weil der Merker weg ist
        $note->update(['note_wert' => 3.0]);
        $this->artisan('notifications:check');

        $this->assertSame(2, DB::table('mail_log')->where('type', NotificationCatalog::LEARNER_AT_RISK)->count());
    }

    #[Test]
    public function dry_run_versendet_nichts_und_hinterlaesst_keine_merker(): void
    {
        Notification::fake();
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);

        $this->artisan('notifications:check', ['--dry-run' => true]);

        Notification::assertNothingSentTo($lernenderUser);
        $this->assertDatabaseCount('mail_log', 0);
        $this->assertDatabaseCount('notification_marks', 0);
        $this->assertDatabaseCount('notification_digest_items', 0);
    }
}
