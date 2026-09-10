<?php

namespace Tests\Feature\Verwaltung;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BetreuungTest extends TestCase
{
    use VerwaltungTestHilfen;

    #[Test]
    public function uebergabe_an_anderen_berufsbildner_sperrt_den_bisherigen(): void
    {
        $lernender = $this->neuerLernender();
        $bisher = User::factory()->berufsbildner()->create();
        $neu = User::factory()->berufsbildner()->create();
        $alteBetreuung = $this->betreue($bisher, $lernender);

        $this->actingAs($bisher)
            ->post(route('berufsbildner.lernende.betreuung.store', $lernender->lernender_id), [
                'berufsbildner_id' => $neu->berufsbildner->berufsbildner_id,
                'gueltig_von' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('berufsbildner.lernende.index'));

        $this->assertSame(now()->subDay()->toDateString(), $alteBetreuung->fresh()->gueltig_bis->toDateString());
        $this->actingAs($bisher)->get(route('berufsbildner.lernende.show', $lernender->lernender_id))->assertNotFound();
        $this->actingAs($neu)->get(route('berufsbildner.lernende.show', $lernender->lernender_id))->assertOk();
    }

    #[Test]
    public function admin_weist_zu_und_beendet_betreuung(): void
    {
        $lernender = $this->neuerLernender();
        $admin = User::factory()->admin()->create();
        $bb = User::factory()->berufsbildner()->create();

        $this->actingAs($admin)
            ->post(route('admin.lernende.betreuung.store', $lernender->lernender_id), [
                'berufsbildner_id' => $bb->berufsbildner->berufsbildner_id,
                'gueltig_von' => now()->subMonth()->toDateString(),
            ])
            ->assertRedirect(route('admin.lernende.show', $lernender->lernender_id));

        $this->actingAs($bb)->get(route('berufsbildner.lernende.show', $lernender->lernender_id))->assertOk();

        $betreuung = $lernender->betreuungen()->sole();
        $this->actingAs($admin)
            ->post(route('admin.betreuungen.beenden', [$lernender->lernender_id, $betreuung->betreuung_id]))
            ->assertRedirect(route('admin.lernende.show', $lernender->lernender_id));

        $this->actingAs($bb)->get(route('berufsbildner.lernende.show', $lernender->lernender_id))->assertNotFound();
    }

    #[Test]
    public function betreuung_eines_anderen_lernenden_ist_nicht_erreichbar(): void
    {
        $admin = User::factory()->admin()->create();
        $bb = User::factory()->berufsbildner()->create();
        $a = $this->neuerLernender();
        $fremdeBetreuung = $this->betreue($bb, $this->neuerLernender());

        $this->actingAs($admin)
            ->post(route('admin.betreuungen.beenden', [$a->lernender_id, $fremdeBetreuung->betreuung_id]))
            ->assertNotFound();

        $this->assertNull($fremdeBetreuung->fresh()->gueltig_bis);
    }
}
