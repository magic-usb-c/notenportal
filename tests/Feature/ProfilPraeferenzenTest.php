<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PATCH /profile/preferences (window.npBefehl, Befehlspalette Ctrl+K): ändert nur die
 * übergebenen Schlüssel, alle anderen bleiben unangetastet. Siehe ProfileController::preferences.
 */
class ProfilPraeferenzenTest extends TestCase
{
    #[Test]
    public function schrift_wird_gespeichert(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->patchJson(route('profile.preferences'), ['schrift' => 'gross'])
            ->assertOk()
            ->assertExactJson(['ok' => true]);

        $this->assertSame('gross', $user->refresh()->praeferenzen['schrift']);
    }

    #[Test]
    public function dichte_wird_gespeichert(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->patchJson(route('profile.preferences'), ['dichte' => 'kompakt'])
            ->assertOk();

        $this->assertSame('kompakt', $user->refresh()->praeferenzen['dichte']);
    }

    #[Test]
    public function theme_wird_gespeichert_und_setzt_kontrast_flag(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->patchJson(route('profile.preferences'), ['theme' => 'kontrast'])
            ->assertOk();

        $user->refresh();
        $this->assertSame('kontrast', $user->praeferenzen['theme']);
        $this->assertTrue($user->kontrast);
    }

    #[Test]
    public function ungueltiger_wert_wird_mit_422_abgewiesen(): void
    {
        $user = User::factory()->lernender()->create(['praeferenzen' => ['schrift' => 'gross']]);

        $this->actingAs($user)
            ->patchJson(route('profile.preferences'), ['schrift' => 'winzig'])
            ->assertStatus(422);

        $this->assertSame('gross', $user->refresh()->praeferenzen['schrift']);
    }

    #[Test]
    public function ohne_bekannten_schluessel_gibt_es_422(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->patchJson(route('profile.preferences'), ['irgendwas' => 'x'])
            ->assertStatus(422);
    }

    #[Test]
    public function gast_wird_umgeleitet(): void
    {
        $this->patch(route('profile.preferences'), ['schrift' => 'gross'])
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function nur_der_uebergebene_schluessel_wird_geaendert(): void
    {
        $user = User::factory()->lernender()->create([
            'praeferenzen' => ['theme' => 'wald', 'akzent' => 'petrol', 'schrift' => 'gross', 'bewegung' => 'reduziert', 'dichte' => 'kompakt', 'diagramm' => 'farbenblind', 'karten_ausgeblendet' => ['ziele']],
        ]);

        $this->actingAs($user)
            ->patchJson(route('profile.preferences'), ['schrift' => 'sehr-gross'])
            ->assertOk();

        $user->refresh();
        $this->assertSame([
            'theme' => 'wald',
            'akzent' => 'petrol',
            'schrift' => 'sehr-gross',
            'bewegung' => 'reduziert',
            'dichte' => 'kompakt',
            'diagramm' => 'farbenblind',
            'karten_ausgeblendet' => ['ziele'],
        ], $user->praeferenzen);
    }

    #[Test]
    public function diagramm_wird_gespeichert(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->patchJson(route('profile.preferences'), ['diagramm' => 'farbenblind'])
            ->assertOk();

        $this->assertSame('farbenblind', $user->refresh()->praeferenzen['diagramm']);
    }

    #[Test]
    public function ungueltiger_diagramm_wert_wird_mit_422_abgewiesen(): void
    {
        $user = User::factory()->lernender()->create(['praeferenzen' => ['diagramm' => 'standard']]);

        $this->actingAs($user)
            ->patchJson(route('profile.preferences'), ['diagramm' => 'infrarot'])
            ->assertStatus(422);

        $this->assertSame('standard', $user->refresh()->praeferenzen['diagramm']);
    }
}
