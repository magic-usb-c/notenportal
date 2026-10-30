<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Models\CalendarEvent;
use App\Models\CalendarFeed;
use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Pruefung;
use App\Models\Semester;
use App\Models\User;
use App\Services\Calendar\CalendarSync;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CalendarSyncFachUndZeitTest extends TestCase
{
    private User $user;

    private CalendarFeed $feed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->lernender()->create();
        $this->feed = CalendarFeed::create(['lernender_id' => $this->user->lernender->lernender_id, 'url' => 'https://schulnetz.example/ical/geheim']);
    }

    private function pruefungsIcs(string $kuerzel, CarbonImmutable $start): string
    {
        $f = fn (CarbonImmutable $d) => $d->format('Ymd\THis');
        $text = "Prüfung\n{$kuerzel}-INXX 99 B-lehrer LB1: Algebra\nPrüfungsart: schriftlich\nDauer: 45 Minuten\nGewichtung\n1\n";
        $ev = "BEGIN:VEVENT\r\nUID:e1\r\nDTSTAMP:20260901T120000Z\r\nDTSTART;TZID=Europe/Zurich:{$f($start)}\r\n"
            ."DTEND;TZID=Europe/Zurich:{$f($start->addMinutes(45))}\r\nSUMMARY:{$kuerzel}-INXX 99 B-lehrer\r\n"
            .'DESCRIPTION:'.str_replace(["\n", ','], ['\\n', '\\,'], $text)."\r\nEND:VEVENT\r\n";

        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Schulnetz//DE\r\n{$ev}END:VCALENDAR\r\n";
    }

    private function track(string $typ, string $beginn, ?string $ende = null): void
    {
        DB::table('lernender_tracks')->insert([
            'lernender_id' => $this->user->lernender->lernender_id,
            'track_typ' => $typ,
            'start_datum' => $beginn,
            'end_datum' => $ende,
            'start_semester_id' => Semester::factory()->create()->semester_id,
        ]);
    }

    #[Test]
    public function fach_ausserhalb_der_freigabe_wird_nicht_an_die_pruefung_gebunden(): void
    {
        Fach::factory()->create(['track_typ' => 'BMS', 'kurzname' => 'ZZQ', 'name' => 'Fremdfach']);

        $stats = app(CalendarSync::class)->apply($this->feed, $this->pruefungsIcs('ZZQ', CarbonImmutable::now('Europe/Zurich')->addDays(10)->setTime(10, 0)));

        $this->assertSame(0, $stats['exams']);
        $this->assertSame(1, $stats['unmatched']);
        $this->assertSame(0, Pruefung::count());
    }

    #[Test]
    public function gleiches_kuerzel_waehlt_das_fach_des_eigenen_bildungsgangs(): void
    {
        $kat = Kategorie::where('code', 'FACH')->value('kategorie_id');
        Fach::factory()->create(['track_typ' => 'BMS', 'kategorie_id' => $kat, 'kurzname' => 'MAT', 'name' => 'Mathematik BM']);
        $beruf = Fach::factory()->create(['track_typ' => null, 'kategorie_id' => $kat, 'kurzname' => 'MAT', 'name' => 'Mathematik']);
        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $this->user->lernender->lehrberuf_id, 'fach_id' => $beruf->fach_id, 'aktiv' => 1]);

        app(CalendarSync::class)->apply($this->feed, $this->pruefungsIcs('MAT', CarbonImmutable::now('Europe/Zurich')->addDays(10)->setTime(10, 0)));

        $this->assertSame([$beruf->fach_id], Pruefung::pluck('fach_id')->all());
    }

    #[Test]
    public function track_fach_wird_gebunden_wenn_der_track_bis_zum_pruefungsdatum_beginnt(): void
    {
        $bms = Fach::factory()->create(['track_typ' => 'BMS', 'kurzname' => 'BMX', 'name' => 'BMS Fach']);
        $pruefung = CarbonImmutable::now('Europe/Zurich')->addDays(20)->setTime(10, 0);
        $this->track('BMS', $pruefung->subDays(5)->toDateString());

        app(CalendarSync::class)->apply($this->feed, $this->pruefungsIcs('BMX', $pruefung));

        $this->assertSame([$bms->fach_id], Pruefung::pluck('fach_id')->all());
    }

    #[Test]
    public function track_fach_wird_nicht_gebunden_wenn_der_track_nach_dem_pruefungsdatum_beginnt(): void
    {
        Fach::factory()->create(['track_typ' => 'BMS', 'kurzname' => 'BMX', 'name' => 'BMS Fach']);
        $pruefung = CarbonImmutable::now('Europe/Zurich')->addDays(20)->setTime(10, 0);
        $this->track('BMS', $pruefung->addDays(5)->toDateString());

        $stats = app(CalendarSync::class)->apply($this->feed, $this->pruefungsIcs('BMX', $pruefung));

        $this->assertSame(1, $stats['unmatched']);
        $this->assertSame(0, Pruefung::count());
    }

    #[Test]
    public function termine_ohne_zeitzone_gelten_in_der_zeitzone_der_anwendung(): void
    {
        config(['app.timezone' => 'Europe/Zurich']);
        $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00', 'Europe/Zurich'));
        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//x//DE\r\n"
            ."BEGIN:VEVENT\r\nUID:f1\r\nDTSTAMP:20261001T000000Z\r\nDTSTART:20261109T080000\r\nDTEND:20261109T090000\r\nSUMMARY:Termin f1\r\nEND:VEVENT\r\n"
            ."BEGIN:VEVENT\r\nUID:t1\r\nDTSTAMP:20261001T000000Z\r\nDTSTART;TZID=Europe/Zurich:20261109T080000\r\nDTEND;TZID=Europe/Zurich:20261109T090000\r\nSUMMARY:Termin t1\r\nEND:VEVENT\r\n"
            ."END:VCALENDAR\r\n";

        app(CalendarSync::class)->apply($this->feed, $ics);

        $this->assertSame('08:00', CalendarEvent::where('uid', 'f1')->firstOrFail()->starts_at->format('H:i'));
        $this->assertSame('08:00', CalendarEvent::where('uid', 't1')->firstOrFail()->starts_at->format('H:i'));
    }
}
