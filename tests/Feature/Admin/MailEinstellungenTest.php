<?php

namespace Tests\Feature\Admin;

use App\Models\Einstellung;
use App\Models\MailLog;
use App\Models\User;
use App\Services\Notifications\MailContent;
use App\Services\Notifications\MailSettings;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailEinstellungenTest extends TestCase
{
    #[Test]
    public function admin_speichert_mail_einstellungen_und_passwort_wird_verschluesselt(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.mail.update'), [
                'mail_host' => 'smtp.beispielbetrieb.ch',
                'mail_port' => '587',
                'mail_encryption' => 'tls',
                'mail_username' => 'versand@beispielbetrieb.ch',
                'mail_password' => 'GeheimesPasswort!2026',
                'mail_from_address' => 'noreply@beispielbetrieb.ch',
                'mail_from_name' => 'Notenportal Test-AG',
                'mail_redirect_to' => '',
            ])
            ->assertRedirect(route('admin.betrieb.edit'))
            ->assertSessionHas('success');

        $zeile = Einstellung::query()->where('schluessel', MailSettings::PASSWORD)->first();
        $this->assertNotNull($zeile);
        $this->assertStringNotContainsString('GeheimesPasswort!2026', (string) $zeile->wert);
        $this->assertSame('GeheimesPasswort!2026', Crypt::decryptString($zeile->wert));

        $werte = MailSettings::values();
        $this->assertSame('db', $werte['source']);
        $this->assertTrue($werte['has_password']);
    }

    #[Test]
    public function leeres_passwortfeld_laesst_bestehendes_passwort_unveraendert(): void
    {
        $admin = User::factory()->admin()->create();
        MailSettings::save([
            'mail_host' => 'smtp.beispielbetrieb.ch',
            'mail_port' => '587',
            'mail_encryption' => 'tls',
            'mail_username' => 'versand@beispielbetrieb.ch',
            'mail_password' => 'ErstesPasswort!2026',
            'mail_from_address' => 'noreply@beispielbetrieb.ch',
            'mail_from_name' => '',
            'mail_redirect_to' => '',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.mail.update'), [
                'mail_host' => 'smtp.beispielbetrieb.ch',
                'mail_port' => '587',
                'mail_encryption' => 'tls',
                'mail_username' => 'versand@beispielbetrieb.ch',
                'mail_password' => '',
                'mail_from_address' => 'noreply@beispielbetrieb.ch',
                'mail_from_name' => '',
                'mail_redirect_to' => '',
            ])
            ->assertRedirect(route('admin.betrieb.edit'));

        $zeile = Einstellung::query()->where('schluessel', MailSettings::PASSWORD)->first();
        $this->assertSame('ErstesPasswort!2026', Crypt::decryptString($zeile->wert));
    }

    #[Test]
    public function passwort_entfernen_loescht_das_gespeicherte_passwort(): void
    {
        $admin = User::factory()->admin()->create();
        MailSettings::save([
            'mail_host' => 'smtp.beispielbetrieb.ch',
            'mail_port' => '587',
            'mail_encryption' => 'tls',
            'mail_username' => 'versand@beispielbetrieb.ch',
            'mail_password' => 'ErstesPasswort!2026',
            'mail_from_address' => 'noreply@beispielbetrieb.ch',
            'mail_from_name' => '',
            'mail_redirect_to' => '',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.mail.update'), [
                'mail_host' => 'smtp.beispielbetrieb.ch',
                'mail_port' => '587',
                'mail_encryption' => 'tls',
                'mail_username' => 'versand@beispielbetrieb.ch',
                'mail_password' => '',
                'mail_password_clear' => '1',
                'mail_from_address' => 'noreply@beispielbetrieb.ch',
                'mail_from_name' => '',
                'mail_redirect_to' => '',
            ]);

        $this->assertFalse(MailSettings::values()['has_password']);
    }

    #[Test]
    public function testmail_an_echte_adresse_erzeugt_versandprotokoll_eintrag(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.mail.test'), ['test_to' => 'empfang@beispielfirma.ch']);

        $response->assertRedirect(route('admin.betrieb.edit'));
        $response->assertSessionHas('success');

        $log = MailLog::where('recipient', 'empfang@beispielfirma.ch')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('test', $log->type);
        $this->assertSame(MailLog::SENT, $log->status);
        $this->assertStringNotContainsString('GeheimesPasswort', json_encode($log->payload));
    }

    #[Test]
    public function testadresse_wird_als_nicht_zustellbar_markiert(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.mail.test'), ['test_to' => 'jemand@beispiel.local']);

        $response->assertRedirect(route('admin.betrieb.edit'));
        $response->assertSessionHas('error');

        $log = MailLog::where('recipient', 'jemand@beispiel.local')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(MailLog::SKIPPED, $log->status);
    }

    #[Test]
    public function versandprotokoll_laedt_und_filtert(): void
    {
        $admin = User::factory()->admin()->create();
        MailLog::create([
            'type' => 'test', 'recipient' => 'alpha@beispielfirma.ch',
            'subject' => 'Testmail aus dem Notenportal', 'status' => MailLog::SENT, 'payload' => [],
        ]);
        MailLog::create([
            'type' => 'test', 'recipient' => 'beta@beispielfirma.ch',
            'subject' => 'Testmail aus dem Notenportal', 'status' => MailLog::FAILED, 'error' => 'SMTP-Fehler', 'payload' => [],
        ]);

        $alle = $this->actingAs($admin)->get(route('admin.mail-log.index'));
        $alle->assertOk();
        $alle->assertSee('alpha@beispielfirma.ch');
        $alle->assertSee('beta@beispielfirma.ch');

        $gefiltert = $this->actingAs($admin)->get(route('admin.mail-log.index', ['status' => MailLog::FAILED]));
        $gefiltert->assertOk();
        $gefiltert->assertSee('beta@beispielfirma.ch');
        $gefiltert->assertDontSee('alpha@beispielfirma.ch');
    }

    #[Test]
    public function erneut_senden_erzeugt_einen_neuen_eintrag(): void
    {
        $admin = User::factory()->admin()->create();
        $log = MailLog::create([
            'type' => 'test', 'recipient' => 'gamma@beispielfirma.ch',
            'subject' => 'Testmail aus dem Notenportal', 'status' => MailLog::FAILED, 'error' => 'SMTP-Fehler',
            'payload' => (new MailContent(subject: 'Testmail aus dem Notenportal'))->toArray(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.mail-log.retry', $log->id));
        $response->assertRedirect(route('admin.mail-log.index'));
        $response->assertSessionHas('success');

        $this->assertSame(2, MailLog::where('recipient', 'gamma@beispielfirma.ch')->count());
        $neu = MailLog::where('recipient', 'gamma@beispielfirma.ch')->orderByDesc('id')->first();
        $this->assertNotSame($log->id, $neu->id);
        $this->assertSame(MailLog::SENT, $neu->status);
    }

    #[Test]
    public function erneut_senden_an_deaktiviertes_konto_wird_abgelehnt(): void
    {
        $admin = User::factory()->admin()->create();
        $weg = User::factory()->lernender()->create(['aktiv' => false]);
        $log = MailLog::create([
            'user_id' => $weg->benutzer_id, 'type' => 'test', 'recipient' => $weg->email,
            'subject' => 'Konto', 'status' => MailLog::FAILED,
            'payload' => (new MailContent(subject: 'Konto'))->toArray(),
        ]);

        $this->actingAs($admin)->post(route('admin.mail-log.retry', $log->id))->assertSessionHas('error');
        $this->assertSame(1, MailLog::count());
    }

    #[Test]
    public function nicht_admins_bekommen_403(): void
    {
        foreach (['lernender', 'berufsbildner'] as $rolle) {
            $user = User::factory()->{$rolle}()->create();
            $this->actingAs($user)->put(route('admin.mail.update'), [])->assertForbidden();
            $this->actingAs($user)->post(route('admin.mail.test'), ['test_to' => 'a@beispielfirma.ch'])->assertForbidden();
            $this->actingAs($user)->get(route('admin.mail-log.index'))->assertForbidden();
        }
    }
}
