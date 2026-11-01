<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Modul;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CsvAuslieferungTest extends TestCase
{
    #[Test]
    public function alle_csv_exporte_mit_noten_und_feedback_sind_privat_und_no_store(): void
    {
        $admin = User::factory()->admin()->create();
        $bb = User::factory()->berufsbildner()->create();
        $lernender = User::factory()->lernender()->create();
        $lernenderId = $lernender->lernender->lernender_id;

        $antworten = [
            'learner.grades.export' => $this->actingAs($lernender)->get(route('learner.grades.export')),
            'trainer.grades.export_all' => $this->actingAs($bb)->get(route('trainer.grades.export_all')),
            'admin.learners.grades.export' => $this->actingAs($admin)->get(route('admin.learners.grades.export', $lernenderId)),
            'admin.reports.grades.export' => $this->actingAs($admin)->get(route('admin.reports.grades.export', ['semester' => 'alle'])),
            'admin.feedback.export' => $this->actingAs($admin)->get(route('admin.feedback.export')),
        ];

        foreach ($antworten as $route => $antwort) {
            $antwort->assertOk();
            $this->assertTrue($antwort->headers->hasCacheControlDirective('no-store'), $route.': no-store fehlt');
            $this->assertTrue($antwort->headers->hasCacheControlDirective('private'), $route.': private fehlt');
            $this->assertFalse($antwort->headers->hasCacheControlDirective('public'), $route.': public gesetzt');
            $antwort->streamedContent();
        }
    }

    #[Test]
    public function die_importvorlage_maskiert_modultitel_und_bleibt_einlesbar(): void
    {
        $a = User::factory()->lernender()->create();
        $b = User::factory()->lernender()->create();
        $this->actingAs($a)->post('/modules', ['modul_nummer' => 'Y77', 'titel' => 'Netz;=1+1;Klick']);
        $modul = Modul::where('modul_nummer', 'Y77')->firstOrFail();
        $this->actingAs($b)->post("/modules/{$modul->modul_id}/enroll");

        $csv = $this->actingAs($b)->get(route('learner.grades.import.template'))->assertOk()->getContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $zeilen = array_values(array_filter(explode("\r\n", substr($csv, 3))));
        $felder = array_map(fn (string $z) => str_getcsv($z, ';', '"', ''), $zeilen);

        $this->assertSame(['Datum', 'Fach/Modul', 'Titel', 'Note', 'Gewicht'], $felder[0]);
        $this->assertGreaterThanOrEqual(2, count($felder));
        foreach (array_slice($felder, 1) as $zeile) {
            $this->assertCount(5, $zeile, 'Semikolon im Titel darf die Zeile nicht aufspalten');
            $this->assertDoesNotMatchRegularExpression('/^[=+\-@]/', $zeile[1], 'Formelzelle');
        }
        $this->assertContains('Y77 Netz;=1+1;Klick', array_column(array_slice($felder, 1), 1));
    }

    #[Test]
    public function die_importvorlage_neutralisiert_eine_modulnummer_mit_gleichheitszeichen(): void
    {
        $a = User::factory()->lernender()->create();
        $this->actingAs($a)->post('/modules', ['modul_nummer' => '=cmd', 'titel' => 'Harmlos']);
        $modul = Modul::where('modul_nummer', '=cmd')->first();
        $this->assertNotNull($modul);
        $this->actingAs($a)->post("/modules/{$modul->modul_id}/enroll");

        $csv = $this->actingAs($a)->get(route('learner.grades.import.template'))->assertOk()->getContent();

        foreach (array_filter(explode("\r\n", substr($csv, 3))) as $z) {
            foreach (str_getcsv($z, ';', '"', '') as $feld) {
                $this->assertDoesNotMatchRegularExpression('/^[=+\-@]/', $feld);
            }
        }
        $this->assertStringContainsString("'=CMD", $csv);
    }
}
