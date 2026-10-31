<?php

namespace Tests\Feature\Admin;

use App\Models\MailLog;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Notifications\MailContent;
use App\Services\Notifications\MailSettings;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
use App\Support\Einstellungen;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Tests\TestCase;

/** Bei gesetzter Mail-Umleitung: Testmail-Toast nennt das Ziel, und umgeleitete Mails tragen keinen Abbestell-Link der Originalperson. */
class MailUmleitungHinweisTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->envNeuLesen();
        parent::tearDown();
    }

    /** MailSettings hält die .env-Werte pro Prozess fest; ein Test, der den Standard-Mailer ändert, liest sie neu. */
    private function envNeuLesen(): void
    {
        (new ReflectionProperty(MailSettings::class, 'envCaptured'))->setValue(null, false);
    }

    #[Test]
    public function testmail_toast_nennt_das_umleitungsziel(): void
    {
        $admin = User::factory()->admin()->create();
        Einstellungen::set(MailSettings::REDIRECT_TO, 'umleitung@beispielbetrieb.ch');

        $this->actingAs($admin)->post(route('admin.mail.test'), ['test_to' => 'andere@example.org']);

        $log = MailLog::latest('id')->first();
        $this->assertSame(MailLog::SENT, $log->status, (string) $log->error);
        $this->assertSame('umleitung@beispielbetrieb.ch', $log->redirected_to);
        $toast = (string) session('success');
        $this->assertStringContainsString('umleitung@beispielbetrieb.ch', $toast);
        $this->assertStringContainsString('andere@example.org', $toast);
    }

    #[Test]
    public function testmail_toast_ohne_umleitung_bleibt_unveraendert(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.mail.test'), ['test_to' => 'empfang@beispielfirma.ch']);

        $this->assertSame('Testmail an empfang@beispielfirma.ch verschickt.', session('success'));
    }

    #[Test]
    public function umgeleitete_mail_hat_weder_abbestell_link_noch_list_unsubscribe(): void
    {
        Einstellungen::set(MailSettings::REDIRECT_TO, 'umleitung@beispielbetrieb.ch');
        config(['mail.default' => 'array']);
        Einstellungen::vergessen();
        $this->envNeuLesen();
        MailSettings::apply();
        $lernender = User::factory()->lernender()->create(['email' => 'lernende@beispielfirma.ch']);

        Notifier::dispatch($lernender, NotificationCatalog::COMMENT_ADDED, new MailContent(subject: 'Neuer Kommentar', lines: ['Hallo']), now: true);

        $nachricht = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->last();
        $this->assertNotNull($nachricht);
        $mail = $nachricht->getOriginalMessage();
        $this->assertSame(['umleitung@beispielbetrieb.ch'], array_map(fn ($a) => $a->getAddress(), $mail->getTo()));
        $this->assertNull($mail->getHeaders()->get('List-Unsubscribe'));
        $this->assertNull($mail->getHeaders()->get('List-Unsubscribe-Post'));
        $this->assertStringNotContainsString('/unsubscribe', (string) $mail->getHtmlBody());
        $this->assertStringNotContainsString('Abbestellen', (string) $mail->getTextBody());
        $this->assertNull(NotificationPreference::where('user_id', $lernender->benutzer_id)->value('frequency'));
    }

    #[Test]
    public function ohne_umleitung_bleibt_der_abbestell_link_erhalten(): void
    {
        config(['mail.default' => 'array']);
        Einstellungen::vergessen();
        $this->envNeuLesen();
        MailSettings::apply();
        $lernender = User::factory()->lernender()->create(['email' => 'lernende@beispielfirma.ch']);

        Notifier::dispatch($lernender, NotificationCatalog::COMMENT_ADDED, new MailContent(subject: 'Neuer Kommentar', lines: ['Hallo']), now: true);

        $mail = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertNotNull($mail->getHeaders()->get('List-Unsubscribe'));
    }
}
