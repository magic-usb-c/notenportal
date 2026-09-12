<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\Note;
use App\Models\Semester;
use App\Models\User;
use App\Services\Auswertung\NotenQuelle;
use App\Services\Notifications\AccountMails;
use App\Services\Notifications\MailContent;
use App\Services\Notifications\Messages\BackupFailed;
use App\Services\Notifications\Messages\BelowThreshold;
use App\Services\Notifications\Messages\CommentAdded;
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
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

/**
 * Prüft, dass jeder Link, der in einer Mail landet (MailContent::actionUrl), für seinen
 * Empfänger wirklich erreichbar ist (< 400) – Ausgangspunkt war ein gemeldeter 403 auf
 * einem Mail-Link. Baut MailContent direkt aus den Messages/*-Klassen mit einem
 * realistischen Empfänger je Rolle, statt echten Mailversand auszulösen.
 * Konto-Links (Passwort setzen/zurücksetzen) sind token-basiert und werden als Gast geprüft.
 */
class MailLinksTest extends TestCase
{
    use VerwaltungTestHilfen;

    #[Test]
    public function konto_links_sind_als_gast_erreichbar(): void
    {
        $lernender = User::factory()->lernender()->create();

        foreach ([
            'Konto eröffnet' => fn () => AccountMails::accountCreated($lernender),
            'Passwort-Reset durch Admin' => fn () => AccountMails::passwordResetByAdmin($lernender),
        ] as $label => $bauen) {
            $token = Password::broker('invites')->createToken($lernender);
            $url = route('password.reset', ['token' => $token, 'email' => $lernender->email]);

            $status = $this->get($this->pfad($url))->getStatusCode();
            $this->assertLessThan(400, $status, "[{$label}] {$url} -> {$status} (Gast)");
        }
    }

    #[Test]
    public function noten_und_kommentar_links_sind_fuer_den_empfaenger_erreichbar(): void
    {
        $lernenderUser = User::factory()->lernender()->create();
        $lernender = $lernenderUser->lernender;
        $trainer = User::factory()->berufsbildner()->create();
        $this->betreue($trainer, $lernender);

        $note = Note::factory()->create(['lernender_id' => $lernender->lernender_id]);

        $trainerZielUrl = route('trainer.learners.show', $lernender->lernender_id);
        $lernenderZielUrl = route('learner.grades.index', ['_open' => $note->note_id]);

        $faelle = [
            'GradeAdded::einzeln (an Trainer)' => [GradeAdded::einzeln($lernender, $note, $trainerZielUrl), $trainer],
            'GradeAdded::sammel (an Trainer)' => [GradeAdded::sammel($lernender, 3, $trainerZielUrl), $trainer],
            'CommentAdded (an Trainer)' => [CommentAdded::content($note, 'Bitte anschauen.', $lernenderUser, $trainerZielUrl), $trainer],
            'CommentAdded (an Lernenden)' => [CommentAdded::content($note, 'Antwort.', $trainer, $lernenderZielUrl), $lernenderUser],
            'GradeCorrected (an Lernenden)' => [GradeCorrected::content($note, '4.0', $lernenderZielUrl), $lernenderUser],
            'GradeSeen::einzeln (an Lernenden)' => [GradeSeen::einzeln($note, $lernenderZielUrl), $lernenderUser],
            'GradeSeen::sammel (an Lernenden)' => [GradeSeen::sammel(2, route('learner.grades.index')), $lernenderUser],
            'BelowThreshold fuerBetreuer (an Trainer)' => [
                BelowThreshold::content($lernender, [['label' => 'BMS', 'alt' => 4.5, 'neu' => 3.5]], fuerBetreuer: true), $trainer,
            ],
            'BelowThreshold (an Lernenden)' => [
                BelowThreshold::content($lernender, [['label' => 'BMS', 'alt' => 4.5, 'neu' => 3.5]], fuerBetreuer: false), $lernenderUser,
            ],
            'Inactivity (an Lernenden)' => [Inactivity::content($lernender, 30), $lernenderUser],
            'LearnerAssigned (an Trainer)' => [LearnerAssigned::content($lernender), $trainer],
            'LearnerAtRisk (an Trainer)' => [LearnerAtRisk::content($lernender, ['Schnitt unter 4.0']), $trainer],
        ];

        $this->pruefeAlle($faelle);
    }

    #[Test]
    public function semester_links_sind_fuer_den_empfaenger_erreichbar(): void
    {
        $lernenderUser = User::factory()->lernender()->create();
        $lernender = $lernenderUser->lernender;
        $trainer = User::factory()->berufsbildner()->create();
        $this->betreue($trainer, $lernender);

        $semester = Semester::factory()->create();
        $auswertung = (new NotenQuelle)->auswertung($lernender->lernender_id);

        $faelle = [
            'SemesterEnding (an Lernenden)' => [SemesterEnding::content($semester, [], []), $lernenderUser],
            'SemesterClosed::lernender (an Lernenden)' => [SemesterClosed::lernender($semester, $auswertung), $lernenderUser],
            'SemesterClosed::betreuer (an Trainer)' => [
                SemesterClosed::betreuer($semester, [['lernender' => $lernender, 'schnitt' => 4.5, 'ungenuegend' => 0]]), $trainer,
            ],
        ];

        $this->pruefeAlle($faelle);
    }

    #[Test]
    public function feedback_und_betrieb_links_sind_fuer_den_empfaenger_erreichbar(): void
    {
        $admin = User::factory()->admin()->create();
        $lernenderUser = User::factory()->lernender()->create();
        $trainer = User::factory()->berufsbildner()->create();

        $feedback = Feedback::factory()->create(['benutzer_id' => $lernenderUser->benutzer_id]);

        $faelle = [
            'FeedbackReceived (an Admin)' => [FeedbackReceived::content($feedback, $lernenderUser), $admin],
            'FeedbackAnswered (an Lernenden)' => [FeedbackAnswered::content($feedback), $lernenderUser],
            'FeedbackAnswered (an Trainer)' => [FeedbackAnswered::content($feedback), $trainer],
            'FeedbackAnswered (an Admin)' => [FeedbackAnswered::content($feedback), $admin],
        ];

        $this->pruefeAlle($faelle);

        // BackupFailed hat keinen actionUrl – nur sicherstellen, dass der Aufbau nicht crasht.
        $this->assertNull(BackupFailed::content('Testfehler')->actionUrl);
    }

    /** @param  array<string, array{0: MailContent, 1: User}>  $faelle */
    private function pruefeAlle(array $faelle): void
    {
        foreach ($faelle as $label => [$content, $empfaenger]) {
            if ($content->actionUrl === null) {
                continue;
            }
            $status = $this->actingAs($empfaenger)->get($this->pfad($content->actionUrl))->getStatusCode();
            $this->assertLessThan(400, $status, "[{$label}] {$content->actionUrl} -> {$status} für {$empfaenger->email}");
        }
    }

    private function pfad(string $url): string
    {
        $pfad = (string) parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);

        return $query ? "{$pfad}?{$query}" : $pfad;
    }
}
