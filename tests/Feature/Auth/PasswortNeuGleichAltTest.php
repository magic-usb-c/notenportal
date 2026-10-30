<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Factories\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Funktionsprüfung «auth»: Das Profil lehnt wie der Erstwechsel das bisherige Passwort als neues ab. */
class PasswortNeuGleichAltTest extends TestCase
{
    #[Test]
    public function bisheriges_passwort_als_neues_wird_abgelehnt(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->from(route('settings.profile'))->put(route('password.update'), [
            'current_password' => UserFactory::PASSWORT,
            'password' => UserFactory::PASSWORT,
            'password_confirmation' => UserFactory::PASSWORT,
        ])
            ->assertRedirect(route('settings.profile'))
            ->assertSessionHasErrorsIn('updatePassword', 'password');
    }
}
