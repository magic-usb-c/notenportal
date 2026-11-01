<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Models\Note;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Import\TabellenLeser;
use Database\Seeders\BasisSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Befunde der Funktionsprüfung Runde 9: Speicher bei sehr vielen Leerzeilen, manipulierte Zeilen-JSON beim Prüfen und Übernehmen. */
class ImportRobustheitTest extends TestCase
{
    private User $user;

    private int $lid;

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
        $this->lid = (int) $this->user->lernender->lernender_id;
    }

    private function tempDatei(string $inhalt): string
    {
        $pfad = tempnam(sys_get_temp_dir(), 'np').'.csv';
        file_put_contents($pfad, $inhalt);

        return $pfad;
    }

    // ---- Speicher ------------------------------------------------------------------------------

    #[Test]
    public function csv_mit_sehr_vielen_leerzeilen_wird_ohne_speicherspitze_gelesen(): void
    {
        $inhalt = "Datum;Fach/Modul;Titel;Note\r\n".str_repeat("\r\n", 300000)."02.03.2026;M908;LB1;4,5\n03.03.2026;M908;LB2;5\n".str_repeat(";;;\n", 1000);
        $pfad = $this->tempDatei($inhalt);
        unset($inhalt);

        try {
            memory_reset_peak_usage();
            $vorher = memory_get_peak_usage();
            [$zeilen, $laenger] = (new TabellenLeser)->lesenMitHinweis($pfad, 'csv');
            $spitze = memory_get_peak_usage() - $vorher;
        } finally {
            @unlink($pfad);
        }

        $this->assertCount(3, $zeilen);
        $this->assertSame(['Datum', 'Fach/Modul', 'Titel', 'Note'], $zeilen[0]);
        $this->assertSame(['03.03.2026', 'M908', 'LB2', '5'], $zeilen[2]);
        $this->assertFalse($laenger);
        $this->assertLessThan(30 * 1024 * 1024, $spitze, 'Lesen darf nicht mit der Zeilenzahl der Datei wachsen.');
    }

    #[Test]
    public function csv_mit_sehr_vielen_kurzen_zeilen_bricht_nach_der_grenze_ab_und_meldet_mehr(): void
    {
        $pfad = $this->tempDatei("Datum;Fach/Modul;Titel;Note\n".str_repeat("02.03.2026;M908;T;4\n", 300000));

        try {
            memory_reset_peak_usage();
            $vorher = memory_get_peak_usage();
            [$zeilen, $laenger] = (new TabellenLeser)->lesenMitHinweis($pfad, 'csv');
            $spitze = memory_get_peak_usage() - $vorher;
        } finally {
            @unlink($pfad);
        }

        $this->assertCount(TabellenLeser::MAX_ZEILEN + 20, $zeilen);
        $this->assertTrue($laenger);
        $this->assertLessThan(30 * 1024 * 1024, $spitze);
    }

    #[Test]
    public function csv_trennzeichen_und_zeichensatz_bleiben_erkannt(): void
    {
        $leser = new TabellenLeser;

        $this->assertSame([['a', 'b'], ['c', 'd']], $leser->csv("\xEF\xBB\xBFa;b\r\n\r\nc;d\r"));
        $this->assertSame([['Datum', 'Titel'], ['x', 'Prüfung']], $leser->csv("\n\nDatum,Titel\nx,Pr\xFCfung\n"));
        $this->assertSame([['a', 'b'], ['c', 'd']], $leser->csv("a\tb\n \nc\td"));
    }

    #[Test]
    public function csv_hochladen_mit_vielen_leerzeilen_zeigt_die_vorschau(): void
    {
        $inhalt = "Datum;Fach/Modul;Titel;Note\n".str_repeat("\n", 300000)."02.03.2026;M908;LB1;4,5\n";

        $this->actingAs($this->user)->post(route('learner.grades.import.read'), ['datei' => UploadedFile::fake()->createWithContent('noten.csv', $inhalt)])
            ->assertRedirect(route('learner.grades.import.index'));

        $this->assertCount(1, session('notenimport.'.$this->lid)['zeilen']);
    }

    // ---- Manipuliertes JSON ----------------------------------------------------------------------

    /** @return array<string, array{string}> */
    public static function manipulierteZeilen(): array
    {
        return [
            'Array im Datum' => ['[{"nr":1,"datum":["x"],"bezug":"modul:1","note":"4.5","gewicht":100,"uebernehmen":true}]'],
            'Arrays in allen Feldern' => ['[{"nr":1,"datum":["x"],"bezug":[1],"note":[],"gewicht":{}}]'],
            'Objekt im Titel' => ['[{"nr":1,"datum":"2026-03-02","titel":{"a":1}}]'],
            'Zeile ist kein Array' => ['["x"]'],
            'Zeile ist eine Zahl' => ['[5]'],
        ];
    }

    private function vorschau(): array
    {
        $this->actingAs($this->user)->post(route('learner.grades.import.read'), ['datei' => UploadedFile::fake()->createWithContent('n.csv', "Datum;Fach/Modul;Titel;Note\n02.03.2026;M908;LB1;4,5\n")])->assertRedirect();

        return session('notenimport.'.$this->lid);
    }

    #[Test]
    #[DataProvider('manipulierteZeilen')]
    public function manipulierte_zeilen_geben_beim_pruefen_422(string $json): void
    {
        $this->vorschau();

        $this->postJson(route('learner.grades.import.validate'), ['zeilen' => $json])->assertStatus(422);
    }

    #[Test]
    #[DataProvider('manipulierteZeilen')]
    public function manipulierte_zeilen_geben_beim_uebernehmen_422_und_erhalten_die_vorschau(string $json): void
    {
        $vorschau = $this->vorschau();

        $this->post(route('learner.grades.import.apply'), ['zeilen' => $json, 'token' => $vorschau['token']])->assertStatus(422);

        $this->assertSame($vorschau['token'], session('notenimport.'.$this->lid)['token']);
        $this->assertSame(0, Note::query()->where('lernender_id', $this->lid)->count());
    }

    #[Test]
    public function unveraenderte_vorschau_laesst_sich_weiterhin_pruefen_und_uebernehmen(): void
    {
        $vorschau = $this->vorschau();
        $json = json_encode($vorschau['zeilen']);

        $this->postJson(route('learner.grades.import.validate'), ['zeilen' => $json])->assertOk();
        $this->post(route('learner.grades.import.apply'), ['zeilen' => $json, 'token' => $vorschau['token']])->assertSessionHas('success');
        $this->assertSame(1, Note::query()->where('lernender_id', $this->lid)->count());
    }
}
