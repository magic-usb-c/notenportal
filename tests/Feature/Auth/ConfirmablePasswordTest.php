<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Factories\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** app/Http/Controllers/Auth/ConfirmablePasswordController.php: erneute Passwort-Bestätigung für heikle Aktionen. */
class ConfirmablePasswordTest extends TestCase
{
    #[Test]
    public function seite_wird_fuer_angemeldete_angezeigt(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->get(route('password.confirm'))->assertOk();
    }

    #[Test]
    public function gast_wird_zum_login_umgeleitet(): void
    {
        $this->get(route('password.confirm'))->assertRedirect(route('login'));
    }

    #[Test]
    public function falsches_passwort_wird_abgewiesen(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->post(route('password.confirm.store'), ['password' => 'falsch'])
            ->assertSessionHasErrors('password');
        $this->assertNull(session('auth.password_confirmed_at'));
    }

    #[Test]
    public function richtiges_passwort_bestaetigt_und_leitet_weiter(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->post(route('password.confirm.store'), ['password' => UserFactory::PASSWORT])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertIsInt(session('auth.password_confirmed_at'));
        $this->assertEqualsWithDelta(time(), session('auth.password_confirmed_at'), 5);
    }
}
