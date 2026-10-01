<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Darstellung;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Persönliche Darstellung in den Einstellungen: jede Änderung geht einzeln an PATCH /profile/preferences
 * (eigene Akzentfarbe, Schriftart, Ecken, Transparenz, Tastenkürzel, Startseite …) und gilt sofort;
 * dazu «Auf Standard zurücksetzen». Das Kontoformular (profile.update) lässt sie unberührt.
 * Siehe ProfileController::preferences/resetPreferences, PraeferenzenRequest, Darstellung::fuer.
 */
class DarstellungProfilTest extends TestCase
{
    #[Test]
    public function eigene_farbe_wird_gespeichert_und_liefert_die_geprueften_tokens(): void
    {
        $user = User::factory()->lernender()->create();

        $antwort = $this->actingAs($user)
            ->patchJson(route('profile.preferences'), ['akzent' => 'eigen', 'akzent_eigen' => '#3355FF'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $user->refresh();
        $this->assertSame('eigen', $user->praeferenzen['akzent']);
        $this->assertSame('#3355ff', $user->praeferenzen['akzent_eigen']);
        // Dieselben Tokens wie <x-akzent-eigen-stil> beim Laden, damit die Vorschau nicht vom gespeicherten Stand abweicht
        $this->assertStringContainsString('--accent:', (string) $antwort->json('akzent_stil'));
    }

    #[Test]
    public function ungueltiger_hex_wird_abgewiesen(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->patchJson(route('profile.preferences'), ['akzent' => 'eigen', 'akzent_eigen' => 'nicht-hex'])
            ->assertJsonValidationErrors('akzent_eigen');

        $this->assertNull($user->refresh()->praeferenzen);
    }

    #[Test]
    public function eigene_farbe_ohne_gespeicherte_oder_mitgesendete_farbe_ist_pflicht(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->patchJson(route('profile.preferences'), ['akzent' => 'eigen'])
            ->assertJsonValidationErrors('akzent_eigen');

        $this->actingAs($user)
            ->patchJson(route('profile.preferences'), ['akzent' => 'eigen', 'akzent_eigen' => ''])
            ->assertJsonValidationErrors('akzent_eigen');
    }

    #[Test]
    public function zurueck_zur_eigenen_farbe_nutzt_die_gespeicherte(): void
    {
        $user = User::factory()->lernender()->create(['praeferenzen' => ['akzent' => 'eigen', 'akzent_eigen' => '#3355ff']]);

        $this->actingAs($user)->patchJson(route('profile.preferences'), ['akzent_eigen' => '#aa2200'])->assertOk();
        $this->assertSame('#aa2200', $user->refresh()->praeferenzen['akzent_eigen']);

        $this->patchJson(route('profile.preferences'), ['akzent' => 'eigen'])->assertOk();
        $this->assertSame('#aa2200', $user->refresh()->praeferenzen['akzent_eigen']);
    }

    #[Test]
    public function akzent_eigen_wird_verworfen_wenn_ein_anderer_akzent_gewaehlt_ist(): void
    {
        $user = User::factory()->lernender()->create(['praeferenzen' => ['akzent' => 'eigen', 'akzent_eigen' => '#3355ff']]);

        $this->actingAs($user)
            ->patchJson(route('profile.preferences'), ['akzent' => 'gruen'])
            ->assertOk()
            ->assertJsonPath('akzent_stil', '');

        $user->refresh();
        $this->assertSame('gruen', $user->praeferenzen['akzent']);
        $this->assertNull($user->praeferenzen['akzent_eigen']);
    }

    #[Test]
    public function schriftart_ecken_transparenz_tastenkuerzel_werden_einzeln_gespeichert(): void
    {
        $user = User::factory()->lernender()->create();
        $this->actingAs($user);

        foreach (['schriftart' => 'lesefreundlich', 'ecken' => 'eckig', 'transparenz' => 'reduziert', 'tastenkuerzel' => 'aus'] as $schluessel => $wert) {
            $this->patchJson(route('profile.preferences'), [$schluessel => $wert])->assertExactJson(['ok' => true]);
        }

        $user->refresh();
        $this->assertSame('lesefreundlich', $user->praeferenzen['schriftart']);
        $this->assertSame('eckig', $user->praeferenzen['ecken']);
        $this->assertSame('reduziert', $user->praeferenzen['transparenz']);
        $this->assertSame('aus', $user->praeferenzen['tastenkuerzel']);
        // Was nicht gesendet wurde, bleibt beim Standard
        $this->assertSame(Darstellung::SCHRIFT_NORMAL, $user->praeferenzen['schrift']);
    }

    #[Test]
    public function ungueltige_werte_werden_abgewiesen(): void
    {
        $user = User::factory()->lernender()->create();
        $this->actingAs($user);

        foreach (['schriftart' => 'kursiv', 'ecken' => 'rundlich', 'transparenz' => 'halb', 'diagramm' => 'infrarot', 'notenanzeige' => '3', 'darstellung' => 'grau'] as $schluessel => $wert) {
            $this->patchJson(route('profile.preferences'), [$schluessel => $wert])->assertJsonValidationErrors($schluessel);
        }

        $this->assertNull($user->refresh()->praeferenzen);
        // Ohne bekannten Schlüssel gibt es nichts zu speichern
        $this->patchJson(route('profile.preferences'), ['unbekannt' => 'x'])->assertUnprocessable();
    }

    #[Test]
    public function startseite_wird_nur_innerhalb_der_eigenen_rolle_akzeptiert(): void
    {
        $lernender = User::factory()->lernender()->create();

        $this->actingAs($lernender)->patchJson(route('profile.preferences'), ['startseite' => 'noten'])->assertOk();
        $this->assertSame('noten', $lernender->refresh()->praeferenzen['startseite']);

        $this->patchJson(route('profile.preferences'), ['startseite' => 'lernende'])->assertJsonValidationErrors('startseite');
        $this->assertSame('noten', $lernender->refresh()->praeferenzen['startseite']);
    }

    #[Test]
    public function erscheinungsbild_wird_gespeichert_ohne_die_praeferenzen_anzufassen(): void
    {
        $user = User::factory()->lernender()->create(['praeferenzen' => ['schrift' => 'gross']]);

        $this->actingAs($user)->patchJson(route('profile.preferences'), ['darstellung' => 'dunkel'])->assertExactJson(['ok' => true]);

        $user->refresh();
        $this->assertSame('dunkel', $user->darstellung);
        $this->assertSame(['schrift' => 'gross'], $user->praeferenzen);
    }

    #[Test]
    public function kontoformular_laesst_die_darstellung_unberuehrt(): void
    {
        $user = User::factory()->admin()->create(['praeferenzen' => ['akzent' => 'gruen', 'schrift' => 'gross']]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'vorname' => $user->vorname, 'nachname' => $user->nachname, 'email' => $user->email,
                'akzent' => 'rot', 'schrift' => 'klein', 'navigation' => 'unten',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(['akzent' => 'gruen', 'schrift' => 'gross'], $user->refresh()->praeferenzen);
    }

    #[Test]
    public function reset_setzt_alle_darstellungs_praeferenzen_zurueck_ausser_dashboard_karten(): void
    {
        $user = User::factory()->lernender()->create([
            'kontrast' => true,
            'praeferenzen' => [
                'theme' => 'wald', 'akzent' => 'eigen', 'akzent_eigen' => '#3355ff',
                'schrift' => 'gross', 'schriftart' => 'serif', 'bewegung' => 'reduziert',
                'dichte' => 'kompakt', 'diagramm' => 'farbenblind', 'notenanzeige' => '2',
                'ecken' => 'eckig', 'transparenz' => 'reduziert',
                'tastenkuerzel' => 'aus', 'navigation' => 'seite', 'startseite' => 'noten',
                'karten_ausgeblendet' => ['ziele'],
            ],
        ]);

        $this->actingAs($user)
            ->delete(route('profile.preferences.reset'))
            ->assertRedirect(route('settings.profile'));

        $user->refresh();
        $this->assertFalse($user->kontrast);
        $this->assertSame([
            'theme' => null, 'akzent' => null, 'akzent_eigen' => null,
            'schrift' => Darstellung::SCHRIFT_NORMAL, 'schriftart' => Darstellung::SCHRIFTART_STANDARD,
            'bewegung' => Darstellung::BEWEGUNG_NORMAL, 'dichte' => Darstellung::DICHTE_NORMAL,
            'diagramm' => Darstellung::DIAGRAMM_STANDARD, 'notenanzeige' => Darstellung::NOTENANZEIGE_1,
            'ecken' => Darstellung::ECKEN_RUND, 'transparenz' => Darstellung::TRANSPARENZ_NORMAL,
            'tastenkuerzel' => Darstellung::TASTENKUERZEL_AN, 'navigation' => Darstellung::NAVIGATION_SEITE, 'startseite' => 'dashboard',
            'karten_ausgeblendet' => ['ziele'],
        ], $user->praeferenzen);
    }

    #[Test]
    public function seitenleiste_laesst_sich_in_den_einstellungen_und_per_schalter_waehlen(): void
    {
        $user = User::factory()->admin()->create();

        // Standard: Seitenleiste (HIG «Sidebars»: nicht standardmässig ausblenden) mit beiden Schaltern
        $this->actingAs($user)->get(route('admin.master-data.subjects.index'))->assertOk()
            ->assertSee('data-navigation="seite"', false)->assertDontSee('data-navigation="oben"', false)
            ->assertSee(__('Seitenleiste ausblenden'))->assertSee(__('Seitenleiste einblenden'))
            ->assertSeeInOrder([__('Stammdaten'), __('Fächer'), __('Betrieb'), __('Einstellungen')]);

        $this->patchJson(route('profile.preferences'), ['navigation' => 'oben'])->assertOk();
        $this->assertSame('oben', $user->refresh()->praeferenzen['navigation']);
        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee('data-navigation="oben"', false)->assertDontSee('data-navigation="seite"', false);

        $this->patchJson(route('profile.preferences'), ['navigation' => 'seite'])->assertOk();
        $this->assertSame('seite', $user->refresh()->praeferenzen['navigation']);
        $this->patchJson(route('profile.preferences'), ['navigation' => 'links'])->assertUnprocessable();

        // Ein Schnellwechsel einer anderen Einstellung (Befehlspalette) lässt die Wahl stehen
        $this->patchJson(route('profile.preferences'), ['navigation' => 'oben'])->assertOk();
        $this->patchJson(route('profile.preferences'), ['dichte' => Darstellung::DICHTEN[0]])->assertOk();
        $this->assertSame('oben', $user->refresh()->praeferenzen['navigation']);
    }

    #[Test]
    public function diagramm_und_notenanzeige_werden_gespeichert(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->patchJson(route('profile.preferences'), ['diagramm' => 'farbenblind', 'notenanzeige' => '2'])->assertOk();

        $user->refresh();
        $this->assertSame('farbenblind', $user->praeferenzen['diagramm']);
        $this->assertSame('2', $user->praeferenzen['notenanzeige']);
    }

    #[Test]
    public function einstellungsseite_zeigt_die_gespeicherte_wahl(): void
    {
        $user = User::factory()->lernender()->create(['praeferenzen' => ['schriftart' => 'serif', 'ecken' => 'eckig']]);

        $html = (string) $this->actingAs($user)->get(route('settings.profile'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/name="schriftart"[^>]*value="serif"[^>]*checked|value="serif"[^>]*name="schriftart"[^>]*checked/s', $html);
        $this->assertMatchesRegularExpression('/name="ecken"[^>]*value="eckig"[^>]*checked|value="eckig"[^>]*name="ecken"[^>]*checked/s', $html);
    }

    #[Test]
    public function gast_wird_umgeleitet(): void
    {
        $this->delete(route('profile.preferences.reset'))->assertRedirect(route('login'));
        $this->patchJson(route('profile.preferences'), ['schrift' => 'gross'])->assertUnauthorized();
    }
}
