<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use App\Http\Middleware\SetLocale;
use App\Models\Fach;
use App\Models\Feedback;
use App\Models\MailLog;
use App\Models\Note;
use App\Models\Semester;
use App\Models\User;
use App\Services\Notifications\AccountMails;
use App\Services\Notifications\GradeWatcher;
use App\Services\Notifications\Messages\FeedbackReceived;
use App\Services\Notifications\NotificationCatalog;
use App\Support\Einstellungen;
use Database\Seeders\BasisSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

/** Labels, Rollen und optionale Zeilen der Kontomails und der Unterschreitungsmail stimmen mit der Sprache des Empfängers überein. */
class MailSpracheEmpfaengerTest extends TestCase
{
    use VerwaltungTestHilfen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BasisSeeder::class);
        Einstellungen::set(SetLocale::WAHL_AKTIV, '1');
    }

    #[Test]
    public function unterschreitungsmail_baut_schnitt_und_semester_in_der_empfaengersprache(): void
    {
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch', 'locale' => 'de']);
        $lernender = $lernenderUser->lernender;
        $semester = Semester::factory()->create();
        $fach = Fach::factory()->create(['name' => 'Mathematik', 'kurzname' => 'Mathematik']);
        $bb = User::factory()->berufsbildner()->create(['email' => 'bb@firma.ch', 'locale' => 'en']);
        $this->betreue($bb, $lernender);

        // Der Auslöser arbeitet deutsch, der Berufsbildner liest englisch.
        app()->setLocale('de');
        $watcher = new GradeWatcher;
        $vorher = $watcher->schnappschuss($lernender->lernender_id);
        Note::factory()->create([
            'lernender_id' => $lernender->lernender_id,
            'fach_id' => $fach->fach_id,
            'semester_id' => $semester->semester_id,
            'note_wert' => 3.0,
            'pruefungsdatum' => now()->subDays(2)->toDateString(),
        ]);
        $watcher->pruefen($lernender->lernender_id, $vorher);

        $enMail = MailLog::where('user_id', $bb->benutzer_id)->where('type', NotificationCatalog::BELOW_THRESHOLD)->sole();
        $enText = json_encode($enMail->payload, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Semester average', $enText);
        $this->assertStringNotContainsString('Semesterschnitt', $enText);
        $this->assertStringNotContainsString('1. Semester', $enText);

        $deMail = MailLog::where('user_id', $lernenderUser->benutzer_id)->where('type', NotificationCatalog::BELOW_THRESHOLD)->sole();
        $deText = json_encode($deMail->payload, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Semesterschnitt', $deText);
    }

    #[Test]
    public function rollenname_erscheint_in_der_sprache_des_empfaengers(): void
    {
        app()->setLocale('en');
        $melder = User::factory()->lernender()->create(['vorname' => 'Nina', 'nachname' => 'Huber']);
        $feedback = Feedback::factory()->create(['benutzer_id' => $melder->benutzer_id]);

        $zeile = FeedbackReceived::content($feedback, $melder)->lines[0];
        $this->assertStringContainsString('(Apprentice)', $zeile);
        $this->assertStringNotContainsString('Lernender', $zeile);

        $neu = User::factory()->berufsbildner()->create(['email' => 'neu@firma.ch', 'locale' => 'en']);
        AccountMails::accountCreated($neu);
        $facts = MailLog::where('user_id', $neu->benutzer_id)->where('type', NotificationCatalog::ACCOUNT_CREATED)->sole()->payload;
        $this->assertStringContainsString('Trainer', json_encode($facts, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('Berufsbildner', json_encode($facts, JSON_UNESCAPED_UNICODE));
    }

    #[Test]
    public function kontomail_laesst_die_betriebszeile_ohne_betriebsnamen_weg(): void
    {
        Einstellungen::set(Einstellungen::BETRIEB_NAME, '');
        $neu = User::factory()->lernender()->create(['email' => 'neu@firma.ch']);
        AccountMails::accountCreated($neu);
        $payload = MailLog::where('user_id', $neu->benutzer_id)->where('type', NotificationCatalog::ACCOUNT_CREATED)->sole()->payload;
        $this->assertStringNotContainsString('Betrieb', json_encode($payload, JSON_UNESCAPED_UNICODE));

        Einstellungen::set(Einstellungen::BETRIEB_NAME, 'Muster AG');
        $zweiter = User::factory()->lernender()->create(['email' => 'zweiter@firma.ch']);
        AccountMails::accountCreated($zweiter);
        $payload = MailLog::where('user_id', $zweiter->benutzer_id)->where('type', NotificationCatalog::ACCOUNT_CREATED)->sole()->payload;
        $this->assertStringContainsString('Muster AG', json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
}
