<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Console\Commands\AktivitaetenAufraeumen;
use App\Models\Aktivitaet;
use App\Models\Lehrberuf;
use App\Models\Note;
use App\Models\Rolle;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Betrieb\Sicherung;
use App\Support\Protokoll;
use Database\Factories\UserFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

/**
 * Block AB: Aktivitätsprotokoll (Audit-Log). Prüft, dass jedes im Auftrag genannte Ereignis
 * tatsächlich geschrieben wird, die Sperrliste Geheimnisse nie durchlässt, die Seite nur für
 * Admins erreichbar ist, Filter/Paginierung funktionieren, das Aufräum-Kommando alte Einträge
 * löscht und – analog zu ThemeTest::ohne_erreichbare_datenbank… – nichts bricht, solange die
 * Tabelle (vor der Migration auf Prod) noch nicht existiert.
 */
class AktivitaetsprotokollTest extends TestCase
{
    private function letzterEintrag(): Aktivitaet
    {
        return Aktivitaet::orderByDesc('id')->firstOrFail();
    }

    // -----------------------------------------------------------------------------------------
    // Anmeldung (Listener)
    // -----------------------------------------------------------------------------------------

    #[Test]
    public function erfolgreiche_anmeldung_wird_protokolliert(): void
    {
        $user = User::factory()->lernender()->create();

        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::PASSWORT])
            ->assertRedirect('/dashboard');

        $eintrag = $this->letzterEintrag();
        $this->assertSame(Protokoll::AUTH_ANMELDUNG_ERFOLGREICH, $eintrag->aktion);
        $this->assertSame($user->benutzer_id, $eintrag->benutzer_id);
    }

    #[Test]
    public function fehlgeschlagene_anmeldung_bei_bestehendem_konto_verlinkt_das_konto(): void
    {
        $user = User::factory()->lernender()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'falsches-passwort']);

        $eintrag = $this->letzterEintrag();
        $this->assertSame(Protokoll::AUTH_ANMELDUNG_FEHLGESCHLAGEN, $eintrag->aktion);
        // Niemand ist angemeldet (Anmeldung ist ja gescheitert) – benutzer_id (handelnde Person)
        // bleibt null, das getroffene Konto steht in ziel_id/ziel_bezeichnung.
        $this->assertNull($eintrag->benutzer_id);
        $this->assertSame($user->benutzer_id, $eintrag->ziel_id);
    }

    #[Test]
    public function fehlgeschlagene_anmeldung_bei_unbekanntem_konto_zeigt_nie_die_eingegebene_adresse(): void
    {
        $geheim = 'streng-geheime-adresse@beispiel.test';

        $this->post('/login', ['email' => $geheim, 'password' => 'irgendwas']);

        $eintrag = $this->letzterEintrag();
        $this->assertSame(Protokoll::AUTH_ANMELDUNG_FEHLGESCHLAGEN, $eintrag->aktion);
        $this->assertNull($eintrag->benutzer_id);
        $this->assertSame('unbekanntes Konto', $eintrag->details['konto'] ?? null);
        $this->assertStringNotContainsString($geheim, json_encode($eintrag->details));
    }

    #[Test]
    public function abmeldung_wird_protokolliert(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->post('/logout');

        $eintrag = $this->letzterEintrag();
        $this->assertSame(Protokoll::AUTH_ABMELDUNG, $eintrag->aktion);
        $this->assertSame($user->benutzer_id, $eintrag->benutzer_id);
    }

    #[Test]
    public function passwort_reset_wird_protokolliert(): void
    {
        $user = User::factory()->lernender()->create();
        $token = Password::broker('users')->createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NeuesPasswort!2027',
            'password_confirmation' => 'NeuesPasswort!2027',
        ])->assertRedirect(route('login'));

        $eintrag = $this->letzterEintrag();
        $this->assertSame(Protokoll::AUTH_PASSWORT_GEAENDERT, $eintrag->aktion);
        // Beim Zurücksetzen ist niemand angemeldet – benutzer_id (handelnde Person) bleibt null,
        // das betroffene Konto steht in ziel_id.
        $this->assertNull($eintrag->benutzer_id);
        $this->assertSame($user->benutzer_id, $eintrag->ziel_id);
    }

    // -----------------------------------------------------------------------------------------
    // Passwortänderung im Profil und Erstpasswort-Wechsel (kein Doppel-Eintrag mit LogPasswordReset,
    // das nur beim Broker-Reset über NewPasswordController::store() feuert)
    // -----------------------------------------------------------------------------------------

    #[Test]
    public function passwortaenderung_im_profil_wird_protokolliert_ohne_doppel_eintrag(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => UserFactory::PASSWORT,
            'password' => 'NeuesPasswort!2026',
            'password_confirmation' => 'NeuesPasswort!2026',
        ])->assertSessionHasNoErrors();

        $eintrag = $this->letzterEintrag();
        $this->assertSame(Protokoll::AUTH_PASSWORT_GEAENDERT, $eintrag->aktion);
        $this->assertSame($user->benutzer_id, $eintrag->benutzer_id);
        $this->assertSame(1, Aktivitaet::where('aktion', Protokoll::AUTH_PASSWORT_GEAENDERT)->count());
    }

    #[Test]
    public function erstpasswort_wechsel_wird_protokolliert_ohne_doppel_eintrag(): void
    {
        $user = User::factory()->lernender()->create(['passwort_wechsel_noetig' => true]);

        $this->actingAs($user)->put(route('password.initial.update'), [
            'password' => 'EigenesPasswort2026',
            'password_confirmation' => 'EigenesPasswort2026',
        ])->assertRedirect(route('dashboard'));

        $eintrag = $this->letzterEintrag();
        $this->assertSame(Protokoll::AUTH_PASSWORT_GEAENDERT, $eintrag->aktion);
        $this->assertSame($user->benutzer_id, $eintrag->benutzer_id);
        $this->assertSame(1, Aktivitaet::where('aktion', Protokoll::AUTH_PASSWORT_GEAENDERT)->count());
    }

    // -----------------------------------------------------------------------------------------
    // Admin: Konten
    // -----------------------------------------------------------------------------------------

    #[Test]
    public function admin_legt_konto_an_wird_protokolliert(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'vorname' => 'Bea',
            'nachname' => 'Bildnerin',
            'email' => 'bea@example.test',
            'benutzername' => 'bea',
            'passwort' => 'Sicher12345',
            'passwort_confirmation' => 'Sicher12345',
            'rolle_id' => Rolle::where('name', 'Berufsbildner')->value('rolle_id'),
        ])->assertSessionHasNoErrors();

        $eintrag = $this->letzterEintrag();
        $this->assertSame(Protokoll::ADMIN_KONTO_ANGELEGT, $eintrag->aktion);
        $this->assertSame($admin->benutzer_id, $eintrag->benutzer_id);
        $this->assertSame('bea', User::where('benutzername', 'bea')->value('benutzername'));
    }

    #[Test]
    public function lernenden_konto_anlage_ueber_die_verwaltung_wird_protokolliert(): void
    {
        $admin = User::factory()->admin()->create();
        $lehrberuf = Lehrberuf::factory()->create(['aktiv' => true]);

        $this->actingAs($admin)->post(route('admin.learners.store'), [
            'vorname' => 'Nina',
            'nachname' => 'Neuling',
            'email' => 'nina.neuling@example.test',
            'benutzername' => 'ninaneuling',
            'lehrberuf_id' => $lehrberuf->lehrberuf_id,
            'lehrbeginn' => '2025-08-01',
            'lehrende' => '2029-07-31',
        ])->assertSessionHasNoErrors();

        $eintrag = $this->letzterEintrag();
        $this->assertSame(Protokoll::ADMIN_KONTO_ANGELEGT, $eintrag->aktion);
        $this->assertSame(['rolle' => 'Lernender'], $eintrag->details);
    }

    #[Test]
    public function konto_anlage_in_der_ersteinrichtung_wird_protokolliert(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.setup.people'), ['personen' => [
            ['vorname' => 'Michael', 'nachname' => 'Baumann', 'email' => 'mb@betrieb.test', 'rolle' => 'Berufsbildner'],
        ]])->assertSessionHasNoErrors();

        $eintrag = $this->letzterEintrag();
        $this->assertSame(Protokoll::ADMIN_KONTO_ANGELEGT, $eintrag->aktion);
        $this->assertSame(['rolle' => 'Berufsbildner'], $eintrag->details);

        $lehrberuf = Lehrberuf::factory()->create(['aktiv' => true]);
        $this->actingAs($admin)->post(route('admin.setup.learners'), ['lernende' => [[
            'vorname' => 'Nina', 'nachname' => 'Huber', 'email' => 'nina@betrieb.test',
            'lehrberuf_id' => $lehrberuf->lehrberuf_id, 'lehrbeginn' => '2025-08-01',
        ]]])->assertSessionHasNoErrors();

        $eintragLernender = $this->letzterEintrag();
        $this->assertSame(Protokoll::ADMIN_KONTO_ANGELEGT, $eintragLernender->aktion);
        $this->assertSame(['rolle' => 'Lernender'], $eintragLernender->details);
    }

    #[Test]
    public function admin_aendert_rollen_wird_protokolliert(): void
    {
        $admin = User::factory()->admin()->create();
        $bb = User::factory()->berufsbildner()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $bb->benutzer_id), [
            'vorname' => $bb->vorname,
            'nachname' => $bb->nachname,
            'email' => $bb->email,
            'benutzername' => $bb->benutzername,
            'rollen' => ['Admin'],
        ])->assertSessionHasNoErrors();

        $eintrag = $this->letzterEintrag();
        $this->assertSame(Protokoll::ADMIN_ROLLEN_GEAENDERT, $eintrag->aktion);
        $this->assertSame(['Berufsbildner'], $eintrag->details['alt']);
        $this->assertSame(['Admin'], $eintrag->details['neu']);
    }

    #[Test]
    public function admin_reset_link_wird_protokolliert(): void
    {
        $admin = User::factory()->admin()->create();
        $bb = User::factory()->berufsbildner()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $bb->benutzer_id), [
            'vorname' => $bb->vorname,
            'nachname' => $bb->nachname,
            'email' => $bb->email,
            'benutzername' => $bb->benutzername,
            'rollen' => ['Berufsbildner'],
            'passwort' => 'NeuesSicher123',
            'passwort_confirmation' => 'NeuesSicher123',
        ])->assertSessionHasNoErrors();

        $eintrag = Aktivitaet::where('aktion', Protokoll::ADMIN_RESET_LINK_GESCHICKT)->sole();
        $this->assertSame($bb->benutzer_id, $eintrag->ziel_id);
        $this->assertStringNotContainsString('NeuesSicher123', json_encode($eintrag->toArray()));
    }

    #[Test]
    public function deaktivieren_und_reaktivieren_wird_protokolliert(): void
    {
        $admin = User::factory()->admin()->create();
        $bb = User::factory()->berufsbildner()->create();

        $this->actingAs($admin)->post(route('admin.users.toggle-active', $bb->benutzer_id));
        $this->assertSame(Protokoll::ADMIN_KONTO_DEAKTIVIERT, $this->letzterEintrag()->aktion);

        $this->actingAs($admin)->post(route('admin.users.toggle-active', $bb->benutzer_id));
        $this->assertSame(Protokoll::ADMIN_KONTO_REAKTIVIERT, $this->letzterEintrag()->aktion);
    }

    // -----------------------------------------------------------------------------------------
    // Admin: Betrieb, Mail, Sicherungen
    // -----------------------------------------------------------------------------------------

    #[Test]
    public function betrieb_geaendert_protokolliert_nur_feldnamen_nie_werte(): void
    {
        $admin = User::factory()->admin()->create();
        $werte = [
            'betrieb_name' => 'Geheime Beispiel AG',
            'note_gut' => '5', 'note_genuegend' => '4', 'note_kritisch' => '3.5',
            'rundung_gesamt' => '0.1', 'frist_inaktiv_tage' => 30, 'frist_lehrende_tage' => 60,
        ];

        $this->actingAs($admin)->put(route('admin.operations.update'), $werte)->assertSessionHasNoErrors();

        $eintrag = $this->letzterEintrag();
        $this->assertSame(Protokoll::ADMIN_BETRIEB_GEAENDERT, $eintrag->aktion);
        $this->assertSame(array_keys($werte), $eintrag->details['felder']);
        $this->assertStringNotContainsString('Geheime Beispiel AG', json_encode($eintrag->details));
    }

    #[Test]
    public function mail_einstellungen_geaendert_protokolliert_nur_feldnamen_nie_das_passwort(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.mail.update'), [
            'mail_host' => 'smtp.beispielbetrieb.ch',
            'mail_port' => '587',
            'mail_encryption' => 'tls',
            'mail_username' => 'versand@beispielbetrieb.ch',
            'mail_password' => 'GeheimesPasswort!2026',
            'mail_from_address' => 'noreply@beispielbetrieb.ch',
            'mail_from_name' => 'Notenportal Test-AG',
            'mail_redirect_to' => '',
        ])->assertSessionHasNoErrors();

        $eintrag = $this->letzterEintrag();
        $this->assertSame(Protokoll::ADMIN_BETRIEB_GEAENDERT, $eintrag->aktion);
        $this->assertContains('mail_password', $eintrag->details['felder']);
        $this->assertStringNotContainsString('GeheimesPasswort!2026', json_encode($eintrag->details));
    }

    #[Test]
    public function sicherung_erstellen_herunterladen_loeschen_wird_protokolliert(): void
    {
        Storage::fake('local');
        Process::fake(function (PendingProcess $prozess) {
            foreach ((array) $prozess->command as $teil) {
                if (str_starts_with((string) $teil, '--result-file=')) {
                    file_put_contents(substr((string) $teil, 14), str_repeat("-- Dump\n", 30));
                }
            }

            return Process::result();
        });
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.operations.backups.store'))->assertSessionHas('success');
        $this->assertSame(Protokoll::ADMIN_SICHERUNG_ERSTELLT, $this->letzterEintrag()->aktion);

        $name = app(Sicherung::class)->liste()[0]['name'];

        $this->actingAs($admin)->get(route('admin.operations.backups.show', $name));
        $this->assertSame(Protokoll::ADMIN_SICHERUNG_HERUNTERGELADEN, $this->letzterEintrag()->aktion);

        $this->actingAs($admin)->delete(route('admin.operations.backups.destroy', $name))->assertSessionHas('success');
        $this->assertSame(Protokoll::ADMIN_SICHERUNG_GELOESCHT, $this->letzterEintrag()->aktion);
    }

    // -----------------------------------------------------------------------------------------
    // Admin: Datenauskunft, Noten, Import, Betreuung
    // -----------------------------------------------------------------------------------------

    #[Test]
    public function datenauskunft_fuer_ein_konto_wird_protokolliert(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $bb = User::factory()->berufsbildner()->create();

        $this->actingAs($admin)->get(route('admin.users.data-export', $bb->benutzer_id));

        $eintrag = $this->letzterEintrag();
        $this->assertSame(Protokoll::ADMIN_DATENAUSKUNFT_ERSTELLT, $eintrag->aktion);
        $this->assertSame($bb->benutzer_id, $eintrag->ziel_id);
    }

    #[Test]
    public function eigene_datenauskunft_wird_als_eigene_aktion_protokolliert_nicht_als_admin_aktion(): void
    {
        Storage::fake('local');
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('profile.data-export'));

        $eintrag = $this->letzterEintrag();
        $this->assertSame(Protokoll::AUTH_DATENAUSKUNFT_EIGENE, $eintrag->aktion);
        $this->assertNotSame(Protokoll::ADMIN_DATENAUSKUNFT_ERSTELLT, $eintrag->aktion);
        $this->assertSame($user->benutzer_id, $eintrag->benutzer_id);
        $this->assertSame($user->benutzer_id, $eintrag->ziel_id);
    }

    #[Test]
    public function note_loeschen_durch_admin_wird_protokolliert(): void
    {
        $admin = User::factory()->admin()->create();
        $lernender = User::factory()->lernender()->create()->lernender;
        $note = Note::factory()->create(['lernender_id' => $lernender->lernender_id]);

        $this->actingAs($admin)
            ->delete(route('admin.learners.grades.destroy', [$lernender->lernender_id, $note->note_id]))
            ->assertSessionHas('success');

        $eintrag = $this->letzterEintrag();
        $this->assertSame(Protokoll::ADMIN_NOTE_GELOESCHT, $eintrag->aktion);
        $this->assertSame($note->note_id, $eintrag->ziel_id);
    }

    #[Test]
    public function notenimport_uebernehmen_durch_admin_wird_protokolliert(): void
    {
        $kategorie = DB::table('kategorien')->pluck('kategorie_id', 'code');
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $modul = DB::table('module')->insertGetId(['modul_nummer' => '431', 'titel' => 'Aufträge durchführen']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $lehrberuf, 'modul_id' => $modul, 'kategorie_id' => $kategorie['FACH']]);
        DB::table('semester')->insert(['bezeichnung' => '25/26-2', 'start_datum' => '2026-02-01', 'end_datum' => '2026-07-31', 'sortierung' => 10]);
        Konfiguration::vergessen();

        $lernenderUser = User::factory()->lernender(['lehrberuf_id' => $lehrberuf, 'lehrbeginn' => '2024-08-01'])->create();
        $lernenderId = (int) $lernenderUser->lernender->lernender_id;
        $admin = User::factory()->admin()->create();
        $csv = "Datum;Fach/Modul;Titel;Note;Gewicht\n02.03.2026;M431;LB1;4,5;50%\n";

        $this->actingAs($admin)
            ->post(route('admin.learners.grades.import.read', $lernenderId), ['datei' => UploadedFile::fake()->createWithContent('noten.csv', $csv)])
            ->assertRedirect(route('admin.learners.grades.import.index', $lernenderId));

        $vorschau = session('notenimport.'.$lernenderId);

        $this->actingAs($admin)
            ->post(route('admin.learners.grades.import.apply', $lernenderId), ['zeilen' => json_encode($vorschau['zeilen']), 'token' => $vorschau['token']])
            ->assertSessionHas('success');

        $eintrag = $this->letzterEintrag();
        $this->assertSame(Protokoll::ADMIN_NOTENIMPORT_UEBERNOMMEN, $eintrag->aktion);
        $this->assertSame(1, $eintrag->details['anzahl']);
    }

    #[Test]
    public function betreuung_anlegen_und_beenden_wird_protokolliert(): void
    {
        $admin = User::factory()->admin()->create();
        $bb = User::factory()->berufsbildner()->create();
        $lernender = User::factory()->lernender()->create()->lernender;

        $this->actingAs($admin)->post(route('admin.learners.supervision.store', $lernender->lernender_id), [
            'berufsbildner_id' => $bb->berufsbildner->berufsbildner_id,
            'gueltig_von' => now()->subMonth()->toDateString(),
        ])->assertSessionHasNoErrors();

        $eintragAngelegt = $this->letzterEintrag();
        $this->assertSame(Protokoll::ADMIN_BETREUUNG_ANGELEGT, $eintragAngelegt->aktion);

        $betreuungId = $lernender->betreuungen()->sole()->betreuung_id;
        $this->actingAs($admin)->post(route('admin.supervisions.end', [$lernender->lernender_id, $betreuungId]));

        $this->assertSame(Protokoll::ADMIN_BETREUUNG_BEENDET, $this->letzterEintrag()->aktion);
    }

    // -----------------------------------------------------------------------------------------
    // ziel_bezeichnung (Spaltenlänge 150)
    // -----------------------------------------------------------------------------------------

    #[Test]
    public function lange_ziel_bezeichnung_wird_auf_150_zeichen_gekuerzt(): void
    {
        $langerName = str_repeat('Ä', 200);
        $ziel = User::factory()->make(['vorname' => $langerName, 'nachname' => '']);

        Protokoll::schreiben(Protokoll::ADMIN_KONTO_ANGELEGT, $ziel);

        $eintrag = $this->letzterEintrag();
        $this->assertSame(150, mb_strlen($eintrag->ziel_bezeichnung));
        $this->assertSame(mb_substr($langerName, 0, 150), $eintrag->ziel_bezeichnung);
    }

    // -----------------------------------------------------------------------------------------
    // Sperrliste
    // -----------------------------------------------------------------------------------------

    #[Test]
    public function sperrliste_haelt_geheimnisse_aus_dem_protokoll_fern(): void
    {
        Protokoll::schreiben('test.sperrliste', null, [
            'passwort' => 'geheim1',
            'Passwort_hash' => 'geheim2',
            'neues_password' => 'geheim3',
            'api_token' => 'geheim4',
            'secret_key' => 'geheim5',
            'smtp_passwort' => 'geheim6',
            'ist_geheim' => 'geheim7',
            'normal' => 'sichtbar',
            'verschachtelt' => ['token' => 'geheim8', 'ok' => 'sichtbar2'],
        ]);

        $eintrag = Aktivitaet::where('aktion', 'test.sperrliste')->sole();
        $roh = json_encode($eintrag->details);

        foreach (['geheim1', 'geheim2', 'geheim3', 'geheim4', 'geheim5', 'geheim6', 'geheim7', 'geheim8'] as $geheim) {
            $this->assertStringNotContainsString($geheim, $roh);
        }
        $this->assertSame('sichtbar', $eintrag->details['normal']);
        $this->assertSame('sichtbar2', $eintrag->details['verschachtelt']['ok']);
    }

    // -----------------------------------------------------------------------------------------
    // Seite: Zugriff, Filter, Paginierung
    // -----------------------------------------------------------------------------------------

    #[Test]
    public function seite_ist_nur_fuer_admin_erreichbar(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.activity.index'))->assertOk();

        $this->actingAs(User::factory()->berufsbildner()->create())
            ->get(route('admin.activity.index'))->assertForbidden();

        $this->actingAs(User::factory()->lernender()->create())
            ->get(route('admin.activity.index'))->assertForbidden();
    }

    #[Test]
    public function seite_zeigt_leeren_zustand_ohne_eintraege(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.activity.index'))
            ->assertOk()
            ->assertSee('Noch keine Einträge im Aktivitätsprotokoll.');
    }

    #[Test]
    public function seite_zeigt_neueste_zuerst_mit_paginierung(): void
    {
        $admin = User::factory()->admin()->create();

        // Aktivitaet::create() würde erstellt_am (CREATED_AT) immer auf «jetzt» setzen – für
        // unterschiedliche Zeitpunkte pro Zeile daher direkt über den Query Builder einfügen.
        for ($i = 0; $i < 60; $i++) {
            DB::table('aktivitaeten')->insert([
                'benutzer_id' => $admin->benutzer_id,
                'aktion' => Protokoll::ADMIN_KONTO_ANGELEGT,
                'ziel_bezeichnung' => 'Eintrag '.$i,
                'erstellt_am' => now()->subMinutes(60 - $i),
            ]);
        }

        $antwort = $this->actingAs($admin)->get(route('admin.activity.index'))->assertOk();

        $eintraege = $antwort->viewData('eintraege');
        $this->assertSame(60, $eintraege->total());
        $this->assertSame(50, $eintraege->count());
        $this->assertSame('Eintrag 59', $eintraege->first()->ziel_bezeichnung);
    }

    #[Test]
    public function filter_nach_aktion_person_und_zeitraum_wirken(): void
    {
        $admin = User::factory()->admin()->create();
        $anderer = User::factory()->admin()->create(['vorname' => 'Petra', 'nachname' => 'Muster']);

        DB::table('aktivitaeten')->insert(['benutzer_id' => $admin->benutzer_id, 'aktion' => Protokoll::ADMIN_KONTO_ANGELEGT, 'erstellt_am' => now()->subDays(10)]);
        DB::table('aktivitaeten')->insert(['benutzer_id' => $anderer->benutzer_id, 'aktion' => Protokoll::ADMIN_KONTO_DEAKTIVIERT, 'erstellt_am' => now()]);

        $this->actingAs($admin);

        $nachAktion = $this->get(route('admin.activity.index', ['aktion' => Protokoll::ADMIN_KONTO_DEAKTIVIERT]));
        $this->assertSame(1, $nachAktion->viewData('eintraege')->total());

        $nachPerson = $this->get(route('admin.activity.index', ['person' => 'Petra']));
        $this->assertSame(1, $nachPerson->viewData('eintraege')->total());

        $nachZeitraum = $this->get(route('admin.activity.index', ['von' => now()->subDays(2)->toDateString()]));
        $this->assertSame(1, $nachZeitraum->viewData('eintraege')->total());

        $ohneFilter = $this->get(route('admin.activity.index'));
        $this->assertSame(2, $ohneFilter->viewData('eintraege')->total());
    }

    #[Test]
    public function ungueltige_filterwerte_werden_ignoriert_statt_einen_500er_zu_verursachen(): void
    {
        $admin = User::factory()->admin()->create();

        $antwortVon = $this->actingAs($admin)
            ->get(route('admin.activity.index', ['von' => 'xyz']))
            ->assertOk();
        $this->assertSame('', $antwortVon->viewData('von'));

        $antwortAktion = $this->actingAs($admin)
            ->get(route('admin.activity.index', ['aktion' => ['x']]))
            ->assertOk();
        $this->assertSame('', $antwortAktion->viewData('aktion'));
    }

    #[Test]
    public function personenfilter_escaped_like_platzhalter(): void
    {
        $admin = User::factory()->admin()->create();
        $anderer = User::factory()->admin()->create(['vorname' => 'Petra', 'nachname' => 'Muster']);

        DB::table('aktivitaeten')->insert(['benutzer_id' => $admin->benutzer_id, 'aktion' => Protokoll::ADMIN_KONTO_ANGELEGT, 'erstellt_am' => now()]);
        DB::table('aktivitaeten')->insert(['benutzer_id' => $anderer->benutzer_id, 'aktion' => Protokoll::ADMIN_KONTO_DEAKTIVIERT, 'erstellt_am' => now()]);

        // Ohne Escaping würde '%' als Wildcard beide Zeilen treffen.
        $antwort = $this->actingAs($admin)->get(route('admin.activity.index', ['person' => '%']));
        $this->assertSame(0, $antwort->viewData('eintraege')->total());
    }

    // -----------------------------------------------------------------------------------------
    // Aufräum-Kommando
    // -----------------------------------------------------------------------------------------

    #[Test]
    public function aufraeum_kommando_loescht_nur_alte_eintraege(): void
    {
        DB::table('aktivitaeten')->insert(['aktion' => Protokoll::ADMIN_KONTO_ANGELEGT, 'erstellt_am' => now()->subDays(Protokoll::AUFBEWAHRUNG_TAGE + 1)]);
        DB::table('aktivitaeten')->insert(['aktion' => Protokoll::ADMIN_KONTO_ANGELEGT, 'erstellt_am' => now()->subDays(Protokoll::AUFBEWAHRUNG_TAGE - 1)]);

        $this->artisan(AktivitaetenAufraeumen::class)->assertSuccessful();

        $this->assertSame(1, Aktivitaet::count());
    }

    // -----------------------------------------------------------------------------------------
    // Migration ist idempotent (Prod: Tabelle könnte durch eine parallele Session schon existieren)
    // -----------------------------------------------------------------------------------------

    #[Test]
    public function migration_bricht_nicht_ab_wenn_die_tabelle_schon_existiert(): void
    {
        $this->assertTrue(Schema::hasTable('aktivitaeten'));

        $migration = require database_path('migrations/2026_09_12_000010_aktivitaeten.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('aktivitaeten'));
    }

    // -----------------------------------------------------------------------------------------
    // Fehlende Tabelle (vor der Migration auf Prod): nichts bricht, nichts wird geschrieben
    // -----------------------------------------------------------------------------------------

    #[Test]
    public function ohne_die_tabelle_wird_nichts_geschrieben_und_nichts_bricht(): void
    {
        $cache = new ReflectionProperty(Protokoll::class, 'tabelleVorhanden');
        $cache->setValue(null, null);
        Schema::shouldReceive('hasTable')->andThrow(new RuntimeException('DB nicht erreichbar'));

        try {
            $this->assertFalse(Protokoll::verfuegbar());

            // Darf keine Exception werfen und nichts schreiben.
            Protokoll::schreiben(Protokoll::AUTH_ANMELDUNG_ERFOLGREICH, User::factory()->lernender()->make());
        } finally {
            $cache->setValue(null, null);
        }

        $this->assertSame(0, DB::table('aktivitaeten')->count());
    }

    #[Test]
    public function aufraeum_kommando_bricht_ohne_tabelle_nicht_ab(): void
    {
        $cache = new ReflectionProperty(Protokoll::class, 'tabelleVorhanden');
        $cache->setValue(null, null);
        Schema::shouldReceive('hasTable')->andThrow(new RuntimeException('DB nicht erreichbar'));

        try {
            $this->artisan(AktivitaetenAufraeumen::class)->assertSuccessful();
        } finally {
            $cache->setValue(null, null);
        }
    }
}
