<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\Betreuung;
use App\Models\Dokument;
use App\Models\Fach;
use App\Models\Feedback;
use App\Models\FeedbackStimme;
use App\Models\MailLog;
use App\Models\Note;
use App\Models\NotenKommentar;
use App\Models\Pruefung;
use App\Models\User;
use App\Support\Datenauskunft;
use App\Support\Einstellungen;
use App\Support\NotenSkala;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

/** Art. 25 DSG: eigene Daten herunterladen (Profil) oder, als Admin, für ein beliebiges Konto. */
class DatenauskunftTest extends TestCase
{
    /** @var list<string> */
    private array $aufraeumen = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        // Der Dateiname des ZIP entsteht erst im Controller. Läuft der Test genau
        // über Mitternacht, erwartet er ein anderes Datum als der Server einsetzt.
        $this->freezeTime();
    }

    protected function tearDown(): void
    {
        foreach ($this->aufraeumen as $pfad) {
            @unlink($pfad);
        }
        Datenauskunft::$maxDokumenteBytes = 524_288_000;
        parent::tearDown();
    }

    /** Öffnet das von Datenauskunft::erzeugen gebaute ZIP direkt (ohne HTTP) und löscht die temporäre Datei danach. */
    private function oeffnen(User $user): ZipArchive
    {
        $pfad = app(Datenauskunft::class)->erzeugen($user);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($pfad) === true);
        $this->aufraeumen[] = $pfad;

        return $zip;
    }

    /** Angemeldet und mit einer frisch bestätigten Passwortbestätigung (umgeht password.confirm). */
    private function confirmed(User $user): self
    {
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

        return $this;
    }

    /** Namen und Inhalt jedes einzelnen ZIP-Eintrags aneinandergehängt, um das ganze Archiv nach Geheimnissen zu durchsuchen. */
    private function alleEintraege(ZipArchive $zip): string
    {
        $inhalt = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $inhalt .= $zip->getNameIndex($i)."\n".$zip->getFromIndex($i)."\n";
        }

        return $inhalt;
    }

    #[Test]
    public function lernender_erhaelt_eigene_noten_und_dokumente_ohne_geheimnisse_und_ohne_fremde_daten(): void
    {
        $a = User::factory()->lernender()->create([
            'nachname' => 'Erni', 'vorname' => 'Sina', 'kalender_token' => 'geheimes-a-kalender-token-123',
        ]);
        $b = User::factory()->lernender()->create(['nachname' => 'Keller', 'vorname' => 'Timo']);

        $noteA = Note::factory()->create([
            'lernender_id' => $a->lernender->lernender_id,
            'titel' => '=2+2',
            'note_wert' => 5.0,
            'erfasst_von_benutzer_id' => $a->benutzer_id,
        ]);
        NotenKommentar::create(['note_id' => $noteA->note_id, 'autor_benutzer_id' => $a->benutzer_id, 'kommentar_text' => 'Gut gemacht']);

        $noteB = Note::factory()->create(['lernender_id' => $b->lernender->lernender_id, 'titel' => 'Geheime Fremdnote', 'note_wert' => 3.0]);

        $this->actingAs($a)->post(route('learner.documents.store'), [
            'datei' => UploadedFile::fake()->create('zeugnis.pdf', 20, 'application/pdf'),
            'art' => 'zeugnis',
        ])->assertSessionHasNoErrors();

        $this->actingAs($b)->post(route('learner.documents.store'), [
            'datei' => UploadedFile::fake()->create('fremdes-dokument.pdf', 20, 'application/pdf'),
            'art' => 'zeugnis',
        ])->assertSessionHasNoErrors();

        MailLog::create(['user_id' => $a->benutzer_id, 'type' => 'test', 'recipient' => $a->email, 'subject' => 'Willkommen', 'status' => MailLog::SENT, 'created_at' => now(), 'sent_at' => now()]);

        $zip = $this->oeffnen($a);

        foreach (['README.txt', 'konto.json', 'noten.csv', 'noten.json', 'pruefungen.csv', 'ziele.json', 'belegungen.json',
            'feedback.json', 'versandprotokoll.csv', 'dokumente.json'] as $datei) {
            $this->assertNotFalse($zip->locateName($datei), "fehlt: {$datei}");
        }
        $this->assertNotFalse($zip->locateName('dokumente/erni_sina_zeugnis.pdf'));

        $konto = $zip->getFromName('konto.json');
        $this->assertStringNotContainsString('passwort', strtolower($konto));
        $this->assertStringNotContainsString('kalender_token', $konto);
        $this->assertStringNotContainsString('remember_token', $konto);

        $notenCsv = $zip->getFromName('noten.csv');
        $this->assertStringContainsString("'=2+2", $notenCsv);
        $this->assertStringNotContainsString('Fremdnote', $notenCsv);

        $notenJson = json_decode($zip->getFromName('noten.json'), true);
        $this->assertCount(1, $notenJson);
        $this->assertSame($noteA->note_id, $notenJson[0]['note_id']);

        // Ganzes Archiv (alle Einträge, nicht nur die offensichtlichen Dateien): keine internen Geheimnisse,
        // keine fremde Notentitel, kein fremder Dokumentname.
        $alles = $this->alleEintraege($zip);
        $dokumentA = Dokument::where('lernender_id', $a->lernender->lernender_id)->firstOrFail();
        $dokumentB = Dokument::where('lernender_id', $b->lernender->lernender_id)->firstOrFail();
        $this->assertStringNotContainsString($dokumentA->sha256, $alles);
        $this->assertStringNotContainsString('geheimes-a-kalender-token-123', $alles);
        $this->assertStringNotContainsString('Geheime Fremdnote', $alles);
        $this->assertStringNotContainsString($dokumentB->originalname, $alles);
        $this->assertStringNotContainsString('fremdes-dokument', strtolower($alles));

        $zip->close();
    }

    #[Test]
    public function stufe_statt_zahl_erscheint_in_csv_und_json(): void
    {
        // Prüferbefund: Sport «C» stand als leere Note im CSV und als "note": null im JSON
        $a = User::factory()->lernender()->create();
        Note::factory()->create(['lernender_id' => $a->lernender->lernender_id, 'titel' => 'Sporttag', 'note_wert' => null, 'note_stufe' => 'C']);

        $zip = $this->oeffnen($a);
        $this->assertMatchesRegularExpression('/Sporttag;C;/', (string) $zip->getFromName('noten.csv'));
        $json = json_decode((string) $zip->getFromName('noten.json'), true);
        $this->assertNull($json[0]['note']);
        $this->assertSame('C', $json[0]['stufe']);
        $zip->close();
    }

    #[Test]
    public function pruefungen_csv_zeigt_bei_verknuepfter_stufennote_die_stufe_und_bei_zahlennote_die_zahl(): void
    {
        // Prüferbefund: Eine Prüfung mit verknüpfter Stufennote (note_wert null) ergab eine leere Notenspalte.
        $a = User::factory()->lernender()->create();
        $lernenderId = $a->lernender->lernender_id;
        $fach = Fach::factory()->create();

        $stufe = Note::factory()->create(['lernender_id' => $lernenderId, 'titel' => 'Sporttag', 'note_wert' => null, 'note_stufe' => 'A']);
        $zahl = Note::factory()->create(['lernender_id' => $lernenderId, 'titel' => 'Mathe', 'note_wert' => 5.5, 'note_stufe' => null]);

        Pruefung::create(['lernender_id' => $lernenderId, 'fach_id' => $fach->fach_id, 'titel' => 'Sportprüfung', 'datum' => '2026-05-02', 'note_id' => $stufe->note_id]);
        Pruefung::create(['lernender_id' => $lernenderId, 'fach_id' => $fach->fach_id, 'titel' => 'Matheprüfung', 'datum' => '2026-05-01', 'note_id' => $zahl->note_id]);

        $zip = $this->oeffnen($a);
        $zeilen = array_map(
            fn (string $zeile) => str_getcsv($zeile, ';', '"', ''),
            array_values(array_filter(preg_split('/\R/', (string) $zip->getFromName('pruefungen.csv')))),
        );
        $zip->close();

        $kopf = $zeilen[0];
        $notenSpalte = array_search(__('Note'), $kopf, true);
        $this->assertNotFalse($notenSpalte);
        $nachTitel = [];
        foreach (array_slice($zeilen, 1) as $zeile) {
            $nachTitel[$zeile[array_search(__('Titel'), $kopf, true)]] = $zeile[$notenSpalte];
        }

        $this->assertSame(NotenSkala::stufeText('A'), $nachTitel['Sportprüfung']);
        $this->assertNotSame('', $nachTitel['Sportprüfung']);
        $this->assertSame('5.50', number_format((float) $nachTitel['Matheprüfung'], 2, '.', ''));
    }

    #[Test]
    public function feedback_stimmen_json_erscheint_nur_bei_vorhandenen_stimmen_ohne_fremden_text(): void
    {
        $a = User::factory()->lernender()->create();

        // Ohne Stimmen fehlt die Datei.
        $zipOhne = $this->oeffnen($a);
        $this->assertFalse($zipOhne->locateName('feedback_stimmen.json'));
        $zipOhne->close();

        if (! FeedbackStimme::tabelleVorhanden()) {
            $this->markTestSkipped('feedback_stimmen-Tabelle fehlt (Migration noch nicht gelaufen).');
        }

        $fremdeMeldung = Feedback::factory()->create([
            'text' => 'Geheimer Text der fremden Meldung, darf nicht in der Auskunft stehen',
            'kategorie' => Feedback::KATEGORIE_IDEE,
        ]);
        FeedbackStimme::query()->insert([
            'feedback_id' => $fremdeMeldung->feedback_id,
            'benutzer_id' => $a->benutzer_id,
            'erstellt_am' => now(),
        ]);

        $zip = $this->oeffnen($a);
        $this->assertNotFalse($zip->locateName('feedback_stimmen.json'));

        $stimmen = json_decode($zip->getFromName('feedback_stimmen.json'), true);
        $this->assertCount(1, $stimmen);
        $this->assertSame($fremdeMeldung->feedback_id, $stimmen[0]['meldung_id']);
        $this->assertSame(Feedback::KATEGORIE_IDEE, $stimmen[0]['kategorie']);
        $this->assertSame(['erstellt_am', 'meldung_id', 'kategorie'], array_keys($stimmen[0]));

        $alles = $this->alleEintraege($zip);
        $this->assertStringNotContainsString('Geheimer Text der fremden Meldung', $alles);

        $zip->close();
    }

    #[Test]
    public function berufsbildner_erhaelt_betreuungen_und_eigene_kommentare_statt_notenexport(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $lernender = User::factory()->lernender()->create()->lernender;
        Betreuung::create(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $lernender->lernender_id, 'gueltig_von' => now()->subMonths(6)->toDateString()]);

        $note = Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'titel' => 'Vertrauliche Note', 'note_wert' => 5.75]);
        NotenKommentar::create(['note_id' => $note->note_id, 'autor_benutzer_id' => $bb->benutzer_id, 'kommentar_text' => 'Weiter so']);

        $zip = $this->oeffnen($bb);

        $this->assertNotFalse($zip->locateName('betreuungen.csv'));
        $this->assertNotFalse($zip->locateName('kommentare.csv'));
        $this->assertFalse($zip->locateName('noten.csv'));
        $this->assertStringContainsString('Weiter so', $zip->getFromName('kommentare.csv'));

        // Der ganze ZIP-Inhalt darf weder den Notenwert noch den Titel der fremden Note enthalten.
        $alles = $this->alleEintraege($zip);
        $this->assertStringNotContainsString('Vertrauliche Note', $alles);
        $this->assertStringNotContainsString('5.75', $alles);

        $zip->close();
    }

    #[Test]
    public function admin_kann_datenauskunft_fuer_fremdes_konto_erstellen_berufsbildner_nicht(): void
    {
        $admin = User::factory()->admin()->create();
        $lernender = User::factory()->lernender()->create(['nachname' => 'Vogel', 'vorname' => 'Nora']);

        $erwarteterName = 'datenauskunft-'.$lernender->benutzername.'-'.now()->format('Y-m-d').'.zip';

        $this->actingAs($admin)
            ->get(route('admin.users.data-export', $lernender->benutzer_id))
            ->assertOk()
            ->assertDownload($erwarteterName);

        $this->actingAs(User::factory()->berufsbildner()->create())
            ->get(route('admin.users.data-export', $lernender->benutzer_id))
            ->assertForbidden();
    }

    #[Test]
    public function admin_kann_datenauskunft_fuer_geloeschtes_konto_erstellen(): void
    {
        $admin = User::factory()->admin()->create();
        $lernender = User::factory()->lernender()->create(['nachname' => 'Weber', 'vorname' => 'Elin']);
        $lernender->delete();

        $erwarteterName = 'datenauskunft-'.$lernender->benutzername.'-'.now()->format('Y-m-d').'.zip';

        $this->actingAs($admin)
            ->get(route('admin.users.data-export', $lernender->benutzer_id))
            ->assertOk()
            ->assertDownload($erwarteterName);
    }

    #[Test]
    public function fehler_beim_zip_bau_fuehrt_zurueck_mit_meldung_statt_500(): void
    {
        // Eine unlesbare Datei besteht is_file(), lässt aber $zip->close() scheitern. Als root gibt es keine
        // unlesbare Datei – dort lässt sich der Fehler auf diesem Weg nicht herbeiführen.
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('Als root sind auch Dateien mit Modus 0 lesbar.');
        }
        Storage::fake('local');
        $user = User::factory()->lernender()->create();
        $pfad = 'lernende/'.$user->lernender->lernender_id.'/dokumente/gesperrt.pdf';
        Storage::disk('local')->put($pfad, '%PDF-1.4');
        chmod(Storage::disk('local')->path($pfad), 0);
        Dokument::create([
            'lernender_id' => $user->lernender->lernender_id, 'art' => 'zeugnis', 'titel' => 'Gesperrt', 'originalname' => 'gesperrt.pdf',
            'pfad' => $pfad, 'mime' => 'application/pdf', 'groesse' => 8, 'sha256' => str_repeat('a', 64),
            'hochgeladen_von_benutzer_id' => $user->benutzer_id,
        ]);

        $this->confirmed($user)->from(route('settings.profile'))->get(route('profile.data-export'))
            ->assertRedirect(route('settings.profile'))->assertSessionHas('error');
        $this->confirmed(User::factory()->admin()->create())->from(route('admin.users.index'))->get(route('admin.users.data-export', $user->benutzer_id))
            ->assertRedirect(route('admin.users.index'))->assertSessionHas('error');
    }

    #[Test]
    public function eigene_datenauskunft_verlangt_anmeldung(): void
    {
        $this->get(route('profile.data-export'))->assertRedirect(route('login'));
    }

    #[Test]
    public function eigene_datenauskunft_ohne_passwortbestaetigung_leitet_zur_bestaetigung_um(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->get(route('profile.data-export'))
            ->assertRedirect(route('password.confirm'));
    }

    #[Test]
    public function eigene_route_liefert_das_zip_als_download(): void
    {
        $user = User::factory()->lernender()->create();
        $erwarteterName = 'datenauskunft-'.$user->benutzername.'-'.now()->format('Y-m-d').'.zip';

        $this->confirmed($user)
            ->get(route('profile.data-export'))
            ->assertOk()
            ->assertDownload($erwarteterName);
    }

    #[Test]
    public function export_verwendet_die_sprache_des_exportierten_kontos_nicht_die_der_auslösenden_person(): void
    {
        Einstellungen::set(SetLocale::WAHL_AKTIV, '1');

        $admin = User::factory()->admin()->create(['locale' => 'en']);
        $lernender = User::factory()->lernender()->create(['locale' => 'de']);

        Pruefung::create([
            'lernender_id' => $lernender->lernender->lernender_id,
            'fach_id' => Fach::factory()->create()->fach_id,
            'titel' => 'Testprüfung',
            'datum' => now()->toDateString(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.users.data-export', $lernender->benutzer_id))
            ->assertOk();

        $pfad = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($pfad) === true);
        $pruefungenCsv = $zip->getFromName('pruefungen.csv');
        $zip->close();
        @unlink($pfad);

        // Kopfzeile in Deutsch (Sprache des Lernenden), obwohl die auslösende Admin-Person Englisch eingestellt hat.
        $this->assertStringContainsString('Abgesagt', $pruefungenCsv);
        $this->assertStringNotContainsString('Cancelled', $pruefungenCsv);
    }

    #[Test]
    public function zu_grosse_dokumente_liefern_eine_fehlermeldung_statt_ein_zip(): void
    {
        $user = User::factory()->lernender()->create();

        Dokument::create([
            'lernender_id' => $user->lernender->lernender_id,
            'art' => 'zeugnis',
            'titel' => 'Grosses Dokument',
            'originalname' => 'gross.pdf',
            'pfad' => 'lernende/'.$user->lernender->lernender_id.'/dokumente/gross.pdf',
            'mime' => 'application/pdf',
            'groesse' => 1000,
            'sha256' => str_repeat('a', 64),
            'hochgeladen_von_benutzer_id' => $user->benutzer_id,
        ]);

        Datenauskunft::$maxDokumenteBytes = 500;

        $this->confirmed($user)
            ->from(route('settings.profile'))
            ->get(route('profile.data-export'))
            ->assertRedirect(route('settings.profile'))
            ->assertSessionHas('error');
    }
}
