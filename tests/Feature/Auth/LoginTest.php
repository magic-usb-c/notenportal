<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Factories\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginTest extends TestCase
{
    public static function rollen(): array
    {
        return [
            'Admin' => ['admin', 'admin.dashboard'],
            'Berufsbildner' => ['berufsbildner', 'trainer.dashboard'],
            'Lernender' => ['lernender', 'learner.dashboard'],
        ];
    }

    #[Test]
    public function loginseite_wird_angezeigt(): void
    {
        $this->get('/login')->assertOk()->assertSee('Passwort vergessen');
    }

    #[Test]
    #[DataProvider('rollen')]
    public function login_fuehrt_auf_das_dashboard_der_rolle(string $rolle, string $dashboard): void
    {
        $user = User::factory()->{$rolle}()->create();

        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::PASSWORT])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->get('/dashboard')->assertRedirect(route($dashboard));
        $this->get(route($dashboard))->assertOk();
    }

    #[Test]
    public function falsches_passwort_wird_abgewiesen(): void
    {
        $user = User::factory()->lernender()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'falsch'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    #[Test]
    public function inaktive_benutzer_koennen_sich_nicht_anmelden(): void
    {
        $user = User::factory()->lernender()->inaktiv()->create();

        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::PASSWORT])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    #[Test]
    public function geloeschte_benutzer_koennen_sich_nicht_anmelden(): void
    {
        $user = User::factory()->lernender()->create();
        $user->delete();

        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::PASSWORT])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    #[Test]
    public function nach_fuenf_fehlversuchen_wird_gesperrt(): void
    {
        $user = User::factory()->lernender()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'falsch']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::PASSWORT])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    #[Test]
    public function deaktivierung_beendet_die_laufende_sitzung(): void
    {
        $user = User::factory()->lernender()->create();
        $this->actingAs($user);

        $user->update(['aktiv' => false]);

        $this->get(route('learner.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    #[Test]
    public function logout_beendet_die_sitzung(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    #[Test]
    public function registrierung_existiert_nicht(): void
    {
        foreach (['/register', '/verify-email'] as $uri) {
            $this->get($uri)->assertNotFound();
        }
    }

    #[Test]
    public function passwortbestaetigung_existiert_und_verlangt_anmeldung(): void
    {
        $this->get('/confirm-password')->assertRedirect(route('login'));
    }

    #[Test]
    public function passwort_vergessen_und_zuruecksetzen_sind_erreichbar(): void
    {
        $this->get('/forgot-password')->assertOk();
        $this->get('/reset-password/irgendein-token')->assertOk();
    }
}
