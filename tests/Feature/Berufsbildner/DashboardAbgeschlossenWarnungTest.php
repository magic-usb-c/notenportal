<?php

namespace Tests\Feature\Berufsbildner;

use App\Models\Note;
use App\Models\Semester;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

class DashboardAbgeschlossenWarnungTest extends TestCase
{
    use VerwaltungTestHilfen;

    private function warnblock(string $html): string
    {
        $this->assertSame(1, preg_match('/data-np-untergenuegend>([\s\S]*?)<\/section>/', $html, $m));

        return $m[1];
    }

    public function test_abgeschlossene_lernende_steht_nicht_in_der_warnliste(): void
    {
        $l = $this->neuerLernender(['vorname' => 'Fertig', 'nachname' => 'Gelernt'], ['lehrbeginn' => now()->subYears(4)->toDateString(), 'lehrende' => now()->subDays(5)->toDateString()]);
        $bb = $this->verwalter('trainer', $l);
        $sem = Semester::factory()->create();
        Note::factory()->create(['lernender_id' => $l->lernender_id, 'semester_id' => $sem->semester_id, 'note_wert' => 3.0, 'pruefungsdatum' => now()->subDays(20)->toDateString()]);

        $r = $this->actingAs($bb)->get(route('trainer.dashboard'));

        $r->assertOk();
        $this->assertStringNotContainsString('Fertig Gelernt', $this->warnblock($r->getContent()));
    }

    public function test_laufende_lernende_mit_ungenuegender_note_steht_weiter_in_der_warnliste(): void
    {
        $l = $this->neuerLernender(['vorname' => 'Laufend', 'nachname' => 'Lernend'], ['lehrbeginn' => now()->subYears(1)->toDateString(), 'lehrende' => now()->addYears(2)->toDateString()]);
        $bb = $this->verwalter('trainer', $l);
        $sem = Semester::factory()->create();
        Note::factory()->create(['lernender_id' => $l->lernender_id, 'semester_id' => $sem->semester_id, 'note_wert' => 3.0, 'pruefungsdatum' => now()->subDays(20)->toDateString()]);

        $r = $this->actingAs($bb)->get(route('trainer.dashboard'));

        $r->assertOk();
        $this->assertStringContainsString('Laufend Lernend', $this->warnblock($r->getContent()));
    }
}
