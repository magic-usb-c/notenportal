<?php

declare(strict_types=1);

namespace Tests\Feature\Benachrichtigungen;

use App\Models\Fach;
use App\Models\Lehrberuf;
use App\Models\MailLog;
use App\Models\Note;
use App\Models\Pruefung;
use App\Models\Semester;
use App\Models\User;
use App\Services\Notifications\AccountMails;
use App\Services\Notifications\MailContent;
use App\Services\Notifications\Messages\CommentAdded;
use App\Services\Notifications\Messages\ExamReminder;
use App\Services\Notifications\Messages\GradeAdded;
use App\Services\Notifications\Messages\GradeCorrected;
use App\Services\Notifications\Messages\GradeSeen;
use App\Services\Notifications\Messages\Inactivity;
use App\Services\Notifications\Messages\LearnerAssigned;
use App\Services\Notifications\Messages\SemesterEnding;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Titel, erster Absatz und Fakten einer Mail ergänzen sich: dieselbe Angabe steht nicht zwei- oder dreimal untereinander. */
class MailTexteOhneWiederholungTest extends TestCase
{
    private function ohneDoppelung(MailContent $c): void
    {
        $this->assertNotSame($c->title ?? $c->subject, $c->lines[0], 'Erster Absatz wiederholt den Titel');
        $this->assertNotSame(rtrim($c->subject, '.'), rtrim($c->lines[0], '.'));
    }

    #[Test]
    public function importmail_wiederholt_den_betreff_nicht_und_nennt_den_zeitpunkt(): void
    {
        $lernender = User::factory()->lernender()->create(['vorname' => 'Nina', 'nachname' => 'Huber'])->lernender;
        $this->freezeTime();

        foreach ([1, 7] as $anzahl) {
            $c = GradeAdded::sammel($lernender, $anzahl, '/x');

            $this->ohneDoppelung($c);
            $this->assertStringNotContainsString($anzahl.' Note', $c->lines[0], 'Die Anzahl gehört in den Betreff');
            $this->assertSame(now()->format('d.m.Y, H:i'), $c->facts['Importiert am']);
        }
    }

    #[Test]
    public function admin_reset_mail_hat_eigenen_absatz_gueltigkeit_und_hinweis(): void
    {
        $user = User::factory()->lernender()->create();

        AccountMails::passwordResetByAdmin($user);

        $c = MailContent::fromArray(MailLog::where('user_id', $user->benutzer_id)->firstOrFail()->payload);
        $tage = intdiv((int) config('auth.passwords.invites.expire'), 1440);

        $this->ohneDoppelung($c);
        $this->assertSame($tage.' Tage', $c->facts['Gültig für']);
        $this->assertNotSame([], $c->outro);
    }

    #[Test]
    public function betreuungsmail_nennt_den_lehrberuf_nur_in_der_faktentabelle(): void
    {
        $lernender = User::factory()->lernender()->create()->lernender;
        $beruf = Lehrberuf::query()->findOrFail($lernender->lehrberuf_id)->name;

        $c = LearnerAssigned::content($lernender);

        $this->assertSame($beruf, $c->facts['Lehrberuf']);
        $this->assertStringNotContainsString($beruf, $c->lines[0]);

        $c = LearnerAssigned::content($lernender, today()->addDays(10));
        $this->assertStringNotContainsString($beruf, $c->lines[0]);
    }

    #[Test]
    public function kommentarmail_nennt_fach_und_datum_nur_in_der_faktentabelle(): void
    {
        $lernender = User::factory()->lernender()->create()->lernender;
        $autor = User::factory()->berufsbildner()->create();
        $note = Note::factory()->create(['lernender_id' => $lernender->lernender_id]);

        $c = CommentAdded::content($note, 'Bitte anschauen.', $autor, '/x');

        $this->assertStringNotContainsString($c->facts['Fach / Modul'], $c->lines[0]);
        $this->assertStringNotContainsString($c->facts['Datum'], $c->lines[0]);
        $this->assertStringNotContainsString(':', $c->lines[0], 'Der Doppelpunkt führte in die Faktentabelle statt zum Kommentar');
    }

