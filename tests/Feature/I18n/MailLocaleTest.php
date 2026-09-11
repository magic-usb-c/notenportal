<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use App\Http\Middleware\SetLocale;
use App\Models\DigestItem;
use App\Models\MailLog;
use App\Models\User;
use App\Notifications\PortalMail;
use App\Services\Notifications\MailContent;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
use App\Support\Einstellungen;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Mails entstehen in der Sprache des Empfängers, nicht in der des auslösenden Requests. */
class MailLocaleTest extends TestCase
{
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
}
