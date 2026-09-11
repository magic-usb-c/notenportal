<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use App\Http\Middleware\SetLocale;
use App\Models\DigestItem;
use App\Models\Fach;
use App\Models\MailLog;
use App\Models\Note;
use App\Models\Pruefung;
use App\Models\Semester;
use App\Models\User;
use App\Notifications\PortalMail;
use App\Services\Notifications\AccountMails;
use App\Services\Notifications\MailContent;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
use App\Support\Einstellungen;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

/** Mails entstehen in der Sprache des Empfängers, nicht in der des auslösenden Requests. */
class MailLocaleTest extends TestCase
{
    use VerwaltungTestHilfen;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Einstellungen::set(SetLocale::WAHL_AKTIV, '1');
    }

    private function empfaenger(?string $locale, string $name): User
    {
        return User::factory()->lernender()->create(['email' => "{$name}@firma.ch", 'locale' => $locale]);
    }

    #[Test]
    public function passwort_reset_in_der_sprache_des_empfaengers(): void
    {
        $englisch = $this->empfaenger('en', 'english');
        $deutsch = $this->empfaenger('de', 'deutsch');

        $this->post(route('password.email'), ['email' => $englisch->email])->assertRedirect();
        $this->post(route('password.email'), ['email' => $deutsch->email])->assertRedirect();

        $log = MailLog::where('user_id', $englisch->benutzer_id)->sole();
        $this->assertSame('Reset password', $log->subject);
        $this->assertSame(['You asked to reset your password.'], $log->payload['lines']);
        $this->assertSame('Passwort zurücksetzen', MailLog::where('user_id', $deutsch->benutzer_id)->sole()->subject);

        Notification::assertSentTo($englisch, PortalMail::class, fn ($mail, $kanaele, $empfaenger, $locale) => $locale === 'en');
        Notification::assertSentTo($deutsch, PortalMail::class, fn ($mail, $kanaele, $empfaenger, $locale) => $locale === 'de');
    }

    /** Konto-Mail (AccountMails, Broker «invites») wird ebenfalls in der Sprache des Empfängers gebaut. */
    #[Test]
    public function konto_mail_in_der_sprache_des_empfaengers(): void
    {
        $englisch = $this->empfaenger('en', 'english');

        AccountMails::accountCreated($englisch);

        $log = MailLog::where('user_id', $englisch->benutzer_id)->where('type', NotificationCatalog::ACCOUNT_CREATED)->sole();
        $this->assertSame('Your account in Notenportal', $log->subject);
        $this->assertSame(['An account was set up for you in Notenportal.'], $log->payload['lines']);
    }

    #[Test]
    public function ohne_sprachwahl_bekommen_alle_die_standardsprache(): void
    {
        Einstellungen::set(SetLocale::WAHL_AKTIV, '0');
        $benutzer = $this->empfaenger('en', 'english');

        $this->post(route('password.email'), ['email' => $benutzer->email]);

        $this->assertSame('Passwort zurücksetzen', MailLog::where('user_id', $benutzer->benutzer_id)->sole()->subject);
    }

    #[Test]
    public function die_locale_des_requests_bleibt_unveraendert(): void
    {
        App::setLocale('de');
        Carbon::setLocale('de');

        $log = Notifier::dispatch($this->empfaenger('en', 'english'), NotificationCatalog::PASSWORD_RESET,
            fn () => new MailContent(subject: __('Passwort zurücksetzen').' '.App::getLocale()));

        $this->assertSame('Reset password en', $log->subject);
        $this->assertSame('de', App::getLocale());
        $this->assertSame('de', Carbon::getLocale());
    }

    #[Test]
    public function tageszusammenfassung_in_der_sprache_des_empfaengers(): void
    {
        $englisch = $this->empfaenger('en', 'english');
        $deutsch = $this->empfaenger(null, 'deutsch');
        foreach ([$englisch, $deutsch] as $benutzer) {
            foreach (['Mathematik', 'Englisch'] as $titel) {
                DigestItem::create(['user_id' => $benutzer->benutzer_id, 'type' => 'test', 'title' => $titel, 'body' => '5.0', 'url' => '/']);
            }
        }

        $this->artisan('notifications:digest')->assertSuccessful();

        $this->assertSame('2 updates in Notenportal', MailLog::where('user_id', $englisch->benutzer_id)->sole()->subject);
        $this->assertSame('2 Neuigkeiten im Notenportal', MailLog::where('user_id', $deutsch->benutzer_id)->sole()->subject);
    }

    /** Kommentar-Mail wird für den Empfänger (Berufsbildner, locale=en) gebaut, nicht in der Sprache des auslösenden Lernenden. */
    #[Test]
    public function kommentar_mail_in_der_sprache_des_empfaengers(): void
    {
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch', 'locale' => 'de']);
        $lernender = $lernenderUser->lernender;
        $note = Note::factory()->create(['lernender_id' => $lernender->lernender_id]);

        $bb = User::factory()->berufsbildner()->create(['email' => 'bb@firma.ch', 'locale' => 'en']);
        $this->betreue($bb, $lernender);

        $this->actingAs($lernenderUser)
            ->post(route('comments.store', $note->note_id), ['kommentar_text' => 'Bitte anschauen, danke.'])
            ->assertSessionHas('success');

        $subject = MailLog::where('user_id', $bb->benutzer_id)->where('type', NotificationCatalog::COMMENT_ADDED)->sole()->subject;
        $this->assertStringStartsWith('New comment on ', $subject);
    }

    /** Neue-Note-Meldung an den Berufsbildner (locale=en) wird englisch gebaut, auch als Tageszusammenfassung. */
    #[Test]
    public function note_erfasst_mail_in_der_sprache_des_empfaengers(): void
    {
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch', 'locale' => 'de']);
        $lernender = $lernenderUser->lernender;
        $semester = Semester::factory()->create();
        $this->bmsTrack($lernender, $semester->semester_id);
        $fach = Fach::factory()->create(['track_typ' => 'BMS']);

        $bb = User::factory()->berufsbildner()->create(['email' => 'bb@firma.ch', 'locale' => 'en']);
        $this->betreue($bb, $lernender);

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

        // GRADE_ADDED ist standardmässig eine Tageszusammenfassung, kein Sofortversand.
        $titel = DigestItem::where('user_id', $bb->benutzer_id)->where('type', NotificationCatalog::GRADE_ADDED)->sole()->title;
        $this->assertStringStartsWith('New grade from ', $titel);
    }

    /** notifications:check (Prüfungserinnerung) baut die Mail in der Sprache des Lernenden (Empfänger). */
    #[Test]
    public function check_notifications_mail_in_der_sprache_des_empfaengers(): void
    {
        App::setLocale('de');
        Carbon::setLocale('de');

        $lernenderUser = $this->empfaenger('en', 'lernender');
        $fach = Fach::factory()->create();
        Pruefung::create([
            'lernender_id' => $lernenderUser->lernender->lernender_id,
            'fach_id' => $fach->fach_id,
            'titel' => 'Testat',
            'datum' => today()->addDays(3)->toDateString(),
            'gewichtung_prozent' => 50,
        ]);

        $this->artisan('notifications:check')->assertSuccessful();

        $subject = MailLog::where('user_id', $lernenderUser->benutzer_id)->where('type', NotificationCatalog::EXAM_REMINDER)->sole()->subject;
        $this->assertStringStartsWith('Exam in ', $subject);
    }

    /** GradeWatcher (Schnitt unter Grenzwert) meldet dem Berufsbildner (locale=en) englisch, unabhängig von der Sprache des Lernenden. */
    #[Test]
    public function grade_watcher_mail_in_der_sprache_des_empfaengers(): void
    {
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch', 'locale' => 'de']);
        $lernender = $lernenderUser->lernender;
        $semester = Semester::factory()->create();
        $this->bmsTrack($lernender, $semester->semester_id);
        $fach = Fach::factory()->create(['track_typ' => 'BMS']);

        $bb = User::factory()->berufsbildner()->create(['email' => 'bb@firma.ch', 'locale' => 'en']);
        $this->betreue($bb, $lernender);

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

        $subject = MailLog::where('user_id', $bb->benutzer_id)->where('type', NotificationCatalog::BELOW_THRESHOLD)->sole()->subject;
        $this->assertStringContainsString('failing', $subject);
        $this->assertStringNotContainsString('ungenügend', $subject);
    }
}
