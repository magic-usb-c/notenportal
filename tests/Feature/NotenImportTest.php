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
        $this->modul = DB::table('module')->insertGetId(['modul_nummer' => '908', 'titel' => 'Testmodul Theta durchführen']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $lehrberuf, 'modul_id' => $this->modul, 'kategorie_id' => $kategorie['FACH']]);
        $this->fach = DB::table('faecher')->insertGetId(['kategorie_id' => $kategorie['ABU'], 'name' => 'Sprache und Kommunikation', 'kurzname' => 'SK']);
        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $lehrberuf, 'fach_id' => $this->fach]);
        DB::table('semester')->insert(['bezeichnung' => '25/26-2', 'start_datum' => '2026-02-01', 'end_datum' => '2026-07-31', 'sortierung' => 10]);
        Konfiguration::vergessen();
        $this->user = User::factory()->lernender(['lehrberuf_id' => $lehrberuf, 'lehrbeginn' => '2024-08-01'])->create();
    }

    #[Test]
    public function csv_wird_erkannt_zugeordnet_und_nach_pruefung_importiert(): void
    {
        $csv = "Datum;Fach/Modul;Titel;Note;Gewicht\n02.03.2026;M908;LB1;4,5;50%\n09.03.2026;Sprache;Vortrag;5.0;\n10.03.2026;Turnen;X;4;100\n";
        $this->actingAs($this->user)
            ->post(route('learner.grades.import.read'), ['datei' => UploadedFile::fake()->createWithContent('noten.csv', $csv)])
            ->assertRedirect(route('learner.grades.import.index'));

        $vorschau = session('notenimport.'.$this->user->lernender->lernender_id);
        $this->assertSame(['ok', 'pruefen', 'fehler'], array_column($vorschau['zeilen'], 'status'));
        $this->assertSame('modul:'.$this->modul, $vorschau['zeilen'][0]['bezug']);
        $this->assertSame(50.0, $vorschau['zeilen'][0]['gewicht']);
        // Unsichere Zuordnung («prüfen») ist nicht vorausgewählt – erst nach Bestätigung durch die Person wird sie übernommen
        $this->assertSame([true, false, false], array_column($vorschau['zeilen'], 'uebernehmen'));
        $this->get(route('learner.grades.import.index'))->assertOk()->assertSee('noten.csv');

        $vorschau['zeilen'][1]['uebernehmen'] = true;
        // Weiter zur Notenliste des Semesters, in dem die neuen Noten liegen – nicht zur Standardansicht
        $semesterId = (int) DB::table('semester')->where('bezeichnung', '25/26-2')->value('semester_id');
        $this->post(route('learner.grades.import.apply'), ['zeilen' => json_encode($vorschau['zeilen']), 'token' => $vorschau['token']])
            ->assertRedirect(route('learner.grades.index', ['semester_id' => $semesterId]))->assertSessionHas('success');

        $this->assertEqualsCanonicalizing([4.5, 5.0], Note::query()->pluck('note_wert')->map(fn ($n) => (float) $n)->all());
        $this->assertSame(50.0, (float) Note::query()->where('note_wert', 4.5)->value('gewichtung_prozent'));

        $this->post(route('learner.grades.import.read'), ['datei' => UploadedFile::fake()->createWithContent('noten.csv', $csv)]);
        $this->assertSame('doppelt', session('notenimport.'.$this->user->lernender->lernender_id)['zeilen'][0]['status']);
    }

    #[Test]
    public function import_prueft_das_datum_tagesgenau_gegen_lehrbeginn_und_lehrende_und_nur_im_format_jahr_monat_tag(): void
    {
        DB::table('semester')->insert(['bezeichnung' => '24/25-1', 'start_datum' => '2024-08-01', 'end_datum' => '2025-01-31', 'sortierung' => 1]);
        Konfiguration::vergessen();
        $this->user->lernender->update(['lehrende' => '2025-01-31']);
        $import = app(NotenImport::class);
        $lernenderId = (int) $this->user->lernender->lernender_id;
        $benutzerId = (int) $this->user->benutzer_id;
        $zeile = fn (string $datum, float $note) => [['nr' => 1, 'datum' => $datum, 'bezug' => 'modul:'.$this->modul, 'titel' => null, 'note' => $note, 'gewicht' => 100, 'uebernehmen' => true]];

        // Genau am Lehrbeginn und genau am Lehrende: erlaubt (der frühere Zeichenkettenvergleich gegen
        // «2024-08-01 00:00:00» lehnte den Lehrbeginn ab); einen Tag davor bzw. danach: abgelehnt
        $this->assertSame(1, $import->importieren($zeile('2024-08-01', 5.0), $lernenderId, $benutzerId)['neu']);
        $this->assertSame(1, $import->importieren($zeile('2025-01-31', 4.5), $lernenderId, $benutzerId)['neu']);
        $this->assertStringContainsString('Lehrbeginn', $import->importieren($zeile('2024-07-31', 5.5), $lernenderId, $benutzerId)['fehler'][0]);
        $this->assertStringContainsString('Ende der Lehre', $import->importieren($zeile('2025-02-01', 5.5), $lernenderId, $benutzerId)['fehler'][0]);

        // Nur JJJJ-MM-TT – ein anderes Format wird als Fehler gemeldet statt still gedeutet
        $ergebnis = $import->importieren($zeile('2024-8-5', 4.0), $lernenderId, $benutzerId);
        $this->assertSame(0, $ergebnis['neu']);
        $this->assertStringContainsString('Datum', $ergebnis['fehler'][0]);
        $this->assertSame(2, Note::query()->count());
    }

    #[Test]
    public function import_ueber_mehrere_semester_oeffnet_die_standardansicht_und_fehler_liefern_kein_semester(): void
    {
        DB::table('semester')->insert(['bezeichnung' => '25/26-1', 'start_datum' => '2025-08-01', 'end_datum' => '2026-01-31', 'sortierung' => 9]);
        Konfiguration::vergessen();
        $lernenderId = (int) $this->user->lernender->lernender_id;

        // Zwei Semester → kein eindeutiges Ziel, die Notenliste öffnet in der Standardansicht
        $csv = "Datum;Fach/Modul;Titel;Note;Gewicht\n01.09.2025;M908;LB1;5,0;50%\n02.03.2026;M908;LB2;4,5;50%\n";
        $this->actingAs($this->user)
            ->post(route('learner.grades.import.read'), ['datei' => UploadedFile::fake()->createWithContent('noten.csv', $csv)])
            ->assertRedirect(route('learner.grades.import.index'));
        $vorschau = session('notenimport.'.$lernenderId);
        $this->assertSame(['ok', 'ok'], array_column($vorschau['zeilen'], 'status'));
        $this->post(route('learner.grades.import.apply'), ['zeilen' => json_encode($vorschau['zeilen']), 'token' => $vorschau['token']])
            ->assertRedirect(route('learner.grades.index'))->assertSessionHas('success');
        $this->assertSame(2, Note::query()->count());

        // Fehlerfall: keine Note angelegt, also auch kein Semester gemeldet
        $ergebnis = app(NotenImport::class)->importieren(
            [['nr' => 1, 'datum' => '2024-07-31', 'bezug' => 'modul:'.$this->modul, 'titel' => null, 'note' => 5.0, 'gewicht' => 100, 'uebernehmen' => true]],
            $lernenderId,
            (int) $this->user->benutzer_id,
        );
        $this->assertSame(0, $ergebnis['neu']);
        $this->assertSame([], $ergebnis['semester_ids']);
    }

    #[Test]
    public function dubletten_innerhalb_derselben_datei_werden_markiert_und_nicht_vorausgewaehlt(): void
    {
        $csv = "Datum;Fach/Modul;Titel;Note;Gewicht\n02.03.2026;SK;LB1;4,5;50%\n02.03.2026;SK;LB1;4,5;50%\n";
        $this->actingAs($this->user)
            ->post(route('learner.grades.import.read'), ['datei' => UploadedFile::fake()->createWithContent('noten.csv', $csv)])
            ->assertRedirect(route('learner.grades.import.index'));

        $vorschau = session('notenimport.'.$this->user->lernender->lernender_id);
        $this->assertSame(['ok', 'doppelt'], array_column($vorschau['zeilen'], 'status'));
        $this->assertSame('mehrfach in dieser Datei', $vorschau['zeilen'][1]['meldung']);
        $this->assertSame([true, false], array_column($vorschau['zeilen'], 'uebernehmen'));
    }

    #[Test]
    public function mehrdeutiges_datum_und_gewicht_werden_als_warnung_markiert_und_bleiben_vorausgewaehlt(): void
    {
        $csv = "Datum;Fach/Modul;Titel;Note;Gewicht\n03/04/2026;SK;LB1;4,5;50%\n09.03.2026;SK;LB2;4,5;0.5\n";
        $this->actingAs($this->user)
            ->post(route('learner.grades.import.read'), ['datei' => UploadedFile::fake()->createWithContent('noten.csv', $csv)])
            ->assertRedirect(route('learner.grades.import.index'));

        $vorschau = session('notenimport.'.$this->user->lernender->lernender_id);
        $this->assertSame(['warnung', 'warnung'], array_column($vorschau['zeilen'], 'status'));
        $this->assertSame('Datum mehrdeutig (Tag/Monat vertauschbar) – bitte prüfen', $vorschau['zeilen'][0]['meldung']);
        $this->assertSame('Gewicht als Anteil gedeutet (z. B. 0.5 → 50 %) – bitte prüfen', $vorschau['zeilen'][1]['meldung']);
        // Warnungen sind (anders als «prüfen») weiterhin vorausgewählt – die Zuordnung selbst ist ja sicher
        $this->assertSame([true, true], array_column($vorschau['zeilen'], 'uebernehmen'));
    }

    #[Test]
    public function erneut_pruefen_stuft_nur_bei_tatsaechlich_geaendertem_bezug_als_sicher_ein(): void
    {
        $csv = "Datum;Fach/Modul;Titel;Note;Gewicht\n09.03.2026;Sprache;Vortrag;5.0;\n";
        $this->actingAs($this->user)
            ->post(route('learner.grades.import.read'), ['datei' => UploadedFile::fake()->createWithContent('noten.csv', $csv)]);

        $vorschau = session('notenimport.'.$this->user->lernender->lernender_id);
        $this->assertSame('pruefen', $vorschau['zeilen'][0]['status']);
        $this->assertFalse($vorschau['zeilen'][0]['sicher']);

        // Ein Klick auf «Erneut prüfen» ohne Korrektur am Bezug darf die nur geratene Zuordnung nicht
        // stillschweigend als «bereit» einstufen – sie bleibt zur Prüfung markiert.
        $zeile = [...$vorschau['zeilen'][0], 'uebernehmen' => true];
        $antwort = $this->actingAs($this->user)
            ->post(route('learner.grades.import.validate'), ['zeilen' => json_encode([$zeile])])
            ->assertOk()
            ->json();

        $this->assertSame('pruefen', $antwort['zeilen'][0]['status']);
        $this->assertFalse($antwort['zeilen'][0]['sicher']);
        $this->assertTrue($antwort['zeilen'][0]['uebernehmen']);
        // Die Vorschau in der Session wird mit dem neu geprüften Ergebnis aktualisiert
        $this->assertSame('pruefen', session('notenimport.'.$this->user->lernender->lernender_id)['zeilen'][0]['status']);

        // Erst wenn die Person den Bezug tatsächlich ändert (bewusste Wahl), gilt er als sicher
        $zeile2 = [...$antwort['zeilen'][0], 'bezug' => 'modul:'.$this->modul, 'uebernehmen' => true];
        $antwort2 = $this->actingAs($this->user)
            ->post(route('learner.grades.import.validate'), ['zeilen' => json_encode([$zeile2])])
            ->assertOk()
            ->json();

        $this->assertSame('ok', $antwort2['zeilen'][0]['status']);
        $this->assertTrue($antwort2['zeilen'][0]['sicher']);
    }

    #[Test]
    public function eine_ausgewaehlte_ungueltige_zeile_verhindert_den_gesamten_import(): void
    {
        $csv = "Datum;Fach/Modul;Titel;Note;Gewicht\n02.03.2026;SK;LB1;4,5;50%\n10.03.2026;Turnen;X;4;100\n";
        $this->actingAs($this->user)
            ->post(route('learner.grades.import.read'), ['datei' => UploadedFile::fake()->createWithContent('noten.csv', $csv)]);

        $vorschau = session('notenimport.'.$this->user->lernender->lernender_id);
        $this->assertSame(['ok', 'fehler'], array_column($vorschau['zeilen'], 'status'));

        // Beide Zeilen ausgewählt (auch die ungültige) – alles oder nichts: es darf nichts gespeichert werden
        $zeilen = array_map(fn ($z) => [...$z, 'uebernehmen' => true], $vorschau['zeilen']);
        $this->post(route('learner.grades.import.apply'), ['zeilen' => json_encode($zeilen), 'token' => $vorschau['token']])
            ->assertSessionHas('error');

        $this->assertSame(0, Note::count());
    }

    #[Test]
    public function eine_als_dublette_markierte_zeile_wird_beim_uebernehmen_serverseitig_blockiert(): void
    {
        $csv = "Datum;Fach/Modul;Titel;Note;Gewicht\n02.03.2026;SK;LB1;4,5;50%\n";
        $this->actingAs($this->user)
            ->post(route('learner.grades.import.read'), ['datei' => UploadedFile::fake()->createWithContent('noten.csv', $csv)]);
        $vorschau = session('notenimport.'.$this->user->lernender->lernender_id);
        $zeile = [...$vorschau['zeilen'][0], 'uebernehmen' => true];
        $this->post(route('learner.grades.import.apply'), ['zeilen' => json_encode([$zeile]), 'token' => $vorschau['token']])
            ->assertSessionHas('success');
        $this->assertSame(1, Note::count());

        // Erneuter Import derselben Datei: die Vorschau markiert die Zeile als Dublette und wählt sie nicht vor –
        // ein manipuliertes/veraltetes "uebernehmen: true" darf trotzdem nichts anlegen (serverseitige Prüfung).
        $this->post(route('learner.grades.import.read'), ['datei' => UploadedFile::fake()->createWithContent('noten.csv', $csv)]);
        $vorschau2 = session('notenimport.'.$this->user->lernender->lernender_id);
        $this->assertSame('doppelt', $vorschau2['zeilen'][0]['status']);
        $this->assertFalse($vorschau2['zeilen'][0]['uebernehmen']);

        $zeile2 = [...$vorschau2['zeilen'][0], 'uebernehmen' => true];
        $this->post(route('learner.grades.import.apply'), ['zeilen' => json_encode([$zeile2]), 'token' => $vorschau2['token']])
            ->assertSessionHas('error');
        $this->assertSame(1, Note::count());

        // Dublette innerhalb derselben Übernahme (zwei gleiche Zeilen zusammen ausgewählt) – alles oder nichts
        $csvGleich = "Datum;Fach/Modul;Titel;Note;Gewicht\n05.03.2026;SK;LB2;4.0;100\n05.03.2026;SK;LB2;4.0;100\n";
        $this->post(route('learner.grades.import.read'), ['datei' => UploadedFile::fake()->createWithContent('gleich.csv', $csvGleich)]);
        $vorschau3 = session('notenimport.'.$this->user->lernender->lernender_id);
        $zeilen3 = array_map(fn ($z) => [...$z, 'uebernehmen' => true], $vorschau3['zeilen']);
        $this->post(route('learner.grades.import.apply'), ['zeilen' => json_encode($zeilen3), 'token' => $vorschau3['token']])
            ->assertSessionHas('error');
        $this->assertSame(1, Note::count());
    }

    #[Test]
    public function ein_zweiter_post_derselben_vorschau_legt_nichts_ein_zweites_mal_an(): void
    {
        $csv = "Datum;Fach/Modul;Titel;Note;Gewicht\n02.03.2026;SK;LB1;4,5;50%\n";
        $this->actingAs($this->user)
            ->post(route('learner.grades.import.read'), ['datei' => UploadedFile::fake()->createWithContent('noten.csv', $csv)]);
        $vorschau = session('notenimport.'.$this->user->lernender->lernender_id);
        $zeile = [...$vorschau['zeilen'][0], 'uebernehmen' => true];
        $payload = ['zeilen' => json_encode([$zeile]), 'token' => $vorschau['token']];

        $this->post(route('learner.grades.import.apply'), $payload)->assertSessionHas('success');
        $this->assertSame(1, Note::count());

        // Zweiter Post derselben Daten (Zurück-Knopf, Doppelklick, zweiter Tab): kein gültiges Token mehr
        $this->post(route('learner.grades.import.apply'), $payload)
            ->assertSessionHas('error', __('Diese Vorschau wurde bereits übernommen.'));

        $this->assertSame(1, Note::count());
    }

    #[Test]
    public function excel_ohne_kopfzeile_wird_nach_inhalt_gelesen(): void
    {
        $blatt = new Spreadsheet;
        $blatt->getActiveSheet()->fromArray([
            [Date::PHPToExcel(new \DateTime('2026-04-14')), 'Modul 908 Theta', 'Projekt', 5.5],
            [Date::PHPToExcel(new \DateTime('2026-05-05')), 'SK', 'Aufsatz', 4.25],
            [Date::PHPToExcel(new \DateTime('2026-06-02')), 'SK', 'Formel', '=4+1'],
        ]);
        $pfad = tempnam(sys_get_temp_dir(), 'np').'.xlsx';
        (new Xlsx($blatt))->save($pfad);

        $tabelle = app(TabellenLeser::class)->lesen($pfad, 'xlsx');
        $zeilen = app(NotenImport::class)->vorschau($tabelle, (int) $this->user->lernender->lernender_id)['zeilen'];
        unlink($pfad);

        $this->assertSame(['2026-04-14', '2026-05-05', '2026-06-02'], array_column($zeilen, 'datum'));
        $this->assertSame([5.5, 4.25, 5.0], array_column($zeilen, 'note'));
        $this->assertSame(['modul:'.$this->modul, 'fach:'.$this->fach, 'fach:'.$this->fach], array_column($zeilen, 'bezug'));
    }

    #[Test]
    public function vorschau_und_erneutes_pruefen_fragen_die_datenbank_nicht_pro_zeile(): void
    {
        $lernenderId = (int) $this->user->lernender->lernender_id;
        $import = app(NotenImport::class);
        $abfragen = function (int $anzahl) use ($import, $lernenderId): array {
            $csv = "Datum;Fach/Modul;Titel;Note;Gewicht\n";
            for ($i = 0; $i < $anzahl; $i++) {
                $csv .= sprintf("%s;%s;Test %d;4.5;100\n", date('d.m.Y', strtotime("2026-02-02 +$i days")), $i % 2 ? 'M908' : 'SK', $i);
            }
            $pfad = tempnam(sys_get_temp_dir(), 'np').'.csv';
            file_put_contents($pfad, $csv);
            $tabelle = app(TabellenLeser::class)->lesen($pfad, 'csv');
            unlink($pfad);

            DB::flushQueryLog();
            DB::enableQueryLog();
            $zeilen = $import->vorschau($tabelle, $lernenderId)['zeilen'];
            $vorschau = count(DB::getQueryLog());
            DB::flushQueryLog();
            $import->pruefeZeilen($zeilen, $lernenderId, $zeilen);
            $pruefen = count(DB::getQueryLog());
            DB::disableQueryLog();
            $this->assertCount($anzahl, $zeilen);
            $this->assertSame(['ok'], array_values(array_unique(array_column($zeilen, 'status'))));

            return [$vorschau, $pruefen];
        };

        [$vorschauKlein, $pruefenKlein] = $abfragen(4);
        [$vorschauGross, $pruefenGross] = $abfragen(60);
        // Zehnmal mehr Zeilen dürfen kaum mehr Abfragen kosten: Lernender, Semester und erlaubte Fächer/Module
        // lädt NoteService im Batch einmal, nicht pro Zeile.
        $this->assertLessThanOrEqual($vorschauKlein + 3, $vorschauGross, "Vorschau: $vorschauKlein Abfragen bei 4 Zeilen, $vorschauGross bei 60");
        $this->assertLessThanOrEqual($pruefenKlein + 3, $pruefenGross, "Erneut prüfen: $pruefenKlein Abfragen bei 4 Zeilen, $pruefenGross bei 60");
    }

    #[Test]
    public function pdf_text_und_werte_werden_sauber_zerlegt(): void
    {
        $zeilen = app(TabellenLeser::class)->textZeilen("Notenliste Nina\n02.03.2026 M908 Theta 4.5 50 %\n\nDatum    Fach    Note");
        $this->assertSame(['02.03.2026', 'M908 Theta', '', '4.5', '50'], $zeilen[1]);

        $import = app(NotenImport::class);
        $this->assertSame('2026-03-02', $import->datum('2.3.2026'));
        $this->assertNull($import->note('4.33'));
        $this->assertSame(75.0, $import->gewicht('0.75'));
        $this->assertFalse($import->gewicht('150'));
    }

    #[Test]
    public function vorlage_und_verwerfen_stehen_dem_lernenden_zur_verfuegung(): void
    {
        $this->actingAs($this->user)
            ->get(route('learner.grades.import.template'))
            ->assertOk()->assertSee('Datum;Fach/Modul;Titel;Note;Gewicht', false);

        $csv = "Datum;Fach/Modul;Titel;Note;Gewicht\n02.03.2026;M908;LB1;4,5;50%\n";
        $this->actingAs($this->user)
            ->post(route('learner.grades.import.read'), ['datei' => UploadedFile::fake()->createWithContent('noten.csv', $csv)]);
        $this->assertNotNull(session('notenimport.'.$this->user->lernender->lernender_id));

        $this->actingAs($this->user)
            ->post(route('learner.grades.import.discard'))
            ->assertRedirect(route('learner.grades.import.index'));

        $this->assertNull(session('notenimport.'.$this->user->lernender->lernender_id));
        $this->assertSame(0, Note::count());
    }

    #[Test]
    public function vorschau_verwerfen_fragt_vorher_nach(): void
    {
        $csv = "Datum;Fach/Modul;Titel;Note;Gewicht\n02.03.2026;M908;LB1;4,5;50%\n";
        $this->actingAs($this->user)
            ->post(route('learner.grades.import.read'), ['datei' => UploadedFile::fake()->createWithContent('noten.csv', $csv)]);

        $this->get(route('learner.grades.import.index'))
            ->assertOk()
            ->assertSee('id="import-verwerfen"', false)
            ->assertSee('data-bestaetigen="Vorschau verwerfen?"', false)
            ->assertSee('data-bestaetigen-text="Alle Korrekturen gehen verloren."', false)
            ->assertSee('data-bestaetigen-knopf="Verwerfen"', false);
    }

    #[Test]
    public function berufsbildner_importieren_keine_noten_admin_schon(): void
    {
        $lernenderId = (int) $this->user->lernender->lernender_id;
        $bb = User::factory()->berufsbildner()->create();
        DB::table('betreuungen')->insert(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $lernenderId, 'gueltig_von' => '2024-08-01']);

        $this->actingAs($bb)->get(route('trainer.learners.grades.import.index', $lernenderId))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.learners.grades.import.index', $lernenderId))->assertOk();
        $this->get(route('admin.learners.grades.import.template', $lernenderId))->assertOk()->assertSee('Datum;Fach/Modul;Titel;Note;Gewicht', false);
    }
}
