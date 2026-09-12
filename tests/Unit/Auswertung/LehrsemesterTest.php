<?php

declare(strict_types=1);

namespace Tests\Unit\Auswertung;

use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Support\Lehrsemester;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * app/Support/Lehrsemester.php: relative Semesternummer eines Lernenden
 * («5. Semester» statt Rohcode «26/27-1»), Grundlage von Block B.
 */
class LehrsemesterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Konfiguration::vergessen();
        Lehrsemester::vergessen();
    }

    /** @return array<string, int> bezeichnung => semester_id */
    private function semesterReihe(): array
    {
        $reihen = [
            ['bezeichnung' => '24/25-1', 'start_datum' => '2024-08-01', 'end_datum' => '2025-01-31', 'sortierung' => 1],
            ['bezeichnung' => '24/25-2', 'start_datum' => '2025-02-01', 'end_datum' => '2025-07-31', 'sortierung' => 2],
            ['bezeichnung' => '25/26-1', 'start_datum' => '2025-08-01', 'end_datum' => '2026-01-31', 'sortierung' => 3],
            ['bezeichnung' => '25/26-2', 'start_datum' => '2026-02-01', 'end_datum' => '2026-07-31', 'sortierung' => 4],
            ['bezeichnung' => '26/27-1', 'start_datum' => '2026-08-01', 'end_datum' => '2027-01-31', 'sortierung' => 5],
        ];

        $ids = [];
        foreach ($reihen as $r) {
            $ids[$r['bezeichnung']] = DB::table('semester')->insertGetId($r);
        }

        return $ids;
    }

    private function lernender(string $lehrbeginn): int
    {
        return (int) User::factory()->lernender(['lehrbeginn' => $lehrbeginn])->create()->lernender->lernender_id;
    }

    #[Test]
    public function start_2425_1_ist_in_2627_1_im_5_semester(): void
    {
        $s = $this->semesterReihe();
        $l = $this->lernender('2024-08-01');

        $this->assertSame(5, Lehrsemester::nummer($l, $s['26/27-1']));
    }

    #[Test]
    public function start_2627_1_ist_dort_selbst_im_1_semester(): void
    {
        $s = $this->semesterReihe();
        $l = $this->lernender('2026-08-01');

        $this->assertSame(1, Lehrsemester::nummer($l, $s['26/27-1']));
    }

    #[Test]
    public function lehrbeginn_mitten_im_semester_zaehlt_ab_dessen_semester(): void
    {
        $s = $this->semesterReihe();
        // 15.10.2024 liegt innerhalb 24/25-1 (01.08.2024 - 31.01.2025) -> gilt als 1. Semester.
        $l = $this->lernender('2024-10-15');

        $this->assertSame(1, Lehrsemester::nummer($l, $s['24/25-1']));
        $this->assertSame(2, Lehrsemester::nummer($l, $s['24/25-2']));
        $this->assertSame(5, Lehrsemester::nummer($l, $s['26/27-1']));
    }

    #[Test]
    public function semester_vor_dem_lehrbeginn_liefert_null(): void
    {
        $s = $this->semesterReihe();
        $l = $this->lernender('2025-08-01');

        $this->assertNull(Lehrsemester::nummer($l, $s['24/25-1']));
        $this->assertNull(Lehrsemester::nummer($l, $s['24/25-2']));
        $this->assertSame(1, Lehrsemester::nummer($l, $s['25/26-1']));
    }

    #[Test]
    public function luecke_in_der_sortierung_bricht_die_zaehlung_nicht(): void
    {
        $s = $this->semesterReihe();
        DB::table('semester')->where('semester_id', $s['24/25-2'])->delete();
        Lehrsemester::vergessen();

        $l = $this->lernender('2024-08-01');

        // 24/25-1 (sortierung 1) bleibt das 1. Semester, 26/27-1 (sortierung 5) trotz Lücke weiterhin das 5.
        $this->assertSame(1, Lehrsemester::nummer($l, $s['24/25-1']));
        $this->assertSame(5, Lehrsemester::nummer($l, $s['26/27-1']));
    }

    #[Test]
    public function unbekanntes_semester_liefert_null(): void
    {
        $this->semesterReihe();
        $l = $this->lernender('2024-08-01');

        $this->assertNull(Lehrsemester::nummer($l, 999999));
    }

    #[Test]
    public function name_formatiert_die_nummer(): void
    {
        $this->assertSame('5. Semester', Lehrsemester::name(5));
        $this->assertSame('1. Semester', Lehrsemester::name(1));
    }

    #[Test]
    public function vorladen_liest_keine_zusaetzliche_abfrage_pro_person(): void
    {
        $s = $this->semesterReihe();
        $l1 = $this->lernender('2024-08-01');
        $l2 = $this->lernender('2026-08-01');

        Lehrsemester::vorladen(DB::table('lernende')->whereIn('lernender_id', [$l1, $l2])->pluck('lehrbeginn', 'lernender_id')->all());

        DB::enableQueryLog();
        $n1 = Lehrsemester::nummer($l1, $s['26/27-1']);
        $n2 = Lehrsemester::nummer($l2, $s['26/27-1']);
        $abfragen = count(DB::getQueryLog());
        DB::flushQueryLog();
        DB::disableQueryLog();

        $this->assertSame(5, $n1);
        $this->assertSame(1, $n2);
        $this->assertSame(0, $abfragen);
    }
}
