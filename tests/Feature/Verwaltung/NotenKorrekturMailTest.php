<?php

declare(strict_types=1);

namespace Tests\Feature\Verwaltung;

use App\Models\Fach;
use App\Models\Note;
use App\Models\Semester;
use App\Models\User;
use App\Notifications\PortalMail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotenKorrekturMailTest extends TestCase
{
    use VerwaltungTestHilfen;

    #[Test]
    public function korrektur_mail_nennt_nach_fachwechsel_das_neue_fach(): void
    {
        Notification::fake();
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernende@firma.ch']);
        $lernender = $lernenderUser->lernender;
        $semester = Semester::factory()->create();
        $this->bmsTrack($lernender, $semester->semester_id);
        $alt = Fach::factory()->create(['track_typ' => 'BMS', 'name' => 'Fach Alt']);
        $neu = Fach::factory()->create(['track_typ' => 'BMS', 'name' => 'Fach Neu']);
        $note = Note::factory()->create([
            'lernender_id' => $lernender->lernender_id, 'semester_id' => $semester->semester_id,
            'fach_id' => $alt->fach_id, 'note_wert' => 4.5,
        ]);
        $bb = User::factory()->berufsbildner()->create();
        $this->betreue($bb, $lernender);

        $this->actingAs($bb)->put(route('trainer.learners.grades.update', [$lernender->lernender_id, $note->note_id]), [
            'typ' => 'fach', 'fach_id' => $neu->fach_id, 'pruefungsdatum' => now()->toDateString(),
            'note_wert' => 5.25, 'gewichtung_prozent' => 100,
        ])->assertSessionHasNoErrors();

        $this->assertSame($neu->fach_id, (int) $note->fresh()->fach_id);
        Notification::assertSentTo($lernenderUser, PortalMail::class, function (PortalMail $mail) {
            return str_contains($mail->content->subject, 'Fach Neu') && ! str_contains($mail->content->subject, 'Fach Alt');
        });
    }
}
