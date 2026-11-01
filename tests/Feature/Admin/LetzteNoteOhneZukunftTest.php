<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Berufsbildner;
use App\Models\Betreuung;
use App\Models\Lernender;
use App\Models\Note;
use App\Models\Semester;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Bericht;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LetzteNoteOhneZukunftTest extends TestCase
{
    /** @return array{0: Lernender, 1: Berufsbildner, 2: string, 3: string} */
    private function lernenderMitAltUndZukunftsnote(): array
    {
        Konfiguration::vergessen();
        $semester = Semester::factory()->create(['start_datum' => '2026-08-01', 'end_datum' => '2027-01-31']);
        $lernender = Lernender::factory()->create(['lehrbeginn' => '2024-08-01', 'lehrende' => now()->addYear()->toDateString()]);
        $alt = now()->subDays(100)->toDateString();
        $zukunft = now()->addDays(60)->toDateString();
        Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'semester_id' => $semester->semester_id, 'pruefungsdatum' => $alt]);
        Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'semester_id' => $semester->semester_id, 'pruefungsdatum' => $zukunft]);
        $bb = Berufsbildner::factory()->create();
        Betreuung::factory()->create(['berufsbildner_id' => $bb->berufsbildner_id, 'lernender_id' => $lernender->lernender_id]);

        return [$lernender, $bb, $alt, $zukunft];
    }

    #[Test]
    public function eine_note_mit_zukuenftigem_datum_haelt_den_lernenden_nicht_aus_der_kachel_ohne_note(): void
    {
        [$lernender, $bb] = $this->lernenderMitAltUndZukunftsnote();
        $admin = User::factory()->admin()->create();

        $stats = $this->actingAs($admin)->get(route('admin.trainers.index'))->assertOk()->viewData('stats')[$bb->berufsbildner_id];

        $this->assertSame(1, $stats->ohne_noten);
        $liste = $this->actingAs($admin)->get(route('admin.learners.index', ['warnung' => 'keine_noten']))->assertOk()
            ->viewData('zeilen')->map(fn ($z) => $z->lernender->lernender_id)->all();
        $this->assertContains($lernender->lernender_id, $liste, 'Lernendenliste und Kachel müssen übereinstimmen.');
    }

    #[Test]
    public function der_bericht_zeigt_als_letzte_note_nicht_das_zukuenftige_datum_und_der_export_auch_nicht(): void
    {
        [$lernender, , $alt, $zukunft] = $this->lernenderMitAltUndZukunftsnote();
        $admin = User::factory()->admin()->create();

        $zeile = app(Bericht::class)->noten(['semester_id' => null, 'lehrberuf_id' => null, 'berufsbildner_id' => null])['zeilen']
            ->firstWhere('id', $lernender->lernender_id);

        $this->assertSame($alt, Carbon::parse($zeile->letzte)->toDateString());
        $this->assertSame(2, $zeile->pruefungen, 'Die Anzahl zählt weiterhin alle Noten.');

        $csv = $this->actingAs($admin)->get(route('admin.reports.grades.export', ['semester' => 'alle']))->assertOk()->streamedContent();
        $this->assertStringContainsString(Carbon::parse($alt)->format('d.m.Y'), $csv);
        $this->assertStringNotContainsString(Carbon::parse($zukunft)->format('d.m.Y'), $csv);
    }

    #[Test]
    public function lernende_nur_mit_zukunftsnote_haben_im_bericht_keine_letzte_note(): void
    {
        Konfiguration::vergessen();
        $semester = Semester::factory()->create(['start_datum' => '2026-08-01', 'end_datum' => '2027-01-31']);
        $lernender = Lernender::factory()->create(['lehrbeginn' => '2024-08-01']);
        Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'semester_id' => $semester->semester_id, 'pruefungsdatum' => now()->addDays(10)->toDateString()]);

        $zeile = app(Bericht::class)->noten(['semester_id' => null, 'lehrberuf_id' => null, 'berufsbildner_id' => null])['zeilen']
            ->firstWhere('id', $lernender->lernender_id);

        $this->assertNull($zeile->letzte);
    }
}
