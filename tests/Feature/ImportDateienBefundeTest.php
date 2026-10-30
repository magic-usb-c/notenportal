<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auswertung\Notenbaum\BaumVorlage;
use App\Services\Import\PdfLeser;
use App\Services\Import\TabellenLeser;
use App\Support\Modulkatalog;
use Database\Seeders\BasisSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

/**
 * Befunde der Funktionsprüfung, Gruppe «import-dateien»: Notenbaum-Vorlage mit BOM, Dekompressionsbomben
 * in Tabellen und PDFs, Modulkatalog-Ernte mit Arrays an Stellen, die Text erwarten.
 * Alle Inhalte sind erfunden; die «Bomben» sind klein genug, dass der Test schnell läuft.
 */
class ImportDateienBefundeTest extends TestCase
{
    /** @var list<string> */
    private array $aufraeumen = [];

    protected function tearDown(): void
    {
        foreach ($this->aufraeumen as $pfad) {
            @unlink($pfad);
        }
        parent::tearDown();
    }

    private function tempDatei(string $endung): string
    {
        $pfad = tempnam(sys_get_temp_dir(), 'np').'.'.$endung;
        $this->aufraeumen[] = $pfad;

        return $pfad;
    }

    // ---- Notenbaum-Vorlage ----------------------------------------------------------------------

    #[Test]
    public function notenbaum_vorlage_mit_utf8_bom_wird_gelesen(): void
    {
        $json = (string) file_get_contents(resource_path('vorlagen/notenbaeume/informatiker-efz-bivo2020.json'));

        $ohne = BaumVorlage::lesen($json);
        $mit = BaumVorlage::lesen("\xEF\xBB\xBF".$json);

        $this->assertSame($ohne, $mit);
    }

    #[Test]
    public function notenbaum_vorlage_ohne_json_meldet_weiterhin_kein_gueltiges_json(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(__('Die Datei ist kein gültiges JSON.'));

        BaumVorlage::lesen("\xEF\xBB\xBFkein json");
    }

    // ---- Tabellen: xlsx/ods/xls -----------------------------------------------------------------

