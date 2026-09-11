<?php

declare(strict_types=1);

namespace Tests\Feature\Berufsbildner;

use App\Models\Betreuung;
use App\Models\Note;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardDiagrammTest extends TestCase
{
    #[Test]
    public function dashboard_zeigt_klassentabelle_mit_sparkline(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $lernender = User::factory()->lernender()->create(['nachname' => 'Diagrammfrau']);

        Betreuung::factory()->create([
            'berufsbildner_id' => $bb->berufsbildner->berufsbildner_id,
            'lernender_id' => $lernender->lernender->lernender_id,
        ]);

        Note::factory()->create(['lernender_id' => $lernender->lernender->lernender_id]);

        $this->actingAs($bb)
            ->get(route('trainer.dashboard'))
            ->assertOk()
            ->assertSee('Meine Lernenden')
            ->assertSee('Diagrammfrau')
            ->assertSee('role="radiogroup"', false);
    }

    #[Test]
    public function dashboard_rendert_auch_ohne_betreute_lernende(): void
    {
        $bb = User::factory()->berufsbildner()->create();

        $this->actingAs($bb)
            ->get(route('trainer.dashboard'))
            ->assertOk()
            ->assertSee('Alle 0 Lernenden im Plan');
    }
}