    #[Test]
    public function pruefungserinnerung_nennt_das_datum_nicht_im_absatz(): void
    {
        $lernender = User::factory()->lernender()->create()->lernender;
        $p = Pruefung::create([
            'lernender_id' => $lernender->lernender_id,
            'fach_id' => Fach::factory()->create()->fach_id,
            'titel' => 'LB',
            'datum' => now()->addWeek()->toDateString(),
            'quelle' => Pruefung::MANUELL,
        ]);

        $c = ExamReminder::content($p->fresh());

        $this->ohneDoppelung($c);
        $datum = $p->datum->format('d.m.Y');
        $this->assertStringContainsString($datum, $c->subject);
        $this->assertStringNotContainsString($datum, $c->lines[0]);
        $this->assertSame($datum, $c->facts['Datum']);
    }

    #[Test]
    public function korrekturmail_nennt_fach_und_datum_nur_in_der_faktentabelle(): void
    {
        $lernender = User::factory()->lernender()->create()->lernender;
        $note = Note::factory()->create(['lernender_id' => $lernender->lernender_id]);

        $c = GradeCorrected::content($note, '4.0', '/x');

        $this->ohneDoppelung($c);
        $this->assertStringNotContainsString($c->facts['Fach / Modul'], $c->lines[0]);
        $this->assertStringNotContainsString($c->facts['Datum'], $c->lines[0]);
        $this->assertSame('Deine Note wurde nachträglich geändert.', $c->lines[0]);
    }

    #[Test]
    public function gesehen_mail_nennt_fach_und_datum_nur_in_der_faktentabelle_und_den_namen_der_person(): void
    {
        $lernender = User::factory()->lernender()->create()->lernender;
        $note = Note::factory()->create(['lernender_id' => $lernender->lernender_id]);
        $bb = User::factory()->berufsbildner()->create(['vorname' => 'Peter', 'nachname' => 'Muster']);

        $c = GradeSeen::einzeln($note, '/x', $bb);

        $this->assertStringNotContainsString($c->facts['Fach / Modul'], $c->lines[0]);
        $this->assertStringNotContainsString($c->facts['Datum'], $c->lines[0]);
        $this->assertSame('Peter Muster hat die Note angesehen.', $c->lines[0]);
        $this->assertStringNotContainsString('Dein Berufsbildner', $c->lines[0], 'Das Geschlecht der Person ist unbekannt');

        $ohneName = GradeSeen::einzeln($note, '/x');
        $this->assertSame('Die Note wurde angesehen.', $ohneName->lines[0]);
    }

    #[Test]
    public function sammelmail_nennt_die_person_statt_dein_berufsbildner(): void
    {
        $admin = User::factory()->admin()->create(['vorname' => 'Laura', 'nachname' => 'Muster']);

        $this->assertSame('Laura Muster hat 1 Note als gesehen markiert.', GradeSeen::sammel(1, '/x', $admin)->lines[0]);
        $this->assertSame('Laura Muster hat 3 Noten als gesehen markiert.', GradeSeen::sammel(3, '/x', $admin)->lines[0]);
        $this->assertSame('3 Noten wurden als gesehen markiert.', GradeSeen::sammel(3, '/x')->lines[0]);
    }

    #[Test]
    public function inaktivitaetsmail_nennt_die_tage_nur_im_betreff(): void
    {
        $lernender = User::factory()->lernender()->create()->lernender;

        $c = Inactivity::content($lernender, 21);

        $this->assertStringContainsString('21', $c->subject);
        $this->assertStringNotContainsString('21', $c->lines[0]);
        $this->assertSame('21', preg_replace('/\D/', '', (string) $c->digestTitle), 'Die Tageszusammenfassung behält die Tageszahl');
    }

    #[Test]
    public function semesterendemail_nennt_semester_und_ende_nur_in_titel_und_fakten(): void
    {
        $semester = Semester::factory()->create();

        $c = SemesterEnding::content($semester, [], []);

        $this->assertStringNotContainsString($c->facts['Semester'], $c->lines[0]);
        $this->assertStringNotContainsString($c->facts['Ende'], $c->lines[0]);
        $this->assertStringContainsString($c->facts['Semester'], (string) $c->digestTitle, 'Die Tageszusammenfassung behält das Semester');
    }
}