    /** Ein Zip mit einem grossen, aber stark komprimierbaren Eintrag, den kein Leser braucht. */
    private function zipMitBallast(string $pfad, string $mimetype, int $bytes): void
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($pfad, ZipArchive::OVERWRITE | ZipArchive::CREATE));
        $zip->addFromString('ballast.bin', str_repeat('A', $bytes));
        $zip->close();
    }

    private function gueltigeTabelle(string $schreiber): string
    {
        $blatt = new Spreadsheet;
        $blatt->getActiveSheet()->fromArray([['Datum', 'Fach', 'Note'], ['11.09.2026', 'Mathematik', 5.5]]);
        $pfad = $this->tempDatei($schreiber === 'Xls' ? 'xls' : ($schreiber === 'Ods' ? 'ods' : 'xlsx'));
        match ($schreiber) {
            'Xls' => (new Xls($blatt))->save($pfad),
            'Ods' => (new Ods($blatt))->save($pfad),
            default => (new Xlsx($blatt))->save($pfad),
        };

        return $pfad;
    }

    /** Eine gültige xlsx/ods, an die ein riesiger, gut komprimierbarer Eintrag angehängt ist. */
    private function tabelleMitBallast(string $schreiber): string
    {
        $pfad = $this->gueltigeTabelle($schreiber);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($pfad));
        $zip->addFromString('ballast.bin', str_repeat('A', TabellenLeser::MAX_ENTPACKT_BYTES + 1024));
        $zip->close();

        return $pfad;
    }

    #[Test]
    public function xlsx_mit_ueberschrittener_entpackter_groesse_wird_abgelehnt(): void
    {
        $pfad = $this->tabelleMitBallast('Xlsx');
        $this->assertLessThan(1024 * 1024, filesize($pfad), 'Die Datei selbst bleibt klein.');

        $this->expectException(\RuntimeException::class);
        app(TabellenLeser::class)->lesen($pfad, 'xlsx');
    }

    #[Test]
    public function ods_mit_ueberschrittener_entpackter_groesse_wird_abgelehnt(): void
    {
        $pfad = $this->tabelleMitBallast('Ods');

        $this->expectException(\RuntimeException::class);
        app(TabellenLeser::class)->lesen($pfad, 'ods');
    }

    #[Test]
    public function der_upload_einer_xlsx_bombe_meldet_datei_lasst_sich_nicht_lesen(): void
    {
        $this->seed(BasisSeeder::class);
        $user = User::factory()->lernender()->create();
        $pfad = $this->tabelleMitBallast('Xlsx');

        $this->actingAs($user)
            ->post(route('learner.grades.import.read'), ['datei' => new UploadedFile($pfad, 'noten.xlsx', null, null, true)])
            ->assertSessionHasErrors(['datei' => __('Die Datei lässt sich nicht lesen.')]);
    }

    #[Test]
    public function normale_xlsx_ods_und_xls_werden_weiterhin_gelesen(): void
    {
        foreach (['Xlsx' => 'xlsx', 'Ods' => 'ods', 'Xls' => 'xls'] as $schreiber => $endung) {
            $tabelle = app(TabellenLeser::class)->lesen($this->gueltigeTabelle($schreiber), $endung);

            $this->assertSame(['Datum', 'Fach', 'Note'], array_slice($tabelle[0], 0, 3), $schreiber);
            $this->assertSame('5.5', $tabelle[1][2], $schreiber);
        }
    }

    #[Test]
    public function excel_mit_wert_in_ferner_zeile_und_spalte_liest_nur_den_begrenzten_bereich(): void
    {
        $blatt = new Spreadsheet;
        $blatt->getActiveSheet()->fromArray([['Datum', 'Fach', 'Note'], ['11.09.2026', 'Mathematik', 5.5]]);
        $blatt->getActiveSheet()->setCellValue('XFD1000000', 'x');
        $pfad = $this->tempDatei('xlsx');
        (new Xlsx($blatt))->save($pfad);

        $tabelle = app(TabellenLeser::class)->lesen($pfad, 'xlsx');

        $this->assertCount(2, $tabelle);
        $this->assertLessThanOrEqual(TabellenLeser::MAX_SPALTEN, max(array_map(count(...), $tabelle)));
    }

    // ---- PDF ------------------------------------------------------------------------------------

    /** Ein PDF mit zusätzlichen, von keiner Seite benutzten Flate-Streams, die gross entpackt werden. */
    private function pdfMitFlateBombe(int $entpackt, int $anzahl = 1): string
    {
        $text = 'BT /F1 12 Tf 72 720 Td (Deutsch 4.5) Tj ET';
        $bombe = (string) gzcompress(str_repeat("\x00", $entpackt), 9);
        $objekte = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($text)." >>\nstream\n".$text."\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            ...array_fill(0, $anzahl, '<< /Length '.strlen($bombe).' /Filter /FlateDecode >>'."\nstream\n".$bombe."\nendstream"),
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

    #[Test]
    public function pdf_mit_flate_bombe_entpackt_hoechstens_das_limit(): void
    {
        $pfad = $this->tempDatei('pdf');
        // unter dem Gesamtlimit, aber über dem Limit pro Stream: geparst, der Stream nur bis zum Limit entpackt
        file_put_contents($pfad, $this->pdfMitFlateBombe(PdfLeser::MAX_ENTPACKT_BYTES * 2));
        $this->assertLessThan(1024 * 1024, filesize($pfad));

        $vorher = memory_get_usage(true);
        memory_reset_peak_usage();
        $text = PdfLeser::text($pfad);
        $spitze = memory_get_peak_usage(true) - $vorher;

        $this->assertStringContainsString('Deutsch 4.5', $text);
        $this->assertLessThan(PdfLeser::MAX_ENTPACKT_BYTES * 3, $spitze);
    }

    #[Test]
    public function pdf_mit_vielen_streams_knapp_unter_dem_limit_wird_ohne_speicherspitze_abgelehnt(): void
    {
        // Jeder Stream bleibt unter dem Limit pro Stream, zusammen weit über dem Gesamtlimit
        $pfad = $this->tempDatei('pdf');
        file_put_contents($pfad, $this->pdfMitFlateBombe(intdiv(PdfLeser::MAX_ENTPACKT_BYTES, 2), 12));
        $this->assertLessThan(1024 * 1024, filesize($pfad));

        $vorher = memory_get_usage(true);
        memory_reset_peak_usage();
        try {
            PdfLeser::text($pfad);
            $this->fail('PDF-Bombe wurde geparst');
        } catch (\RuntimeException) {
        }

        $this->assertLessThan(PdfLeser::MAX_ENTPACKT_BYTES, memory_get_peak_usage(true) - $vorher);
    }

    #[Test]
    public function pdf_ueber_der_groessengrenze_wird_nicht_geparst(): void
    {
        $pfad = $this->tempDatei('pdf');
        $handle = fopen($pfad, 'w');
        fwrite($handle, "%PDF-1.4\n");
        ftruncate($handle, PdfLeser::MAX_DATEI_BYTES + 1);
        fclose($handle);

        $this->expectException(\RuntimeException::class);
        PdfLeser::text($pfad);
    }

    #[Test]
    public function der_tabellenleser_liest_pdf_text_ueber_den_helfer(): void
    {
        $pfad = $this->tempDatei('pdf');
        file_put_contents($pfad, $this->pdfMitFlateBombe(1024));

        $zeilen = app(TabellenLeser::class)->lesen($pfad, 'pdf');

        $this->assertNotSame([], $zeilen);
    }

    // ---- Modulkatalog ---------------------------------------------------------------------------

    #[Test]
    public function modulkatalog_mit_arrays_an_textstellen_wirft_nicht_und_ueberspringt_den_unsinn(): void
    {
        $roh = Modulkatalog::ausJson((string) json_encode([
            'format' => Modulkatalog::FORMAT,
            'geerntet_am' => ['x'],
            'module' => [
                ['nummer' => ['1'], 'titel' => 'Erstes'],
                ['nummer' => '987', 'version' => ['2'], 'titel' => ['a' => 'b'], 'kompetenzfeld' => ['f'], 'publiziert_am' => ['2026-01-01'],
                    'handlungsziele' => [['nummer' => ['1'], 'text' => 'T'], 'nur-text', ['nummer' => '1', 'text' => ['x']]],
                    'lbv' => ['elemente' => [['bezeichnung' => ['x']], 'text']]],
                ['nummer' => '988', 'version' => '2', 'titel' => 'Gültiges Modul', 'kompetenzfeld' => ['f']],
                'ein-skalar',
            ],
            'abschluesse' => [
                ['name' => ['x'], 'module' => []],
                'skalar',
                ['name' => 'Prüfberuf/in EFZ (2026)', 'kennung' => ['k'], 'module' => [['nummer' => ['9']], 'text', ['nummer' => '988', 'version' => ['x'], 'titel' => ['x'], 'lehrjahr' => ['x']]]],
            ],
        ]));

        $this->assertNull($roh['stand']);
        $nummern = array_column($roh['module'], 'nummer');
        $this->assertContains('988', $nummern);
        $this->assertNotEmpty($roh['fehler']);
    }

    #[Test]
    public function modulkatalog_upload_mit_arrays_gibt_keine_500(): void
    {
        $this->seed(BasisSeeder::class);
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $inhalt = (string) json_encode([
            'format' => 1,
            'geerntet_am' => ['x'],
            'module' => [['nummer' => '987', 'version' => ['2'], 'titel' => ['x']], 'skalar'],
            'abschluesse' => ['skalar', ['name' => 'Prüfberuf', 'module' => ['text']]],
        ]);

        $antwort = $this->actingAs($admin)
            ->post(route('admin.master-data.modules.catalog.read'), ['datei' => UploadedFile::fake()->createWithContent('katalog.json', $inhalt)]);

        $this->assertNotSame(500, $antwort->getStatusCode());
        $antwort->assertRedirect();
    }
}
