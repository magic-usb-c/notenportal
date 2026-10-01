<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Wege auf den Fehlerseiten: Hauptknopf je Fehler, «Zurück» nur mit Herkunft aus dem Portal,
 * «Mit anderem Konto anmelden» als zweiter Weg unter dem Hauptknopf.
 */
class FehlerseitenWegeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::match(['get', 'post'], '/_fehlerseite/{code}', fn (int $code) => abort($code))->middleware('web');
    }

    #[Test]
    public function wartung_laedt_erneut_und_fuehrt_erst_danach_zur_anmeldung(): void
    {
        $this->get('/_fehlerseite/503')
            ->assertStatus(503)
            ->assertSeeInOrder([__('Erneut laden'), __('Zur Anmeldung')])
            ->assertSee('href="'.url('/_fehlerseite/503').'"', false);
    }

    #[Test]
    public function drosselung_bietet_erneut_versuchen_vor_der_uebersicht(): void
    {
        $this->actingAs(User::factory()->lernender()->create())
            ->get('/_fehlerseite/429')
            ->assertStatus(429)
            ->assertSeeInOrder([__('Erneut versuchen'), __('Zur Übersicht')]);
    }

    #[Test]
    public function angemeldete_gelangen_zur_uebersicht_nicht_zum_dashboard(): void
    {
        $this->actingAs(User::factory()->lernender()->create())
            ->get('/_fehlerseite/404')
            ->assertStatus(404)
            ->assertSee(__('Zur Übersicht'))
            ->assertDontSee('Zum Dashboard');
    }

    #[Test]
    public function zurueck_erscheint_nur_mit_herkunft_aus_dem_portal(): void
    {
        $this->get('/_fehlerseite/404')->assertDontSee('>'.__('Zurück').'<', false);

        $this->withHeader('Referer', url('/login'))->get('/_fehlerseite/404')->assertSee('>'.__('Zurück').'<', false);

        $this->withHeader('Referer', 'https://fremd.example/seite')->get('/_fehlerseite/404')->assertDontSee('>'.__('Zurück').'<', false);

        // Gleicher Host, aber fremdes Schema oder fremder Port: nicht dieses Portal
        $this->withHeader('Referer', 'ftp://'.request()->getHost().'/x')->get('/_fehlerseite/404')->assertDontSee('>'.__('Zurück').'<', false);
        $this->withHeader('Referer', 'http://'.request()->getHost().':8443/x')->get('/_fehlerseite/404')->assertDontSee('>'.__('Zurück').'<', false);
    }

    #[Test]
    public function gedrosseltes_formular_bietet_erneut_versuchen_auf_seiner_eigenen_seite(): void
    {
        // Häufigster Fall: POST /login mit Referer /login – der Hauptweg führt zurück zum Formular
        $this->withHeader('Referer', url('/_fehlerseite/429'))
            ->post('/_fehlerseite/429')
            ->assertStatus(429)
            ->assertSeeInOrder([__('Erneut versuchen'), __('Zur Anmeldung')])
            ->assertSee('href="'.url('/_fehlerseite/429').'"', false);
    }

    #[Test]
    public function kontowechsel_steht_unter_dem_hauptknopf(): void
    {
        $this->actingAs(User::factory()->lernender()->create())
            ->get('/_fehlerseite/403')
            ->assertStatus(403)
            ->assertSeeInOrder([__('Zur Übersicht'), __('Mit anderem Konto anmelden')]);
    }
}
