<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    public static function rollen(): array
    {
        return [['admin'], ['berufsbildner'], ['lernender']];
    }

    #[Test]
    #[DataProvider('rollen')]
    public function profilseite_wird_angezeigt(string $rolle): void
    {
        $this->actingAs(User::factory()->{$rolle}()->create())
            ->get(route('profile.edit'))
            ->assertOk();
    }

    #[Test]
    public function passwort_wird_mit_aktuellem_passwort_geaendert(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => UserFactory::PASSWORT,
                'password' => 'NeuesPasswort!2026',
                'password_confirmation' => 'NeuesPasswort!2026',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('NeuesPasswort!2026', $user->refresh()->passwort_hash));
    }

    #[Test]
    public function falsches_aktuelles_passwort_wird_abgewiesen(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => 'falsch',
                'password' => 'NeuesPasswort!2026',
                'password_confirmation' => 'NeuesPasswort!2026',
            ])
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->assertTrue(Hash::check(UserFactory::PASSWORT, $user->refresh()->passwort_hash));
    }
}
