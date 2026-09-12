<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Darstellung;
use App\Support\Einstellungen;
use App\Support\Theme;
use Illuminate\Support\Facades\Schema;
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
    public function persoenliches_theme_ueberschreibt_das_betriebs_theme(): void
    {
        Einstellungen::set(Einstellungen::THEME, 'sandstein');
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->get(route('settings.profile'))->assertSee('name="theme"', false);

        $this->actingAs($user)
            ->patch(route('profile.update'), ['email' => $user->email, 'darstellung' => 'hell', 'theme' => 'kontrast'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($user->refresh()->kontrast);
        $this->assertSame('kontrast', $user->praeferenzen['theme']);
        $this->actingAs($user)->get(route('learner.dashboard'))->assertSee('data-theme="kontrast"', false);

        $this->actingAs($user)
            ->patch(route('profile.update'), ['email' => $user->email, 'darstellung' => 'hell', 'theme' => ''])
            ->assertSessionHasNoErrors();
        $this->assertFalse($user->refresh()->kontrast);
        $this->assertNull($user->praeferenzen['theme']);
        $this->actingAs($user)->get(route('learner.dashboard'))->assertSee('data-theme="sandstein"', false);
    }

    #[Test]
    public function praeferenz_hat_vorrang_vor_altem_kontrast_flag_und_betrieb(): void
    {
        Einstellungen::set(Einstellungen::THEME, 'sandstein');

        // Weder Präferenz noch Kontrast-Flag: Betriebs-Theme gilt.
        $user = User::factory()->lernender()->create(['kontrast' => false, 'praeferenzen' => null]);
        $this->assertSame('sandstein', Theme::fuer($user));

        // Nur das alte Kontrast-Flag: greift wie bisher.
        $user->kontrast = true;
        $user->save();
        $this->assertSame(Theme::KONTRAST, Theme::fuer($user->refresh()));

        // Persönliches Theme sticht das alte Kontrast-Flag: höchste Priorität.
        $user->praeferenzen = ['theme' => 'wald'];
        $user->save();
        $this->assertSame('wald', Theme::fuer($user->refresh()));
    }

    #[Test]
    public function ungueltige_praeferenzen_fallen_still_auf_den_standard_zurueck(): void
    {
        $user = User::factory()->lernender()->create([
            'praeferenzen' => ['theme' => 'neon', 'akzent' => 'himbeer', 'schrift' => 'winzig', 'bewegung' => 'schnell'],
        ]);

        $praeferenzen = Darstellung::fuer($user);

        $this->assertNull($praeferenzen['theme']);
        $this->assertNull($praeferenzen['akzent']);
        $this->assertSame(Darstellung::SCHRIFT_NORMAL, $praeferenzen['schrift']);
        $this->assertSame(Darstellung::BEWEGUNG_NORMAL, $praeferenzen['bewegung']);
    }

    #[Test]
    public function layout_attribute_werden_nur_gesetzt_wenn_nicht_standard(): void
    {
        $standard = User::factory()->lernender()->create();
        $response = $this->actingAs($standard)->get(route('learner.dashboard'));
        $response->assertDontSee('data-akzent', false);
        $response->assertDontSee('data-schrift', false);
        $response->assertDontSee('data-bewegung', false);

        $angepasst = User::factory()->lernender()->create([
            'praeferenzen' => ['theme' => 'wald', 'akzent' => 'gruen', 'schrift' => 'sehr-gross', 'bewegung' => 'reduziert'],
        ]);
        $response = $this->actingAs($angepasst)->get(route('learner.dashboard'));
        $response->assertSee('data-theme="wald"', false);
        $response->assertSee('data-akzent="gruen"', false);
        $response->assertSee('data-schrift="sehr-gross"', false);
        $response->assertSee('data-bewegung="reduziert"', false);
    }

    #[Test]
    public function data_dichte_wird_nur_gesetzt_wenn_nicht_standard(): void
    {
        $standard = User::factory()->lernender()->create();
        $this->actingAs($standard)->get(route('learner.dashboard'))->assertDontSee('data-dichte', false);

        $kompakt = User::factory()->lernender()->create(['praeferenzen' => ['dichte' => 'kompakt']]);
        $this->actingAs($kompakt)->get(route('learner.dashboard'))->assertSee('data-dichte="kompakt"', false);
    }

    #[Test]
    public function data_diagramm_wird_nur_gesetzt_wenn_nicht_standard(): void
    {
        $standard = User::factory()->lernender()->create();
        $this->actingAs($standard)->get(route('learner.dashboard'))->assertDontSee('data-diagramm', false);

        $farbenblind = User::factory()->lernender()->create(['praeferenzen' => ['diagramm' => 'farbenblind']]);
        $this->actingAs($farbenblind)->get(route('learner.dashboard'))->assertSee('data-diagramm="farbenblind"', false);
    }

    public static function neueThemes(): array
    {
        return [['fjord'], ['bernstein'], ['schiefer']];
    }

    #[Test]
    #[DataProvider('neueThemes')]
    public function neues_theme_kann_persoenlich_gewaehlt_werden_und_steht_im_html(string $theme): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['email' => $user->email, 'darstellung' => 'hell', 'theme' => $theme])
            ->assertSessionHasNoErrors();

        $this->assertSame($theme, $user->refresh()->praeferenzen['theme']);
        $this->actingAs($user)->get(route('learner.dashboard'))->assertSee('data-theme="'.$theme.'"', false);
    }

    #[Test]
    public function data_schriftart_ecken_transparenz_werden_nur_gesetzt_wenn_nicht_standard(): void
    {
        $standard = User::factory()->lernender()->create();
        $response = $this->actingAs($standard)->get(route('learner.dashboard'));
        $response->assertDontSee('data-schriftart', false);
        $response->assertDontSee('data-ecken', false);
        $response->assertDontSee('data-transparenz', false);

        $angepasst = User::factory()->lernender()->create([
            'praeferenzen' => ['schriftart' => 'serif', 'ecken' => 'eckig', 'transparenz' => 'reduziert'],
        ]);
        $response = $this->actingAs($angepasst)->get(route('learner.dashboard'));
        $response->assertSee('data-schriftart="serif"', false);
        $response->assertSee('data-ecken="eckig"', false);
        $response->assertSee('data-transparenz="reduziert"', false);
    }

    #[Test]
    public function data_schriftart_ecken_transparenz_stehen_auch_auf_gast_und_fehler_layout(): void
    {
        $angepasst = User::factory()->lernender()->create([
            'praeferenzen' => ['schriftart' => 'lesefreundlich', 'ecken' => 'eckig', 'transparenz' => 'reduziert'],
        ]);

        // errors.layout: z. B. 403 bei einem für Lernende gesperrten Admin-Bereich.
        $response = $this->actingAs($angepasst)->get(route('admin.dashboard'));
        $response->assertForbidden();
        $response->assertSee('data-schriftart="lesefreundlich"', false);
        $response->assertSee('data-ecken="eckig"', false);
        $response->assertSee('data-transparenz="reduziert"', false);

        // layouts.guest: z. B. /login, allerdings ohne angemeldeten Benutzer (kein npAkzentEigen dort möglich)
        $this->get('/login')->assertDontSee('data-schriftart', false);
    }

    #[Test]
    public function eigene_farbe_erscheint_nur_bei_gueltigem_gespeichertem_wert(): void
    {
        $ohne = User::factory()->lernender()->create();
        $this->actingAs($ohne)->get(route('learner.dashboard'))->assertDontSee("[data-akzent='eigen']", false);

        $mit = User::factory()->lernender()->create(['praeferenzen' => ['akzent' => 'eigen', 'akzent_eigen' => '#3355ff']]);
        $response = $this->actingAs($mit)->get(route('learner.dashboard'));
        $response->assertSee("[data-akzent='eigen']", false);
        $response->assertDontSee('#3355ff');
        $response->assertDontSee('#3355FF');
    }

    #[Test]
    public function eigene_farbe_entfaellt_beim_theme_kontrast(): void
    {
        $user = User::factory()->lernender()->create([
            'praeferenzen' => ['theme' => 'kontrast', 'akzent' => 'eigen', 'akzent_eigen' => '#3355ff'],
        ]);

        $this->actingAs($user)->get(route('learner.dashboard'))->assertDontSee("[data-akzent='eigen']", false);
    }

    #[Test]
    public function beim_theme_kontrast_wird_data_akzent_nicht_gesetzt(): void
    {
        $user = User::factory()->lernender()->create([
            'praeferenzen' => ['theme' => 'kontrast', 'akzent' => 'orange'],
        ]);

        $response = $this->actingAs($user)->get(route('learner.dashboard'));
        $response->assertSee('data-theme="kontrast"', false);
        $response->assertDontSee('data-akzent', false);
    }

    #[Test]
    public function ohne_erreichbare_datenbank_faellt_die_darstellung_auf_standard_zurueck(): void
    {
        $cache = new \ReflectionProperty(Darstellung::class, 'spalteVorhanden');
        $cache->setValue(null, null);
        Schema::shouldReceive('hasColumn')->andThrow(new \RuntimeException('DB nicht erreichbar'));

        try {
            $this->assertFalse(Darstellung::praeferenzenOptionVerfuegbar());
            $this->assertNull(Darstellung::fuer(User::factory()->make(['praeferenzen' => ['theme' => 'wald']]))['theme']);
        } finally {
            $cache->setValue(null, null);
        }
    }

    #[Test]
    public function abgewiesener_wert_zeigt_wieder_die_gespeicherte_wahl(): void
    {
        $user = User::factory()->lernender()->create(['praeferenzen' => ['theme' => 'wald', 'akzent' => 'rot']]);

        $html = $this->actingAs($user)->from(route('settings.profile'))->followingRedirects()
            ->patch(route('profile.update'), ['email' => $user->email, 'darstellung' => 'hell', 'theme' => 'gibtsnicht', 'akzent' => 'lila'])
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/name="theme" value="wald"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/name="akzent" value="rot"[^>]*checked/', $html);
    }
}
