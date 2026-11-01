<?php

namespace Tests\Feature\Lernender;

use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotenSemesterStarttagTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function am_ersten_semestertag_wird_das_laufende_semester_gewaehlt(): void
    {
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'SST', 'name' => 'Starttag EFZ']);
        $nummer = 1;
        for ($jahr = 2024; $jahr < 2028; $jahr++) {
            DB::table('semester')->insert(['bezeichnung' => "$jahr-1", 'start_datum' => "$jahr-08-01", 'end_datum' => ($jahr + 1).'-01-31', 'sortierung' => $nummer++]);
            DB::table('semester')->insert(['bezeichnung' => "$jahr-2", 'start_datum' => ($jahr + 1).'-02-01', 'end_datum' => ($jahr + 1).'-07-31', 'sortierung' => $nummer++]);
        }
        Konfiguration::vergessen();

        // Lehrende liegt in der Zukunft: das letzte Semester der Liste ist nicht das laufende
        $lernender = User::factory()->lernender(['lehrberuf_id' => $lehrberuf, 'lehrbeginn' => '2024-08-01', 'lehrende' => '2028-07-31'])->create();

        foreach (['2027-02-01', '2027-08-01', '2027-02-02', '2027-01-31', '2027-07-31'] as $tag) {
            Carbon::setTestNow($tag.' 09:00:00');

            $antwort = $this->actingAs($lernender)->get(route('learner.grades.index'));
            $antwort->assertOk();

            $erwartet = (int) DB::table('semester')->where('start_datum', '<=', $tag)->where('end_datum', '>=', $tag)->value('semester_id');
            $this->assertGreaterThan(0, $erwartet);
            $this->assertSame($erwartet, $antwort->viewData('selectedSemesterId'), "Am $tag muss das laufende Semester gewählt sein");
        }
    }
}
