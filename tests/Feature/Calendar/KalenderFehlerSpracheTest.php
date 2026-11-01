<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Http\Middleware\SetLocale;
use App\Models\CalendarFeed;
use App\Models\User;
use App\Services\Calendar\CalendarSync;
use App\Support\Einstellungen;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/** Fehlertexte der Kalender-Einbindung folgen der Sprache der Oberfläche, auch der gespeicherte Abgleichfehler. */
class KalenderFehlerSpracheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Einstellungen::set(SetLocale::WAHL_AKTIV, '1');
    }

    #[Test]
    public function ungueltige_kalenderadresse_wird_in_englischer_oberflaeche_englisch_gemeldet(): void
    {
        $user = User::factory()->lernender()->create(['locale' => 'en']);

        foreach (['ftp://example.org/x.ics', 'http://127.0.0.1/x.ics'] as $url) {
            $fehler = $this->actingAs($user)->post(route('learner.calendar.feed.store'), ['url' => $url])
                ->assertSessionHasErrors('url')->baseResponse->getSession()->get('errors')->first('url');

            $this->assertMatchesRegularExpression('/address/i', $fehler);
            $this->assertDoesNotMatchRegularExpression('/Adresse|Bitte|nicht erlaubt/', $fehler, $url);
        }
    }

    #[Test]
    public function wurfstellen_und_fehlertext_folgen_der_aktuellen_sprache(): void
    {
        App::setLocale('en');
        try {
            CalendarSync::assertPublicUrl('ftp://example.org/x.ics');
            $this->fail('Ausnahme erwartet');
        } catch (RuntimeException $e) {
            $this->assertSame('Please enter an http(s) or webcal address.', CalendarSync::fehlertext($e));
        }
        $this->assertSame('Calendar not reachable (timeout or unknown server).', CalendarSync::fehlertext(new \LogicException('x')));

        App::setLocale('de');
        $this->assertSame('Kalender nicht erreichbar (Zeitüberschreitung oder unbekannter Server).', CalendarSync::fehlertext(new \LogicException('x')));
    }

    #[Test]
    public function gespeicherter_deutscher_abgleichfehler_erscheint_in_den_einstellungen_englisch(): void
    {
        $user = User::factory()->lernender()->create(['locale' => 'en']);
        Http::fake(['*' => Http::response('Serverfehler', 500)]);
        $feed = CalendarFeed::create(['lernender_id' => $user->lernender->lernender_id, 'url' => 'https://93.184.216.34/x.ics']);

        // Cron läuft in der Standardsprache: der Fehler wird deutsch gespeichert.
        App::setLocale('de');
        try {
            app(CalendarSync::class)->sync($feed);
            $this->fail('Ausnahme erwartet');
        } catch (RuntimeException) {
        }
        $this->assertSame('Kalender nicht erreichbar (HTTP 500).', $feed->fresh()->last_error);

        $this->actingAs($user)->get(route('settings.calendar'))
            ->assertOk()
            ->assertSee('Calendar not reachable (HTTP 500).')
            ->assertDontSee('Kalender nicht erreichbar (HTTP 500).');

        $feed->update(['last_error' => 'Die Kalenderdatei ist unvollständig. Die bisherigen Termine bleiben unverändert.']);
        $this->actingAs($user)->get(route('settings.calendar'))
            ->assertSee('The calendar file is incomplete.');
    }
}
