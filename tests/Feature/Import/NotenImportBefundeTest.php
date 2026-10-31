<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Models\Note;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Import\NotenImport;
use Database\Seeders\BasisSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Befunde der Funktionsprüfung am Notenimport: Kopferkennung, Korrekturen, Zeilengrenze, Stufenfächer. */
class NotenImportBefundeTest extends TestCase
{
    private User $user;

    private int $lid;

    private int $modul;

    private int $fach;

    private int $sport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $kategorie = DB::table('kategorien')->pluck('kategorie_id', 'code');
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $this->modul = DB::table('module')->insertGetId(['modul_nummer' => '908', 'titel' => 'Testmodul Theta durchführen']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $lehrberuf, 'modul_id' => $this->modul, 'kategorie_id' => $kategorie['FACH']]);
        $this->fach = DB::table('faecher')->insertGetId(['kategorie_id' => $kategorie['ABU'], 'name' => 'Sprache und Kommunikation', 'kurzname' => 'SK']);
        $this->sport = DB::table('faecher')->insertGetId(['kategorie_id' => $kategorie['ABU'], 'name' => 'Sport', 'kurzname' => 'SP', 'skala' => 'stufe']);
        DB::table('lehrberuf_faecher')->insert([['lehrberuf_id' => $lehrberuf, 'fach_id' => $this->fach], ['lehrberuf_id' => $lehrberuf, 'fach_id' => $this->sport]]);
        DB::table('semester')->insert(['bezeichnung' => '25/26-2', 'start_datum' => '2026-02-01', 'end_datum' => '2026-07-31', 'sortierung' => 10]);
        Konfiguration::vergessen();
        $this->user = User::factory()->lernender(['lehrberuf_id' => $lehrberuf, 'lehrbeginn' => '2024-08-01'])->create();
        $this->lid = (int) $this->user->lernender->lernender_id;
    }

    private function lesen(string $csv, string $name = 'noten.csv')
    {
        return $this->actingAs($this->user)->post(route('learner.grades.import.read'), ['datei' => UploadedFile::fake()->createWithContent($name, $csv)]);
    }

    /** @return array<string, mixed> */
    private function vorschau(): array
    {
        return session('notenimport.'.$this->lid);
    }

    // ---- Kopf: Gewichtungsspalte --------------------------------------------------------------

    /** @return array<string, array{string, string}> */
    public static function gewichtKoepfe(): array
    {
        $liste = [];
        foreach (['Gewichtung (%)', 'Gewicht (%)', 'Gewichtung in %', 'Gewichtung%', 'Gewicht in Prozent', 'Gewichtung:', 'Anteil', 'Anteil in %', 'Gew. (%)'] as $gewicht) {
            foreach (['Note', 'Notenwert', 'Schulnote'] as $note) {
                $liste["$gewicht / $note"] = [$gewicht, $note];
            }
        }

        return $liste;
    }

    #[Test]
    #[DataProvider('gewichtKoepfe')]
    public function gewichtungsspalte_mit_klammer_oder_zusatz_wird_erkannt(string $gewichtKopf, string $noteKopf): void
    {
        $tabelle = [['Datum', 'Fach/Modul', 'Titel', $noteKopf, $gewichtKopf], ['02.03.2026', 'M908', 'LB1', '4,5', '50'], ['09.03.2026', 'M908', 'LB2', '5', '25']];

        $vorschau = app(NotenImport::class)->vorschau($tabelle, $this->lid);

        $this->assertSame(4, $vorschau['erkannt']['gewicht']);
        $this->assertSame(3, $vorschau['erkannt']['note']);
        $this->assertSame([50.0, 25.0], array_column($vorschau['zeilen'], 'gewicht'));
        $this->assertSame([4.5, 5.0], array_column($vorschau['zeilen'], 'note'));
    }

    #[Test]
    public function bisher_erkannte_koepfe_bleiben_erkannt_auch_das_blosse_prozentzeichen(): void
    {
        foreach (['Gewicht', 'Gewichtung', 'Gew.', 'Gew. %', 'Gewicht %', '%'] as $kopf) {
            $tabelle = [['Datum', 'Fach/Modul', 'Titel', 'Note', $kopf], ['02.03.2026', 'M908', 'LB1', '4,5', '50']];
            $vorschau = app(NotenImport::class)->vorschau($tabelle, $this->lid);
            $this->assertSame(4, $vorschau['erkannt']['gewicht'] ?? null, "Kopf «{$kopf}»");
            $this->assertSame([50.0], array_column($vorschau['zeilen'], 'gewicht'), "Kopf «{$kopf}»");
        }
    }

    #[Test]
    public function eine_titelzeile_ueber_der_kopfzeile_und_fremde_koepfe_mit_klammer_stoeren_nicht(): void
    {
        $tabelle = [
            ['Notenliste Lehrjahr 2 (Entwurf)', '', '', '', ''],
            ['Datum (Prüfung)', 'Fach (Modul)', 'Bezeichnung', 'Note (1-6)', 'Gewichtung (%)'],
            ['02.03.2026', 'M908', 'LB1', '4,5', '50'],
        ];

        $vorschau = app(NotenImport::class)->vorschau($tabelle, $this->lid);

        $this->assertSame(['datum' => 0, 'bezug' => 1, 'titel' => 2, 'note' => 3, 'gewicht' => 4], $vorschau['erkannt']);
        $this->assertSame([50.0], array_column($vorschau['zeilen'], 'gewicht'));
        $this->assertSame(3, $vorschau['zeilen'][0]['nr']);
    }

    #[Test]
    public function faktorspalten_werden_nicht_als_prozent_gedeutet(): void
    {
        // «Faktor 1» heisst volles Gewicht, nie 1 %; «Faktor 0,5» halbes Gewicht; ein Faktor über 1 ist kein gültiges Gewicht
        // und wird sichtbar als Fehler gemeldet statt als 2 % verbucht.
        foreach (['Faktor', 'Gewicht (Faktor)', 'Gewichtung (1-3)'] as $kopf) {
            $tabelle = [['Datum', 'Fach/Modul', 'Titel', 'Note', $kopf], ['02.03.2026', 'M908', 'A', '4,5', '1'], ['03.03.2026', 'M908', 'B', '5', '0,5'], ['04.03.2026', 'M908', 'C', '5', '2']];
            $zeilen = app(NotenImport::class)->vorschau($tabelle, $this->lid)['zeilen'];

            $this->assertSame(100.0, (float) $zeilen[0]['gewicht'], "Kopf «{$kopf}»");
            $this->assertSame('ok', $zeilen[0]['status'], "Kopf «{$kopf}»");
            $this->assertSame(50.0, (float) $zeilen[1]['gewicht'], "Kopf «{$kopf}»");
            $this->assertSame('fehler', $zeilen[2]['status'], "Kopf «{$kopf}» mit Faktor 2");
        }
    }

    #[Test]
    public function prozentspannen_im_kopf_bleiben_prozent(): void
    {
        foreach (['Gewicht (0-100)', 'Gewichtung (1–100)', 'Gewicht (0 - 100)'] as $kopf) {
            $tabelle = [['Datum', 'Fach/Modul', 'Titel', 'Note', $kopf], ['02.03.2026', 'M908', 'A', '4,5', '30']];
            $zeilen = app(NotenImport::class)->vorschau($tabelle, $this->lid)['zeilen'];

            $this->assertSame(30.0, (float) $zeilen[0]['gewicht'], "Kopf «{$kopf}»");
            $this->assertSame('ok', $zeilen[0]['status'], "Kopf «{$kopf}»");
        }
    }

    #[Test]
    public function ungueltiges_gewicht_wird_beim_erneuten_pruefen_und_uebernehmen_nicht_still_zu_100(): void
    {
        $this->lesen("Datum;Fach/Modul;Titel;Note;Faktor\n02.03.2026;SK;A;4.5;2\n03.03.2026;SK;B;5;1\n");
        $v = $this->vorschau();
        $this->assertSame('200', $v['zeilen'][0]['gewicht']);
        $this->assertSame('fehler', $v['zeilen'][0]['status']);

        // «Erneut prüfen» ohne Korrektur: die Vorschau sendet das Feld so zurück, wie es steht (Zahl, wenn es eine ist)
        $z = [...$v['zeilen'][0], 'gewicht' => 200, 'uebernehmen' => true];
        $antwort = $this->postJson(route('learner.grades.import.validate'), ['zeilen' => json_encode([$z])])->assertOk()->json('zeilen.0');
        $this->assertSame('fehler', $antwort['status']);
        $this->assertFalse($antwort['uebernehmen']);

        $this->from(route('learner.grades.import.index'))
            ->post(route('learner.grades.import.apply'), ['zeilen' => json_encode([$z]), 'token' => $v['token']])
            ->assertSessionHas('error');
        $this->assertSame(0, Note::query()->count());
        $this->assertSame('fehler', $this->vorschau()['zeilen'][0]['status']);

        // Prozentspalte mit Text: bleibt Text und damit ungültig
        $this->lesen("Datum;Fach/Modul;Titel;Note;Gewicht\n02.03.2026;SK;A;4.5;viel\n");
        $v = $this->vorschau();
        $this->assertSame('viel', $v['zeilen'][0]['gewicht']);
        $antwort = $this->postJson(route('learner.grades.import.validate'), ['zeilen' => json_encode([[...$v['zeilen'][0], 'uebernehmen' => true]])])->json('zeilen.0');
        $this->assertSame('fehler', $antwort['status']);
    }

    // ---- Fehlgeschlagene Übernahme -----------------------------------------------------------

    #[Test]
    public function fehlgeschlagene_uebernahme_behaelt_die_korrekturen_und_die_nicht_gewaehlten_zeilen(): void
    {
        $this->lesen("Datum;Fach/Modul;Titel;Note;Gewicht\n02.03.2026;SK;A;4.5;100\n03.03.2026;SK;B;4.5;100\n04.03.2026;SK;C;5;100\n");
        $v = $this->vorschau();
        $z = $v['zeilen'];
        // Zeile 1: Titel korrigiert; Zeile 2: Datum ausserhalb der Lehrzeit; Zeile 3 wurde abgewählt und wird nicht gesendet
        $z[0]['titel'] = 'KORRIGIERT';
        $z[1]['datum'] = '2020-01-01';
        $gesendet = [$z[0], $z[1]];

        $this->from(route('learner.grades.import.index'))
            ->post(route('learner.grades.import.apply'), ['zeilen' => json_encode($gesendet), 'token' => $v['token']])
            ->assertSessionHas('error');

        $neu = $this->vorschau();
        $this->assertCount(3, $neu['zeilen']);
        $this->assertSame('KORRIGIERT', $neu['zeilen'][0]['titel']);
        $this->assertSame('2020-01-01', $neu['zeilen'][1]['datum']);
        $this->assertSame('fehler', $neu['zeilen'][1]['status']);
        $this->assertFalse($neu['zeilen'][1]['uebernehmen']);
        $this->assertSame('C', $neu['zeilen'][2]['titel']);
        $this->assertFalse($neu['zeilen'][2]['uebernehmen'], 'nicht gesendete Zeile bleibt abgewählt');
        $this->assertNotSame($v['token'], $neu['token']);
        $this->get(route('learner.grades.import.index'))->assertOk()->assertSee('KORRIGIERT');
        $this->assertSame(0, Note::query()->count());
    }

    #[Test]
    public function fehlgeschlagene_uebernahme_ohne_zeilen_behaelt_den_alten_stand(): void
    {
        $this->lesen("Datum;Fach/Modul;Titel;Note;Gewicht\n02.03.2026;SK;A;4.5;100\n03.03.2026;SK;B;5;100\n");
        $v = $this->vorschau();

        $this->from(route('learner.grades.import.index'))
            ->post(route('learner.grades.import.apply'), ['zeilen' => '[]', 'token' => $v['token']])
            ->assertSessionHas('error');

        $neu = $this->vorschau();
        $this->assertCount(2, $neu['zeilen']);
        $this->assertSame(array_column($v['zeilen'], 'titel'), array_column($neu['zeilen'], 'titel'));
    }

    #[Test]
    public function fehlgeschlagene_uebernahme_nimmt_keine_unbekannten_zeilen_vom_client_auf(): void
    {
        $this->lesen("Datum;Fach/Modul;Titel;Note;Gewicht\n02.03.2026;SK;A;4.5;100\n");
        $v = $this->vorschau();
        $z = $v['zeilen'][0];
        $z['datum'] = '2020-01-01';
        $fremd = ['nr' => 99, 'datum' => '2026-03-05', 'bezug' => 'fach:'.$this->fach, 'titel' => 'EINGESCHOBEN', 'note' => 6, 'gewicht' => 100, 'uebernehmen' => true];

        $this->from(route('learner.grades.import.index'))
            ->post(route('learner.grades.import.apply'), ['zeilen' => json_encode([$z, $fremd]), 'token' => $v['token']])
            ->assertSessionHas('error');

        $this->assertSame([2], array_column($this->vorschau()['zeilen'], 'nr'));
    }

    // ---- Zeilengrenze -------------------------------------------------------------------------

    private function csvMitZeilen(int $anzahl): string
    {
        $csv = "Datum;Fach/Modul;Titel;Note;Gewicht\n";
        for ($i = 0; $i < $anzahl; $i++) {
            $csv .= sprintf("%02d.03.2026;SK;Pruefung %d;4,5;100\n", 1 + ($i % 28), $i);
        }

        return $csv;
    }

    #[Test]
    public function mehr_als_500_zeilen_werden_in_der_vorschau_gemeldet(): void
    {
        $this->lesen($this->csvMitZeilen(530))->assertRedirect();

        $v = $this->vorschau();
        $this->assertCount(500, $v['zeilen']);
        $this->assertTrue($v['abgeschnitten']);
        $this->get(route('learner.grades.import.index'))->assertOk()
            ->assertSee(__('Nur die ersten :anzahl Zeilen gelesen. Den Rest bitte als zweite Datei importieren.', ['anzahl' => 500]));
    }

    #[Test]
    public function genau_500_zeilen_und_weniger_zeigen_keine_meldung(): void
    {
        foreach ([500, 120] as $anzahl) {
            $this->lesen($this->csvMitZeilen($anzahl))->assertRedirect();
            $this->assertFalse($this->vorschau()['abgeschnitten'], "$anzahl Zeilen");
            $this->assertCount($anzahl, $this->vorschau()['zeilen']);
            $this->get(route('learner.grades.import.index'))->assertOk()->assertDontSee('Zeilen gelesen. Den Rest');
        }
    }

    #[Test]
    public function auch_eine_excel_datei_ueber_der_grenze_wird_gemeldet(): void
    {
        $blatt = new Spreadsheet;
        $blatt->getActiveSheet()->fromArray(array_map(fn ($z) => str_getcsv($z, ';'), explode("\n", trim($this->csvMitZeilen(600)))), null, 'A1', true);
        $pfad = tempnam(sys_get_temp_dir(), 'np').'.xlsx';
        (new Xlsx($blatt))->save($pfad);
        try {
            $this->actingAs($this->user)->post(route('learner.grades.import.read'), ['datei' => new UploadedFile($pfad, 'noten.xlsx', null, null, true)])->assertRedirect();
        } finally {
            @unlink($pfad);
        }

        $this->assertCount(500, $this->vorschau()['zeilen']);
        $this->assertTrue($this->vorschau()['abgeschnitten']);
    }

    // ---- Stufenfächer -------------------------------------------------------------------------

    #[Test]
    public function stufennoten_sind_importierbar_und_werden_als_stufe_gespeichert(): void
    {
        $this->lesen("Datum;Fach/Modul;Titel;Note;Gewicht\n02.03.2026;Sport;Test;B;100\n03.03.2026;Sport;Test 2;c;100\n04.03.2026;Sport;Test 3;D;100\n")->assertRedirect();

        $v = $this->vorschau();
        $this->assertSame(['ok', 'ok', 'ok'], array_column($v['zeilen'], 'status'));
        $this->assertSame(['B', 'C', 'd'], array_column($v['zeilen'], 'note'));
        $this->assertSame([true, true, true], array_column($v['zeilen'], 'uebernehmen'));

        $this->post(route('learner.grades.import.apply'), ['zeilen' => json_encode($v['zeilen']), 'token' => $v['token']])
            ->assertSessionHas('success');

        $this->assertEqualsCanonicalizing(['B', 'C', 'd'], Note::query()->pluck('note_stufe')->all());
        $this->assertSame([null], Note::query()->pluck('note_wert')->unique()->values()->all());
        $this->assertSame($this->sport, (int) Note::query()->value('fach_id'));
    }

    #[Test]
    public function der_eigene_export_mit_stufennoten_laesst_sich_wieder_einlesen(): void
    {
        $semester = (int) DB::table('semester')->value('semester_id');
        $kategorie = (int) DB::table('kategorien')->value('kategorie_id');
        foreach (['B' => '2026-03-02', 'd' => '2026-03-09'] as $stufe => $datum) {
            Note::factory()->create(['lernender_id' => $this->lid, 'fach_id' => $this->sport, 'kategorie_id' => $kategorie, 'semester_id' => $semester,
                'pruefungsdatum' => $datum, 'note_wert' => null, 'note_stufe' => $stufe]);
        }
        $csv = $this->actingAs($this->user)->get(route('learner.grades.export'))->streamedContent();
        Note::query()->forceDelete();

        $this->lesen($csv)->assertRedirect();

        $v = $this->vorschau();
        $this->assertSame(['ok', 'ok'], array_column($v['zeilen'], 'status'));
        $this->assertSame(['B', 'd'], array_column($v['zeilen'], 'note'));
        $this->post(route('learner.grades.import.apply'), ['zeilen' => json_encode($v['zeilen']), 'token' => $v['token']])->assertSessionHas('success');
        $this->assertEqualsCanonicalizing(['B', 'd'], Note::query()->pluck('note_stufe')->all());
    }

    #[Test]
    public function stufe_bei_zahlenfach_und_zahl_bei_stufenfach_sind_sichtbare_fehler(): void
    {
        $this->lesen("Datum;Fach/Modul;Titel;Note;Gewicht\n02.03.2026;SK;A;B;100\n03.03.2026;Sport;B;4,5;100\n04.03.2026;Sport;C;x;100\n")->assertRedirect();

        $zeilen = $this->vorschau()['zeilen'];
        $this->assertSame(['fehler', 'fehler', 'fehler'], array_column($zeilen, 'status'));
        $this->assertNotSame('', (string) $zeilen[0]['meldung']);
        $this->assertNotSame(__('Note ungültig'), $zeilen[0]['meldung']);
        $this->assertSame([false, false, false], array_column($zeilen, 'uebernehmen'));
    }

    #[Test]
    public function stufennoten_werden_als_dublette_erkannt_und_beim_erneuten_pruefen_neu_bewertet(): void
    {
        $csv = "Datum;Fach/Modul;Titel;Note;Gewicht\n02.03.2026;Sport;Test;B;100\n";
        $this->lesen($csv);
        $v = $this->vorschau();
        $this->post(route('learner.grades.import.apply'), ['zeilen' => json_encode($v['zeilen']), 'token' => $v['token']])->assertSessionHas('success');

        $this->lesen($csv);
        $this->assertSame('doppelt', $this->vorschau()['zeilen'][0]['status']);

        // «Erneut prüfen»: Kleinschreibung wird zur Stufe, eine andere Stufe am selben Tag ist keine Dublette
        $z = $this->vorschau()['zeilen'][0];
        $z['note'] = 'a';
        $antwort = $this->postJson(route('learner.grades.import.validate'), ['zeilen' => json_encode([$z])])->assertOk()->json('zeilen.0');
        $this->assertSame('A', $antwort['note']);
        $this->assertSame('ok', $antwort['status']);
        $z['note'] = 'b';
        $this->assertSame('doppelt', $this->postJson(route('learner.grades.import.validate'), ['zeilen' => json_encode([$z])])->json('zeilen.0.status'));
    }

    #[Test]
    public function der_import_prueft_die_stufe_serverseitig(): void
    {
        $import = app(NotenImport::class);
        $zeile = fn ($note) => [['nr' => 1, 'datum' => '2026-03-02', 'bezug' => 'fach:'.$this->sport, 'titel' => null, 'note' => $note, 'gewicht' => 100, 'uebernehmen' => true]];

        $this->assertSame(0, $import->importieren($zeile('E'), $this->lid, (int) $this->user->benutzer_id)['neu']);
        $this->assertSame(0, $import->importieren($zeile(4.5), $this->lid, (int) $this->user->benutzer_id)['neu']);
        $this->assertSame(1, $import->importieren($zeile('b'), $this->lid, (int) $this->user->benutzer_id)['neu']);
        $this->assertSame('B', Note::query()->value('note_stufe'));
    }
}
