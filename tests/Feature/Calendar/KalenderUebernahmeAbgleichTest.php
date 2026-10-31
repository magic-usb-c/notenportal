<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Models\CalendarEvent;
use App\Models\CalendarFeed;
use App\Models\Kategorie;
use App\Models\Modul;
use App\Models\Pruefung;
use App\Models\User;
use App\Services\Calendar\CalendarSync;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Manuell übernommene Kalender-Prüfung bleibt beim nächsten Abgleich verknüpft; erneutes Übernehmen gibt keinen 500. */
class KalenderUebernahmeAbgleichTest extends TestCase
{
    /** @return array{0: User, 1: Modul, 2: CalendarFeed} */
    private function aufbau(): array
    {
        config(['notenportal.calendar_allow_private' => true]);
        $user = User::factory()->lernender()->create();
        $modul = Modul::factory()->create(['modul_nummer' => '902', 'titel' => 'Testmodul']);
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $user->lernender->lehrberuf_id, 'modul_id' => $modul->modul_id, 'kategorie_id' => Kategorie::value('kategorie_id'),
        ]);
        $feed = CalendarFeed::create(['lernender_id' => $user->lernender->lernender_id, 'url' => 'https://schulnetz.example/ical/y']);
        $start = CarbonImmutable::now('Europe/Zurich')->addDays(10)->setTime(13, 15);
        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//X//DE\r\nBEGIN:VEVENT\r\nUID:ex-9\r\nDTSTAMP:20260901T120000Z\r\n"
            ."DTSTART;TZID=Europe/Zurich:{$start->format('Ymd\THis')}\r\nDTEND;TZID=Europe/Zurich:{$start->addMinutes(45)->format('Ymd\THis')}\r\n"
            ."SUMMARY:Prüfung Allgemein\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        Http::fake(['*' => Http::response($ics, 200)]);

        return [$user, $modul, $feed];
    }

    private function erneutAbgleichen(CalendarFeed $feed): array
    {
        $feed->refresh()->forceFill(['etag' => null, 'last_modified' => null])->save();

        return app(CalendarSync::class)->sync($feed->fresh());
    }

    #[Test]
    public function uebernommene_pruefung_bleibt_nach_dem_abgleich_verknuepft_und_zaehlt_nicht_als_unzugeordnet(): void
    {
        [$user, $modul, $feed] = $this->aufbau();
        $erster = app(CalendarSync::class)->sync($feed);
        $this->assertSame(1, $erster['unmatched']);

        $event = CalendarEvent::where('kind', 'exam')->sole();
        $this->actingAs($user)->post(route('learner.exams.adopt', $event->id), ['bezug' => 'modul:'.$modul->modul_id])->assertRedirect();
        $this->assertSame(1, Pruefung::count());
        $pruefungId = Pruefung::sole()->pruefung_id;
        $this->assertSame($pruefungId, $event->fresh()->pruefung_id);

        $zweiter = $this->erneutAbgleichen($feed);

        $this->assertSame($pruefungId, CalendarEvent::where('kind', 'exam')->sole()->pruefung_id, 'Verknüpfung nach dem Abgleich verloren');
        $this->assertSame(0, $zweiter['unmatched']);
    }

    #[Test]
    public function erneutes_uebernehmen_einer_schon_uebernommenen_pruefung_leitet_mit_hinweis_um(): void
    {
        [$user, $modul, $feed] = $this->aufbau();
        app(CalendarSync::class)->sync($feed);
        $event = CalendarEvent::where('kind', 'exam')->sole();
        $this->actingAs($user)->post(route('learner.exams.adopt', $event->id), ['bezug' => 'modul:'.$modul->modul_id])->assertRedirect();

        // Altbestand: das Ereignis hat die Verknüpfung durch einen früheren Abgleich verloren.
        CalendarEvent::whereKey($event->id)->update(['pruefung_id' => null]);

        $this->actingAs($user)->withoutExceptionHandling()
            ->post(route('learner.exams.adopt', $event->id), ['bezug' => 'modul:'.$modul->modul_id])
            ->assertRedirect()->assertSessionHas('success', __('Prüfung war bereits übernommen.'));

        $this->assertSame(1, Pruefung::count());
        $this->assertSame(Pruefung::sole()->pruefung_id, $event->fresh()->pruefung_id);
    }
}
