<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Darstellung;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Profilformular «Darstellung» (Block Z, Persönliche Darstellung II): eigene Akzentfarbe,
 * Schriftart, Ecken, Transparenz, Tastenkürzel, Startseite und «Auf Standard zurücksetzen».
 * Siehe ProfileController::update/resetPreferences, ProfileUpdateRequest, Darstellung::fuer.
 */
class DarstellungProfilTest extends TestCase
{
    #[Test]
    public function eigene_farbe_wird_gespeichert(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'email' => $user->email, 'darstellung' => 'hell',
                'akzent' => 'eigen', 'akzent_eigen' => '#3355FF',
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('eigen', $user->praeferenzen['akzent']);
        $this->assertSame('#3355FF', $user->praeferenzen['akzent_eigen']);
        // Beim Lesen (Darstellung::fuer) wird unabhängig von der Gross-/Kleinschreibung normalisiert.
        $this->assertSame('#3355ff', Darstellung::fuer($user)['akzent_eigen']);
    }

    #[Test]
    public function ungueltiger_hex_wird_abgewiesen(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'email' => $user->email, 'darstellung' => 'hell',
                'akzent' => 'eigen', 'akzent_eigen' => 'nicht-hex',
            ])
            ->assertSessionHasErrors('akzent_eigen');
    }

    #[Test]
    public function akzent_eigen_ohne_akzent_eigen_gewaehlt_ist_pflicht(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'email' => $user->email, 'darstellung' => 'hell',
                'akzent' => 'eigen', 'akzent_eigen' => '',
            ])
            ->assertSessionHasErrors('akzent_eigen');
    }

    #[Test]
    public function akzent_eigen_wird_verworfen_wenn_ein_anderer_akzent_gewaehlt_ist(): void
    {
        $user = User::factory()->lernender()->create(['praeferenzen' => ['akzent' => 'eigen', 'akzent_eigen' => '#3355ff']]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'email' => $user->email, 'darstellung' => 'hell',
                'akzent' => 'gruen', 'akzent_eigen' => '#3355ff',
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('gruen', $user->praeferenzen['akzent']);
        $this->assertNull($user->praeferenzen['akzent_eigen']);
    }

    #[Test]
    public function schriftart_ecken_transparenz_tastenkuerzel_werden_gespeichert(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'email' => $user->email, 'darstellung' => 'hell',
                'schriftart' => 'lesefreundlich', 'ecken' => 'eckig',
                'transparenz' => 'reduziert', 'tastenkuerzel' => 'aus',
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('lesefreundlich', $user->praeferenzen['schriftart']);
        $this->assertSame('eckig', $user->praeferenzen['ecken']);
        $this->assertSame('reduziert', $user->praeferenzen['transparenz']);
        $this->assertSame('aus', $user->praeferenzen['tastenkuerzel']);
    }

    #[Test]
    public function ungueltige_werte_werden_abgewiesen(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['email' => $user->email, 'darstellung' => 'hell', 'schriftart' => 'kursiv'])
            ->assertSessionHasErrors('schriftart');

        $this->actingAs($user)
            ->patch(route('profile.update'), ['email' => $user->email, 'darstellung' => 'hell', 'ecken' => 'rundlich'])
            ->assertSessionHasErrors('ecken');

        $this->actingAs($user)
            ->patch(route('profile.update'), ['email' => $user->email, 'darstellung' => 'hell', 'transparenz' => 'halb'])
            ->assertSessionHasErrors('transparenz');
    }

    #[Test]
    public function startseite_wird_nur_innerhalb_der_eigenen_rolle_akzeptiert(): void
    {
        $lernender = User::factory()->lernender()->create();

        $this->actingAs($lernender)
            ->patch(route('profile.update'), ['email' => $lernender->email, 'darstellung' => 'hell', 'startseite' => 'noten'])
            ->assertSessionHasNoErrors();
        $this->assertSame('noten', $lernender->refresh()->praeferenzen['startseite']);

        $this->actingAs($lernender)
            ->patch(route('profile.update'), ['email' => $lernender->email, 'darstellung' => 'hell', 'startseite' => 'lernende'])
            ->assertSessionHasErrors('startseite');
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
            'tastenkuerzel' => Darstellung::TASTENKUERZEL_AN, 'navigation' => Darstellung::NAVIGATION_OBEN, 'startseite' => 'dashboard',
            'karten_ausgeblendet' => ['ziele'],
        ], $user->praeferenzen);
    }

    #[Test]
    public function seitenleiste_laesst_sich_im_profil_und_per_schalter_waehlen(): void
    {
        $user = User::factory()->admin()->create();
        $profil = fn (array $mehr) => ['vorname' => $user->vorname, 'nachname' => $user->nachname, 'email' => $user->email, 'darstellung' => 'hell'] + $mehr;

        // Standard: Navigation oben, Seitenleiste nur als ausgeblendetes Markup
        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->assertDontSee('data-navigation="seite"', false)
            ->assertSee(__('Seitenleiste einblenden'));

        $this->patch(route('profile.update'), $profil(['navigation' => 'seite']))->assertSessionHasNoErrors();
        $this->assertSame('seite', $user->refresh()->praeferenzen['navigation']);
        $this->get(route('admin.master-data.subjects.index'))->assertOk()->assertSee('data-navigation="seite"', false)
            ->assertSeeInOrder([__('Stammdaten'), __('Fächer'), __('Betrieb'), __('Allgemein')]);

        // Schalter in der Leiste (PATCH /profile/preferences) ändert nur diesen Schlüssel
        $this->patchJson(route('profile.preferences'), ['navigation' => 'oben'])->assertOk();
        $user->refresh();
        $this->assertSame('oben', $user->praeferenzen['navigation']);
        $this->patchJson(route('profile.preferences'), ['navigation' => 'links'])->assertUnprocessable();
        $this->patch(route('profile.update'), $profil(['navigation' => 'unten']))->assertSessionHasErrors('navigation');

        // Profil speichern ohne das Feld lässt die Wahl stehen
        $this->patchJson(route('profile.preferences'), ['navigation' => 'seite'])->assertOk();
        $this->patch(route('profile.update'), $profil([]))->assertSessionHasNoErrors();
        $this->assertSame('seite', $user->refresh()->praeferenzen['navigation']);
    }

    #[Test]
    public function diagramm_und_notenanzeige_werden_gespeichert(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'email' => $user->email, 'darstellung' => 'hell',
                'diagramm' => 'farbenblind', 'notenanzeige' => '2',
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('farbenblind', $user->praeferenzen['diagramm']);
        $this->assertSame('2', $user->praeferenzen['notenanzeige']);
    }

    #[Test]
    public function ungueltige_diagramm_und_notenanzeige_werte_werden_abgewiesen(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['email' => $user->email, 'darstellung' => 'hell', 'diagramm' => 'infrarot'])
            ->assertSessionHasErrors('diagramm');

        $this->actingAs($user)
            ->patch(route('profile.update'), ['email' => $user->email, 'darstellung' => 'hell', 'notenanzeige' => '3'])
            ->assertSessionHasErrors('notenanzeige');
    }

    #[Test]
    public function gast_wird_bei_reset_umgeleitet(): void
    {
        $this->delete(route('profile.preferences.reset'))->assertRedirect(route('login'));
    }
}
