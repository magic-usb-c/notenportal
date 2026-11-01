<?php

declare(strict_types=1);

namespace Tests\Feature\Benachrichtigungen;

use App\Models\Fach;
use App\Models\Note;
use App\Models\Semester;
use App\Models\User;
use App\Notifications\PortalMail;
use App\Services\Notifications\Empfaenger;
use App\Services\Notifications\NotificationCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

/** Funktionsprüfung Runde 15, Gruppe «jobs»: Semesterabschluss-Mail an Betreuer (Stichtag, Nachzügler). Testadressen enden auf «.ch». */
class JobsBefundeRunde15Test extends TestCase
{
    use VerwaltungTestHilfen;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function betreuer_dessen_betreuung_am_semesterende_auslief_bekommt_den_abschluss(): void
    {
        Notification::fake();
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        $bb = User::factory()->berufsbildner()->create(['email' => 'bb@firma.ch']);
        $ende = today()->subDay();
        $this->betreue($bb, $lernenderUser->lernender)->update(['gueltig_bis' => $ende->toDateString()]);
        $semester = Semester::factory()->create(['start_datum' => today()->subMonths(6)->toDateString(), 'end_datum' => $ende->toDateString()]);
        Note::factory()->create([
            'lernender_id' => $lernenderUser->lernender->lernender_id, 'semester_id' => $semester->semester_id,
            'fach_id' => Fach::factory()->create()->fach_id, 'note_wert' => 3.5,
        ]);

        $this->artisan('notifications:check');

        Notification::assertSentTo($lernenderUser, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::SEMESTER_CLOSED);
        Notification::assertSentTo($bb, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::SEMESTER_CLOSED);
    }

    #[Test]
    public function aktive_betreuer_ohne_stichtag_kennt_nur_heutige_betreuungen(): void
    {
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        $bb = User::factory()->berufsbildner()->create(['email' => 'bb@firma.ch']);
        $gestern = today()->subDay();
        $this->betreue($bb, $lernenderUser->lernender)->update(['gueltig_bis' => $gestern->toDateString()]);
        $id = (int) $lernenderUser->lernender->lernender_id;

        $this->assertCount(0, Empfaenger::aktiveBetreuer($id));
        $this->assertCount(1, Empfaenger::aktiveBetreuer($id, $gestern));
        $this->assertCount(0, Empfaenger::aktiveBetreuer($id, $gestern->copy()->addDays(2)));
    }

    #[Test]
    public function nachzuegler_im_siebentagefenster_bekommt_der_betreuer_nachgemeldet(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-02-01 06:30', 'Europe/Zurich'));
        Notification::fake();
        $u1 = User::factory()->lernender()->create(['email' => 'erste@firma.ch']);
        $u2 = User::factory()->lernender()->create(['email' => 'zweite@firma.ch']);
        $bb = User::factory()->berufsbildner()->create(['email' => 'bb@firma.ch']);
        $this->betreue($bb, $u1->lernender);
        $this->betreue($bb, $u2->lernender);
        $semester = Semester::factory()->create(['start_datum' => '2026-08-01', 'end_datum' => '2027-01-31']);
        $fach = Fach::factory()->create();
        Note::factory()->create(['lernender_id' => $u1->lernender->lernender_id, 'semester_id' => $semester->semester_id, 'fach_id' => $fach->fach_id, 'note_wert' => 4.5, 'pruefungsdatum' => '2027-01-10']);

        $this->artisan('notifications:check');

        Carbon::setTestNow(Carbon::parse('2027-02-04 06:30', 'Europe/Zurich'));
        Note::factory()->create(['lernender_id' => $u2->lernender->lernender_id, 'semester_id' => $semester->semester_id, 'fach_id' => $fach->fach_id, 'note_wert' => 3.0, 'pruefungsdatum' => '2027-01-20']);

        $this->artisan('notifications:check');
        $this->artisan('notifications:check');

        $mails = DB::table('mail_log')->where('type', NotificationCatalog::SEMESTER_CLOSED)->where('recipient', 'bb@firma.ch')->get();
        $this->assertCount(2, $mails, 'erste Sammelmail plus kurze Nachmeldung, kein dritter Versand');
        $name = fn (User $u) => trim($u->vorname.' '.$u->nachname);
        $nutzlast = fn (object $zeile) => json_decode((string) $zeile->payload, true)['table']['rows'];
        $this->assertSame([$name($u1)], array_column($nutzlast($mails->first()), 0));
        $this->assertSame([$name($u2)], array_column($nutzlast($mails->last()), 0), 'die Nachmeldung nennt nur den Nachzügler');
    }
}
