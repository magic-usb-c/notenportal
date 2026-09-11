<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use App\Support\Einstellungen;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UmschalterTest extends TestCase
{
    private function sprachwahl(bool $aktiv, string $standard = 'de'): void
    {
        Einstellungen::set(SetLocale::WAHL_AKTIV, $aktiv ? '1' : '0');
        Einstellungen::set(SetLocale::STANDARD, $standard);
    }

    #[Test]
    public function benutzer_speichert_seine_sprache(): void
    {
        $this->sprachwahl(true);
        $benutzer = User::factory()->lernender()->create();

        $this->actingAs($benutzer)->from(route('profile.edit'))
            ->put(route('profile.locale'), ['locale' => 'en'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success', 'Language saved.');

        $this->assertSame('en', $benutzer->refresh()->locale);
        $this->actingAs($benutzer)->get(route('profile.edit'))->assertSee('<html lang="en"', false)->assertSee('My profile');
    }

    #[Test]
    public function nur_deutsch_und_englisch_sind_erlaubt(): void
    {
        $this->sprachwahl(true);
        $benutzer = User::factory()->lernender()->create();

        $this->actingAs($benutzer)->put(route('profile.locale'), ['locale' => 'fr'])->assertSessionHasErrors('locale');
        $this->assertNull($benutzer->refresh()->locale);
    }

    #[Test]
    public function ohne_sprachwahl_gibt_es_keinen_umschalter(): void
    {
        $this->sprachwahl(false);
        $benutzer = User::factory()->lernender()->create(['locale' => 'en']);

        $this->actingAs($benutzer)->put(route('profile.locale'), ['locale' => 'en'])->assertNotFound();
        $this->actingAs($benutzer)->get(route('profile.edit'))
            ->assertSee('<html lang="de"', false)
            ->assertDontSee(route('profile.locale'), false);
    }

    #[Test]
    public function mit_sprachwahl_erscheint_der_umschalter(): void
    {
        $this->sprachwahl(true);

        $this->actingAs(User::factory()->lernender()->create())->get(route('profile.edit'))
            ->assertSee(route('profile.locale'), false)
            ->assertSee('English');
    }

    #[Test]
    public function gast_mit_englischem_browser_sieht_englisch(): void
    {
        $this->sprachwahl(true);

        $this->withHeader('Accept-Language', 'en-GB,en;q=0.8,de;q=0.5')->get('/login')
            ->assertSee('<html lang="en"', false)
            ->assertSee('Log in');
        $this->withHeader('Accept-Language', 'fr-CH,fr;q=0.9')->get('/login')->assertSee('<html lang="de"', false);
    }

    #[Test]
    public function gast_ohne_sprachwahl_sieht_die_standardsprache(): void
    {
        $this->sprachwahl(false);
        $this->withHeader('Accept-Language', 'en')->get('/login')->assertSee('<html lang="de"', false)->assertSee('Anmelden');

        $this->sprachwahl(false, 'en');
        $this->get('/login')->assertSee('<html lang="en"', false);
    }

    #[Test]
    public function gast_waehlt_die_sprache_fuer_die_sitzung(): void
    {
        $this->sprachwahl(true);

        $this->put(route('profile.locale'), ['locale' => 'en'])->assertRedirect()->assertSessionHas('locale', 'en');
        $this->withHeader('Accept-Language', 'de')->get('/login')->assertSee('<html lang="en"', false);
    }

    #[Test]
    public function admin_stellt_standardsprache_und_sprachwahl_ein(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.operations.edit'))->assertOk()->assertSee('sprache_standard', false);
        $this->actingAs($admin)
            ->put(route('admin.operations.language.update'), ['sprache_standard' => 'en', 'sprachwahl_aktiv' => '1'])
            ->assertRedirect(route('admin.operations.edit'));

        $this->assertSame('en', Einstellungen::get(SetLocale::STANDARD));
        $this->assertSame('1', Einstellungen::get(SetLocale::WAHL_AKTIV));

        $this->actingAs($admin)->put(route('admin.operations.language.update'), ['sprache_standard' => 'de'])->assertRedirect();
        $this->assertSame('0', Einstellungen::get(SetLocale::WAHL_AKTIV));
    }

    #[Test]
    public function nur_admins_stellen_die_sprache_ein(): void
    {
        $this->actingAs(User::factory()->berufsbildner()->create())
            ->put(route('admin.operations.language.update'), ['sprache_standard' => 'en'])
            ->assertForbidden();
    }
}
