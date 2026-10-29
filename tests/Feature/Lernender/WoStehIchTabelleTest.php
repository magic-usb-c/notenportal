<?php

declare(strict_types=1);

namespace Tests\Feature\Lernender;

use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Übersicht «Wo stehe ich»: Der Umschalter wechselt Semester und Lehrzeit, deshalb stehen beide Tabellen
 * serverseitig im Dokument; Alpine blendet per x-show um, ohne JS gilt der Servermodus.
 */
class WoStehIchTabelleTest extends TestCase
{
    private int $modulNeu;

    private int $modulAlt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $fach = DB::table('kategorien')->where('code', 'FACH')->value('kategorie_id');
        $this->modulNeu = $this->modul($lehrberuf, $fach, '908', 'Testmodul Theta durchführen');
        $this->modulAlt = $this->modul($lehrberuf, $fach, '909', 'Testmodul Omega prüfen');
        DB::table('semester')->insert([
            ['bezeichnung' => 'T-1', 'start_datum' => now()->subMonths(8)->toDateString(), 'end_datum' => now()->subMonths(2)->toDateString(), 'sortierung' => 10],
            ['bezeichnung' => 'T-2', 'start_datum' => now()->subMonths(2)->addDay()->toDateString(), 'end_datum' => now()->addMonths(4)->toDateString(), 'sortierung' => 11],
        ]);
        Konfiguration::vergessen();
        $this->actingAs(User::factory()->lernender(['lehrberuf_id' => $lehrberuf])->create());
    }

    private function modul(int $lehrberuf, int $kategorie, string $nummer, string $titel): int
    {
        $id = DB::table('module')->insertGetId(['modul_nummer' => $nummer, 'titel' => $titel]);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $lehrberuf, 'modul_id' => $id, 'kategorie_id' => $kategorie]);

        return $id;
    }

    private function note(int $modul, string $wert, string $datum): void
    {
        $this->post(route('learner.grades.store'), [
            'typ' => 'modul', 'modul_id' => $modul, 'pruefungsdatum' => $datum,
            'note_wert' => $wert, 'gewichtung_prozent' => 100,
        ])->assertSessionHasNoErrors();
    }

    /** Eine Note im laufenden Semester (T-2), eine im vorigen (T-1): nur die Lehrzeit kennt beide. */
    private function zweiSemester(): void
    {
        $this->note($this->modulNeu, '5.0', now()->subDays(3)->toDateString());
        $this->note($this->modulAlt, '4.0', now()->subMonths(5)->toDateString());
    }

    /** Das <table>-Element eines Zeitraums samt Inhalt. */
    private function tabelle(string $html, string $zeitraum): ?string
    {
        preg_match('/<table[^>]*data-zeitraum="'.$zeitraum.'".*?<\/table>/s', $html, $treffer);

        return $treffer[0] ?? null;
    }

    #[Test]
    public function beide_tabellen_stehen_serverseitig_im_dokument_und_haengen_am_modus(): void
    {
        $this->zweiSemester();

        $html = $this->get(route('learner.dashboard'))->assertOk()->getContent();

        $semester = $this->tabelle($html, 'semester');
        $lehrzeit = $this->tabelle($html, 'lehrzeit');
        $this->assertNotNull($semester, 'Tabelle Semester fehlt');
        $this->assertNotNull($lehrzeit, 'Tabelle Lehrzeit fehlt');
        $this->assertStringContainsString("x-show=\"modus === 'semester'\"", $semester);
        $this->assertStringContainsString("x-show=\"modus === 'lehrzeit'\"", $lehrzeit);
        $this->assertSame(2, substr_count($html, 'data-zeitraum='), 'Genau eine Tabelle je Zeitraum');
    }

    #[Test]
    public function die_zeilen_unterscheiden_semester_und_lehrzeit(): void
    {
        $this->zweiSemester();

        $html = $this->get(route('learner.dashboard'))->assertOk()->getContent();
        $semester = $this->tabelle($html, 'semester');
        $lehrzeit = $this->tabelle($html, 'lehrzeit');

        $this->assertStringContainsString('Testmodul Theta durchführen', $semester);
        $this->assertStringNotContainsString('Testmodul Omega prüfen', $semester);
        $this->assertStringContainsString('Testmodul Theta durchführen', $lehrzeit);
        $this->assertStringContainsString('Testmodul Omega prüfen', $lehrzeit);
    }

    #[Test]
    public function ohne_javascript_ist_der_servermodus_sichtbar(): void
    {
        $this->zweiSemester();

        $html = $this->get(route('learner.dashboard'))->assertOk()->getContent();

        // Semester hat Zeilen, also ist es der Servermodus: es trägt kein x-cloak, die Lehrzeit schon
        $this->assertStringNotContainsString('x-cloak', strtok($this->tabelle($html, 'semester'), '>'));
        $this->assertStringContainsString('x-cloak', strtok($this->tabelle($html, 'lehrzeit'), '>'));
        $this->assertMatchesRegularExpression('/modus: (\'|&#039;)semester(\'|&#039;)/', $html);
    }
}
