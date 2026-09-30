<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use App\Http\Middleware\SetLocale;
use App\Models\Fach;
use App\Models\Feedback;
use App\Models\Lernender;
use App\Models\Note;
use App\Models\Pruefung;
use App\Models\Semester;
use App\Models\User;
use App\Services\Auswertung\NotenQuelle;
use App\Services\Notifications\MailContent;
use App\Services\Notifications\Messages\BackupFailed;
use App\Services\Notifications\Messages\BelowThreshold;
use App\Services\Notifications\Messages\CommentAdded;
use App\Services\Notifications\Messages\ExamReminder;
use App\Services\Notifications\Messages\FeedbackAnswered;
use App\Services\Notifications\Messages\FeedbackReceived;
use App\Services\Notifications\Messages\GradeAdded;
use App\Services\Notifications\Messages\GradeCorrected;
use App\Services\Notifications\Messages\GradeSeen;
use App\Services\Notifications\Messages\Inactivity;
use App\Services\Notifications\Messages\LearnerAssigned;
use App\Services\Notifications\Messages\LearnerAtRisk;
use App\Services\Notifications\Messages\SemesterClosed;
use App\Services\Notifications\Messages\SemesterEnding;
use App\Support\Einstellungen;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Admin-Bereich und Transaktions-Mails auf Englisch: Seiten und Mail-Inhalte werden mit
 * app()->setLocale('en') bzw. App::setLocale('en') gerendert. Jeder dabei verwendete
 * literale Schlüssel aus DEINE-DATEIEN (routes/web.php admin.*, resources/views/admin/**,
 * resources/views/mail/**, app/Http/Controllers/Admin/**, app/Services/Notifications/Messages/**,
 * NotificationCatalog.php, PortalMail.php) muss eine EN-Übersetzung in lang/areas/admin/en.json haben.
 */
class AdminEnglischTest extends TestCase
{
    /** Pfadpräfixe, für die diese Klasse 0 fehlende Schlüssel verlangt (kein offen.php-Rabatt). */
    private const array MEIN_BEREICH = [
        'resources/views/dashboards/admin.blade.php',
        'resources/views/admin/',
        'resources/views/mail/',
        'app/Http/Controllers/Admin/',
        'app/Services/Notifications/Messages/',
        'app/Services/Notifications/NotificationCatalog.php',
        'app/Notifications/PortalMail.php',
    ];

    /** @var array<string, true> */
    private array $fehlend = [];

    protected function setUp(): void
    {
        parent::setUp();

        // AppServiceProvider registriert lang/areas/admin bereits global; zur Sicherheit hier erneut
        // (doppelte Pfade sind unschädlich, Laravel dedupliziert beim Laden der Dateien).
        app('translator')->addJsonPath(lang_path('areas/admin'));

        Carbon::setTestNow('2026-09-08 10:00:00');
        (new DemoSeeder)->run();
        Einstellungen::set(SetLocale::WAHL_AKTIV, '1');

        Lang::handleMissingKeysUsing(function (string $schluessel, array $ersetzungen, ?string $locale) {
            if ($locale === 'en') {
                $this->fehlend[$schluessel] = true;
            }

            return $schluessel;
        });
    }

    protected function tearDown(): void
    {
        Lang::handleMissingKeysUsing(null);
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::where('email', 'laura.frei@demo.example')->firstOrFail();
    }

    private function assertKeineFehlendenSchluessel(): void
    {
        $verwendet = Schluessel::verwendet();
        $verboten = [];

        foreach (array_keys($this->fehlend) as $schluessel) {
            $dateien = $verwendet[$schluessel] ?? [];
            $inMeinemBereich = array_filter($dateien, fn (string $datei) => $this->istMeinBereich($datei));

            // Schlüssel, die nur dynamisch gebaut werden (z. B. __($variable)), tauchen nicht in
            // Schluessel::verwendet() auf; dann zählt ihr Auftreten in dieser Testklasse selbst.
            if ($dateien === [] && $inMeinemBereich === []) {
                $verboten[] = "«{$schluessel}» (dynamisch oder unbekannte Datei)";

                continue;
            }

            if ($inMeinemBereich !== []) {
                $verboten[] = "«{$schluessel}» (".implode(', ', $inMeinemBereich).')';
            }
        }

        $this->assertSame([], $verboten, "EN-Übersetzung fehlt:\n".implode("\n", $verboten));
    }

    private function istMeinBereich(string $datei): bool
    {
        foreach (self::MEIN_BEREICH as $praefix) {
            if (str_starts_with($datei, $praefix)) {
                return true;
            }
        }

        return false;
    }

    /** App::withLocale() gibt es in diesem Laravel nicht – Locale manuell setzen und zurücksetzen. */
    private function mitEnglisch(callable $fn): mixed
    {
        $vorher = App::getLocale();
        App::setLocale('en');

        try {
            return $fn();
        } finally {
            App::setLocale($vorher);
        }
    }

    // ---------------------------------------------------------------------
    // Seiten
    // ---------------------------------------------------------------------

    #[Test]
    public function admin_seiten_auf_englisch(): void
    {
        $admin = $this->admin();
        $admin->update(['locale' => 'en']);

        $seiten = [
            route('admin.dashboard'),
            route('admin.learners.index'),
            route('admin.users.index'),
            route('admin.operations.edit'),
            route('admin.master-data.modules.index'),
            route('admin.feedback.index'),
            route('admin.setup', 'operations'),
        ];

        foreach ($seiten as $url) {
            $this->actingAs($admin)->get($url)
                ->assertOk()
                ->assertSee('<html lang="en"', false);
        }

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertSee('Master data')
            ->assertSee('Overview');

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertSee('Add user');

        $this->assertKeineFehlendenSchluessel();
    }

    // ---------------------------------------------------------------------
    // Mails: Messages/** direkt, mit App::setLocale('en')
    // ---------------------------------------------------------------------

    private function lernender(string $email): Lernender
    {
        return Lernender::whereHas('benutzer', fn ($q) => $q->where('email', $email))->firstOrFail();
    }

    #[Test]
    public function mail_inhalte_auf_englisch(): void
    {
        $nina = $this->lernender('nina.huber@demo.example');
        $note = Note::where('lernender_id', $nina->lernender_id)->whereNull('geloescht_am')->firstOrFail();
        $semester = Semester::where('semester_id', $note->semester_id)->firstOrFail();
        $autor = User::where('email', 'michael.baumann@demo.example')->firstOrFail();
        $fach = $note->fach_id ? Fach::find($note->fach_id) : Fach::query()->firstOrFail();

        $pruefung = Pruefung::create([
            'lernender_id' => $nina->lernender_id,
            'fach_id' => $fach->fach_id,
            'titel' => 'Testprüfung',
            'datum' => Carbon::now()->addDays(5)->toDateString(),
            'uhrzeit' => true,
            'pruefungsart' => 'schriftlich',
            'hilfsmittel' => 'Taschenrechner',
            'stoff' => 'Kapitel 1-3',
            'notizen' => 'Ruhig bleiben',
            'dauer_minuten' => 45,
            'gewichtung_prozent' => 20,
            'quelle' => Pruefung::MANUELL,
        ]);

        $feedback = Feedback::factory()->create([
            'benutzer_id' => $nina->benutzer_id,
            'admin_notiz' => 'Danke für den Hinweis.',
        ]);

        $auswertung = (new NotenQuelle)->auswertung($nina->lernender_id);

        /** @var list<MailContent> $inhalte */
        $inhalte = $this->mitEnglisch(function () use ($nina, $note, $autor, $pruefung, $feedback, $semester, $auswertung) {
            return [
                GradeAdded::einzeln($nina, $note, '/x'),
                GradeAdded::sammel($nina, 3, '/x'),
                GradeCorrected::content($note, '4.0', '/x'),
                GradeSeen::einzeln($note, '/x'),
                GradeSeen::sammel(2, '/x'),
                CommentAdded::content($note, 'Gut gemacht!', $autor, '/x'),
                BelowThreshold::content($nina, [['label' => 'Mathematik', 'alt' => 4.5, 'neu' => 3.8]], true),
                BelowThreshold::content($nina, [['label' => 'Mathematik', 'alt' => 4.5, 'neu' => 3.8]], false),
                ExamReminder::content($pruefung),
                Inactivity::content($nina, 30),
                LearnerAssigned::content($nina),
                LearnerAtRisk::content($nina, ['Schnitt unter 4.0', 'Prüfung verpasst']),
                FeedbackReceived::content($feedback, $nina->benutzer),
                FeedbackAnswered::content($feedback),
                BackupFailed::content('Verbindung fehlgeschlagen'),
                SemesterClosed::betreuer($semester, [['lernender' => $nina, 'schnitt' => 4.2, 'ungenuegend' => 1]]),
                SemesterClosed::lernender($semester, $auswertung),
                SemesterEnding::content($semester, [$pruefung], []),
            ];
        });

        $gerendert = $this->mitEnglisch(function () use ($inhalte) {
            return array_map(fn (MailContent $c) => [
                'subject' => $c->subject,
                'html' => view('mail.portal', [
                    'c' => $c,
                    'vorname' => 'Nina',
                    'anlass' => 'Test',
                    'abmeldenUrl' => null,
                    'einstellungenUrl' => null,
                    'betrieb' => 'Demo AG',
                    'portalUrl' => url('/'),
                ])->render(),
                'text' => view('mail.portal-text', [
                    'c' => $c,
                    'vorname' => 'Nina',
                    'anlass' => 'Test',
                    'abmeldenUrl' => null,
                    'einstellungenUrl' => null,
                    'betrieb' => 'Demo AG',
                    'portalUrl' => url('/'),
                ])->render(),
            ], $inhalte);
        });

        $this->assertNotEmpty($gerendert);
        foreach ($gerendert as $mail) {
            $this->assertStringNotContainsString('ungültig', $mail['html']);
        }

        $subjects = array_column($gerendert, 'subject');
        $this->assertContains('New grade from '.trim($nina->benutzer->vorname.' '.$nina->benutzer->nachname).': '.$fach->name, $subjects);
        $this->assertContains('Backup failed', $subjects);

        $this->assertKeineFehlendenSchluessel();
    }
}
