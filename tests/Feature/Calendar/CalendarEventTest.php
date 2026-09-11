<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Models\CalendarEvent;
use App\Models\CalendarFeed;
use App\Models\Fach;
use App\Models\Pruefung;
use App\Models\User;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** app/Models/CalendarEvent.php: Beziehungen zum Kalender-Abo und zur zugeordneten Prüfung, Typ-Casts. */
class CalendarEventTest extends TestCase
{
    #[Test]
    public function laedt_zugehoerigen_feed_und_pruefung(): void
    {
        $user = User::factory()->lernender()->create();
        $feed = CalendarFeed::create(['lernender_id' => $user->lernender->lernender_id, 'url' => 'https://schulnetz.example/geheim']);
        $fach = Fach::factory()->create();
        $pruefung = Pruefung::create([
            'lernender_id' => $user->lernender->lernender_id,
            'fach_id' => $fach->fach_id,
            'titel' => 'LB1',
            'datum' => now()->addWeek()->toDateString(),
        ]);

        $event = CalendarEvent::create([
            'lernender_id' => $user->lernender->lernender_id,
            'calendar_feed_id' => $feed->id,
            'uid' => 'uid-1',
            'kind' => CalendarEvent::EXAM,
            'summary' => 'Prüfung LB1',
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addMinutes(45),
            'all_day' => false,
            'pruefung_id' => $pruefung->pruefung_id,
        ]);

        $this->assertTrue($event->feed->is($feed));
        $this->assertTrue($event->pruefung->is($pruefung));
        $this->assertInstanceOf(Carbon::class, $event->starts_at);
        $this->assertInstanceOf(Carbon::class, $event->ends_at);
        $this->assertFalse($event->all_day);
    }

    #[Test]
    public function ohne_zugeordnete_pruefung_bleibt_die_beziehung_leer(): void
    {
        $user = User::factory()->lernender()->create();
        $feed = CalendarFeed::create(['lernender_id' => $user->lernender->lernender_id, 'url' => 'https://schulnetz.example/geheim']);
        $event = CalendarEvent::create([
            'lernender_id' => $user->lernender->lernender_id,
            'calendar_feed_id' => $feed->id,
            'uid' => 'uid-2',
            'kind' => CalendarEvent::APPOINTMENT,
            'summary' => 'Elternabend',
            'starts_at' => now()->addWeek(),
            'all_day' => false,
        ]);

        $this->assertNull($event->pruefung);
    }
}
