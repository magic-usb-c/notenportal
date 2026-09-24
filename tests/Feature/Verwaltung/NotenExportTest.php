<?php

declare(strict_types=1);

namespace Tests\Feature\Verwaltung;

use App\Models\Note;
use App\Models\Semester;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotenExportTest extends TestCase
{
    use VerwaltungTestHilfen;

    #[Test]
    public function stammdaten_mit_formelzeichen_werden_im_csv_entschaerft(): void
    {
        $lernender = $this->neuerLernender();
        $semester = Semester::factory()->create(['bezeichnung' => '=1+2']);
        Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'semester_id' => $semester->semester_id]);

        $csv = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.learners.grades.export', $lernender->lernender_id))->assertOk()->streamedContent();

        $this->assertStringContainsString("'=1+2", $csv);
    }
}
