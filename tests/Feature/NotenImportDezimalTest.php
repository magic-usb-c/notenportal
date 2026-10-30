<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use Database\Seeders\BasisSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Notenimport: Note als Textfeld mit Dezimalkomma, Statusnamen in Satzschreibung, Meldung an den Feldern verknüpft. */
class NotenImportDezimalTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $kategorie = DB::table('kategorien')->pluck('kategorie_id', 'code');
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $modul = DB::table('module')->insertGetId(['modul_nummer' => '908', 'titel' => 'Testmodul Theta durchführen']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $lehrberuf, 'modul_id' => $modul, 'kategorie_id' => $kategorie['FACH']]);
        DB::table('semester')->insert(['bezeichnung' => '25/26-2', 'start_datum' => '2026-02-01', 'end_datum' => '2026-07-31', 'sortierung' => 10]);
        Konfiguration::vergessen();
        $this->user = User::factory()->lernender(['lehrberuf_id' => $lehrberuf, 'lehrbeginn' => '2024-08-01'])->create();
    }

    private function vorschau(): array
    {
        $csv = "Datum;Fach/Modul;Titel;Note;Gewicht\n02.03.2026;M908;LB1;5.0;100\n";
        $this->actingAs($this->user)
            ->post(route('learner.grades.import.read'), ['datei' => UploadedFile::fake()->createWithContent('noten.csv', $csv)]);

        return session('notenimport.'.$this->user->lernender->lernender_id);
    }

    #[Test]
    public function eine_in_der_vorschau_getippte_note_mit_komma_wird_uebernommen(): void
    {
        $vorschau = $this->vorschau();
        $vorschau['zeilen'][0]['note'] = '4,5';

        $this->post(route('learner.grades.import.apply'), ['zeilen' => json_encode($vorschau['zeilen']), 'token' => $vorschau['token']])
            ->assertSessionHas('success');

        $this->assertEquals([4.5], Note::query()->pluck('note_wert')->map(fn ($n) => (float) $n)->all());
    }

    #[Test]
    public function die_note_der_vorschau_ist_ein_textfeld_und_meldung_und_felder_sind_verknuepft(): void
    {
        $this->vorschau();

        $html = $this->get(route('learner.grades.import.index'))->assertOk()->getContent();
        $this->assertSame(1, preg_match('/<input\b[^>]*x-model="z\.note"[^>]*>/s', $html, $m), 'Notenfeld fehlt');
        $this->assertStringContainsString('type="text"', $m[0]);
        $this->assertStringContainsString('inputmode="decimal"', $m[0]);
        $this->assertStringNotContainsString('x-model.number="z.note"', $html);

        // Gewicht ebenso: ein Zahlenfeld mit step=1 blockierte 33.33 im Browser
        $this->assertSame(1, preg_match('/<input\b[^>]*x-model="z\.gewicht"[^>]*>/s', $html, $g), 'Gewichtsfeld fehlt');
        $this->assertStringContainsString('type="text"', $g[0]);
        $this->assertStringContainsString('inputmode="decimal"', $g[0]);

        // aria-invalid braucht aria-describedby auf die Zeilenmeldung (Datum, Fach/Modul, Note)
        $this->assertStringContainsString(":id=\"'import-meldung-' + z.nr\"", $html);
        $this->assertGreaterThanOrEqual(3, substr_count($html, ':aria-describedby="meldungId(z)"'));
    }

    #[Test]
    public function statusnamen_beginnen_gross(): void
    {
        $this->vorschau();

        $html = $this->get(route('learner.grades.import.index'))->assertOk()->getContent();
        foreach (['Bereit', 'Prüfen', 'Bereits erfasst', 'Warnung', 'Fehler'] as $name) {
            $this->assertStringContainsString($name, $html);
        }
        // Jede Statusbezeichnung beginnt gross: in der Zuordnung des Skripts und in der Kennzahlenleiste
        $this->assertMatchesRegularExpression("/ok:\\s*'Bereit'/u", $html);
        $this->assertMatchesRegularExpression("/pruefen:\\s*'Prüfen'/u", $html);
        $this->assertMatchesRegularExpression("/doppelt:\\s*'Bereits erfasst'/u", $html);
        foreach (['bereit', 'prüfen', 'bereits erfasst'] as $klein) {
            $this->assertStringNotContainsString('>'.$klein.'<', $html);
            $this->assertStringNotContainsString("'".$klein."'", $html);
        }
    }
}
