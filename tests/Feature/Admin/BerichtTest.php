<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Fach;
use App\Models\Note;
use App\Models\User;
use App\Services\Bericht;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BerichtTest extends TestCase
{
    #[Test]
    public function bericht_laedt_fuer_alle_zeitraeume_und_sortierungen(): void
    {
        $admin = User::factory()->admin()->create();
        $lernender = User::factory()->lernender()->create(['nachname' => 'Berichtmann']);

        foreach ([[], ['semester' => 'alle'], ['sort' => 'gesamt', 'dir' => 'desc'], ['sort' => 'unbekannt']] as $parameter) {
            $this->actingAs($admin)
                ->get(route('admin.reports.grades', $parameter))
                ->assertOk()
                ->assertSee('Berichtmann');
        }
    }

    #[Test]
    public function export_enthaelt_lernende_und_status(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->lernender()->create(['nachname' => 'Berichtmann']);

        $csv = $this->actingAs($admin)->get(route('admin.reports.grades.export', ['semester' => 'alle']))->assertOk()->streamedContent();

        $this->assertStringContainsString('Berichtmann', $csv);
        $this->assertStringContainsString('Gesamtnote', $csv);
    }

    #[Test]
    public function verteilung_der_zeugnisnoten_enthaelt_tabellenalternative(): void
    {
        $admin = User::factory()->admin()->create();
        $lernender = User::factory()->lernender()->create();
        Note::factory()->create(['lernender_id' => $lernender->lernender->lernender_id, 'note_wert' => 4.5]);

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.grades', ['semester' => 'alle']))
            ->assertOk()
            ->assertSee('Verteilung der Zeugnisnoten')
            ->assertSee('Als Tabelle');

        $response->assertSeeInOrder(['Note', 'Zeugnisnoten', 'Stufe', '4.5', '1', 'genügend']);
    }

    #[Test]
    public function verteilung_der_zeugnisnoten_zeigt_stufenlegende(): void
    {
        $admin = User::factory()->admin()->create();
        $lernender = User::factory()->lernender()->create();
        Note::factory()->create(['lernender_id' => $lernender->lernender->lernender_id, 'note_wert' => 4.5]);

        $this->actingAs($admin)
            ->get(route('admin.reports.grades', ['semester' => 'alle']))
            ->assertOk()
            ->assertSeeInOrder(['ungenügend', 'knapp', 'genügend', 'gut']);
    }

    #[Test]
    public function nicht_zaehlendes_fach_gilt_im_bericht_nicht_als_ungenuegend(): void
    {
        $lernender = User::factory()->lernender()->create()->lernender;
        $idaf = Fach::factory()->create(['zaehlt' => false]);
        Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'fach_id' => $idaf->fach_id, 'note_wert' => 2.0]);
        Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'note_wert' => 5.0]);

        $zeile = app(Bericht::class)->noten(['semester_id' => null, 'lehrberuf_id' => null, 'berufsbildner_id' => null])['zeilen']
            ->firstWhere('id', (int) $lernender->lernender_id);

        $this->assertSame(0, $zeile->ungenuegend);
    }

    #[Test]
    public function gesamtschnitt_nach_lehrjahr_gruppiert_und_zaehlt_lernende(): void
    {
        Carbon::setTestNow('2026-09-11 10:00:00');

        $admin = User::factory()->admin()->create();

        // Lehrjahr 1: zwei Lernende mit Noten 4.0 und 6.0 → Schnitt 5.0, 2 Lernende.
        $a = User::factory()->lernender(['lehrbeginn' => '2026-08-01'])->create();
        Note::factory()->create(['lernender_id' => $a->lernender->lernender_id, 'note_wert' => 4.0]);
        $b = User::factory()->lernender(['lehrbeginn' => '2026-08-15'])->create();
        Note::factory()->create(['lernender_id' => $b->lernender->lernender_id, 'note_wert' => 6.0]);

        // Lehrjahr 2: eine Lernende mit Note 5.0 → Schnitt 5.0, 1 Lernende.
        $c = User::factory()->lernender(['lehrbeginn' => '2025-08-01'])->create();
        Note::factory()->create(['lernender_id' => $c->lernender->lernender_id, 'note_wert' => 5.0]);

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.grades', ['semester' => 'alle']))
            ->assertOk()
            ->assertSee('Gesamtschnitt nach Lehrjahr')
            ->assertSee('1. Lehrjahr')
            ->assertSee('2. Lehrjahr');

        $response->assertSeeInOrder(['1. Lehrjahr', '5.0', '2', '2. Lehrjahr', '5.0', '1']);

        Carbon::setTestNow();
    }
}
