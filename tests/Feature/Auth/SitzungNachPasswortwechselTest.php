<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Funktionsprüfung «auth»: Ein Passwortwechsel muss Sitzungen auf anderen Geräten beenden
 * (AuthenticateSession in der web-Gruppe), die eigene Sitzung bleibt gültig.
 */
class SitzungNachPasswortwechselTest extends TestCase
{
    private function sitzungsHash(User $user): string
    {
        return Auth::guard('web')->hashPasswordForCookie($user->passwort_hash);
    }

    #[Test]
    public function passwortwechsel_im_profil_beendet_die_sitzung_auf_dem_anderen_geraet(): void
    {
        $user = User::factory()->lernender()->create();
        $altHash = $this->sitzungsHash($user);

        $this->actingAs($user)
            ->withSession(['password_hash_web' => $altHash])
            ->from(route('settings.profile'))
            ->put(route('password.update'), [
                'current_password' => UserFactory::PASSWORT,
                'password' => 'NeuesPasswort!2026',
                'password_confirmation' => 'NeuesPasswort!2026',
            ])
            ->assertSessionHasNoErrors();

        // Die eigene Sitzung hat den neuen Hash übernommen und bleibt gültig.
        $this->assertSame($this->sitzungsHash($user->refresh()), session('password_hash_web'));

        // Das andere Gerät trägt noch den alten Hash und fliegt raus.
        Auth::forgetGuards();
        $this->actingAs($user->fresh())
            ->withSession(['password_hash_web' => $altHash])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function die_eigene_sitzung_bleibt_nach_dem_wechsel_gueltig(): void
    {
        $user = User::factory()->lernender()->create();
        $altHash = $this->sitzungsHash($user);

        $this->actingAs($user)
            ->withSession(['password_hash_web' => $altHash])
            ->put(route('password.update'), [
                'current_password' => UserFactory::PASSWORT,
                'password' => 'NeuesPasswort!2026',
                'password_confirmation' => 'NeuesPasswort!2026',
            ]);

        $neuerHash = session('password_hash_web');
        Auth::forgetGuards();
        $this->actingAs($user->fresh())
            ->withSession(['password_hash_web' => $neuerHash])
            ->get(route('settings.profile'))
            ->assertOk();
    }

    #[Test]
    public function passwortwechsel_durch_reset_beendet_offene_sitzungen(): void
    {
        $user = User::factory()->lernender()->create();
        $altHash = $this->sitzungsHash($user);

        // Reset/Einladung ändert den Hash ausserhalb der Sitzung des Benutzers.
        $user->forceFill(['passwort_hash' => 'AndereskPasswort!2026'])->save();

        Auth::forgetGuards();
        $this->actingAs($user->fresh())
            ->withSession(['password_hash_web' => $altHash])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function json_anfrage_einer_beendeten_sitzung_bekommt_401(): void
    {
        $user = User::factory()->lernender()->create();
        $altHash = $this->sitzungsHash($user);
        $user->forceFill(['passwort_hash' => 'AndereskPasswort!2026'])->save();

        Auth::forgetGuards();
        $this->actingAs($user->fresh())
            ->withSession(['password_hash_web' => $altHash])
            ->postJson(route('session.keep-alive'))
            ->assertUnauthorized();
    }
}
