<?php

declare(strict_types=1);

namespace Tests\Feature\Benachrichtigungen;

use App\Models\DigestItem;
use App\Models\Fach;
use App\Models\MailLog;
use App\Models\Note;
use App\Models\NotificationPolicy;
use App\Models\NotificationPreference;
use App\Models\Pruefung;
use App\Models\User;
use App\Notifications\PortalMail;
use App\Services\Auswertung\Konfiguration;
use App\Services\Notifications\NotificationCatalog;
use App\Support\Betrieb;
use App\Support\Einstellungen;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Befunde der Funktionsprüfung (Gruppe «benachrichtigungen»): Schwelle, Zählen, Pflichtanlass, inaktive Konten, Digest, Merker. */
class BenachrichtigungenFunktionspruefungTest extends TestCase
{
    #[Test]
    public function inaktivitaetsmail_folgt_der_frist_aus_dem_betrieb(): void
    {
        Notification::fake();
        Einstellungen::set(Einstellungen::FRIST_INAKTIV_TAGE, '14');
        $user = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        $id = $user->lernender->lernender_id;
        DB::table('lernende')->where('lernender_id', $id)->update(['lehrbeginn' => now()->subYear()->toDateString(), 'lehrende' => null]);
        Note::factory()->create(['lernender_id' => $id, 'erstellt_am' => now()->subDays(20), 'pruefungsdatum' => now()->subDays(20)->toDateString()]);

        $this->assertSame(14, Betrieb::fristInaktivTage());
        $this->artisan('notifications:check');

        $this->assertSame(1, Notification::sent($user, PortalMail::class)->filter(fn (PortalMail $m) => $m->type === NotificationCatalog::INACTIVITY)->count());
    }

    #[Test]
    public function inaktivitaet_hat_im_katalog_keine_zweite_schwelle(): void
    {
        $params = NotificationCatalog::get(NotificationCatalog::INACTIVITY)['params'];

        $this->assertArrayNotHasKey('days', $params);
        $this->assertArrayHasKey('repeat_days', $params);
    }

    #[Test]
    public function nicht_zaehlendes_fach_loest_keine_mail_neu_ungenuegend_aus(): void
    {
        $this->assertSame([], $this->notiereNote3(zaehlt: 0));
    }

    #[Test]
    public function zaehlendes_fach_loest_die_mail_neu_ungenuegend_aus(): void
    {
        $this->assertContains('below_threshold', array_column($this->notiereNote3(zaehlt: 1), 'type'));
    }

