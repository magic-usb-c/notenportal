<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Nach jeder Passwortänderung sind offene Links beider Broker («users» und «invites») ungültig. */
class PasswortLinksNachWechselTest extends TestCase
{
    private const string NEU = 'Selbstgewaehlt-9981-Xq';

    private const string ANGREIFER = 'Angreifer-Pass-7712-Zz';

    private function offeneTokens(User $user): int
    {
        return DB::table('password_reset_tokens')->where('email', $user->email)->count()
            + DB::table('password_invite_tokens')->where('email', $user->email)->count();
    }

    private function versuchMitAltemLink(User $user, string $token): void
    {
        $this->post('/reset-password', [
            'token' => $token, 'email' => $user->email,
            'password' => self::ANGREIFER, 'password_confirmation' => self::ANGREIFER,
        ])->assertSessionHasErrors('email');

        $this->assertFalse(Hash::check(self::ANGREIFER, $user->fresh()->passwort_hash));
    }

    #[Test]
    public function einladungslink_ist_nach_passwort_vergessen_ungueltig(): void
    {
        $user = User::factory()->lernender()->create();
        $einladung = Password::broker('invites')->createToken($user);
        $vergessen = Password::broker('users')->createToken($user);

        $this->post('/reset-password', [
            'token' => $vergessen, 'email' => $user->email,
            'password' => self::NEU, 'password_confirmation' => self::NEU,
        ])->assertRedirect('/login');

        $this->assertTrue(Hash::check(self::NEU, $user->fresh()->passwort_hash));
        $this->assertSame(0, $this->offeneTokens($user));
        $this->versuchMitAltemLink($user, $einladung);
    }

    #[Test]
    public function vergessen_link_ist_nach_einladungslink_ungueltig(): void
    {
        $user = User::factory()->lernender()->create();
        $vergessen = Password::broker('users')->createToken($user);
        $einladung = Password::broker('invites')->createToken($user);

        $this->post('/reset-password', [
            'token' => $einladung, 'email' => $user->email,
            'password' => self::NEU, 'password_confirmation' => self::NEU,
        ])->assertRedirect('/login');

        $this->assertSame(0, $this->offeneTokens($user));
        $this->versuchMitAltemLink($user, $vergessen);
    }

    #[Test]
    public function einladungslink_ist_nach_passwortwechsel_im_profil_ungueltig(): void
    {
        $user = User::factory()->lernender()->create();
        $einladung = Password::broker('invites')->createToken($user);
        $vergessen = Password::broker('users')->createToken($user);

        $this->actingAs($user)->put('/password', [
            'current_password' => UserFactory::PASSWORT,
            'password' => self::NEU, 'password_confirmation' => self::NEU,
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check(self::NEU, $user->fresh()->passwort_hash));
        $this->assertSame(0, $this->offeneTokens($user));
        auth()->logout();
        $this->versuchMitAltemLink($user, $einladung);
        $this->versuchMitAltemLink($user, $vergessen);
    }

    #[Test]
    public function einladungslink_ist_nach_dem_erstpasswort_ungueltig(): void
    {
        $user = User::factory()->lernender()->create(['passwort_wechsel_noetig' => true]);
        $einladung = Password::broker('invites')->createToken($user);

        $this->actingAs($user)->put(route('password.initial.update'), [
            'password' => self::NEU, 'password_confirmation' => self::NEU,
        ])->assertRedirect(route('dashboard'));

        $this->assertTrue(Hash::check(self::NEU, $user->fresh()->passwort_hash));
        $this->assertSame(0, $this->offeneTokens($user));
        auth()->logout();
        $this->versuchMitAltemLink($user, $einladung);
    }
}
