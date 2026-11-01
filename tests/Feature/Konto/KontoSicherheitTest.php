<?php

declare(strict_types=1);

namespace Tests\Feature\Konto;

use App\Models\User;
use App\Services\Notifications\AccountMails;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Gruppe «konto-sicherheit»: Cache-Header der Datenauskunft, Vergessen-Link nach Admin-Reset, Fehlertext bei ungültiger Signatur. */
class KontoSicherheitTest extends TestCase
{
    #[Test]
    public function datenauskunft_downloads_sind_nicht_cachebar(): void
    {
        Storage::fake('local');
        $lernender = User::factory()->lernender()->create();
        $admin = User::factory()->admin()->create();

        $eigene = $this->actingAs($lernender)->withSession(['auth.password_confirmed_at' => time()])->get('/profile/data-export');
        $eigene->assertOk();
        $this->assertStringContainsString('no-store', (string) $eigene->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $eigene->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', (string) $eigene->headers->get('Cache-Control'));

        $fremde = $this->actingAs($admin)->get('/admin/users/'.$lernender->benutzer_id.'/data-export');
        $fremde->assertOk();
        $this->assertStringContainsString('no-store', (string) $fremde->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', (string) $fremde->headers->get('Cache-Control'));
    }

    #[Test]
    public function alter_vergessen_link_ist_nach_admin_reset_ungueltig(): void
    {
        $admin = User::factory()->admin()->create();
        $lernender = User::factory()->lernender()->create();
        $token = Password::broker('users')->createToken($lernender);

        $this->actingAs($admin)->post('/admin/learners/'.$lernender->lernender->lernender_id.'/account/password')->assertSessionHas('success');
        auth()->logout();
        $this->flushSession();

        $this->post('/reset-password', ['token' => $token, 'email' => $lernender->email, 'password' => 'Angreifer-9999-Zz', 'password_confirmation' => 'Angreifer-9999-Zz']);
        $this->assertFalse(Hash::check('Angreifer-9999-Zz', $lernender->fresh()->passwort_hash));
    }

    #[Test]
    public function alter_vergessen_link_ist_nach_admin_reset_eines_deaktivierten_kontos_ungueltig(): void
    {
        $lernender = User::factory()->lernender()->create(['aktiv' => false]);
        $token = Password::broker('users')->createToken($lernender);

        AccountMails::passwordResetByAdmin($lernender);

        $this->assertFalse(Password::broker('users')->tokenExists($lernender, $token));
    }

    #[Test]
    public function ungueltige_signatur_zeigt_deutsche_meldung(): void
    {
        $user = User::factory()->lernender()->create();
        $url = URL::signedRoute('notifications.unsubscribe', ['user' => $user->benutzer_id, 'type' => 'learner_assigned']);

        $antwort = $this->get($url.'x');
        $antwort->assertStatus(403);
        $this->assertStringNotContainsString('Invalid signature', $antwort->getContent());
        $antwort->assertSee(__('Dieser Link ist nicht mehr gültig.'));
    }
}
