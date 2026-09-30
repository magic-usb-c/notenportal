<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Dokument;
use App\Models\Note;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Import\NotenImport;
use App\Services\Import\Schulnetz;
use App\Services\Import\TabellenLeser;
use App\Services\Import\ZeugnisAbgleich;
use Database\Seeders\BasisSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Formate aus der Praxis: Schulnetz-Exporte (Aktuelle Noten, Zeugnisnoten, Lehrpersonenliste) und Zeugnisse mit OCR-Textlayer.
 * Fixtures sind anonymisiert, Aufbau und Textfluss wie der Textlayer der Originale (smalot/pdfparser).
 */
class ImportFormateTest extends TestCase
{
    private User $user;

    private int $lernenderId;

    private int $semester;

    /** @var array<string, int> */
    private array $modul = [];

    /** @var array<string, int> */
    private array $fach = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(BasisSeeder::class);
        $k = DB::table('kategorien')->pluck('kategorie_id', 'code');
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        foreach (['904' => 'FACH', '901' => 'FACH', '902' => 'FACH', '906' => 'FACH', '908' => 'FACH', '907' => 'UEK'] as $nr => $ort) {
            $this->modul[$nr] = DB::table('module')->insertGetId(['modul_nummer' => (string) $nr, 'titel' => 'Modul '.$nr]);
            DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $lehrberuf, 'modul_id' => $this->modul[$nr], 'kategorie_id' => $k[$ort]]);
        }
        foreach (['Deutsch' => 'D', 'Englisch' => 'E', 'Mathematik' => 'M', 'Naturwissenschaften' => 'NW', 'Wirtschaft und Recht' => 'WR', 'Interdisziplinäres Arbeiten in den Fächern' => 'IDAF'] as $name => $kurz) {
            $this->fach['BMS '.$name] = DB::table('faecher')->insertGetId(['kategorie_id' => $k['BMS'], 'track_typ' => 'BMS', 'name' => $name, 'kurzname' => $kurz]);
        }
        foreach (['Englisch' => 'EN', 'Mathematik' => 'MA'] as $name => $kurz) {
            $this->fach[$name] = DB::table('faecher')->insertGetId(['kategorie_id' => $k['FACH'], 'name' => $name, 'kurzname' => $kurz]);
            DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $lehrberuf, 'fach_id' => $this->fach[$name]]);
        }
        foreach ([['25/26-1', '2025-08-01', '2026-01-31'], ['25/26-2', '2026-02-01', '2026-07-31'], ['26/27-1', '2026-08-01', '2027-01-31']] as $i => [$b, $s, $e]) {
            $this->semester = DB::table('semester')->insertGetId(['bezeichnung' => $b, 'start_datum' => $s, 'end_datum' => $e, 'sortierung' => $i + 1]);
        }
        Konfiguration::vergessen();
        $this->user = User::factory()->lernender(['lehrberuf_id' => $lehrberuf, 'lehrbeginn' => '2025-08-01', 'lehrende' => '2029-07-31'])->create();
        $this->lernenderId = (int) $this->user->lernender->lernender_id;
        DB::table('lernender_tracks')->insert(['lernender_id' => $this->lernenderId, 'track_typ' => 'BMS', 'start_datum' => '2025-08-01',
            'start_semester_id' => DB::table('semester')->where('bezeichnung', '25/26-1')->value('semester_id')]);
    }

    #[Test]
    public function schulnetz_aktuelle_noten_liefern_kurs_thema_note_und_gewicht(): void
    {
        $tabelle = app(TabellenLeser::class)->pdfText($this->fixture('schulnetz-aktuelle-noten.txt'));
        $vorschau = app(NotenImport::class)->vorschau($tabelle, $this->lernenderId);
        $z = collect($vorschau['zeilen'])->keyBy('titel');

        $this->assertSame(Schulnetz::AKTUELLE_NOTEN, $vorschau['format']);
        $this->assertSame(['LB1: Beta-Grundlagen und Begriffe', 'LB2: Beta-Einstellungen', 'LB3: Beta-Abgleich', 'Presentation Module 901', 'Vocabulary Test 2'], $z->keys()->all());
        $werte = fn (string $titel) => [$z[$titel]['bezug'], $z[$titel]['datum'], $z[$titel]['note'], $z[$titel]['gewicht'], $z[$titel]['status']];
        $this->assertSame(['modul:'.$this->modul['902'], '2026-09-02', 4.5, 33.33, 'ok'], $werte('LB1: Beta-Grundlagen und Begriffe'));
        $this->assertSame(['modul:'.$this->modul['902'], '2026-09-09', 5.0, 33.34, 'ok'], $werte('LB2: Beta-Einstellungen'));
        $this->assertSame(['modul:'.$this->modul['902'], '2026-09-16', null, 33.33, 'fehler'], $werte('LB3: Beta-Abgleich'));
        // Kurs ENG bestimmt das Fach (Berufsfachschule, nicht BM), nicht die Modulnummer im Thema
        $this->assertSame(['fach:'.$this->fach['Englisch'], '2026-08-21', 4.5, 100.0, 'ok'], $werte('Presentation Module 901'));
        // «2» gehört zum Thema: Kursschnitt 4.5 passt nur ohne diese Bewertung
        $this->assertSame(['fach:'.$this->fach['Englisch'], '2026-08-28', null, 50.0, 'fehler'], $werte('Vocabulary Test 2'));
        $this->assertSame('Note fehlt', $z['LB3: Beta-Abgleich']['meldung']);

        $ergebnis = app(NotenImport::class)->importieren($vorschau['zeilen'], $this->lernenderId, (int) $this->user->benutzer_id);
        $this->assertSame(3, $ergebnis['neu']);
        $this->assertSame([33.33, 33.34], Note::query()->whereNotNull('modul_belegung_id')->orderBy('pruefungsdatum')->pluck('gewichtung_prozent')->map(fn ($g) => (float) $g)->all());
        $this->assertSame('doppelt', app(NotenImport::class)->vorschau($tabelle, $this->lernenderId)['zeilen'][0]['status']);
    }

    #[Test]
    public function schulnetz_zeugnisnoten_und_stammdaten(): void
    {
        $leser = app(TabellenLeser::class);
        $vorschau = app(NotenImport::class)->vorschau($leser->pdfText($this->fixture('schulnetz-zeugnisnoten.txt')), $this->lernenderId);
        $bezug = array_column($vorschau['zeilen'], 'bezug', 'bezug_roh');

        $this->assertSame(Schulnetz::ZEUGNISNOTEN, $vorschau['format']);
        $this->assertSame(['904 (r)', '905 (r)', '906 (r)', 'ENG (r)', 'MAT (r)', 'ABU (r)', '907 (r)'], array_keys($bezug));
        $this->assertSame(['modul:'.$this->modul['904'], null, 'modul:'.$this->modul['906'], 'fach:'.$this->fach['Englisch'], 'fach:'.$this->fach['Mathematik'], null, 'modul:'.$this->modul['907']], array_values($bezug));
        $this->assertSame([4.5, 5.0, 4.0, 4.5, 5.0, 4.0, 5.5], array_column($vorschau['zeilen'], 'note'));
        $this->assertSame(['Datum fehlt'], array_values(array_unique(array_column($vorschau['zeilen'], 'meldung'))));

        $this->assertSame([], $leser->pdfText("Name Muster\tVorname Lea\nGeburtsdatum 01.01.2009\tAdresszusatz \nGrunddaten - Muster Lea (INXX 99 A)\n10001@stu.example.ch\tSeite 1/3\t11.09.2026"));
    }

    #[Test]
    public function lehrpersonenliste_als_csv_oder_excel_enthaelt_keine_noten(): void
    {
        $csv = "\xEF\xBB\xBF\"Name\";\"Vorname\";\"Kürzel\";\"E-Mail\"\r\n\"Muster\";\"Max\";\"musmax\";\"max.muster@schule.example\"\r\n\"Beispiel-Meier\";\"Eva\";\"beieva\";\"eva.beispiel@schule.example\"\r\n";
        $this->actingAs($this->user)
            ->post(route('learner.grades.import.read'), ['datei' => UploadedFile::fake()->createWithContent('lehrpersonen-export.csv', $csv)])
            ->assertSessionHasErrors(['datei' => 'Keine Noten gefunden.']);

        $blatt = new Spreadsheet;
        $blatt->getActiveSheet()->fromArray([
            ['Berufsschule Musterstadt, 7000 Musterstadt', null, null, '11.09.2026'], ['Muster Lea'], ['Liste der Lehrpersonen'], [null],
            ['Anzahl Datensätze: 2'], [null], ['Name', 'Vorname', 'Kürzel', 'E-Mail'],
            ['Muster', 'Max', 'musmax', 'max.muster@schule.example'], ['Beispiel-Meier', 'Eva', 'beieva', 'eva.beispiel@schule.example'],
        ]);
        $pfad = tempnam(sys_get_temp_dir(), 'np').'.xlsx';
        (new Xlsx($blatt))->save($pfad);
        $tabelle = app(TabellenLeser::class)->lesen($pfad, 'xlsx');
        unlink($pfad);

        $this->assertSame([], app(NotenImport::class)->vorschau($tabelle, $this->lernenderId)['zeilen']);
    }

    #[Test]
    public function excel_mit_wert_in_ferner_spalte_liest_nur_die_ersten_spalten(): void
    {
        $blatt = new Spreadsheet;
        $blatt->getActiveSheet()->fromArray([['Datum', 'Fach', 'Note'], ['11.09.2026', 'Mathematik', 5.5]]);
        $blatt->getActiveSheet()->setCellValue('XFD500', 'x');
        $pfad = tempnam(sys_get_temp_dir(), 'np').'.xlsx';
        (new Xlsx($blatt))->save($pfad);
        $tabelle = app(TabellenLeser::class)->lesen($pfad, 'xlsx');
        unlink($pfad);

        $this->assertSame(['Datum', 'Fach', 'Note'], array_slice($tabelle[0], 0, 3));
        $this->assertSame('5.5', $tabelle[1][2]);
        $this->assertLessThanOrEqual(TabellenLeser::MAX_SPALTEN, max(array_map(count(...), $tabelle)));
    }

    #[Test]
    public function bm_zeugnis_liefert_aktuelle_semesternote_und_trennt_bm_von_berufsfachschule(): void
    {
        $this->assertSame([
            ['Deutsch', 'fach:'.$this->fach['BMS Deutsch'], 4.0, true],
            ['Mathematik', 'fach:'.$this->fach['BMS Mathematik'], 5.0, true],
            ['Englisch', 'fach:'.$this->fach['BMS Englisch'], 5.5, true],
            ['Italienisch', null, 5.0, false],
            ['Naturwissenschaften', 'fach:'.$this->fach['BMS Naturwissenschaften'], 4.5, true],
            ['WirtschaftundRecht', 'fach:'.$this->fach['BMS Wirtschaft und Recht'], 4.0, true],
            ['InterdisziplinäreArbeitenIDAF', 'fach:'.$this->fach['BMS Interdisziplinäres Arbeiten in den Fächern'], 5.5, true],
            ['Enalisch', 'fach:'.$this->fach['Englisch'], 4.5, false],
            ['Mathematik', 'fach:'.$this->fach['Mathematik'], 5.0, true],
            ['Allemeinbildung', null, 4.0, false],
        ], $this->zeugnis('zeugnis-bm-bfs.txt'));
    }

    #[Test]
    public function mehrteiliges_zeugnis_mit_notenportfolio_und_getrennten_ocr_zeilen(): void
    {
        $this->assertSame([
            ['906 TestmodulZetaplanenundauswerten', 'modul:'.$this->modul['906'], 4.5, true],
            ['904 TestmodulDelta-,Prüf-undKontrollverfahrenanwenden', 'modul:'.$this->modul['904'], 5.0, true],
            ['908 TestmodulThetaimMuster-Umfeldbegleiten', 'modul:'.$this->modul['908'], 4.0, true],
            ['907 TestmodulEta-GerätmitMustersystemeinrichten', 'modul:'.$this->modul['907'], 5.0, true],
            ['Deutsch', 'fach:'.$this->fach['BMS Deutsch'], 4.5, true],
            ['Mathematik', 'fach:'.$this->fach['BMS Mathematik'], 3.0, true],
            ['Englisch', 'fach:'.$this->fach['BMS Englisch'], 5.5, true],
            ['Italienisch', null, 5.0, false],
        ], $this->zeugnis('zeugnis-mehrteilig.txt'));
    }

    #[Test]
    public function schulnetz_zeugnisnoten_im_abgleich_und_notenliste_ohne_zeugnis(): void
    {
        $this->assertSame([
            ['904 (r)', 'modul:'.$this->modul['904'], 4.5, true],
            ['905 (r)', null, 5.0, false],
            ['906 (r)', 'modul:'.$this->modul['906'], 4.0, true],
            ['ENG (r)', 'fach:'.$this->fach['Englisch'], 4.5, true],
            ['MAT (r)', 'fach:'.$this->fach['Mathematik'], 5.0, true],
            ['ABU (r)', null, 4.0, false],
            ['907 (r)', 'modul:'.$this->modul['907'], 5.5, true],
        ], $this->zeugnis('schulnetz-zeugnisnoten.txt'));
        $this->assertSame([], $this->zeugnis('schulnetz-aktuelle-noten.txt'));
    }

    #[Test]
    public function abgleich_zeigt_fach_ohne_gegenstueck_als_nicht_zugeordnet(): void
    {
        $this->actingAs($this->user)->post(route('learner.documents.store'), [
            'datei' => UploadedFile::fake()->createWithContent('zeugnis.pdf', $this->pdf(['Zeugnis', 'Deutsch 4.5', 'Italienisch 5.0'])),
            'art' => 'zeugnis', 'semester_id' => $this->semester,
        ])->assertSessionHasNoErrors();

        $html = $this->get(route('learner.documents.reconcile', Dokument::firstOrFail()->dokument_id))
            ->assertOk()->assertSee('Italienisch')->assertSee('nicht zugeordnet')->getContent();
        $this->assertSame(1, substr_count($html, '][bezug]'));
    }

    /** @return list<array{0: string, 1: ?string, 2: float, 3: bool}> */
    private function zeugnis(string $datei): array
    {
        return array_map(fn (array $z) => [$z['label'], $z['bezug'], $z['note'], $z['sicher']],
            app(ZeugnisAbgleich::class)->zeilen($this->fixture($datei), $this->lernenderId));
    }

    private function fixture(string $name): string
    {
        return str_replace('{NUL}', "\x00", (string) file_get_contents(base_path('tests/Fixtures/import/'.$name)));
    }

    /** Minimales PDF mit Textlayer, eine Zeile pro Eintrag. */
    private function pdf(array $zeilen): string
    {
        $inhalt = "BT /F1 11 Tf 14 TL 60 780 Td\n".implode('', array_map(fn ($z) => '('.addcslashes($z, '()\\').") Tj T*\n", $zeilen)).'ET';
        $objekte = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($inhalt)." >>\nstream\n".$inhalt."\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $positionen = [];
        foreach ($objekte as $i => $objekt) {
            $positionen[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$objekt."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objekte) + 1)."\n0000000000 65535 f \n".implode('', array_map(fn ($p) => sprintf("%010d 00000 n \n", $p), $positionen));

        return $pdf.'trailer << /Size '.(count($objekte) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
