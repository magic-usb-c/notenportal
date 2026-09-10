<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Import\NotenImport;
use App\Services\Import\TabellenLeser;
use Database\Seeders\BasisSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotenImportTest extends TestCase
{
    private User $user;

    private int $modul;

    private int $fach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $kategorie = DB::table('kategorien')->pluck('kategorie_id', 'code');
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $this->modul = DB::table('module')->insertGetId(['modul_nummer' => '431', 'titel' => 'Aufträge durchführen']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $lehrberuf, 'modul_id' => $this->modul, 'kategorie_id' => $kategorie['FACH']]);
        $this->fach = DB::table('faecher')->insertGetId(['kategorie_id' => $kategorie['ABU'], 'name' => 'Sprache und Kommunikation', 'kurzname' => 'SK']);
        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $lehrberuf, 'fach_id' => $this->fach]);
        DB::table('semester')->insert(['bezeichnung' => '25/26-2', 'start_datum' => '2026-02-01', 'end_datum' => '2026-07-31', 'sortierung' => 10]);
        Konfiguration::vergessen();
        $this->user = User::factory()->lernender(['lehrberuf_id' => $lehrberuf, 'lehrbeginn' => '2024-08-01', 'lehrende' => '2028-07-31'])->create();
    }

    #[Test]
    public function csv_wird_erkannt_zugeordnet_und_nach_pruefung_importiert(): void
    {
        $csv = "Datum;Fach/Modul;Titel;Note;Gewicht\n02.03.2026;M431;LB1;4,5;50%\n09.03.2026;Sprache;Vortrag;5.0;\n10.03.2026;Turnen;X;4;100\n";
        $this->actingAs($this->user)
            ->post(route('lernender.noten.import.lesen'), ['datei' => UploadedFile::fake()->createWithContent('noten.csv', $csv)])
            ->assertRedirect(route('lernender.noten.import.index'));

        $vorschau = session('notenimport.'.$this->user->lernender->lernender_id);
        $this->assertSame(['ok', 'pruefen', 'fehler'], array_column($vorschau['zeilen'], 'status'));
        $this->assertSame('modul:'.$this->modul, $vorschau['zeilen'][0]['bezug']);
        $this->assertSame(50.0, $vorschau['zeilen'][0]['gewicht']);
        $this->get(route('lernender.noten.import.index'))->assertOk()->assertSee('noten.csv');

        $this->post(route('lernender.noten.import.uebernehmen'), ['zeilen' => json_encode($vorschau['zeilen'])])
            ->assertRedirect(route('lernender.noten.index'))->assertSessionHas('success');

        $this->assertEqualsCanonicalizing([4.5, 5.0], Note::query()->pluck('note_wert')->map(fn ($n) => (float) $n)->all());
        $this->assertSame(50.0, (float) Note::query()->where('note_wert', 4.5)->value('gewichtung_prozent'));

        $this->post(route('lernender.noten.import.lesen'), ['datei' => UploadedFile::fake()->createWithContent('noten.csv', $csv)]);
        $this->assertSame('doppelt', session('notenimport.'.$this->user->lernender->lernender_id)['zeilen'][0]['status']);
    }

    #[Test]
    public function excel_ohne_kopfzeile_wird_nach_inhalt_gelesen(): void
    {
        $blatt = new Spreadsheet;
        $blatt->getActiveSheet()->fromArray([
            [Date::PHPToExcel(new \DateTime('2026-04-14')), 'Modul 431 Aufträge', 'Projekt', 5.5],
            [Date::PHPToExcel(new \DateTime('2026-05-05')), 'SK', 'Aufsatz', 4.25],
        ]);
        $pfad = tempnam(sys_get_temp_dir(), 'np').'.xlsx';
        (new Xlsx($blatt))->save($pfad);

        $tabelle = app(TabellenLeser::class)->lesen($pfad, 'xlsx');
        $zeilen = app(NotenImport::class)->vorschau($tabelle, (int) $this->user->lernender->lernender_id)['zeilen'];
        unlink($pfad);

        $this->assertSame(['2026-04-14', '2026-05-05'], array_column($zeilen, 'datum'));
        $this->assertSame([5.5, 4.25], array_column($zeilen, 'note'));
        $this->assertSame(['modul:'.$this->modul, 'fach:'.$this->fach], array_column($zeilen, 'bezug'));
    }

    #[Test]
    public function pdf_text_und_werte_werden_sauber_zerlegt(): void
    {
        $zeilen = app(TabellenLeser::class)->textZeilen("Notenliste Nina\n02.03.2026 M431 Aufträge 4.5 50 %\n\nDatum    Fach    Note");
        $this->assertSame(['02.03.2026', 'M431 Aufträge', '', '4.5', '50'], $zeilen[1]);

        $import = app(NotenImport::class);
        $this->assertSame('2026-03-02', $import->datum('2.3.2026'));
        $this->assertNull($import->note('4.33'));
        $this->assertSame(75.0, $import->gewicht('0.75'));
        $this->assertFalse($import->gewicht('150'));
    }

    #[Test]
    public function berufsbildner_importieren_keine_noten_admin_schon(): void
    {
        $lernenderId = (int) $this->user->lernender->lernender_id;
        $bb = User::factory()->berufsbildner()->create();
        DB::table('betreuungen')->insert(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $lernenderId, 'gueltig_von' => '2024-08-01']);

        $this->actingAs($bb)->get(route('berufsbildner.lernende.noten.import.index', $lernenderId))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.lernende.noten.import.index', $lernenderId))->assertOk();
        $this->get(route('admin.lernende.noten.import.vorlage', $lernenderId))->assertOk()->assertSee('Datum;Fach/Modul;Titel;Note;Gewicht', false);
    }
}