    /** @return list<array<string, mixed>> Mailprotokoll nach einer Note 3.0 in einem Fach mit zaehlt = $zaehlt */
    private function notiereNote3(int $zaehlt): array
    {
        $kategorie = DB::table('kategorien')->where('code', 'FACH')->value('kategorie_id');
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'W'.$zaehlt, 'name' => 'Testberuf EFZ '.$zaehlt]);
        $fach = DB::table('faecher')->insertGetId(['kategorie_id' => $kategorie, 'name' => 'Testfach '.$zaehlt, 'kurzname' => 'TF'.$zaehlt, 'zaehlt' => $zaehlt]);
        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $lehrberuf, 'fach_id' => $fach]);
        DB::table('semester')->insert(['bezeichnung' => 'S1', 'start_datum' => '2026-01-01', 'end_datum' => '2026-12-31', 'sortierung' => 1]);
        Konfiguration::vergessen();
        $user = User::factory()->lernender(['lehrberuf_id' => $lehrberuf, 'lehrbeginn' => '2025-08-01'])->create();
        Notification::fake();
        MailLog::query()->delete();

        $this->actingAs($user)->post(route('learner.grades.store'), [
            'typ' => 'fach', 'fach_id' => $fach, 'pruefungsdatum' => '2026-03-02', 'note_wert' => '3', 'gewichtung_prozent' => 100,
        ])->assertSessionHasNoErrors();

        return MailLog::query()->get(['type', 'subject'])->toArray();
    }

    #[Test]
    public function one_click_abbestellen_eines_pflichtanlasses_meldet_keinen_erfolg(): void
    {
        $user = User::factory()->lernender()->create();
        $url = URL::signedRoute('notifications.unsubscribe', ['user' => $user->benutzer_id, 'type' => NotificationCatalog::PASSWORD_CHANGED]);

        $antwort = $this->call('POST', $url, content: 'List-Unsubscribe=One-Click');

        $antwort->assertStatus(409);
        $this->assertSame(__('Nicht abbestellbar.'), $antwort->getContent());
        $this->assertDatabaseCount('notification_preferences', 0);
    }

    #[Test]
    public function abbestellen_im_browser_zeigt_bei_pflichtanlass_die_pflichtansicht(): void
    {
        $user = User::factory()->lernender()->create();
        $url = URL::signedRoute('notifications.unsubscribe', ['user' => $user->benutzer_id, 'type' => NotificationCatalog::PASSWORD_CHANGED]);

        $this->post($url)->assertOk()
            ->assertSee('lässt sich nicht abbestellen')
            ->assertDontSee('Du erhältst keine Mails mehr');
        $this->assertDatabaseCount('notification_preferences', 0);
    }

    #[Test]
    public function abbestellen_nach_nachtraeglicher_pflicht_meldet_keinen_erfolg(): void
    {
        $user = User::factory()->lernender()->create();
        NotificationPolicy::updateOrCreate(['type' => NotificationCatalog::COMMENT_ADDED], ['mandatory' => true, 'frequency' => 'immediate']);
        NotificationCatalog::forget();
        $url = URL::signedRoute('notifications.unsubscribe', ['user' => $user->benutzer_id, 'type' => NotificationCatalog::COMMENT_ADDED]);

        $this->call('POST', $url, content: 'List-Unsubscribe=One-Click')->assertStatus(409);
        $this->post($url)->assertOk()->assertDontSee('Abbestellt –');
        $this->assertDatabaseCount('notification_preferences', 0);
    }

    #[Test]
    public function passwort_durch_den_admin_schickt_an_ein_deaktiviertes_konto_keine_mail(): void
    {
        $admin = User::factory()->admin()->create();
        $ehemaliger = User::factory()->berufsbildner()->create(['aktiv' => false, 'email' => 'ehemaliger@firma.ch']);

        $this->actingAs($admin)->put(route('admin.users.update', $ehemaliger->benutzer_id), [
            'vorname' => $ehemaliger->vorname, 'nachname' => $ehemaliger->nachname, 'email' => $ehemaliger->email,
            'benutzername' => $ehemaliger->benutzername, 'rollen' => ['Berufsbildner'],
            'passwort' => 'Neues-Passwort-12345', 'passwort_confirmation' => 'Neues-Passwort-12345',
        ])->assertSessionHas('success');

        $this->assertSame(0, MailLog::where('recipient', 'ehemaliger@firma.ch')->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', 'ehemaliger@firma.ch')->count());
    }

    #[Test]
    public function tageszusammenfassung_laesst_inzwischen_abbestellte_anlaesse_weg(): void
    {
        $user = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        DigestItem::create(['user_id' => $user->benutzer_id, 'type' => 'grade_seen', 'title' => 'ABGEMELDET-ANLASS', 'body' => null, 'url' => 'http://x']);
        DigestItem::create(['user_id' => $user->benutzer_id, 'type' => 'grade_corrected', 'title' => 'BLEIBT-ANLASS', 'body' => null, 'url' => 'http://x']);
        NotificationPreference::create(['user_id' => $user->benutzer_id, 'type' => 'grade_seen', 'frequency' => 'never']);

        $this->artisan('notifications:digest')->assertSuccessful();

        $inhalt = $this->digestInhalt($user);
        $this->assertStringNotContainsString('ABGEMELDET-ANLASS', $inhalt);
        $this->assertStringContainsString('BLEIBT-ANLASS', $inhalt);
        $this->assertSame(0, DigestItem::whereNull('sent_at')->count(), 'Verworfene Einträge dürfen nicht im nächsten Lauf wieder auftauchen');
    }

    #[Test]
    public function tageszusammenfassung_laesst_vom_admin_deaktivierte_anlaesse_weg_und_schickt_ohne_rest_nichts(): void
    {
        $user = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        DigestItem::create(['user_id' => $user->benutzer_id, 'type' => 'grade_seen', 'title' => 'DEAKTIVIERT-ANLASS', 'body' => null, 'url' => 'http://x']);
        NotificationPolicy::create(['type' => 'grade_seen', 'enabled' => false, 'mandatory' => false, 'frequency' => 'daily']);
        NotificationCatalog::forget();

        $this->artisan('notifications:digest')->assertSuccessful();

        $this->assertSame(0, MailLog::where('user_id', $user->benutzer_id)->where('type', 'daily_digest')->count());
        $this->assertSame(0, DigestItem::whereNull('sent_at')->count());
    }

    private function digestInhalt(User $user): string
    {
        return MailLog::where('user_id', $user->benutzer_id)->where('type', 'daily_digest')->get()
            ->map(fn (MailLog $m) => json_encode($m->payload, JSON_UNESCAPED_UNICODE))->implode('');
    }

    #[Test]
    public function pruefungserinnerung_kommt_nach_dem_wiedereinschalten_des_anlasses_nach(): void
    {
        Notification::fake();
        $user = $this->lernenderMitPruefungIn2Tagen();
        $typ = NotificationCatalog::EXAM_REMINDER;

        NotificationPolicy::updateOrCreate(['type' => $typ], ['enabled' => false]);
        NotificationCatalog::forget();
        $this->artisan('notifications:check');
        $this->assertSame(0, DB::table('notification_marks')->where('type', $typ)->count(), 'Ausgeschaltet darf kein Merker entstehen');

        NotificationPolicy::where('type', $typ)->update(['enabled' => true]);
        NotificationCatalog::forget();
        $this->artisan('notifications:check');
        $this->artisan('notifications:check');

        $this->assertSame(1, DB::table('mail_log')->where('type', $typ)->where('user_id', $user->benutzer_id)->count());
    }

    #[Test]
    public function pruefungserinnerung_kommt_nach_dem_zurueckstellen_von_nie_nach(): void
    {
        Notification::fake();
        $user = $this->lernenderMitPruefungIn2Tagen();
        $typ = NotificationCatalog::EXAM_REMINDER;

        NotificationPreference::create(['user_id' => $user->benutzer_id, 'type' => $typ, 'frequency' => 'never']);
        $this->artisan('notifications:check');
        $this->assertSame(0, DB::table('mail_log')->where('type', $typ)->count());

        NotificationPreference::where('user_id', $user->benutzer_id)->where('type', $typ)->update(['frequency' => 'immediate']);
        $this->artisan('notifications:check');

        $this->assertSame(1, DB::table('mail_log')->where('type', $typ)->where('user_id', $user->benutzer_id)->count());
    }

    private function lernenderMitPruefungIn2Tagen(): User
    {
        $user = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        Pruefung::create([
            'lernender_id' => $user->lernender->lernender_id, 'fach_id' => Fach::factory()->create()->fach_id, 'titel' => 'Testat',
            'datum' => today()->addDays(2)->toDateString(), 'gewichtung_prozent' => 50,
        ]);

        return $user;
    }
}
