<?php

namespace Tests\Feature\Lernender;

use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Die Zeugnisübersicht bleibt beim Semesterwechsel: die Ansicht steht in der Adresse und reist in den Links mit. */
class NotenAnsichtBleibtTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function semesterpfeile_tragen_die_zeugnisuebersicht_mit(): void
    {
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'ANS', 'name' => 'Ansicht EFZ']);
        DB::table('semester')->insert([
            ['bezeichnung' => '2025-1', 'start_datum' => '2025-08-01', 'end_datum' => '2026-01-31', 'sortierung' => 1],
            ['bezeichnung' => '2025-2', 'start_datum' => '2026-02-01', 'end_datum' => '2026-07-31', 'sortierung' => 2],
            ['bezeichnung' => '2026-1', 'start_datum' => '2026-08-01', 'end_datum' => '2027-01-31', 'sortierung' => 3],
        ]);
        Konfiguration::vergessen();
        Carbon::setTestNow('2026-03-02 09:00:00');
        $lernender = User::factory()->lernender(['lehrberuf_id' => $lehrberuf, 'lehrbeginn' => '2025-08-01', 'lehrende' => '2028-07-31'])->create();

        $html = $this->actingAs($lernender)->get(route('learner.grades.index', ['ansicht' => 'alle']))->assertOk()->getContent();

        // Startzustand aus der Adresse, beide Pfeile und die Kategorielinks tragen ansicht=alle und das Merkmal fürs Nachziehen
        $this->assertStringContainsString("ansicht: 'alle'", $html);
        preg_match_all('/<a[^>]*aria-label="(?:Vorheriges|Nächstes) Semester"[^>]*>/', $html, $pfeile);
        $this->assertCount(2, $pfeile[0]);
        foreach ($pfeile[0] as $pfeil) {
            $this->assertStringContainsString('ansicht=alle', $pfeil);
            $this->assertStringContainsString('data-behalte-ansicht', $pfeil);
        }

        // Ohne Parameter startet die Semesteransicht, die Pfeile tragen keine Ansicht
        $html = $this->actingAs($lernender)->get(route('learner.grades.index'))->assertOk()->getContent();
        $this->assertStringContainsString("ansicht: 'semester'", $html);
        $this->assertStringNotContainsString('ansicht=alle', $html);
    }
}
