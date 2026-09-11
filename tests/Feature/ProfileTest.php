<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see ThemeTest für Präferenz-Vorrang und Theme::fuer()
 */
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

    #[Test]
    public function darstellungspraeferenzen_werden_gespeichert(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'email' => $user->email,
                'darstellung' => 'system',
                'theme' => 'wald',
                'akzent' => 'petrol',
                'schrift' => 'gross',
                'bewegung_reduziert' => '1',
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame([
            'theme' => 'wald',
            'akzent' => 'petrol',
            'schrift' => 'gross',
            'bewegung' => 'reduziert',
        ], $user->praeferenzen);
        $this->assertFalse($user->kontrast);
    }

    #[Test]
    public function darstellungspraeferenzen_ohne_theme_und_akzent_werden_als_wie_betrieb_gespeichert(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'email' => $user->email,
                'darstellung' => 'system',
                'theme' => '',
                'akzent' => '',
                'schrift' => 'normal',
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNull($user->praeferenzen['theme']);
        $this->assertNull($user->praeferenzen['akzent']);
        $this->assertSame('normal', $user->praeferenzen['schrift']);
        $this->assertSame('normal', $user->praeferenzen['bewegung']);
    }

    public static function ungueltigeDarstellungsfelder(): array
    {
        return [
            'unbekanntes Theme' => ['theme', 'neon'],
            'unbekannter Akzent' => ['akzent', 'himbeer'],
            'unbekannte Schriftgroesse' => ['schrift', 'winzig'],
        ];
    }

    #[Test]
    #[DataProvider('ungueltigeDarstellungsfelder')]
    public function ungueltige_darstellungswerte_werden_abgewiesen(string $feld, string $wert): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'email' => $user->email,
                'darstellung' => 'system',
                $feld => $wert,
            ])
            ->assertSessionHasErrors($feld);

        $this->assertNull($user->refresh()->praeferenzen);
    }
}
