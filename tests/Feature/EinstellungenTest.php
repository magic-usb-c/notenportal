<?php

namespace Tests\Feature;

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
            ->get(route('lernender.dashboard'))
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
}
