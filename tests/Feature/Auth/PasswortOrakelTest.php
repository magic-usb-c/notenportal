<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Funktionsprüfung «auth»: Das aktuelle Passwort darf bei übernommener Sitzung nicht unbegrenzt
 * erraten werden. Gezählt werden nur Fehlversuche, die Sperre kommt als Formularfehler (kein 429).
 */
class PasswortOrakelTest extends TestCase
{
    private function falschesPasswortAendern(User $user, int $mal): void
    {
        for ($i = 0; $i < $mal; $i++) {
            $this->actingAs($user)->from(route('settings.profile'))->put(route('password.update'), [
                'current_password' => 'falsch-'.$i,
                'password' => 'NeuesPasswort!2026',
                'password_confirmation' => 'NeuesPasswort!2026',
            ]);
        }
    }

    #[Test]
    public function passwort_aendern_sperrt_nach_fuenf_fehlversuchen_auch_bei_richtigem_passwort(): void
    {
        $user = User::factory()->lernender()->create();
        $this->falschesPasswortAendern($user, 5);

        $this->actingAs($user)->from(route('settings.profile'))->put(route('password.update'), [
            'current_password' => UserFactory::PASSWORT,
            'password' => 'NeuesPasswort!2026',
            'password_confirmation' => 'NeuesPasswort!2026',
        ])
            ->assertRedirect(route('settings.profile'))
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->assertTrue(Hash::check(UserFactory::PASSWORT, $user->refresh()->passwort_hash));
    }

    #[Test]
    public function passwort_bestaetigen_sperrt_nach_fuenf_fehlversuchen(): void
    {
        $user = User::factory()->lernender()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->from(route('password.confirm'))
                ->post(route('password.confirm.store'), ['password' => 'falsch-'.$i]);
        }

        $this->actingAs($user)->from(route('password.confirm'))
            ->post(route('password.confirm.store'), ['password' => UserFactory::PASSWORT])
            ->assertRedirect(route('password.confirm'))
            ->assertSessionHasErrors('password');
        $this->assertNull(session('auth.password_confirmed_at'));
    }

    #[Test]
    public function email_aendern_sperrt_nach_fuenf_fehlversuchen(): void
    {
        $user = User::factory()->lernender()->create();
        $alt = $user->email;
        $senden = fn (string $passwort) => $this->actingAs($user)->from(route('settings.profile'))
            ->patch(route('profile.update'), [
                'email' => 'neu@example.local',
                'current_password' => $passwort,
                'klasse_schule' => '',
            ]);

        for ($i = 0; $i < 5; $i++) {
            $senden('falsch-'.$i)->assertSessionHasErrors('current_password');
        }

        $senden(UserFactory::PASSWORT)
            ->assertRedirect(route('settings.profile'))
            ->assertSessionHasErrors('current_password');
        $this->assertSame($alt, $user->refresh()->email);
    }

    #[Test]
    public function die_sperre_gilt_gemeinsam_fuer_alle_drei_stellen(): void
    {
        $user = User::factory()->lernender()->create();
        $this->falschesPasswortAendern($user, 5);

        $this->actingAs($user)->from(route('password.confirm'))
            ->post(route('password.confirm.store'), ['password' => UserFactory::PASSWORT])
            ->assertSessionHasErrors('password');
    }

    #[Test]
    public function richtiges_passwort_und_profil_ohne_email_aenderung_zaehlen_nicht(): void
    {
        $user = User::factory()->lernender()->create();

        for ($i = 0; $i < 8; $i++) {
            $this->actingAs($user)->from(route('settings.profile'))->patch(route('profile.update'), [
                'email' => $user->email,
                'klasse_schule' => 'IN1a',
            ])->assertSessionHasNoErrors();
        }
        for ($i = 0; $i < 8; $i++) {
            $this->actingAs($user)->post(route('password.confirm.store'), ['password' => UserFactory::PASSWORT])
                ->assertSessionHasNoErrors();
        }
    }

    #[Test]
    public function erfolgreiche_bestaetigung_setzt_den_zaehler_zurueck(): void
    {
        $user = User::factory()->lernender()->create();
        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($user)->post(route('password.confirm.store'), ['password' => 'falsch-'.$i]);
        }
        $this->actingAs($user)->post(route('password.confirm.store'), ['password' => UserFactory::PASSWORT])
            ->assertSessionHasNoErrors();

        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($user)->post(route('password.confirm.store'), ['password' => 'falsch-'.$i]);
        }
        $this->actingAs($user)->post(route('password.confirm.store'), ['password' => UserFactory::PASSWORT])
            ->assertSessionHasNoErrors();
    }
}
