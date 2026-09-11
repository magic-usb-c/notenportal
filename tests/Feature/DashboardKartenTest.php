<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Lernender;
use App\Models\User;
use App\Support\Darstellung;
use App\Support\DashboardKarten;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ausgeblendete Dashboard-Karten (App\Support\DashboardKarten, Profil-Abschnitt «Übersicht»)
 * werden serverseitig nicht gerendert. Die Daten werden weiterhin berechnet (siehe
 * DashboardController::kartenSichtbar, docs/audit-backlog.md).
 */
class DashboardKartenTest extends TestCase
{
    #[Test]
    public function ausgeblendete_karte_erscheint_nicht_auf_dem_lernenden_dashboard(): void
    {
        $user = User::factory()->lernender()->create([
            'praeferenzen' => ['karten_ausgeblendet' => ['als_naechstes']],
        ]);

        $this->actingAs($user)->get(route('learner.dashboard'))
            ->assertOk()
            ->assertDontSee(__('Als Nächstes'));
    }

    #[Test]
    public function ausgeblendete_karte_erscheint_nicht_auf_dem_berufsbildner_dashboard(): void
    {
        $user = User::factory()->berufsbildner()->create([
            'praeferenzen' => ['karten_ausgeblendet' => ['agenda']],
        ]);

        $this->actingAs($user)->get(route('trainer.dashboard'))
            ->assertOk()
            ->assertDontSee(__('Nächste 14 Tage'));
    }

    #[Test]
    public function ausgeblendete_karte_erscheint_nicht_auf_dem_admin_dashboard(): void
    {
        $user = User::factory()->admin()->create([
            'praeferenzen' => ['karten_ausgeblendet' => ['aktivitaet']],
        ]);

        $this->actingAs($user)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(__('Erfasste Noten pro Woche'));
    }

    #[Test]
    public function admin_rolle_hat_vorrang_vor_einer_zusaetzlichen_lernende_zeile(): void
    {
        $user = User::factory()->admin()->create();
        Lernender::factory()->create(['benutzer_id' => $user->benutzer_id]);

        $this->assertSame(DashboardKarten::ADMIN, DashboardKarten::rolleFuer($user->refresh()));
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
    }

    #[Test]
    public function handlungsbedarf_und_aufmerksamkeit_lassen_sich_nicht_ausblenden(): void
    {
        $this->assertSame([], array_intersect(DashboardKarten::IMMER_SICHTBAR, array_keys(DashboardKarten::fuerRolle(DashboardKarten::ADMIN))));
        $this->assertSame([], array_intersect(DashboardKarten::IMMER_SICHTBAR, array_keys(DashboardKarten::fuerRolle(DashboardKarten::BERUFSBILDNER))));
        $this->assertSame([], array_intersect(DashboardKarten::IMMER_SICHTBAR, DashboardKarten::alleSchluessel()));
    }

    #[Test]
    public function eine_direkt_gespeicherte_immer_sichtbar_karte_wird_beim_lesen_ignoriert(): void
    {
        $user = User::factory()->admin()->create([
            'praeferenzen' => ['karten_ausgeblendet' => ['handlungsbedarf', 'aktivitaet']],
        ]);

        $this->assertSame(['aktivitaet'], Darstellung::fuer($user)['karten_ausgeblendet']);
    }

    #[Test]
    public function handlungsbedarf_erscheint_nicht_als_checkbox_im_profil(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('name="karten[]" value="handlungsbedarf"', false);
    }

    #[Test]
    public function handlungsbedarf_als_karte_wird_von_der_validierung_abgewiesen(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'email' => $user->email,
                'vorname' => $user->vorname,
                'nachname' => $user->nachname,
                'darstellung' => 'system',
                'schrift' => 'normal',
                'karten_uebermittelt' => '1',
                'karten' => ['handlungsbedarf'],
            ])
            ->assertSessionHasErrors('karten.0');

        $this->assertNull($user->refresh()->praeferenzen);
    }

    #[Test]
    public function ohne_ausgeblendete_karten_bleibt_alles_sichtbar(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->get(route('learner.dashboard'))
            ->assertOk()
            ->assertSee(__('Stand'))
            ->assertSee(__('Als Nächstes'));
    }
}
