<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Einstellungen;
use App\Support\Theme;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    #[Test]
    public function standard_ist_gletscher_auch_bei_unbekanntem_wert(): void
    {
        $this->get('/login')->assertSee('data-theme="gletscher"', false);

        Einstellungen::set(Einstellungen::THEME, 'neon');
        $this->assertSame('gletscher', Theme::betrieb());
    }

    #[Test]
    public function admin_speichert_farbthema_und_es_steht_im_html(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.operations.theme.update'), ['theme' => 'sandstein'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.operations.edit'));

        $this->assertSame('sandstein', Einstellungen::get(Einstellungen::THEME));
        $this->actingAs($admin)->get(route('admin.operations.edit'))
            ->assertOk()
            ->assertSee('data-theme="sandstein"', false);
    }

    #[Test]
    public function unbekanntes_theme_wird_abgewiesen(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.operations.theme.update'), ['theme' => 'neon'])
            ->assertSessionHasErrors('theme');

        $this->assertNull(Einstellungen::get(Einstellungen::THEME));
    }

    public static function andereRollen(): array
    {
        return [['berufsbildner'], ['lernender']];
    }

    #[Test]
    #[DataProvider('andereRollen')]
    public function nur_admin_darf_das_farbthema_setzen(string $rolle): void
    {
        $this->actingAs(User::factory()->{$rolle}()->create())
            ->put(route('admin.operations.theme.update'), ['theme' => 'pflaume'])
            ->assertForbidden();

        $this->assertNull(Einstellungen::get(Einstellungen::THEME));
    }

    #[Test]
    public function betriebs_theme_gilt_fuer_app_und_anmeldeseite(): void
    {
        Einstellungen::set(Einstellungen::THEME, 'pflaume');

        $this->get('/login')
            ->assertSee('data-theme="pflaume"', false)
            ->assertDontSee('fonts.bunny.net');

        $this->actingAs(User::factory()->lernender()->create())
            ->get(route('learner.dashboard'))
            ->assertSee('data-theme="pflaume"', false);
    }

    #[Test]
    public function persoenlicher_kontrast_ueberschreibt_das_betriebs_theme(): void
    {
        Einstellungen::set(Einstellungen::THEME, 'sandstein');
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->get(route('profile.edit'))->assertSee('name="kontrast"', false);

        $this->actingAs($user)
            ->patch(route('profile.update'), ['email' => $user->email, 'darstellung' => 'hell', 'kontrast' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($user->refresh()->kontrast);
        $this->actingAs($user)->get(route('learner.dashboard'))->assertSee('data-theme="kontrast"', false);

        $this->actingAs($user)
            ->patch(route('profile.update'), ['email' => $user->email, 'darstellung' => 'hell'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($user->refresh()->kontrast);
        $this->actingAs($user)->get(route('learner.dashboard'))->assertSee('data-theme="sandstein"', false);
    }
}
