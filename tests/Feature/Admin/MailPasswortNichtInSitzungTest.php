<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bei einem Validierungsfehler wird die Eingabe in die Sitzung geflasht (sessions-Tabelle, Klartext).
 * Passwortfelder dürfen dort nie landen.
 */
class MailPasswortNichtInSitzungTest extends TestCase
{
    private const GEHEIM = 'GEHEIM-SMTP-PW-XYZ';

    /** @return array<string, string> */
    private function mailFormMitFehler(): array
    {
        return [
            'mail_host' => 'smtp.beispielbetrieb.ch', 'mail_port' => '587', 'mail_encryption' => 'tls',
            'mail_username' => 'versand', 'mail_password' => self::GEHEIM, 'mail_from_address' => 'kein-mail',
        ];
    }

    private function altEingabe(): string
    {
        return json_encode(session('_old_input') ?? []);
    }

    #[Test]
    public function mail_passwort_landet_bei_validierungsfehler_nicht_in_der_sitzung(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->from(route('admin.operations.edit'))
            ->put(route('admin.mail.update'), $this->mailFormMitFehler())
            ->assertSessionHasErrors('mail_from_address');

        $this->assertArrayNotHasKey('mail_password', session('_old_input') ?? []);
        $this->assertSame('smtp.beispielbetrieb.ch', session('_old_input.mail_host'));
        $this->assertStringNotContainsString(self::GEHEIM, $this->altEingabe());
    }

    #[Test]
    public function mail_passwort_landet_im_einrichtungsschritt_nicht_in_der_sitzung(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->from(route('admin.setup', 'mail'))
            ->post(route('admin.setup.mail'), $this->mailFormMitFehler())
            ->assertSessionHasErrors('mail_from_address');

        $this->assertArrayNotHasKey('mail_password', session('_old_input') ?? []);
        $this->assertStringNotContainsString(self::GEHEIM, $this->altEingabe());
    }

    #[Test]
    public function passwortfelder_des_admin_formulars_werden_bei_validierungsfehler_nicht_geflasht(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->from(route('admin.users.create'))
            ->post(route('admin.users.store'), [
                'vorname' => 'Test', 'nachname' => 'Person', 'email' => 'test.person@example.local',
                'passwort' => self::GEHEIM, 'passwort_confirmation' => self::GEHEIM.'x',
            ])
            ->assertSessionHasErrors('passwort');

        $alt = session('_old_input') ?? [];
        $this->assertArrayNotHasKey('passwort', $alt);
        $this->assertArrayNotHasKey('passwort_confirmation', $alt);
    }
}
