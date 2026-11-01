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

/** Die Spalte dauer_minuten ist smallint unsigned, das manuelle Formular erlaubt 1 bis 600 Minuten. */
class CalendarSyncDauerTest extends TestCase
{
    private User $user;

    private CalendarFeed $feed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->lernender()->create();
        $modul = Modul::factory()->create(['modul_nummer' => '902', 'titel' => 'Testmodul']);
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $this->user->lernender->lehrberuf_id, 'modul_id' => $modul->modul_id,
            'kategorie_id' => Kategorie::value('kategorie_id'),
        ]);
        $this->feed = CalendarFeed::create(['lernender_id' => $this->user->lernender->lernender_id, 'url' => 'https://93.184.216.34/x.ics']);
    }

    private function termin(string $uid, CarbonImmutable $start, CarbonImmutable $ende, string $zusatz = ''): string
    {
        $f = fn (CarbonImmutable $d) => $d->format('Ymd\THis');

        return "BEGIN:VEVENT\r\nUID:{$uid}\r\nDTSTAMP:20260901T120000Z\r\nDTSTART;TZID=Europe/Zurich:{$f($start)}\r\n"
            ."DTEND;TZID=Europe/Zurich:{$f($ende)}\r\nSUMMARY:902-INXX 99 B-musmax\r\n"
            .'DESCRIPTION:Prüfung\\n902-INXX 99 B-musmax LB1: Beta'.$zusatz."\r\nEND:VEVENT\r\n";
    }

    private function kalender(string ...$termine): string
    {
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//X//DE\r\n".implode('', $termine)."END:VCALENDAR\r\n";
    }

    #[Test]
    public function termin_mit_unsinniger_dauer_bricht_den_abgleich_nicht_ab(): void
    {
        $s = CarbonImmutable::now('Europe/Zurich')->addDays(10)->setTime(8, 0);
        $ics = $this->kalender(
            $this->termin('lang', $s, $s->addDays(60)),
            $this->termin('normal', $s->addDay(), $s->addDay()->addMinutes(45)),
            $this->termin('beschrieben', $s->addDays(2), $s->addDays(2)->addMinutes(45), '\\nDauer: 99999 Minuten'),
        );
        Http::fake(['*' => Http::response($ics, 200)]);

        $stats = app(CalendarSync::class)->sync($this->feed->fresh());

        $this->assertSame(3, $stats['exams']);
        $this->assertSame(CalendarFeed::OK, $this->feed->fresh()->last_status);
        $this->assertNull($this->feed->fresh()->last_error);
        $pruefungen = Pruefung::pluck('dauer_minuten', 'extern_uid');
        $this->assertCount(3, $pruefungen);
        $this->assertNull($pruefungen['lang']);
        $this->assertSame(45, $pruefungen['normal']);
        $this->assertNull($pruefungen['beschrieben']);
    }

    #[Test]
    public function dauer_ueber_600_minuten_wird_beim_uebernehmen_nicht_zum_serverfehler(): void
    {
        $s = CarbonImmutable::now('Europe/Zurich')->addDays(10)->setTime(8, 0);
        $event = CalendarEvent::create([
            'lernender_id' => $this->user->lernender->lernender_id, 'calendar_feed_id' => $this->feed->id, 'kind' => CalendarEvent::EXAM,
            'uid' => 'lang', 'summary' => 'Unbekannte Prüfung', 'description' => 'Prüfungsstoff: alles',
            'starts_at' => $s, 'ends_at' => $s->addDays(60), 'all_day' => false,
        ]);

        $this->actingAs($this->user)->post(route('learner.exams.adopt', $event->id), [
            'bezug' => 'modul:'.Modul::where('modul_nummer', '902')->value('modul_id'),
        ])->assertSessionHasNoErrors()->assertRedirect(route('learner.exams.index'));

        $this->assertNull(Pruefung::sole()->dauer_minuten);
    }

    #[Test]
    public function gewoehnliche_dauer_bis_600_minuten_bleibt_erhalten(): void
    {
        $s = CarbonImmutable::now('Europe/Zurich')->addDays(10)->setTime(8, 0);
        Http::fake(['*' => Http::response($this->kalender($this->termin('grenze', $s, $s->addMinutes(600))), 200)]);

        app(CalendarSync::class)->sync($this->feed->fresh());

        $this->assertSame(600, Pruefung::sole()->dauer_minuten);
    }
}
