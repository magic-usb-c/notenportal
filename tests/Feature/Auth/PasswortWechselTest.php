<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswortWechselTest extends TestCase
{
    private function mitStartpasswort(): User
    {
        return User::factory()->lernender()->create(['passwort_wechsel_noetig' => true]);
    }

    #[Test]
    public function konto_mit_startpasswort_wird_zum_wechsel_umgeleitet(): void
    {
        $this->actingAs($this->mitStartpasswort());

        $this->get(route('learner.dashboard'))->assertRedirect(route('password.initial'));
        $this->get(route('learner.grades.index'))->assertRedirect(route('password.initial'));
        $this->get(route('password.initial'))->assertOk();
    }

    #[Test]
    public function neues_passwort_hebt_die_pflicht_auf(): void
    {
        $user = $this->mitStartpasswort();

        $this->actingAs($user)
            ->put(route('password.initial.update'), [
                'password' => 'EigenesPasswort2026',
                'password_confirmation' => 'EigenesPasswort2026',
            ])
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->passwort_wechsel_noetig);
        $this->assertTrue(Hash::check('EigenesPasswort2026', $user->passwort_hash));
        $this->get(route('learner.dashboard'))->assertOk();
    }

    #[Test]
    public function startpasswort_darf_nicht_wiederverwendet_werden(): void
    {
        $user = $this->mitStartpasswort();

        $this->actingAs($user)
            ->from(route('password.initial'))
            ->put(route('password.initial.update'), [
                'password' => UserFactory::PASSWORT,
                'password_confirmation' => UserFactory::PASSWORT,
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue($user->refresh()->passwort_wechsel_noetig);
    }

    #[Test]
    public function abmelden_bleibt_moeglich(): void
    {
        $this->actingAs($this->mitStartpasswort())->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    #[Test]
    public function ohne_pflicht_fuehrt_die_seite_zum_dashboard(): void
    {
        $this->actingAs(User::factory()->lernender()->create())
            ->get(route('password.initial'))
            ->assertRedirect(route('dashboard'));
    }
}
