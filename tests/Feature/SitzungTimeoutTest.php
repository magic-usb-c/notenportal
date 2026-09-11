<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Einstellungen;
use App\Support\Protokoll;
use App\Support\Sitzung;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Block AE: Sitzungs-Timeout (App\Support\Sitzung, Keep-Alive, 419-Redirect, Timeout-Warnung im Layout). */
class SitzungTimeoutTest extends TestCase
{
    #[Test]
    public function standard_entspricht_dem_env_wert(): void
    {
        $this->assertSame((int) env('SESSION_LIFETIME', 120), Sitzung::standard());
    }

    #[Test]
    public function set_und_anwenden_ueberschreibt_die_konfiguration(): void
    {
        Einstellungen::set(Einstellungen::SITZUNG_MINUTEN, '30');
        Sitzung::anwenden();

        $this->assertSame(30, config('session.lifetime'));
    }

    #[Test]
    public function ungueltige_gespeicherte_werte_fallen_auf_standard_zurueck(): void
    {
        foreach (['abc', '0', '9999'] as $wert) {
            Einstellungen::set(Einstellungen::SITZUNG_MINUTEN, $wert);
            $this->assertSame(Sitzung::standard(), Sitzung::minuten(), "Wert: {$wert}");
        }
    }

    #[Test]
    public function admin_formular_lehnt_werte_ausserhalb_15_bis_480_ab(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([14, 481] as $wert) {
            $this->actingAs($admin)->put(route('admin.operations.session.update'), [
                'sitzung_minuten' => $wert,
            ])->assertSessionHasErrors('sitzung_minuten');
        }

        $this->actingAs($admin)->put(route('admin.operations.session.update'), [
            'sitzung_minuten' => 15,
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->put(route('admin.operations.session.update'), [
            'sitzung_minuten' => 480,
        ])->assertSessionHasNoErrors();
    }

    #[Test]
    public function keep_alive_antwortet_204_wenn_angemeldet(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->postJson(route('session.keep-alive'))->assertNoContent();
    }

    #[Test]
    public function keep_alive_antwortet_401_fuer_gast(): void
    {
        $this->postJson(route('session.keep-alive'))->assertStatus(401);
    }

    #[Test]
    public function keep_alive_wird_ab_dem_elften_aufruf_gedrosselt(): void
    {
        $user = User::factory()->lernender()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->postJson(route('session.keep-alive'))->assertNoContent();
        }

        $this->actingAs($user)->postJson(route('session.keep-alive'))->assertStatus(429);
    }

    #[Test]
    public function layout_enthaelt_das_sitzungs_timeout_attribut(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->get(route('learner.dashboard'))
            ->assertSee('data-sekunden=', false);
    }

    #[Test]
    public function abgelaufen_parameter_zeigt_festen_text_ohne_den_wert_auszugeben(): void
    {
        $this->get('/login?abgelaufen=1')->assertSee(__('Du wurdest wegen Inaktivität abgemeldet.'));

        $antwort = $this->get('/login?abgelaufen=<b>');
        $antwort->assertDontSee('<b>', false);
        $antwort->assertDontSee(__('Du wurdest wegen Inaktivität abgemeldet.'));
    }

    #[Test]
    public function abgelaufenes_csrf_token_leitet_um_statt_die_419_seite_zu_zeigen(): void
    {
        Route::middleware('web')->get('/test-csrf-mismatch', function () {
            throw new TokenMismatchException('CSRF token mismatch.');
        });

        $gast = $this->get('/test-csrf-mismatch');
        $gast->assertRedirect(route('login'));
        $gast->assertSessionHas('status');

        $user = User::factory()->lernender()->create();
        $angemeldet = $this->actingAs($user)->from('/dashboard')->get('/test-csrf-mismatch');
        $angemeldet->assertRedirect('/dashboard');
        $angemeldet->assertSessionHas('error');
    }

    #[Test]
    public function json_anfragen_behalten_419_und_geheimnisse_landen_nicht_in_der_session(): void
    {
        Route::middleware('web')->post('/test-csrf-mismatch', function () {
            throw new TokenMismatchException('CSRF token mismatch.');
        });
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->postJson('/test-csrf-mismatch')->assertStatus(419);

        $this->actingAs($user)->from('/admin/operations')
            ->post('/test-csrf-mismatch', ['mail_host' => 'smtp.example.local', 'mail_password' => 'geheim', 'passwort' => 'geheim2'])
            ->assertRedirect('/admin/operations')
            ->assertSessionHasInput('mail_host', 'smtp.example.local')
            ->assertSessionMissing('_old_input.mail_password')
            ->assertSessionMissing('_old_input.passwort');
    }

    #[Test]
    public function speichern_schreibt_einen_protokolleintrag(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.operations.session.update'), [
            'sitzung_minuten' => 30,
        ])->assertRedirect(route('admin.operations.edit'));

        $this->assertDatabaseHas('aktivitaeten', [
            'benutzer_id' => $admin->benutzer_id,
            'aktion' => Protokoll::ADMIN_SITZUNGSDAUER_GEAENDERT,
        ]);
    }

    #[Test]
    public function nicht_admin_darf_die_sitzungsdauer_nicht_speichern(): void
    {
        $lernender = User::factory()->lernender()->create();

        $this->actingAs($lernender)->put(route('admin.operations.session.update'), [
            'sitzung_minuten' => 30,
        ])->assertForbidden();
    }
}
