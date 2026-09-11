<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

class SucheTest extends TestCase
{
    use VerwaltungTestHilfen;

    #[Test]
    public function berufsbildner_findet_nur_betreute_lernende(): void
    {
        $eigener = $this->neuerLernender(['vorname' => 'Zora', 'nachname' => 'Eigen']);
        $this->neuerLernender(['vorname' => 'Zora', 'nachname' => 'Fremd']);
        $bb = User::factory()->berufsbildner()->create();
        $this->betreue($bb, $eigener);

        $treffer = $this->actingAs($bb)->getJson(route('suche', ['q' => 'Zora']))->assertOk()->json();

        $this->assertSame(['Zora Eigen'], array_column($treffer, 'label'));
        $this->assertStringContainsString('/berufsbildner/lernende/'.$eigener->lernender_id, $treffer[0]['url']);
    }

    #[Test]
    public function admin_findet_lernende_und_konten_lernende_nichts(): void
    {
        $this->neuerLernender(['vorname' => 'Zora', 'nachname' => 'Muster']);
        User::factory()->berufsbildner()->create(['vorname' => 'Zora', 'nachname' => 'Bildnerin']);
        $admin = User::factory()->admin()->create();

        $gruppen = array_column($this->actingAs($admin)->getJson(route('suche', ['q' => 'zora']))->json(), 'gruppe');
        $this->assertContains('Lernende', $gruppen);
        $this->assertContains('Konten', $gruppen);

        $lernender = User::factory()->lernender()->create();
        $this->actingAs($lernender)->getJson(route('suche', ['q' => 'zora']))->assertOk()->assertExactJson([]);

        // Direkter Aufruf im Browser zeigt kein rohes JSON
        $this->actingAs($lernender)->get(route('suche'))->assertRedirect(route('dashboard'));
    }

    #[Test]
    public function navigation_zeigt_rollengerechte_eintraege(): void
    {
        $this->actingAs(User::factory()->lernender()->create())->get(route('lernender.dashboard'))
            ->assertSee('Prüfungen')->assertSee('Rechner')->assertDontSee('Stammdaten');
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))
            ->assertSee('Stammdaten')->assertSee('Benutzerkonten');
    }
}
