<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Einstellungen;
use App\Support\Protokoll;
use App\Support\Systemhinweis;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Block AE: Systemhinweis-Banner (layouts._systemhinweis, Login-Banner, Admin-Formular). */
class SystemhinweisTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function ohne_text_erscheint_kein_banner(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->get(route('learner.dashboard'))->assertOk();

        $this->assertNull(Systemhinweis::aktuell($user->fresh()));
    }

    #[Test]
    public function zielgruppe_lernende_ist_nur_fuer_lernende_sichtbar(): void
    {
        $admin = User::factory()->admin()->create();
        $lernender = User::factory()->lernender()->create();
        $bb = User::factory()->berufsbildner()->create();

        $this->hinweisSetzen($admin, ['hinweis_text' => 'Nur fuer Lernende.', 'hinweis_zielgruppe' => 'lernende']);

        $this->actingAs($lernender)->get(route('learner.dashboard'))->assertSee('Nur fuer Lernende.');
        $this->actingAs($bb)->get(route('trainer.dashboard'))->assertDontSee('Nur fuer Lernende.');
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertDontSee('Nur fuer Lernende.');
    }

    #[Test]
    public function zeitfenster_gilt_in_zuericher_zeit_auch_ueber_die_zeitumstellung_hinweg(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->lernender()->create();

        // 25.10.2026: Umstellung von Sommer- auf Winterzeit in Zürich.
        $this->hinweisSetzen($admin, [
            'hinweis_text' => 'Wartungsfenster am Sonntag.',
            'hinweis_beginn' => '2026-10-25T01:00',
            'hinweis_ende' => '2026-10-25T05:00',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-24 22:00:00', 'Europe/Zurich'));
        $this->actingAs($user)->get(route('learner.dashboard'))->assertDontSee('Wartungsfenster am Sonntag.');

        Carbon::setTestNow(Carbon::parse('2026-10-25 03:30:00', 'Europe/Zurich'));
        $this->actingAs($user)->get(route('learner.dashboard'))->assertSee('Wartungsfenster am Sonntag.');

        Carbon::setTestNow(Carbon::parse('2026-10-25 06:00:00', 'Europe/Zurich'));
        $this->actingAs($user)->get(route('learner.dashboard'))->assertDontSee('Wartungsfenster am Sonntag.');
    }

    #[Test]
    public function banner_text_mit_script_wird_escaped(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->lernender()->create();

        $this->hinweisSetzen($admin, ['hinweis_text' => '<script>alert(1)</script>']);

        $antwort = $this->actingAs($user)->get(route('learner.dashboard'));
        $antwort->assertOk();
        $antwort->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $antwort->assertDontSee('<script>alert(1)</script>', false);
    }

    #[Test]
    public function banner_behaelt_zeilenumbrueche(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->lernender()->create();

        $this->hinweisSetzen($admin, ['hinweis_text' => "Zeile eins\nZeile zwei"]);

        $this->actingAs($user)->get(route('learner.dashboard'))
            ->assertSee("Zeile eins\nZeile zwei", false);
    }

    #[Test]
    public function wegklicken_blendet_aus_neuer_text_zeigt_wieder_gleicher_text_bleibt_weg(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->lernender()->create();

        $this->hinweisSetzen($admin, ['hinweis_text' => 'Text A']);
        $version = (string) Einstellungen::get(Einstellungen::HINWEIS_VERSION);

        $this->actingAs($user)->get(route('learner.dashboard'))->assertSee('Text A');

        $this->actingAs($user)->postJson(route('system-notice.dismiss'), ['version' => $version])
            ->assertNoContent();

        $this->actingAs($user)->get(route('learner.dashboard'))->assertDontSee('Text A');

        // Gleicher Text, andere Art: bleibt weggeklickt (gleiche Version).
        $this->hinweisSetzen($admin, ['hinweis_text' => 'Text A', 'hinweis_art' => 'warnung']);
        $this->actingAs($user)->get(route('learner.dashboard'))->assertDontSee('Text A');

        // Neuer Text: neue Version, wieder sichtbar.
        $this->hinweisSetzen($admin, ['hinweis_text' => 'Text B']);
        $this->actingAs($user)->get(route('learner.dashboard'))->assertSee('Text B');
    }

    #[Test]
    public function schliessen_mit_falscher_version_gibt_422_gast_wird_zur_anmeldung_umgeleitet(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->lernender()->create();

        $this->hinweisSetzen($admin, ['hinweis_text' => 'Text']);

        $this->actingAs($user)->postJson(route('system-notice.dismiss'), ['version' => 'falsche-version'])
            ->assertStatus(422);

        $this->actingAsGuest()->post(route('system-notice.dismiss'), ['version' => 'irrelevant'])
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function login_banner_nur_bei_aktiviertem_login_hinweis_und_zielgruppe_alle(): void
    {
        $admin = User::factory()->admin()->create();

        $this->hinweisSetzen($admin, ['hinweis_text' => 'Wartung heute Abend.', 'hinweis_login' => '0']);
        $this->actingAsGuest()->get('/login')->assertDontSee('Wartung heute Abend.');

        $this->hinweisSetzen($admin, [
            'hinweis_text' => 'Wartung heute Abend.',
            'hinweis_login' => '1',
            'hinweis_zielgruppe' => 'lernende',
        ]);
        $this->actingAsGuest()->get('/login')->assertDontSee('Wartung heute Abend.');

        $this->hinweisSetzen($admin, [
            'hinweis_text' => 'Wartung heute Abend.',
            'hinweis_login' => '1',
            'hinweis_zielgruppe' => 'alle',
        ]);
        $this->actingAsGuest()->get('/login')->assertSee('Wartung heute Abend.');
    }

    #[Test]
    public function validierung_lehnt_zu_langen_text_falsche_art_und_ende_vor_beginn_ab(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.operations.notice.update'), [
            'hinweis_text' => str_repeat('a', 301),
            'hinweis_art' => 'info',
            'hinweis_zielgruppe' => 'alle',
        ])->assertSessionHasErrors('hinweis_text');

        $this->actingAs($admin)->put(route('admin.operations.notice.update'), [
            'hinweis_text' => 'Text',
            'hinweis_art' => 'ungueltig',
            'hinweis_zielgruppe' => 'alle',
        ])->assertSessionHasErrors('hinweis_art');

        $this->actingAs($admin)->put(route('admin.operations.notice.update'), [
            'hinweis_text' => 'Text',
            'hinweis_art' => 'info',
            'hinweis_zielgruppe' => 'unbekannt',
        ])->assertSessionHasErrors('hinweis_zielgruppe');

        $this->actingAs($admin)->put(route('admin.operations.notice.update'), [
            'hinweis_text' => 'Text',
            'hinweis_art' => 'info',
            'hinweis_zielgruppe' => 'alle',
            'hinweis_beginn' => '2026-09-10T10:00',
            'hinweis_ende' => '2026-09-10T09:00',
        ])->assertSessionHasErrors('hinweis_ende');
    }

    #[Test]
    public function speichern_schreibt_einen_protokolleintrag(): void
    {
        $admin = User::factory()->admin()->create();

        $this->hinweisSetzen($admin, ['hinweis_text' => 'Text fuers Protokoll.']);

        $this->assertDatabaseHas('aktivitaeten', [
            'benutzer_id' => $admin->benutzer_id,
            'aktion' => Protokoll::ADMIN_SYSTEMHINWEIS_GEAENDERT,
        ]);
    }

    #[Test]
    public function nicht_admin_darf_den_hinweis_nicht_speichern(): void
    {
        $lernender = User::factory()->lernender()->create();

        $this->actingAs($lernender)->put(route('admin.operations.notice.update'), [
            'hinweis_text' => 'Text',
            'hinweis_art' => 'info',
            'hinweis_zielgruppe' => 'alle',
        ])->assertForbidden();
    }

    #[Test]
    public function hinweis_entfernen_leert_nur_den_text(): void
    {
        $admin = User::factory()->admin()->create();
        $this->hinweisSetzen($admin, ['hinweis_text' => 'Text', 'hinweis_zielgruppe' => 'lernende']);

        $this->actingAs($admin)->put(route('admin.operations.notice.update'), [
            'hinweis_entfernen' => '1',
        ])->assertRedirect(route('admin.operations.edit'));

        $this->assertSame('', (string) Einstellungen::get(Einstellungen::HINWEIS_TEXT, ''));
        $this->assertSame('lernende', Einstellungen::get(Einstellungen::HINWEIS_ZIELGRUPPE));
    }

    #[Test]
    public function aktiver_banner_kostet_hoechstens_zwei_abfragen_mehr_auf_dem_dashboard(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->lernender()->create();

        DB::enableQueryLog();
        $this->actingAs($user)->get(route('learner.dashboard'))->assertOk();
        $ohneBanner = count(DB::getQueryLog());
        DB::flushQueryLog();
        DB::disableQueryLog();

        $this->hinweisSetzen($admin, ['hinweis_text' => 'Abfrage-Test.', 'hinweis_zielgruppe' => 'lernende']);

        DB::enableQueryLog();
        $this->actingAs($user)->get(route('learner.dashboard'))->assertSee('Abfrage-Test.');
        $mitBanner = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual($ohneBanner + 2, $mitBanner);
    }

    /** @param array<string, mixed> $overrides */
    private function hinweisSetzen(User $admin, array $overrides = []): void
    {
        $daten = array_merge([
            'hinweis_text' => 'Standardtext.',
            'hinweis_art' => 'info',
            'hinweis_zielgruppe' => 'alle',
            'hinweis_beginn' => '',
            'hinweis_ende' => '',
            'hinweis_login' => '0',
        ], $overrides);

        $this->actingAs($admin)->put(route('admin.operations.notice.update'), $daten)
            ->assertRedirect(route('admin.operations.edit'));
    }
}
