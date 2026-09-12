<?php

declare(strict_types=1);

namespace Tests\Unit\Auswertung;

use App\Models\Semester;
use App\Services\Auswertung\Konfiguration;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * app/Models/Semester.php::neutralerName(): neutraler Semestername für Kontexte mit mehreren
 * Lernenden («HS 2026/27»/«FS 2027»). Review-Nachbesserung: die Jahreszeit wird aus der
 * chronologischen Position im Semesterplan abgeleitet statt aus einer festen Kalendergrenze
 * (Semesterstarts sind frei konfigurierbar, App\Support\Einrichtung::semesterPlan()), und der
 * Name bleibt garantiert eindeutig, auch wenn das eine Kollision ergäbe.
 */
class SemesterNeutralerNameTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Konfiguration::vergessen();
    }

    private function anlegen(string $bezeichnung, string $start, string $ende, int $sortierung): int
    {
        return (int) DB::table('semester')->insertGetId([
            'bezeichnung' => $bezeichnung, 'start_datum' => $start, 'end_datum' => $ende, 'sortierung' => $sortierung,
        ]);
    }

    #[Test]
    public function standardplan_herbst_vor_fruehling(): void
    {
        $this->anlegen('24/25-1', '2024-08-01', '2025-01-31', 1);
        $this->anlegen('24/25-2', '2025-02-01', '2025-07-31', 2);

        $this->assertSame('HS 2024/25', Semester::neutralerName('2024-08-01'));
        $this->assertSame('FS 2025', Semester::neutralerName('2025-02-01'));
    }

    #[Test]
    public function jahreszeit_richtet_sich_nach_position_nicht_nach_festem_monat(): void
    {
        // Ungewöhnliche Planung: Herbstsemester startet im Juli (Monat 7). Die alte, fest
        // verdrahtete Grenze (">= 8 => Herbst") hätte das fälschlich als Frühling eingestuft.
        // Massgeblich ist die Position im Plan: das erste (chronologisch früheste) Semester
        // ist immer «Herbst», unabhängig vom Kalendermonat.
        $this->anlegen('X-1', '2026-07-01', '2026-12-31', 1);
        $this->anlegen('X-2', '2027-01-01', '2027-06-30', 2);

        $this->assertSame('HS 2026/27', Semester::neutralerName('2026-07-01'));
        $this->assertSame('FS 2027', Semester::neutralerName('2027-01-01'));
    }

    #[Test]
    public function kollidierende_namen_bleiben_durch_startdatum_eindeutig(): void
    {
        // Drei Semester pro Jahr (ausserhalb des üblichen Zweier-Rhythmus): das 2. und das 4.
        // Semester (beide "Frühling" nach Position) starten beide 2026 und würden ohne
        // Absicherung denselben neutralen Namen «FS 2026» tragen.
        $this->anlegen('A', '2025-09-01', '2026-01-14', 1); // Position 0 -> Herbst
        $this->anlegen('B', '2026-01-15', '2026-05-31', 2); // Position 1 -> Frühling, Jahr 2026
        $this->anlegen('C', '2026-06-01', '2026-10-31', 3); // Position 2 -> Herbst
        $this->anlegen('D', '2026-11-01', '2027-01-31', 4); // Position 3 -> Frühling, Jahr 2026 (Kollision mit B)

        $namenB = Semester::neutralerName('2026-01-15');
        $namenD = Semester::neutralerName('2026-11-01');

        $this->assertSame('FS 2026', $namenB);
        $this->assertNotSame($namenB, $namenD, 'Zwei verschiedene Semester dürfen nie denselben neutralen Namen tragen.');
        $this->assertStringContainsString('FS 2026', $namenD);
        $this->assertStringContainsString('01.11.2026', $namenD);
    }

    #[Test]
    public function unbekanntes_startdatum_liefert_null(): void
    {
        $this->anlegen('24/25-1', '2024-08-01', '2025-01-31', 1);

        $this->assertNull(Semester::neutralerName('2099-01-01'));
        $this->assertNull(Semester::neutralerName(null));
    }
}
