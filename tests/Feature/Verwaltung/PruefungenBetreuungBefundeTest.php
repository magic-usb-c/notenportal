<?php

declare(strict_types=1);

namespace Tests\Feature\Verwaltung;

use App\Models\Fach;
use App\Models\Lernender;
use App\Models\Pruefung;
use App\Models\User;
use App\Services\Calendar\CalendarExport;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Funktionsprüfung, Gruppe «betreuung»: Prüfungstermine, ID-Länge in der Adresse, ungültiges UTF-8 in der Suche. */
class PruefungenBetreuungBefundeTest extends TestCase
{
    use VerwaltungTestHilfen;

    /** @return array{0: User, 1: array<string, Lernender>} */
    private function bbMitDreiLernenden(): array
    {
        $fachId = Fach::factory()->create()->fach_id;
        $bb = User::factory()->berufsbildner()->create();
        $lernende = [];
        foreach (['Aktivx' => [true, null], 'Ausgetretenx' => [false, null], 'Fertigx' => [true, now()->subMonths(2)->toDateString()]] as $name => [$aktiv, $ende]) {
            $l = $this->neuerLernender(['nachname' => $name, 'aktiv' => $aktiv]);
            if ($ende) {
                $l->update(['lehrende' => $ende]);
            }
            $this->betreue($bb, $l);
            Pruefung::create(['fach_id' => $fachId, 'lernender_id' => $l->lernender_id, 'titel' => 'Alt', 'datum' => now()->subDays(3)->toDateString(), 'gewichtung_prozent' => 100, 'quelle' => Pruefung::MANUELL]);
            $lernende[$name] = $l;
        }

        return [$bb, $lernende];
    }

    #[Test]
    public function deaktivierte_konten_und_abgeschlossene_lehren_zaehlen_nicht_als_note_fehlt(): void
    {
        [$bb] = $this->bbMitDreiLernenden();

        $r = $this->actingAs($bb)->get(route('trainer.exams.index'))->assertOk();

        $namen = $r->viewData('lernende')->map(fn ($e) => $e['lernender']->benutzer->nachname)->all();
        $this->assertContains('Aktivx', $namen);
        $this->assertContains('Fertigx', $namen);
        $this->assertNotContains('Ausgetretenx', $namen);
        $this->assertSame(1, $r->viewData('fehltAlle'));
        $this->assertSame(2, $r->viewData('anzahlAlle'));
        $r->assertDontSee('Ausgetretenx');
    }

    #[Test]
    public function kalender_abo_der_verwaltung_enthaelt_keine_termine_deaktivierter_konten(): void
    {
        [$bb] = $this->bbMitDreiLernenden();

        $ics = app(CalendarExport::class)->forUser($bb);

        $this->assertStringContainsString('Aktivx', $ics);
        $this->assertStringContainsString('Fertigx', $ics);
        $this->assertStringNotContainsString('Ausgetretenx', $ics);
    }

    #[Test]
    public function eine_zu_lange_zifferfolge_in_der_id_endet_mit_404(): void
    {
        $bb = User::factory()->berufsbildner()->create();

        $this->actingAs($bb)->get('/trainer/learners/99999999999999999999')->assertNotFound();
        $this->actingAs($bb)->get('/trainer/learners/9223372036854775808')->assertNotFound();
    }

    #[Test]
    public function lernendenliste_als_json_antwortet_mit_ungueltigem_utf8_in_der_suche_nicht_mit_500(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $this->betreue($bb, $this->neuerLernender());

        $this->actingAs($bb)->getJson('/trainer/learners?suche=M%FCller')->assertOk();
        $this->actingAs($bb)->get('/trainer/learners?suche=M%FCller')->assertOk();
    }
}
