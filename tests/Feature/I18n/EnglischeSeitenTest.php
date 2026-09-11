<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use App\Http\Middleware\SetLocale;
use App\Models\Betreuung;
use App\Models\Dokument;
use App\Models\Feedback;
use App\Models\FeedbackStimme;
use App\Models\Note;
use App\Models\User;
use App\Support\Einrichtung;
use App\Support\Einstellungen;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Dynamischer Nachweis (Ergänzung zu SchluesselTest/AdminEnglischTest & Co., die fehlende
 * Übersetzungsschlüssel prüfen): rendert pro Rolle jede erreichbare GET-Seite mit locale=en und
 * durchsucht den tatsächlich sichtbaren Text (inkl. aria-label/title/placeholder/alt) nach
 * deutschen Resten – Text, der nie durch __() lief, findet SchluesselTest nicht.
 *
 * Freitext/Stammdaten aus dem DemoSeeder (Namen, Fach-/Modul-/Lehrberufsnamen, Kategorien,
 * Notenkommentare, Klassenbezeichnungen, Betriebsname) sind Dateninhalt, kein Oberflächentext,
 * und werden vor der Prüfung aus dem Text entfernt. Downloads (CSV/PDF-Export, ICS, Screenshot,
 * Backup) sind keine Seiten und werden nicht geprüft; ihre Inhalte (z. B. CSV-Köpfe) dürfen
 * deutsch bleiben.
 */
class EnglischeSeitenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Carbon::setTestNow('2026-09-08 10:00:00');
        (new DemoSeeder)->run();
        Einstellungen::set(SetLocale::WAHL_AKTIV, '1');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // -------------------------------------------------------------------------------------------
    // Lernender
    // -------------------------------------------------------------------------------------------

    #[Test]
    public function lernenden_seiten_ohne_deutsche_reste(): void
    {
        $user = User::where('email', 'nina.huber@demo.example')->firstOrFail();
        $user->update(['locale' => 'en']);
        $lernenderId = (int) $user->lernender->lernender_id;
        $noteId = (int) Note::where('lernender_id', $lernenderId)->whereNull('geloescht_am')->firstOrFail()->note_id;

        $this->actingAs($user);

        $dokument = $this->ladeDokumentHoch(route('learner.documents.store'), 'Report card 2026.pdf');

        $seiten = [
            'learner.dashboard' => route('learner.dashboard'),
            'learner.grades.index' => route('learner.grades.index'),
            'learner.grades.calculator' => route('learner.grades.calculator'),
            'learner.grades.create' => route('learner.grades.create'),
            'learner.grades.edit' => route('learner.grades.edit', $noteId),
            'learner.grades.print' => route('learner.grades.print'),
            'learner.grades.import.index' => route('learner.grades.import.index'),
            'learner.exams.index' => route('learner.exams.index'),
            'learner.documents.index' => route('learner.documents.index'),
            'learner.documents.reconcile' => route('learner.documents.reconcile', $dokument->dokument_id),
            'feedback.index' => route('feedback.index'),
            'settings.profile' => route('settings.profile'),
            'settings.calendar' => route('settings.calendar'),
            'settings.data' => route('settings.data'),
            'notifications.settings' => route('notifications.settings'),
        ];

        $this->pruefeSeiten($seiten);
    }

    // -------------------------------------------------------------------------------------------
    // Berufsbildner
    // -------------------------------------------------------------------------------------------

    #[Test]
    public function berufsbildner_seiten_ohne_deutsche_reste(): void
    {
        $bb = User::where('email', 'michael.baumann@demo.example')->firstOrFail();
        $bb->update(['locale' => 'en']);

        $betreuung = Betreuung::query()
            ->whereHas('berufsbildner', fn ($q) => $q->where('benutzer_id', $bb->benutzer_id))
            ->whereNull('gueltig_bis')
            ->firstOrFail();
        $lernenderId = (int) $betreuung->lernender_id;
        $noteId = (int) Note::where('lernender_id', $lernenderId)->whereNull('geloescht_am')->firstOrFail()->note_id;

        $this->actingAs($bb);

        $dokument = $this->ladeDokumentHoch(
            route('trainer.learners.documents.store', $lernenderId),
            'Report card 2026.pdf',
        );

        $seiten = [
            'trainer.dashboard' => route('trainer.dashboard'),
            'trainer.learners.index' => route('trainer.learners.index'),
            'trainer.learners.create' => route('trainer.learners.create'),
            'trainer.learners.show:overview' => route('trainer.learners.show', $lernenderId).'?tab=overview',
            'trainer.learners.show:profil' => route('trainer.learners.show', $lernenderId).'?tab=profil',
            'trainer.learners.edit' => route('trainer.learners.edit', $lernenderId),
            'trainer.learners.calculator' => route('trainer.learners.calculator', $lernenderId),
            'trainer.learners.grades.index' => route('trainer.learners.grades.index', $lernenderId),
            'trainer.learners.grades.edit' => route('trainer.learners.grades.edit', [$lernenderId, $noteId]),
            'trainer.learners.grades.print' => route('trainer.learners.grades.print', $lernenderId),
            'trainer.learners.documents.index' => route('trainer.learners.documents.index', $lernenderId),
            'trainer.learners.documents.reconcile' => route('trainer.learners.documents.reconcile', [$lernenderId, $dokument->dokument_id]),
            'trainer.exams.index' => route('trainer.exams.index'),
            'feedback.index' => route('feedback.index'),
            'settings.profile' => route('settings.profile'),
            'settings.calendar' => route('settings.calendar'),
            'settings.data' => route('settings.data'),
            'notifications.settings' => route('notifications.settings'),
        ];

        $this->pruefeSeiten($seiten);
    }

    // -------------------------------------------------------------------------------------------
    // Admin
    // -------------------------------------------------------------------------------------------

    #[Test]
    public function admin_seiten_ohne_deutsche_reste(): void
    {
        $admin = User::where('email', 'laura.frei@demo.example')->firstOrFail();
        $admin->update(['locale' => 'en']);
        $bb = User::where('email', 'sarah.keller@demo.example')->firstOrFail();

        $lernenderId = (int) DB::table('lernende')->orderBy('lernender_id')->value('lernender_id');
        $noteId = (int) Note::where('lernender_id', $lernenderId)->whereNull('geloescht_am')->firstOrFail()->note_id;
        $modulId = (int) DB::table('module')->orderBy('modul_id')->value('modul_id');
        $fachId = (int) DB::table('faecher')->orderBy('fach_id')->value('fach_id');
        $semesterId = (int) DB::table('semester')->orderBy('semester_id')->value('semester_id');
        $kategorieId = (int) DB::table('kategorien')->orderBy('kategorie_id')->value('kategorie_id');
        $lehrberufId = (int) DB::table('lehrberufe')->orderBy('lehrberuf_id')->value('lehrberuf_id');

        $this->actingAs($admin);

        $dokument = $this->ladeDokumentHoch(
            route('admin.learners.documents.store', $lernenderId),
            'Report card 2026.pdf',
        );

        $feedback = Feedback::factory()->create([
            'benutzer_id' => $bb->benutzer_id,
            'rolle' => 'Berufsbildner',
            'text' => 'The overview page loads much faster now, thanks!',
            'route_name' => 'trainer.learners.grades.index',
            'url' => '/trainer/learners/'.$lernenderId.'/grades',
        ]);
        if (FeedbackStimme::tabelleVorhanden()) {
            FeedbackStimme::query()->insert([
                'feedback_id' => $feedback->feedback_id,
                'benutzer_id' => $bb->benutzer_id,
                'erstellt_am' => now(),
            ]);
        }
        if (Feedback::hatDuplikatSpalte()) {
            Feedback::factory()->duplikatVon($feedback->feedback_id)->create([
                'benutzer_id' => $bb->benutzer_id,
                'rolle' => 'Berufsbildner',
                'text' => 'Same issue here as well.',
                'route_name' => 'trainer.learners.grades.index',
            ]);
        }

        $seiten = [
            'admin.dashboard' => route('admin.dashboard'),
            'admin.learners.index' => route('admin.learners.index'),
            'admin.learners.create' => route('admin.learners.create'),
            'admin.learners.show:overview' => route('admin.learners.show', $lernenderId).'?tab=overview',
            'admin.learners.show:profil' => route('admin.learners.show', $lernenderId).'?tab=profil',
            'admin.learners.edit' => route('admin.learners.edit', $lernenderId),
            'admin.learners.calculator' => route('admin.learners.calculator', $lernenderId),
            'admin.learners.grades.index' => route('admin.learners.grades.index', $lernenderId),
            'admin.learners.grades.create' => route('admin.learners.grades.create', $lernenderId),
            'admin.learners.grades.edit' => route('admin.learners.grades.edit', [$lernenderId, $noteId]),
            'admin.learners.grades.print' => route('admin.learners.grades.print', $lernenderId),
            'admin.learners.grades.import.index' => route('admin.learners.grades.import.index', $lernenderId),
            'admin.learners.documents.index' => route('admin.learners.documents.index', $lernenderId),
            'admin.learners.documents.reconcile' => route('admin.learners.documents.reconcile', [$lernenderId, $dokument->dokument_id]),
            'admin.exams.index' => route('admin.exams.index'),
            'admin.users.index' => route('admin.users.index'),
            'admin.users.create' => route('admin.users.create'),
            'admin.users.edit' => route('admin.users.edit', $bb->benutzer_id),
            'admin.trainers.index' => route('admin.trainers.index'),
            'admin.feedback.index' => route('admin.feedback.index'),
            'admin.feedback.index:sort-stimmen' => route('admin.feedback.index', ['sort' => 'stimmen']),
            'admin.mail-log.index' => route('admin.mail-log.index'),
            'admin.activity.index' => route('admin.activity.index'),
            'admin.notifications.index' => route('admin.notifications.index'),
            'admin.operations.edit' => route('admin.operations.edit'),
            'admin.reports.grades' => route('admin.reports.grades'),
            'admin.master-data.categories.index' => route('admin.master-data.categories.index'),
            'admin.master-data.categories.create' => route('admin.master-data.categories.create'),
            'admin.master-data.categories.edit' => route('admin.master-data.categories.edit', $kategorieId),
            'admin.master-data.modules.index' => route('admin.master-data.modules.index'),
            'admin.master-data.modules.create' => route('admin.master-data.modules.create'),
            'admin.master-data.modules.edit' => route('admin.master-data.modules.edit', $modulId),
            'admin.master-data.professions.index' => route('admin.master-data.professions.index'),
            'admin.master-data.professions.create' => route('admin.master-data.professions.create'),
            'admin.master-data.professions.show' => route('admin.master-data.professions.show', $lehrberufId),
            'admin.master-data.professions.edit' => route('admin.master-data.professions.edit', $lehrberufId),
            'admin.master-data.semesters.index' => route('admin.master-data.semesters.index'),
            'admin.master-data.semesters.create' => route('admin.master-data.semesters.create'),
            'admin.master-data.semesters.edit' => route('admin.master-data.semesters.edit', $semesterId),
            'admin.master-data.subjects.index' => route('admin.master-data.subjects.index'),
            'admin.master-data.subjects.create' => route('admin.master-data.subjects.create'),
            'admin.master-data.subjects.edit' => route('admin.master-data.subjects.edit', $fachId),
            'feedback.index' => route('feedback.index'),
            'settings.profile' => route('settings.profile'),
            'settings.calendar' => route('settings.calendar'),
            'settings.data' => route('settings.data'),
            'notifications.settings' => route('notifications.settings'),
        ];
        foreach (array_keys(Einrichtung::SCHRITTE) as $schritt) {
            $seiten['admin.setup:'.$schritt] = route('admin.setup', $schritt);
        }

        $this->pruefeSeiten($seiten);
    }

    // -------------------------------------------------------------------------------------------
    // Block AE: Systemhinweis-Banner / Sitzungs-Timeout
    // -------------------------------------------------------------------------------------------

    #[Test]
    public function login_und_dashboard_mit_systemhinweis_ohne_deutsche_reste(): void
    {
        $admin = User::where('email', 'laura.frei@demo.example')->firstOrFail();
        $admin->update(['locale' => 'en']);

        $hinweisText = 'Geplante Wartung heute Abend, bitte frühzeitig speichern.';
        $this->actingAs($admin)->put(route('admin.operations.notice.update'), [
            'hinweis_text' => $hinweisText,
            'hinweis_art' => 'warnung',
            'hinweis_zielgruppe' => 'alle',
            'hinweis_beginn' => '',
            'hinweis_ende' => '',
            'hinweis_login' => '1',
        ])->assertSessionHasNoErrors();

        $bekannt = [...self::bekannteDaten(), $hinweisText];

        // Login-Seite (Gast, Sprache übers Sprachumschalt-Cookie), mit ?abgelaufen=1 und aktivem Hinweis.
        $loginAntwort = $this->actingAsGuest()
            ->withHeaders(['Accept-Language' => 'en'])
            ->get('/login?abgelaufen=1');
        $loginAntwort->assertOk();
        $loginAntwort->assertSee('<html lang="en"', false);
        $loginAntwort->assertSee($hinweisText);

        $loginText = self::ohneBekannteDaten(self::sichtbarerText((string) $loginAntwort->getContent()), $bekannt);
        $this->assertSame([], self::deutscheReste($loginText), 'Login-Seite (?abgelaufen=1, mit Hinweis): deutsche Reste.');

        // Dashboard mit aktivem Systemhinweis-Banner.
        $dashboardAntwort = $this->actingAs($admin)->get(route('admin.dashboard'));
        $dashboardAntwort->assertOk();
        $dashboardAntwort->assertSee('<html lang="en"', false);
        $dashboardAntwort->assertSee($hinweisText);

        $dashboardText = self::ohneBekannteDaten(self::sichtbarerText((string) $dashboardAntwort->getContent()), $bekannt);
        $this->assertSame([], self::deutscheReste($dashboardText), 'Dashboard mit Systemhinweis-Banner: deutsche Reste.');
    }

    // -------------------------------------------------------------------------------------------
    // Hilfsmittel
    // -------------------------------------------------------------------------------------------

    private function ladeDokumentHoch(string $url, string $dateiname): Dokument
    {
        $this->post($url, [
            'datei' => UploadedFile::fake()->create($dateiname, 80, 'application/pdf'),
            'art' => 'zeugnis',
        ])->assertSessionHasNoErrors();

        return Dokument::where('originalname', $dateiname)->latest('dokument_id')->firstOrFail();
    }

    /** @param array<string, string> $seiten Name => URL */
    private function pruefeSeiten(array $seiten): void
    {
        $bekannt = self::bekannteDaten();
        $fehlerhaft = [];

        foreach ($seiten as $name => $url) {
            /** @var TestResponse $antwort */
            $antwort = $this->get($url);
            $antwort->assertOk();
            $antwort->assertSee('<html lang="en"', false);

            $text = self::ohneBekannteDaten(self::sichtbarerText((string) $antwort->getContent()), $bekannt);
            $reste = self::deutscheReste($text);

            if ($reste !== []) {
                $fehlerhaft[$name] = $reste;
            }
        }

        $meldung = collect($fehlerhaft)
            ->map(fn (array $reste, string $name) => "{$name}: ".implode(', ', $reste))
            ->implode("\n");

        $this->assertSame([], $fehlerhaft, "Deutsche Reste auf englischen Seiten:\n{$meldung}");
    }

    /**
     * Freitext/Stammdaten: Dateninhalt, kein Oberflächentext – bleibt deutsch. Neben den
     * DemoSeeder-Datensätzen zählt dazu auch der feste Lehrberufe-/Fächer-Katalog des
     * Einrichtungsassistenten (echte Schweizer Berufe-/Fachbezeichnungen, z. B. «Wirtschaft und
     * Recht») sowie dessen Format-Beispieltexte für Modulnummern (analog zu CSV-Vorlagen-Köpfen).
     */
    private static function bekannteDaten(): array
    {
        $spalten = [
            ['module', 'titel'],
            ['faecher', 'name'],
            ['lehrberufe', 'name'],
            ['kategorien', 'name'],
            ['pruefungen', 'titel'],
            ['noten_kommentare', 'kommentar_text'],
            ['lernende', 'klasse_schule'],
            ['lernende', 'klasse_bms'],
            ['benutzer', 'vorname'],
            ['benutzer', 'nachname'],
            ['benutzer', 'benutzername'],
        ];

        $werte = ['Muster AG'];
        foreach ($spalten as [$tabelle, $spalte]) {
            $werte = [...$werte, ...DB::table($tabelle)->whereNotNull($spalte)->distinct()->pluck($spalte)->all()];
        }

        $werte = [...$werte, ...array_keys(Einrichtung::LEHRBERUFE), ...array_values(Einrichtung::LEHRBERUFE)];
        foreach (Einrichtung::FAECHER as $liste) {
            $werte = [...$werte, ...array_values($liste)];
        }
        $werte[] = "431 Aufträge im IT-Umfeld selbstständig durchführen\n162 Daten analysieren und modellieren";
        $werte[] = "106 Datenbanken abfragen, bearbeiten und warten\n187 ICT-Arbeitsplatz in Betrieb nehmen";

        $werte = array_values(array_unique(array_filter($werte, fn ($w) => $w !== '')));
        usort($werte, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        return $werte;
    }

    private static function ohneBekannteDaten(string $text, array $bekannt): string
    {
        return $bekannt === [] ? $text : str_replace($bekannt, ' ', $text);
    }

    /**
     * Sichtbarer Text einer gerenderten Seite: script/style raus, Werte von aria-label/title/
     * placeholder/alt dazu, dann alle Tags weg und Entities dekodiert.
     */
    private static function sichtbarerText(string $html): string
    {
        $ohneScript = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#si', ' ', $html);

        $attributWerte = [];
        preg_match_all('/\s(?:aria-label|title|placeholder|alt)\s*=\s*"([^"]*)"/i', $ohneScript, $treffer);
        foreach ($treffer[1] as $wert) {
            $attributWerte[] = html_entity_decode($wert, ENT_QUOTES | ENT_HTML5);
        }

        $ohneTags = (string) preg_replace('/<[^>]+>/', ' ', $ohneScript);
        $text = html_entity_decode($ohneTags, ENT_QUOTES | ENT_HTML5);

        return $text.' '.implode(' ', $attributWerte);
    }

    /** Häufige deutsche Wörter, die in englischem UI-Text nicht als eigenständige Wörter vorkommen. */
    private const array DEUTSCHE_WOERTER = [
        'und', 'der', 'die', 'das', 'dem', 'den', 'des', 'mit', 'von', 'auf', 'bei', 'noch', 'schon',
        'sehr', 'heute', 'gestern', 'morgen', 'bitte', 'danke', 'oder', 'aber', 'wenn', 'dass', 'weil',
        'damit', 'sowie', 'jedoch', 'bereits', 'innerhalb', 'diese', 'dieser', 'dieses',
        'welche', 'welcher', 'einer', 'eines', 'einem', 'einen', 'eine', 'ein', 'nicht', 'kein',
        'keine', 'keinen', 'keiner', 'ist', 'sind', 'war', 'waren', 'wird', 'werden', 'wurde', 'wurden',
        'kann', 'können', 'muss', 'müssen', 'soll', 'sollen', 'darf', 'dürfen', 'haben',
        'Lernende', 'Lernender', 'Lernenden', 'Berufsbildner', 'Zeugnis', 'Zeugnisse', 'Benutzer',
        'Passwort', 'Anmelden', 'Abmelden', 'Speichern', 'Bearbeiten', 'Erstellen', 'Anlegen', 'Weiter',
        'Abbrechen', 'Aktualisieren', 'Dokument', 'Dokumente', 'Kommentar', 'Kommentare', 'Meldung',
        'Meldungen', 'Sprache', 'Einstellungen', 'Rolle', 'Rollen', 'Hinweis', 'Hinweise', 'Achtung',
        'Fehler', 'Kontakt', 'Zurück', 'Löschen', 'Übersicht', 'Noten',
    ];

    /** @return list<string> gefundene deutsche Wörter/Wortfragmente (dedupliziert) */
    private static function deutscheReste(string $text): array
    {
        $funde = [];

        if (preg_match_all('/\S*[äöüÄÖÜß]\S*/u', $text, $treffer)) {
            $funde = [...$funde, ...$treffer[0]];
        }

        $muster = '/\b(?:'.implode('|', self::DEUTSCHE_WOERTER).')\b/ui';
        if (preg_match_all($muster, $text, $treffer)) {
            $funde = [...$funde, ...$treffer[0]];
        }

        return array_values(array_unique(array_map(fn (string $w) => trim($w, '.,;:!?()[]«»„“'), $funde)));
    }
}
