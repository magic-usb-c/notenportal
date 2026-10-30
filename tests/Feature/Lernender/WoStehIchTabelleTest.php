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
 * Übersicht «Wo stehe ich»: Der Filter «modus» (Semester oder Lehrzeit) steht in der Adresse; der Server
 * zeichnet Diagramm und Tabelle des gewählten Zeitraums, ohne JavaScript gilt derselbe Stand.
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

    /** Die Tabelle des Bausteins «Wo stehe ich» samt Inhalt (genau eine, die des gewählten Zeitraums). */
    private function tabelle(string $html): ?string
    {
        preg_match('/data-np-baustein="wostehe".*?<\/section>/s', $html, $baustein);
        preg_match('/<table.*?<\/table>/s', $baustein[0] ?? '', $treffer);

        return $treffer[0] ?? null;
    }

    #[Test]
    public function der_baustein_zeigt_genau_eine_tabelle_und_der_filter_haengt_in_der_adresse(): void
    {
        $this->zweiSemester();

        $html = $this->get(route('learner.dashboard'))->assertOk()->getContent();

        $this->assertNotNull($this->tabelle($html), 'Tabelle fehlt');
        preg_match('/data-np-baustein="wostehe".*?<\/section>/s', $html, $baustein);
        $this->assertSame(1, substr_count($baustein[0], '<table'), 'Genau eine Tabelle im Baustein');
        $this->assertStringNotContainsString('data-zeitraum=', $html);
        $this->assertMatchesRegularExpression('/<select name="modus"[^>]*>\s*<option value="semester"\s+selected/', $html);

        $lehrzeit = $this->get(route('learner.dashboard', ['modus' => 'lehrzeit']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="lehrzeit"\s+selected/', $lehrzeit);
    }

    #[Test]
    public function die_zeilen_unterscheiden_semester_und_lehrzeit(): void
    {
        $this->zweiSemester();

        $semester = $this->tabelle($this->get(route('learner.dashboard', ['modus' => 'semester']))->assertOk()->getContent());
        $lehrzeit = $this->tabelle($this->get(route('learner.dashboard', ['modus' => 'lehrzeit']))->assertOk()->getContent());

        $this->assertNotNull($semester);
        $this->assertNotNull($lehrzeit);
        $this->assertStringContainsString('Testmodul Theta durchführen', $semester);
        $this->assertStringNotContainsString('Testmodul Omega prüfen', $semester);
        $this->assertStringContainsString('Testmodul Theta durchführen', $lehrzeit);
        $this->assertStringContainsString('Testmodul Omega prüfen', $lehrzeit);
    }

    #[Test]
    public function ohne_filter_gilt_der_servermodus_semester(): void
    {
        $this->zweiSemester();

        $tabelle = $this->tabelle($this->get(route('learner.dashboard'))->assertOk()->getContent());

        $this->assertNotNull($tabelle);
        $this->assertStringContainsString('Testmodul Theta durchführen', $tabelle);
        $this->assertStringNotContainsString('Testmodul Omega prüfen', $tabelle);
    }
}
