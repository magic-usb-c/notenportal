<?php

declare(strict_types=1);

namespace Tests\Feature\Benachrichtigungen;

use App\Models\Fach;
use App\Models\MailLog;
use App\Models\Note;
use App\Models\NotificationMark;
use App\Models\Pruefung;
use App\Models\Semester;
use App\Models\User;
use App\Notifications\PortalMail;
use App\Services\Auswertung\LernstandRechner;
use App\Services\Notifications\Messages\ExamReminder;
use App\Services\Notifications\Messages\SemesterEnding;
use App\Services\Notifications\NotificationCatalog;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Funktionsprüfung Runde 8, Gruppe «jobs»: Inaktivitätsbezug, Abgabe-Erinnerung, Frist der Zeitplan-Sperren. */
class JobsBefundeRunde8Test extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function abgabe(User $lernender, Fach $fach, string $datum, string $art = Pruefung::ART_ABGABE): Pruefung
    {
        return Pruefung::create([
            'lernender_id' => $lernender->lernender->lernender_id,
            'fach_id' => $fach->fach_id,
            'titel' => 'Projektbericht',
            'art' => $art,
            'datum' => $datum,
            'gewichtung_prozent' => 25,
            'pruefungsart' => 'schriftlich',
            'dauer_minuten' => 45,
            'hilfsmittel' => 'Taschenrechner',
        ]);
    }

    // --- #13 Inaktivität zählt ab Portalnutzung ---

    #[Test]
    public function frisches_konto_mitten_in_der_lehre_bekommt_am_ersten_morgen_keine_inaktivitaetsmail(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-26 09:00', 'Europe/Zurich'));
        $user = User::factory()->lernender()->create(['email' => 'neu@firma.ch']);
        $user->lernender->update(['lehrbeginn' => '2025-08-01', 'lehrende' => null]);

        Carbon::setTestNow(Carbon::parse('2026-10-27 06:30', 'Europe/Zurich'));
        $this->artisan('notifications:check');

        Notification::assertNotSentTo($user, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::INACTIVITY);
    }

    #[Test]
    public function inaktivitaetsmail_kommt_erst_nach_der_frist_ab_kontoeroeffnung_und_nennt_die_tage_seither(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-26 09:00', 'Europe/Zurich'));
        $user = User::factory()->lernender()->create(['email' => 'neu@firma.ch']);
        $user->lernender->update(['lehrbeginn' => '2025-08-01', 'lehrende' => null]);
        $anzahl = fn () => Notification::sent($user, PortalMail::class)->filter(fn (PortalMail $m) => $m->type === NotificationCatalog::INACTIVITY)->count();

        Carbon::setTestNow(Carbon::parse('2026-11-20 06:30', 'Europe/Zurich')); // 25 Tage nach Eröffnung
        $this->artisan('notifications:check');
        $this->assertSame(0, $anzahl());

        Carbon::setTestNow(Carbon::parse('2026-11-25 06:30', 'Europe/Zurich')); // 30 Tage
        $this->artisan('notifications:check');
        $this->assertSame(1, $anzahl());
        $this->assertStringContainsString('30', (string) MailLog::query()->where('type', NotificationCatalog::INACTIVITY)->value('subject'));
    }

    #[Test]
    public function wechsel_des_bezugs_schickt_innerhalb_der_wiederholungsfrist_keine_zweite_inaktivitaetsmail(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-30 09:00', 'Europe/Zurich'));
        $user = User::factory()->lernender()->create(['email' => 'pilot@firma.ch']);
        $user->lernender->update(['lehrbeginn' => '2025-08-01', 'lehrende' => null]);
        // Mail nach der alten Regel (Bezug Lehrbeginn) eine Woche vor dem Wechsel
        NotificationMark::query()->insert([
            'user_id' => $user->benutzer_id, 'type' => NotificationCatalog::INACTIVITY,
            'subject_key' => 'inaktiv:'.$user->lernender->lernender_id.':2025-08-01:31', 'created_at' => '2026-10-25 06:30:00',
        ]);
        $anzahl = fn () => Notification::sent($user, PortalMail::class)->filter(fn (PortalMail $m) => $m->type === NotificationCatalog::INACTIVITY)->count();

        Carbon::setTestNow(Carbon::parse('2026-11-01 06:30', 'Europe/Zurich')); // 32 Tage nach Eröffnung, 7 nach der letzten Mail
        $this->artisan('notifications:check');
        $this->assertSame(0, $anzahl());

        Carbon::setTestNow(Carbon::parse('2026-11-08 06:30', 'Europe/Zurich')); // 14 Tage nach der letzten Mail
        $this->artisan('notifications:check');
        $this->assertSame(1, $anzahl());
    }

    #[Test]
    public function ampel_meldet_noch_keine_noten_erst_nach_der_frist_ab_kontoeroeffnung(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-26 09:00', 'Europe/Zurich'));
        $user = User::factory()->lernender()->create(['email' => 'neu@firma.ch']);
        $user->lernender->update(['lehrbeginn' => '2025-08-01', 'lehrende' => null]);
        $id = (int) $user->lernender->lernender_id;
        $gruende = fn () => app(LernstandRechner::class)->fuer([$id])[$id]->gruende;

        Carbon::setTestNow(Carbon::parse('2026-10-27 09:00', 'Europe/Zurich'));
        $this->assertNotContains('noch keine Noten', $gruende());

        Carbon::setTestNow(Carbon::parse('2027-01-10 09:00', 'Europe/Zurich'));
        $this->assertContains('noch keine Noten', $gruende());
    }

    #[Test]
    public function lehrbeginn_nach_kontoeroeffnung_bleibt_der_bezug(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-01 09:00', 'Europe/Zurich'));
        $user = User::factory()->lernender()->create(['email' => 'neu@firma.ch']);
        $user->lernender->update(['lehrbeginn' => '2026-08-01', 'lehrende' => null]);

        Carbon::setTestNow(Carbon::parse('2026-08-31 06:30', 'Europe/Zurich')); // 30 Tage nach Lehrbeginn, 61 nach Eröffnung
        $this->artisan('notifications:check');

        Notification::assertSentTo($user, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::INACTIVITY);
    }

    // --- #14 Abgaben haben eigene Erinnerung ---

    #[Test]
    public function abgabe_erinnerung_hat_eigenen_betreff_satz_und_keine_pruefungsangaben(): void
    {
        $user = User::factory()->lernender()->create();
        $p = $this->abgabe($user, Fach::factory()->create(['name' => 'Informatik']), '2026-10-26');

        $inhalt = ExamReminder::content($p);

        $this->assertSame('Abgabe in Informatik am 26.10.2026', $inhalt->subject);
        $this->assertSame('Abgabe in Informatik am 26.10.2026', $inhalt->digestTitle);
        $this->assertSame(['Dein nächster Abgabetermin steht an.'], $inhalt->lines);
        foreach (['Gewichtung', 'Prüfungsart', 'Dauer', 'Erlaubte Hilfsmittel'] as $schluessel) {
            $this->assertArrayNotHasKey($schluessel, $inhalt->facts);
        }
        $this->assertSame('Projektbericht', $inhalt->facts['Titel']);
    }

    #[Test]
    public function mailtexte_fuer_pruefung_ohne_gesetztes_art_rekursieren_nicht_und_gelten_als_pruefung(): void
    {
        $user = User::factory()->lernender()->create();
        $fach = Fach::factory()->create(['name' => 'Informatik']);
        // ohne 'art' angelegt und nicht neu geladen: das Attribut fehlt am Modell
        $p = Pruefung::create([
            'lernender_id' => $user->lernender->lernender_id,
            'fach_id' => $fach->fach_id,
            'titel' => 'Klassenarbeit',
            'datum' => '2026-10-26',
            'gewichtung_prozent' => 25,
            'pruefungsart' => 'schriftlich',
        ]);

        $this->assertFalse($p->istAbgabe());
        $this->assertSame(Pruefung::ART_PRUEFUNG, $p->art());

        $inhalt = ExamReminder::content($p);
        $this->assertSame('Prüfung in Informatik am 26.10.2026', $inhalt->subject);
        $this->assertArrayHasKey('Gewichtung', $inhalt->facts);

        $semester = Semester::factory()->create();
        $ende = SemesterEnding::content($semester, [$p], []);
        $titel = array_column($ende->sections, 'title');
        $this->assertContains('Offene geplante Prüfungen', $titel);
        $this->assertNotContains('Offene Abgaben', $titel);
    }

    #[Test]
    public function abgabe_erinnerung_ist_auch_englisch_ohne_pruefungswort(): void
    {
        $user = User::factory()->lernender()->create();
        $p = $this->abgabe($user, Fach::factory()->create(['name' => 'Informatik']), '2026-10-26');

        App::setLocale('en');
        $inhalt = ExamReminder::content($p);
        App::setLocale('de');

        $this->assertSame('Submission due in Informatik on 26.10.2026', $inhalt->subject);
        $this->assertSame(['Your next submission deadline is coming up.'], $inhalt->lines);
    }

    #[Test]
    public function pruefung_behaelt_betreff_und_pruefungsangaben(): void
    {
        $user = User::factory()->lernender()->create();
        $p = $this->abgabe($user, Fach::factory()->create(['name' => 'Informatik']), '2026-10-26', Pruefung::ART_PRUEFUNG);

        $inhalt = ExamReminder::content($p);

        $this->assertSame('Prüfung in Informatik am 26.10.2026', $inhalt->subject);
        $this->assertSame(['Deine nächste Prüfung steht an.'], $inhalt->lines);
        $this->assertArrayHasKey('Gewichtung', $inhalt->facts);
        $this->assertArrayHasKey('Dauer', $inhalt->facts);
    }

    #[Test]
    public function tagespruefung_meldet_eine_abgabe_als_abgabe(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-24 06:30', 'Europe/Zurich'));
        $user = User::factory()->lernender()->create(['email' => 'abgabe@firma.ch']);
        Note::factory()->create(['lernender_id' => $user->lernender->lernender_id]);
        $this->abgabe($user, Fach::factory()->create(['name' => 'Informatik']), '2026-10-26');

        $this->artisan('notifications:check');

        $betreff = (string) MailLog::query()->where('type', NotificationCatalog::EXAM_REMINDER)->value('subject');
        $this->assertStringStartsWith('Abgabe in Informatik', $betreff);
        $this->assertStringNotContainsString('Prüfung', $betreff);
    }

    #[Test]
    public function semesterende_fuehrt_abgaben_getrennt_von_pruefungen(): void
    {
        $user = User::factory()->lernender()->create();
        $fach = Fach::factory()->create(['name' => 'Informatik']);
        $semester = Semester::factory()->create(['start_datum' => '2026-08-01', 'end_datum' => '2027-01-31']);
        $abgabe = $this->abgabe($user, $fach, '2027-01-20');
        $pruefung = $this->abgabe($user, $fach, '2027-01-15', Pruefung::ART_PRUEFUNG);

        $inhalt = SemesterEnding::content($semester, [$pruefung, $abgabe], [], (int) $user->lernender->lernender_id);

        $titel = array_column($inhalt->sections, 'text', 'title');
        $this->assertArrayHasKey('Offene geplante Prüfungen', $titel);
        $this->assertArrayHasKey('Offene Abgaben', $titel);
        $this->assertStringContainsString('15.01.2027', $titel['Offene geplante Prüfungen']);
        $this->assertStringNotContainsString('20.01.2027', $titel['Offene geplante Prüfungen']);
        $this->assertStringContainsString('20.01.2027', $titel['Offene Abgaben']);
    }

    #[Test]
    public function semesterende_mit_nur_einer_abgabe_hat_keine_pruefungsueberschrift(): void
    {
        $user = User::factory()->lernender()->create();
        $semester = Semester::factory()->create(['start_datum' => '2026-08-01', 'end_datum' => '2027-01-31']);
        $abgabe = $this->abgabe($user, Fach::factory()->create(), '2027-01-20');

        $inhalt = SemesterEnding::content($semester, [$abgabe], [], (int) $user->lernender->lernender_id);

        $this->assertSame(['Offene Abgaben'], array_column($inhalt->sections, 'title'));
    }

    // --- #16 Zeitplan-Sperren mit Frist ---

    #[Test]
    public function zeitplan_sperren_laufen_nach_wenigen_minuten_ab_statt_nach_einem_tag(): void
    {
        $erwartet = [
            'queue:work' => 5,
            'notenportal:sicherung' => 120,
            'notifications:check' => 30,
            'notifications:digest' => 30,
            'calendar:sync' => 30,
            'queue:prune-failed' => 15,
            'notenportal:aktivitaeten-aufraeumen' => 15,
            'notenportal:feedback-aufraeumen' => 15,
        ];

        $ereignisse = collect(app(Schedule::class)->events());
        $this->assertCount(count($erwartet), $ereignisse);
        foreach ($erwartet as $befehl => $minuten) {
            $e = $ereignisse->first(fn ($e) => str_contains($e->command, $befehl));
            $this->assertNotNull($e, $befehl);
            $this->assertTrue($e->withoutOverlapping, $befehl);
            $this->assertSame($minuten, $e->expiresAt, $befehl);
        }
    }

    #[Test]
    public function verwaiste_sperre_der_sicherung_blockiert_den_naechsten_tag_nicht(): void
    {
        config(['cache.default' => 'database']);
        DB::table('cache_locks')->delete();
        $e = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'notenportal:sicherung'));
        $this->assertTrue($e->mutex->create($e)); // harter Abbruch: Sperre nie freigegeben

        Carbon::setTestNow(now()->addHours(20));

        $this->assertFalse($e->shouldSkipDueToOverlapping());
        $e->mutex->forget($e);
    }
}
