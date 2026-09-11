<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Models\CalendarFeed;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * app/Console/Commands/SyncCalendars.php: ein fehlerhafter Kalender darf die anderen nicht
 * aufhalten; Exit-Code hängt davon ab, ob ALLE Kalender gescheitert sind.
 */
class SyncCalendarsCommandTest extends TestCase
{
    private function ics(): string
    {
        $start = now()->addDay()->setTime(19, 0)->format('Ymd\THis');

        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Schulnetz//DE\r\n"
            ."BEGIN:VEVENT\r\nUID:termin-1\r\nDTSTAMP:20260901T120000Z\r\nDTSTART;TZID=Europe/Zurich:{$start}\r\nSUMMARY:Elternabend\r\nEND:VEVENT\r\n"
            ."END:VCALENDAR\r\n";
    }

    private function feed(?string $url = null): CalendarFeed
    {
        $user = User::factory()->lernender()->create();

        return CalendarFeed::create([
            'lernender_id' => $user->lernender->lernender_id,
            'url' => $url ?? 'https://93.184.216.34/kalender-'.uniqid().'.ics',
        ]);
    }

    #[Test]
    public function ohne_feeds_erfolgreich_und_ohne_ausgabe(): void
    {
        $this->artisan('calendar:sync')->assertSuccessful();
    }

    #[Test]
    public function feed_wird_abgeglichen_und_meldet_die_zahlen(): void
    {
        $feed = $this->feed();
        Http::fake(['https://93.184.216.34/*' => Http::response($this->ics(), 200, ['Content-Type' => 'text/calendar'])]);

        $this->artisan('calendar:sync')
            ->assertSuccessful()
            ->expectsOutputToContain("#{$feed->id} 93.184.216.34: 1 Termine, 0 Prüfungen, 0 ohne Zuordnung, 0 entfernt");
    }

    #[Test]
    public function fehler_eines_feeds_haelt_die_anderen_nicht_auf(): void
    {
        $kaputt = $this->feed('https://93.184.216.34/kaputt.ics');
        $gut = $this->feed('https://93.184.216.34/gut.ics');

        Http::fake([
            'https://93.184.216.34/kaputt.ics' => Http::response('kein kalender', 200),
            'https://93.184.216.34/gut.ics' => Http::response($this->ics(), 200, ['Content-Type' => 'text/calendar']),
        ]);

        $this->artisan('calendar:sync')
            ->assertSuccessful()
            ->expectsOutputToContain("#{$kaputt->id} 93.184.216.34: Die Adresse liefert keinen iCal-Kalender.")
            ->expectsOutputToContain("#{$gut->id} 93.184.216.34: 1 Termine, 0 Prüfungen, 0 ohne Zuordnung, 0 entfernt");

        $this->assertNotNull($kaputt->fresh()->last_error);
        $this->assertNull($gut->fresh()->last_error);
    }

    #[Test]
    public function scheitern_aller_feeds_fuehrt_zu_fehlerhaftem_exit_code(): void
    {
        $this->feed('https://93.184.216.34/kaputt.ics');
        Http::fake(['https://93.184.216.34/*' => Http::response('kein kalender', 200)]);

        $this->artisan('calendar:sync')->assertFailed();
    }

    #[Test]
    public function option_feed_beschraenkt_auf_einen_kalender(): void
    {
        $eins = $this->feed('https://93.184.216.34/eins.ics');
        $zwei = $this->feed('https://93.184.216.34/zwei.ics');
        Http::fake(['https://93.184.216.34/*' => Http::response($this->ics(), 200, ['Content-Type' => 'text/calendar'])]);

        $this->artisan('calendar:sync', ['--feed' => (string) $eins->id])
            ->assertSuccessful()
            ->expectsOutputToContain("#{$eins->id} 93.184.216.34:");

        $this->assertNotNull($eins->fresh()->last_synced_at);
        $this->assertNull($zwei->fresh()->last_synced_at, 'nicht ausgewählter Kalender bleibt unberührt');
    }

    #[Test]
    public function kalender_geloeschter_lernender_wird_uebersprungen(): void
    {
        $feed = $this->feed();
        $feed->lernender->delete();
        Http::fake(['https://93.184.216.34/*' => Http::response($this->ics(), 200, ['Content-Type' => 'text/calendar'])]);

        $this->artisan('calendar:sync')->assertSuccessful();

        $this->assertNull($feed->fresh()->last_synced_at, 'Kalender eines gelöschten Lernenden wird nicht abgeglichen');
    }
}
