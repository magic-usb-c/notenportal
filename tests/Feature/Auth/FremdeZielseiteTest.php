<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Factories\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Mail-Links an eine Adresse einer anderen Rolle führten nach dem Login auf eine 403-Seite
 * (vorgemerkte Ziel-URL), und die 403-Seite bot keinen Kontowechsel.
 */
class FremdeZielseiteTest extends TestCase
{
    #[Test]
    public function ziel_url_einer_fremden_rolle_wird_nach_dem_login_verworfen(): void
    {
        $lernender = User::factory()->lernender()->create();

        $this->get('/admin/feedback')->assertRedirect(route('login'));

        $this->post('/login', ['email' => $lernender->email, 'password' => UserFactory::PASSWORT])
            ->assertRedirect('/dashboard');
        $this->assertNull(session('url.intended'));
    }

    #[Test]
    public function ziel_url_der_eigenen_rolle_bleibt_erhalten(): void
    {
        $admin = User::factory()->admin()->create();

        $this->get('/admin/feedback')->assertRedirect(route('login'));

        $this->post('/login', ['email' => $admin->email, 'password' => UserFactory::PASSWORT])
            ->assertRedirect('/admin/feedback');
    }

    #[Test]
    public function die_403_seite_bietet_den_kontowechsel_an(): void
    {
        $lernender = User::factory()->lernender()->create();

        $this->actingAs($lernender)->get('/admin/feedback')
            ->assertForbidden()
            ->assertSee($lernender->email)
            ->assertSee('Mit anderem Konto anmelden')
            ->assertSee('name="weiter" value="/admin/feedback"', false);
    }

    #[Test]
    public function kontowechsel_meldet_ab_und_merkt_die_seite_vor(): void
    {
        $lernender = User::factory()->lernender()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($lernender)->post('/logout', ['weiter' => '/admin/feedback'])
            ->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post('/login', ['email' => $admin->email, 'password' => UserFactory::PASSWORT])
            ->assertRedirect('/admin/feedback');
    }

    #[Test]
    public function kontowechsel_akzeptiert_keine_fremden_hosts(): void
    {
        $lernender = User::factory()->lernender()->create();

        $this->actingAs($lernender)->post('/logout', ['weiter' => '//boese.example/x'])
            ->assertRedirect('/');
        $this->assertNull(session('url.intended'));
    }
}
