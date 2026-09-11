<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Note;
use App\Models\User;
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

        $response->assertSeeInOrder(['Note', 'Zeugnisnoten', '4.5', '1']);
    }
}
