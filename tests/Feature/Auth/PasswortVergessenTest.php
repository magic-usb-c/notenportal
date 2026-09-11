<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\PortalMail;
use App\Services\Notifications\NotificationCatalog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswortVergessenTest extends TestCase
{
    #[Test]
    public function link_wird_per_mail_verschickt(): void
    {
        Notification::fake();
        $user = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, PortalMail::class, fn (PortalMail $mail) => $mail->type === NotificationCatalog::PASSWORD_RESET);
    }

    #[Test]
    public function neutrale_antwort_auch_fuer_unbekannte_mail(): void
    {
        $bekannt = User::factory()->lernender()->create(['email' => 'bekannt@firma.ch']);

        $antwortBekannt = $this->post(route('password.email'), ['email' => $bekannt->email]);
        $antwortUnbekannt = $this->post(route('password.email'), ['email' => 'niemand@firma.ch']);

        $antwortBekannt->assertSessionHas('status');
        $antwortUnbekannt->assertSessionHas('status');
        $this->assertSame(
            $antwortBekannt->getSession()->get('status'),
            $antwortUnbekannt->getSession()->get('status'),
        );
    }

    #[Test]
    public function inaktiver_benutzer_bekommt_keinen_link(): void
    {
        Notification::fake();
        $user = User::factory()->lernender()->create(['email' => 'inaktiv@firma.ch', 'aktiv' => false]);

        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect();

        Notification::assertNothingSentTo($user);
    }

    #[Test]
    public function reset_setzt_passwort_und_wechsel_noetig_false(): void
    {
        $user = User::factory()->lernender()->create(['passwort_wechsel_noetig' => true]);
        $token = Password::broker('users')->createToken($user);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NeuesPasswort!2027',
            'password_confirmation' => 'NeuesPasswort!2027',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check('NeuesPasswort!2027', $user->passwort_hash));
        $this->assertFalse((bool) $user->passwort_wechsel_noetig);
    }

    #[Test]
    public function token_aus_dem_einladungs_broker_funktioniert_ebenfalls(): void
    {
        $user = User::factory()->lernender()->create();
        $token = Password::broker('invites')->createToken($user);

        // Älter als die 60 Minuten des «users»-Brokers, aber innerhalb der 7 Tage von «invites».
        $this->travel(90)->minutes();

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NeuesPasswort!2027',
            'password_confirmation' => 'NeuesPasswort!2027',
        ]);

        $response->assertRedirect(route('login'));
        $user->refresh();
        $this->assertTrue(Hash::check('NeuesPasswort!2027', $user->passwort_hash));
    }

    #[Test]
    public function vergessen_link_verfaellt_nach_60_minuten_trotz_einladungs_broker(): void
    {
        $user = User::factory()->lernender()->create();
        $token = Password::broker('users')->createToken($user);

        $this->travel(90)->minutes();

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NeuesPasswort!2027',
            'password_confirmation' => 'NeuesPasswort!2027',
        ])->assertSessionHasErrors('email');

        $this->assertFalse(Hash::check('NeuesPasswort!2027', $user->fresh()->passwort_hash));
    }
}
