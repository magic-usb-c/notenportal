<?php

namespace Tests\Feature\Admin;

use App\Services\Notifications\MailSettings;
use App\Support\Einstellungen;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Tests\TestCase;

/** Das Mailpasswort der Installation (.env) darf nie an einen Server gehen, den jemand in der Oberfläche einträgt. */
class MailPasswortRueckfallTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->envNeuLesen();
        config([
            'mail.mailers.smtp.host' => 'smtp.installation.example',
            'mail.mailers.smtp.username' => 'versand@installation.example',
            'mail.mailers.smtp.password' => 'Installationspasswort',
        ]);
    }

    protected function tearDown(): void
    {
        $this->envNeuLesen();
        parent::tearDown();
    }

    #[Test]
    public function fremder_host_ohne_eigenes_passwort_bekommt_das_installationspasswort_nicht(): void
    {
        Einstellungen::set(MailSettings::HOST, 'mail.fremd.example');

        MailSettings::apply();

        $this->assertSame('mail.fremd.example', config('mail.mailers.smtp.host'));
        $this->assertNull(config('mail.mailers.smtp.password'));
    }

    #[Test]
    public function gleicher_host_ohne_eigenes_passwort_behaelt_das_installationspasswort(): void
    {
        Einstellungen::set(MailSettings::HOST, 'SMTP.Installation.example');

        MailSettings::apply();

        $this->assertSame('Installationspasswort', config('mail.mailers.smtp.password'));
    }

    #[Test]
    public function gleicher_host_ohne_verschluesselung_bekommt_das_installationspasswort_nicht(): void
    {
        Einstellungen::set(MailSettings::HOST, 'smtp.installation.example');
        Einstellungen::set(MailSettings::ENCRYPTION, 'none');

        MailSettings::apply();

        $this->assertNull(config('mail.mailers.smtp.password'));
    }

    #[Test]
    public function gleicher_host_ohne_benutzer_behaelt_das_installationspaar(): void
    {
        Einstellungen::set(MailSettings::HOST, 'smtp.installation.example');
        Einstellungen::set(MailSettings::PORT, '587');
        Einstellungen::set(MailSettings::ENCRYPTION, 'tls');

        MailSettings::apply();

        $this->assertSame('versand@installation.example', config('mail.mailers.smtp.username'));
        $this->assertSame('Installationspasswort', config('mail.mailers.smtp.password'));
    }

    #[Test]
    public function gleicher_host_mit_eigenem_benutzer_ohne_passwort_bekommt_das_installationspasswort_nicht(): void
    {
        Einstellungen::set(MailSettings::HOST, 'smtp.installation.example');
        Einstellungen::set(MailSettings::USERNAME, 'anderer@installation.example');

        MailSettings::apply();

        $this->assertSame('anderer@installation.example', config('mail.mailers.smtp.username'));
        $this->assertNull(config('mail.mailers.smtp.password'));
    }

    #[Test]
    public function gleicher_host_mit_installationsbenutzer_bekommt_das_installationspasswort(): void
    {
        Einstellungen::set(MailSettings::HOST, 'smtp.installation.example');
        Einstellungen::set(MailSettings::USERNAME, 'Versand@Installation.example');

        MailSettings::apply();

        $this->assertSame('Installationspasswort', config('mail.mailers.smtp.password'));
    }

    #[Test]
    public function fremder_host_und_ohne_verschluesselung_bekommen_den_installationsbenutzer_nicht(): void
    {
        Einstellungen::set(MailSettings::HOST, 'mail.fremd.example');
        MailSettings::apply();
        $this->assertNull(config('mail.mailers.smtp.username'));

        Einstellungen::set(MailSettings::HOST, 'smtp.installation.example');
        Einstellungen::set(MailSettings::ENCRYPTION, 'none');
        MailSettings::apply();
        $this->assertNull(config('mail.mailers.smtp.username'));
        $this->assertNull(config('mail.mailers.smtp.password'));
    }

    /** MailSettings hält die .env-Werte pro Prozess fest; jeder Test liest sie frisch. */
    private function envNeuLesen(): void
    {
        (new ReflectionProperty(MailSettings::class, 'envCaptured'))->setValue(null, false);
    }
}
