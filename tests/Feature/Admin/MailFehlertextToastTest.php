<?php

namespace Tests\Feature\Admin;

use App\Models\MailLog;
use App\Models\User;
use App\Services\Notifications\MailSettings;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailFehlertextToastTest extends TestCase
{
    private function unerreichbarerServer(): void
    {
        // Lokaler Port ohne Dienst: Verbindung wird sofort verweigert, nichts verlässt den Rechner
        MailSettings::save([
            'mail_host' => '127.0.0.1', 'mail_port' => '2525', 'mail_encryption' => 'none',
            'mail_username' => '', 'mail_password' => '', 'mail_from_address' => 'noreply@beispielbetrieb.ch',
            'mail_from_name' => '', 'mail_redirect_to' => 'umleitung@beispielbetrieb.ch',
        ]);
    }

    private function assertDeutschOhneRohtext(?string $toast): void
    {
        $this->assertNotNull($toast);
        $this->assertStringContainsString('Mailserver', $toast);
        foreach (['stream_socket_client', 'Connection', '127.0.0.1', '2525', 'Symfony'] as $roh) {
            $this->assertStringNotContainsString($roh, $toast);
        }
    }

    #[Test]
    public function testmail_zeigt_bei_nicht_erreichbarem_server_deutschen_text_ohne_rohmeldung(): void
    {
        $admin = User::factory()->admin()->create();
        $this->unerreichbarerServer();

        $this->actingAs($admin)->post(route('admin.mail.test'), ['test_to' => 'empfang@example.com'])
            ->assertSessionHas('error');

        $this->assertDeutschOhneRohtext(session('error'));
        $log = MailLog::latest('id')->first();
        $this->assertSame(MailLog::FAILED, $log->status);
        $this->assertStringContainsString('Connection', (string) $log->error, 'Rohtext bleibt im Versandprotokoll');
    }

    #[Test]
    public function erneut_senden_zeigt_bei_nicht_erreichbarem_server_deutschen_text_ohne_rohmeldung(): void
    {
        $admin = User::factory()->admin()->create();
        $this->unerreichbarerServer();
        $this->actingAs($admin)->post(route('admin.mail.test'), ['test_to' => 'empfang@example.com']);
        $log = MailLog::latest('id')->first();

        $this->actingAs($admin)->post(route('admin.mail-log.retry', $log->id));

        $toast = session('error');
        $this->assertStringContainsString('Erneuter Versand fehlgeschlagen', (string) $toast);
        $this->assertDeutschOhneRohtext($toast);
    }
}
