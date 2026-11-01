<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Models\Dokument;
use App\Models\Note;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Import\NotenImport;
use App\Services\Import\ZeugnisAbgleich;
use App\Support\Csv;
use Database\Seeders\BasisSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Funktionsprüfung Runde 15: Rundreise Apostroph-Schutz der Noten-CSV und kanonischer Bezug «fach:ID» / «modul:ID». */
class BezugSchluesselTest extends TestCase
{
    private User $user;

    private int $lid;

    private int $fach;

    private int $sport;

    private int $semester;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(BasisSeeder::class);
        $kategorie = DB::table('kategorien')->pluck('kategorie_id', 'code');
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $this->fach = DB::table('faecher')->insertGetId(['kategorie_id' => $kategorie['ABU'], 'name' => 'Sprache und Kommunikation', 'kurzname' => 'SK']);
        $this->sport = DB::table('faecher')->insertGetId(['kategorie_id' => $kategorie['ABU'], 'name' => 'Sport', 'kurzname' => 'SP', 'skala' => 'stufe']);
        DB::table('lehrberuf_faecher')->insert([['lehrberuf_id' => $lehrberuf, 'fach_id' => $this->fach], ['lehrberuf_id' => $lehrberuf, 'fach_id' => $this->sport]]);
        $this->semester = DB::table('semester')->insertGetId(['bezeichnung' => '25/26-2', 'start_datum' => '2026-02-01', 'end_datum' => '2026-07-31', 'sortierung' => 10]);
        Konfiguration::vergessen();
        $this->user = User::factory()->lernender(['lehrberuf_id' => $lehrberuf, 'lehrbeginn' => '2024-08-01'])->create();
        $this->lid = (int) $this->user->lernender->lernender_id;
    }

    // ---- Apostroph-Schutz beim Wiedereinlesen -------------------------------------------------

    /** @return array<string, array{string, string}> */
    public static function formelTitel(): array
    {
        return [
            'Gleich' => ["'=1+1", '=1+1'],
            'Plus' => ["'+Test", '+Test'],
            'Minus' => ["'-Test", '-Test'],
            'At' => ["'@Test", '@Test'],
            'Tab' => ["'\tTest", "\tTest"],
            'normaler Apostroph bleibt' => ["'Hallo", "'Hallo"],
            'Apostroph in der Mitte bleibt' => ["L'Hôpital", "L'Hôpital"],
            'ohne Apostroph bleibt' => ['Test', 'Test'],
        ];
    }

    #[Test]
    #[DataProvider('formelTitel')]
    public function entschaerfen_macht_den_schutz_des_exports_rueckgaengig(string $eingabe, string $erwartet): void
    {
        $this->assertSame($erwartet, Csv::entschaerfen($eingabe));
    }

    #[Test]
    public function titel_mit_formelanfang_kommt_nach_export_und_import_unveraendert_an(): void
    {
        foreach (['=1+1', '+Test', '-Test', '@Test'] as $titel) {
            $this->assertSame($titel, Csv::entschaerfen(Csv::safe($titel)));
        }

        $tabelle = [['Datum', 'Fach/Modul', 'Titel', 'Note', 'Gewicht'], ['02.03.2026', 'SK', Csv::safe('=1+1'), '4,5', '50'], ['03.03.2026', 'SK', "'Hallo", '5', '50']];
        $vorschau = app(NotenImport::class)->vorschau($tabelle, $this->lid);

        $this->assertSame(['=1+1', "'Hallo"], array_column($vorschau['zeilen'], 'titel'));
    }

    // ---- Bezug ohne führende Nullen -----------------------------------------------------------

    private function zeile(string $bezug, string $datum = '2026-03-02', string|float $note = 4.5): array
    {
        return ['nr' => 1, 'datum' => $datum, 'bezug' => $bezug, 'titel' => null, 'note' => $note, 'gewicht' => 100, 'uebernehmen' => true];
    }

    /** @return array<string, array{string}> */
    public static function ungueltigeBezuege(): array
    {
        return ['fuehrende Nullen' => ['0'], 'nur Nullen' => ['00'], '19 Ziffern' => ['1234567890123456789']];
    }

    #[Test]
    public function bezug_mit_fuehrender_null_wird_nicht_als_zweite_zeile_derselben_note_angelegt(): void
    {
        $import = app(NotenImport::class);
        $benutzer = (int) $this->user->benutzer_id;
        $this->assertSame(1, $import->importieren([$this->zeile('fach:'.$this->fach)], $this->lid, $benutzer)['neu']);

        $zweite = $import->importieren([$this->zeile('fach:0'.$this->fach)], $this->lid, $benutzer);

        $this->assertSame(0, $zweite['neu']);
        $this->assertNotSame([], $zweite['fehler']);
        $this->assertSame(1, Note::query()->count());
    }

    #[Test]
    public function bezug_mit_fuehrender_null_auf_ein_stufenfach_wird_abgewiesen(): void
    {
        $ergebnis = app(NotenImport::class)->importieren([$this->zeile('fach:0'.$this->sport, '2026-03-02', 4.5)], $this->lid, (int) $this->user->benutzer_id);

        $this->assertSame(0, $ergebnis['neu']);
        $this->assertSame(0, Note::query()->count());
    }

    #[Test]
    #[DataProvider('ungueltigeBezuege')]
    public function zu_lange_oder_nullfuehrende_ids_sind_im_bezug_unzulaessig(string $id): void
    {
        $ergebnis = app(NotenImport::class)->importieren([$this->zeile('fach:'.$id)], $this->lid, (int) $this->user->benutzer_id);
        $this->assertSame(0, $ergebnis['neu']);

        $neu = app(NotenImport::class)->pruefeZeilen([['nr' => 1, 'datum' => '2026-03-02', 'bezug' => 'fach:'.$id, 'note' => '4.5', 'gewicht' => '100']], $this->lid);
        $this->assertNull($neu[0]['bezug']);
    }

    #[Test]
    public function zeugnis_abgleich_behandelt_fach_07_wie_fach_7(): void
    {
        $abgleich = app(ZeugnisAbgleich::class);
        $benutzer = (int) $this->user->benutzer_id;

        // Stufenfach bleibt auch mit führender Null ausgenommen
        $this->assertSame(0, $abgleich->uebernehmen([['bezug' => 'fach:0'.$this->sport, 'note' => '4.5', 'uebernehmen' => '1']], $this->semester, $this->lid, $benutzer)['neu']);
        $this->assertSame(0, Note::query()->count());

        // Dieselbe Zeugnisnote zweimal, einmal mit führender Null: nur eine Note
        $abgleich->uebernehmen([['bezug' => 'fach:'.$this->fach, 'note' => '4.5', 'uebernehmen' => '1']], $this->semester, $this->lid, $benutzer);
        $zweite = $abgleich->uebernehmen([['bezug' => 'fach:0'.$this->fach, 'note' => '4.5', 'uebernehmen' => '1']], $this->semester, $this->lid, $benutzer);
        $this->assertSame(0, $zweite['neu']);
        $this->assertSame(1, Note::query()->count());
    }

    #[Test]
    public function abgleich_uebernehmen_weist_bezug_mit_fuehrender_null_ab(): void
    {
        $this->actingAs($this->user)->post(route('learner.documents.store'), [
            'datei' => UploadedFile::fake()->create('zeugnis.pdf', 30, 'application/pdf'), 'art' => 'zeugnis', 'semester_id' => $this->semester,
        ])->assertSessionHasNoErrors();
        $dokument = Dokument::firstOrFail();

        $this->actingAs($this->user)->post(route('learner.documents.reconcile.apply', $dokument->dokument_id), [
            'semester_id' => $this->semester,
            'zeilen' => [['bezug' => 'fach:0'.$this->fach, 'note' => '4.5', 'uebernehmen' => '1']],
        ])->assertSessionHasErrors('zeilen.0.bezug');

        $this->assertSame(0, Note::query()->count());
    }

    #[Test]
    public function pruefung_anlegen_weist_bezug_mit_fuehrender_null_ab(): void
    {
        $this->actingAs($this->user)->post(route('learner.exams.store'), [
            'bezug' => 'fach:0'.$this->fach, 'datum' => '2026-03-02', 'gewichtung_prozent' => 100,
        ])->assertSessionHasErrors('bezug');
    }
}
