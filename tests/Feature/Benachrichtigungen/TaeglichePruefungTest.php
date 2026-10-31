<?php

declare(strict_types=1);

namespace Tests\Feature\Benachrichtigungen;

use App\Http\Middleware\SetLocale;
use App\Models\Fach;
use App\Models\MailLog;
use App\Models\Note;
use App\Models\NotificationMark;
use App\Models\Pruefung;
use App\Models\Semester;
use App\Models\User;
use App\Notifications\PortalMail;
use App\Services\Auswertung\LernstandRechner;
use App\Services\Notifications\NotificationCatalog;
use App\Support\Betrieb;
use App\Support\Einstellungen;
use Illuminate\Support\Carbon;
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
    public function verschobene_pruefung_wird_zum_neuen_datum_erneut_erinnert(): void
    {
        Notification::fake();
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        $pruefung = Pruefung::create([
            'lernender_id' => $lernenderUser->lernender->lernender_id, 'fach_id' => Fach::factory()->create()->fach_id,
            'titel' => 'Testat', 'datum' => today()->addDays(2)->toDateString(), 'gewichtung_prozent' => 50,
        ]);
        $anzahl = fn () => Notification::sent($lernenderUser, PortalMail::class)->filter(fn (PortalMail $m) => $m->type === NotificationCatalog::EXAM_REMINDER)->count();

        $this->artisan('notifications:check');
        $this->artisan('notifications:check');
        $this->assertSame(1, $anzahl());

        $pruefung->update(['datum' => today()->addDays(20)->toDateString()]);
        $this->travel(17)->days();
        $this->artisan('notifications:check');
        $this->artisan('notifications:check');

        $this->assertSame(2, $anzahl());
    }

    #[Test]
    public function alter_pruefungsmerker_ohne_datum_verhindert_doppelte_erinnerung(): void
    {
        Notification::fake();
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        $pruefung = Pruefung::create([
            'lernender_id' => $lernenderUser->lernender->lernender_id, 'fach_id' => Fach::factory()->create()->fach_id,
            'titel' => 'Testat', 'datum' => today()->addDays(2)->toDateString(), 'gewichtung_prozent' => 50,
        ]);
        $marke = fn (Carbon $am) => NotificationMark::query()->insert([
            'user_id' => $lernenderUser->benutzer_id, 'type' => NotificationCatalog::EXAM_REMINDER,
            'subject_key' => 'pruefung:'.$pruefung->pruefung_id, 'created_at' => $am,
        ]);
        $anzahl = fn () => Notification::sent($lernenderUser, PortalMail::class)->filter(fn (PortalMail $m) => $m->type === NotificationCatalog::EXAM_REMINDER)->count();

        // Alter Merker von gestern (laufendes Fenster): keine zweite Erinnerung
        $marke(now()->subDay());
        $this->artisan('notifications:check');
        $this->assertSame(0, $anzahl());

        // Alter Merker vor dem Fenster (Prüfung seither verschoben): Erinnerung kommt
        NotificationMark::query()->delete();
        $marke(now()->subDays(30));
        $this->artisan('notifications:check');
        $this->assertSame(1, $anzahl());
    }

    #[Test]
    public function inaktivitaetserinnerung_kommt_in_einer_zweiten_inaktivitaetsphase_erneut(): void
    {
        Notification::fake();
        $start = Carbon::create(2027, 3, 1, 7, 0, 0);
        Carbon::setTestNow($start);
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        $id = $lernenderUser->lernender->lernender_id;
        DB::table('lernende')->where('lernender_id', $id)->update(['lehrbeginn' => $start->toDateString(), 'lehrende' => null]);
        $anzahl = fn () => Notification::sent($lernenderUser, PortalMail::class)->filter(fn (PortalMail $m) => $m->type === NotificationCatalog::INACTIVITY)->count();

        try {
            for ($tag = 1; $tag <= 60; $tag++) {
                Carbon::setTestNow($start->copy()->addDays($tag));
                $this->artisan('notifications:check');
            }
            $ersteStufe = $anzahl();
            $this->assertGreaterThanOrEqual(1, $ersteStufe);

            $note = Note::factory()->create(['lernender_id' => $id]);
            DB::table('noten')->where('note_id', $note->note_id)->update(['erstellt_am' => $start->copy()->addDays(60)->toDateTimeString()]);

            for ($tag = 61; $tag <= 100; $tag++) {
                Carbon::setTestNow($start->copy()->addDays($tag));
                $this->artisan('notifications:check');
            }

            $this->assertGreaterThan($ersteStufe, $anzahl());
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function alter_inaktivitaetsmerker_ohne_bezugsdatum_verhindert_doppelte_erinnerung(): void
    {
        Notification::fake();
        $start = Carbon::create(2027, 3, 1, 7, 0, 0);
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        $id = $lernenderUser->lernender->lernender_id;
        DB::table('lernende')->where('lernender_id', $id)->update(['lehrbeginn' => $start->toDateString(), 'lehrende' => null]);
        $tage = Betrieb::fristInaktivTage();
        $marke = fn (Carbon $am) => NotificationMark::query()->insert([
            'user_id' => $lernenderUser->benutzer_id, 'type' => NotificationCatalog::INACTIVITY,
            'subject_key' => 'inaktiv:'.$id.':0', 'created_at' => $am,
        ]);
        $anzahl = fn () => Notification::sent($lernenderUser, PortalMail::class)->filter(fn (PortalMail $m) => $m->type === NotificationCatalog::INACTIVITY)->count();

        try {
            // Alter Merker aus der laufenden Phase: keine zweite Erinnerung
            $marke($start->copy()->addDays($tage));
            Carbon::setTestNow($start->copy()->addDays($tage + 1));
            $this->artisan('notifications:check');
            $this->assertSame(0, $anzahl());

            // Alter Merker liegt vor der laufenden Phase (neue Note danach): Erinnerung kommt
            NotificationMark::query()->delete();
            $marke($start->copy()->subDays(5));
            $this->artisan('notifications:check');
            $this->assertSame(1, $anzahl());
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function semesterabschluss_kommt_auch_wenn_die_lehre_am_semesterende_endet(): void
    {
        Notification::fake();
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        $lernender = $lernenderUser->lernender;
        $bb = User::factory()->berufsbildner()->create(['email' => 'bb@firma.ch']);
        $this->betreue($bb, $lernender);

        $semester = Semester::factory()->create([
            'start_datum' => today()->subMonths(6)->toDateString(),
            'end_datum' => today()->subDay()->toDateString(),
        ]);
        DB::table('lernende')->where('lernender_id', $lernender->lernender_id)->update(['lehrende' => today()->subDay()->toDateString()]);
        Note::factory()->create([
            'lernender_id' => $lernender->lernender_id,
            'semester_id' => $semester->semester_id,
            'fach_id' => Fach::factory()->create()->fach_id,
            'note_wert' => 4.5,
        ]);

        $this->artisan('notifications:check');

        Notification::assertSentTo($lernenderUser, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::SEMESTER_CLOSED);
        Notification::assertSentTo($bb, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::SEMESTER_CLOSED);
    }

    #[Test]
    public function semesterabschluss_kommt_nicht_wenn_die_lehre_vor_dem_semesterende_endete(): void
    {
        Notification::fake();
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        $semester = Semester::factory()->create([
            'start_datum' => today()->subMonths(6)->toDateString(),
            'end_datum' => today()->subDay()->toDateString(),
        ]);
        DB::table('lernende')->where('lernender_id', $lernenderUser->lernender->lernender_id)->update(['lehrende' => today()->subDays(3)->toDateString()]);
        Note::factory()->create([
            'lernender_id' => $lernenderUser->lernender->lernender_id,
            'semester_id' => $semester->semester_id,
            'fach_id' => Fach::factory()->create()->fach_id,
            'note_wert' => 4.5,
        ]);

        $this->artisan('notifications:check');

        Notification::assertNotSentTo($lernenderUser, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::SEMESTER_CLOSED);
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

    #[Test]
    public function lernstand_rot_nennt_die_gruende_in_der_sprache_des_betreuers(): void
    {
        Einstellungen::set(SetLocale::WAHL_AKTIV, '1');
        $lernender = User::factory()->lernender()->create(['email' => 'lernender@firma.ch'])->lernender;
        $en = User::factory()->berufsbildner()->create(['email' => 'en@firma.ch', 'locale' => 'en']);
        $de = User::factory()->berufsbildner()->create(['email' => 'de@firma.ch', 'locale' => 'de']);
        $this->betreue($en, $lernender);
        $this->betreue($de, $lernender);

        $semester = Semester::factory()->create();
        $this->bmsTrack($lernender, $semester->semester_id);
        Note::factory()->create([
            'lernender_id' => $lernender->lernender_id,
            'semester_id' => $semester->semester_id,
            'fach_id' => Fach::factory()->create(['track_typ' => 'BMS'])->fach_id,
            'note_wert' => 3.0,
        ]);

        app()->setLocale('de');
        $this->artisan('notifications:check');

        $text = fn (User $u) => json_encode(MailLog::where('user_id', $u->benutzer_id)->where('type', NotificationCatalog::LEARNER_AT_RISK)->sole()->payload, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Semester average', $text($en));
        $this->assertStringNotContainsString('Semesterschnitt', $text($en));
        $this->assertStringContainsString('Semesterschnitt', $text($de));
    }

    #[Test]
    public function zeugnis_mit_datum_semesterende_zaehlt_nicht_als_letzte_pruefung(): void
    {
        $lernender = User::factory()->lernender()->create(['email' => 'lernender@firma.ch'])->lernender;
        $semester = Semester::factory()->create();
        $fach = Fach::factory()->create(['track_typ' => 'BMS']);
        $this->bmsTrack($lernender, $semester->semester_id);
        foreach ([now()->subDays(5), now()->addDays(60)] as $datum) {
            Note::factory()->create([
                'lernender_id' => $lernender->lernender_id, 'semester_id' => $semester->semester_id,
                'fach_id' => $fach->fach_id, 'note_wert' => 5.0, 'pruefungsdatum' => $datum->toDateString(),
            ]);
        }

        $stand = app(LernstandRechner::class)->fuer([(int) $lernender->lernender_id])[(int) $lernender->lernender_id];

        $this->assertSame(now()->subDays(5)->toDateString(), $stand->letztePruefung->toDateString());
    }
}
