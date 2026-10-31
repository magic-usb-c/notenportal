<?php

declare(strict_types=1);

namespace Tests\Feature\Benachrichtigungen;

use App\Models\Fach;
use App\Models\Lehrberuf;
use App\Models\MailLog;
use App\Models\Note;
use App\Models\Pruefung;
use App\Models\User;
use App\Services\Notifications\AccountMails;
use App\Services\Notifications\MailContent;
use App\Services\Notifications\Messages\CommentAdded;
use App\Services\Notifications\Messages\ExamReminder;
use App\Services\Notifications\Messages\GradeAdded;
use App\Services\Notifications\Messages\LearnerAssigned;
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
}
