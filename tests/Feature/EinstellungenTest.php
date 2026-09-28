<?php

namespace Tests\Feature;

use App\Models\Einstellung;
use App\Models\User;
use App\Support\Einstellungen;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EinstellungenTest extends TestCase
{
    #[Test]
    public function betriebsname_kommt_aus_der_datenbank(): void
    {
        Einstellungen::set(Einstellungen::BETRIEB_NAME, 'Hamilton AG');

        $this->get('/login')->assertSee('Hamilton AG');
        $this->actingAs(User::factory()->lernender()->create())
            ->get(route('learner.dashboard'))
            ->assertSee('Hamilton AG');
    }

    #[Test]
    public function ohne_betriebsname_erscheint_kein_firmenname(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('Hamilton');
    }

    #[Test]
    public function aenderung_wirkt_sofort_trotz_cache(): void
    {
        Einstellungen::set(Einstellungen::BETRIEB_NAME, 'Alt AG');
        $this->assertSame('Alt AG', Einstellungen::get(Einstellungen::BETRIEB_NAME));

        Einstellungen::set(Einstellungen::BETRIEB_NAME, 'Neu AG');
        $this->assertSame('Neu AG', Einstellungen::get(Einstellungen::BETRIEB_NAME));
    }

    #[Test]
    public function alle_wird_pro_request_nur_einmal_geladen_und_von_vergessen_geleert(): void
    {
        Einstellungen::set(Einstellungen::BETRIEB_NAME, 'Erst AG');
        Einstellungen::alle(); // füllt den statischen Zwischenspeicher

        // Änderung direkt an der DB-Zeile, am statischen Zwischenspeicher vorbei.
        Einstellung::where('schluessel', Einstellungen::BETRIEB_NAME)->update(['wert' => 'Umgangen AG']);
        $this->assertSame('Erst AG', Einstellungen::get(Einstellungen::BETRIEB_NAME), 'zweiter Aufruf bedient aus dem statischen Zwischenspeicher');

        Einstellungen::vergessen();
        $this->assertSame('Umgangen AG', Einstellungen::get(Einstellungen::BETRIEB_NAME), 'vergessen() leert auch den statischen Zwischenspeicher');
    }
}
