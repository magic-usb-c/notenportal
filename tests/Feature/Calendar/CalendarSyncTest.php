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
use RuntimeException;
use Tests\TestCase;

class CalendarSyncTest extends TestCase
{
    private User $user;

    private Modul $modul;

    private CalendarFeed $feed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->lernender()->create();
        $this->modul = Modul::factory()->create(['modul_nummer' => '159', 'titel' => 'Directory Services konfigurieren']);
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $this->user->lernender->lehrberuf_id,
            'modul_id' => $this->modul->modul_id,
            'kategorie_id' => Kategorie::where('code', 'FACH')->value('kategorie_id') ?? Kategorie::value('kategorie_id'),
        ]);
        $this->feed = CalendarFeed::create(['lernender_id' => $this->user->lernender->lernender_id, 'url' => 'https://schulnetz.example/ical/geheim']);
    }

    private function ics(array $events): string
    {
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Schulnetz//DE\r\n".implode('', $events)."END:VCALENDAR\r\n";
    }

    private function event(string $uid, CarbonImmutable $start, string $summary, string $beschreibung = '', string $ort = '', int $minuten = 45): string
    {
        $fmt = fn (CarbonImmutable $d) => $d->format('Ymd\THis');
        $desc = $beschreibung !== '' ? 'DESCRIPTION:'.str_replace(["\n", ','], ['\\n', '\\,'], $beschreibung)."\r\n" : '';

        return "BEGIN:VEVENT\r\nUID:{$uid}\r\nDTSTAMP:20260901T120000Z\r\nDTSTART;TZID=Europe/Zurich:{$fmt($start)}\r\n"
            ."DTEND;TZID=Europe/Zurich:{$fmt($start->addMinutes($minuten))}\r\nSUMMARY:{$summary}\r\n{$desc}"
            .($ort !== '' ? "LOCATION:{$ort}\r\n" : '')."END:VEVENT\r\n";
    }

    private function pruefungsEvent(string $uid = 'exam-1', ?CarbonImmutable $start = null): string
    {
        $text = "Prüfung\n159-INPE 24 B-diemar LB1: Verzeichnisdienste und DNS\nPrüfungsstoff\nPrüfungsart: Onlineprüfung in Microsoft Teams\n"
            ."Dauer: 45 Minuten\nHilfsmittel: Keine Unterlagen erlaubt\nSie können:\n- Verzeichnisdienste erklären.\nGewichtung\n0.33333333\n"
            ."Prüfungsdatum festgelegt am\n01.09.2026 13:54";

        return $this->event($uid, $start ?? CarbonImmutable::now('Europe/Zurich')->addDays(14)->setTime(13, 15), '159-INPE 24 B-diemar', $text, 'A5');
    }

    #[Test]
    public function pruefungen_lektionen_und_termine_werden_abgeglichen(): void
    {
        $morgen = CarbonImmutable::now('Europe/Zurich')->addDay()->setTime(8, 50);
        $stats = app(CalendarSync::class)->apply($this->feed, $this->ics([
            $this->pruefungsEvent(),
            $this->event('lesson-1', $morgen, '159-INPE 24 B-diemar', '', 'A5'),
            $this->event('termin-1', $morgen->addDays(3)->setTime(19, 0), 'Elternabend'),
            $this->event('exam-x', $morgen->addDays(5), 'Prüfung 999-INPE 24 B-abcdef LB1', "Prüfung\n999-INPE 24 B-abcdef LB1: Unbekannt"),
        ]));

        $this->assertSame(['events' => 4, 'exams' => 1, 'unmatched' => 1, 'removed' => 0], $stats);
        $p = Pruefung::sole();
        $this->assertSame($this->modul->modul_id, $p->modul_id);
        $this->assertSame('LB1: Verzeichnisdienste und DNS', $p->titel);
        $this->assertSame('13:15:00', $p->uhrzeit);
        $this->assertSame(45, $p->dauer_minuten);
        $this->assertSame('Onlineprüfung in Microsoft Teams', $p->pruefungsart);
        $this->assertSame('Keine Unterlagen erlaubt', $p->hilfsmittel);
        $this->assertSame(33.33, $p->gewichtung_prozent);
        $this->assertStringContainsString('- Verzeichnisdienste erklären.', $p->stoff);
        $this->assertSame('ical', $p->quelle);
        $this->assertSame('A5', $p->raum);
        $this->assertSame(['appointment' => 1, 'exam' => 2, 'lesson' => 1],
            CalendarEvent::query()->selectRaw('kind, COUNT(*) n')->groupBy('kind')->orderBy('kind')->pluck('n', 'kind')->map(fn ($n) => (int) $n)->all());
        $this->assertSame($p->pruefung_id, CalendarEvent::where('uid', 'exam-1')->value('pruefung_id'));
    }

    #[Test]
    public function erneuter_abgleich_behaelt_notizen_und_raeumt_verschwundenes_auf(): void
    {
        $sync = app(CalendarSync::class);
        $sync->apply($this->feed, $this->ics([$this->pruefungsEvent('exam-1'), $this->pruefungsEvent('exam-2', CarbonImmutable::now('Europe/Zurich')->addDays(20)->setTime(8, 0))]));
        $this->assertSame(2, Pruefung::count());
        Pruefung::where('extern_uid', 'exam-1')->update(['notizen' => 'Kapitel 3 lesen']);

        // Verschoben: exam-1 neues Datum, exam-2 fehlt
        $neu = CarbonImmutable::now('Europe/Zurich')->addDays(15)->setTime(10, 0);
        $sync->apply($this->feed, $this->ics([$this->pruefungsEvent('exam-1', $neu)]));

        $p = Pruefung::where('extern_uid', 'exam-1')->sole();
        $this->assertSame('Kapitel 3 lesen', $p->notizen);
        $this->assertSame($neu->toDateString(), $p->datum->toDateString());
        $this->assertNull(Pruefung::where('extern_uid', 'exam-2')->first(), 'unberührte verschwundene Prüfung wird gelöscht');

        // Angefasste Prüfung verschwindet → abgesagt statt gelöscht
        $sync->apply($this->feed, $this->ics([]));
        $this->assertNotNull(Pruefung::where('extern_uid', 'exam-1')->sole()->abgesagt_am);
        $this->assertSame(0, Pruefung::query()->offen()->count());
        $this->assertSame(0, CalendarEvent::count());
    }

    #[Test]
    public function lektionen_lassen_sich_abwaehlen(): void
    {
        $this->feed->update(['import_lessons' => false]);
        app(CalendarSync::class)->apply($this->feed, $this->ics([
            $this->event('lesson-1', CarbonImmutable::now('Europe/Zurich')->addDay()->setTime(8, 50), '159-INPE 24 B-diemar'),
            $this->pruefungsEvent(),
        ]));

        $this->assertSame(['exam'], CalendarEvent::pluck('kind')->all());
    }

    #[Test]
    public function abruf_nur_oeffentlicher_adressen_und_webcal(): void
    {
        $this->assertSame('https://schulnetz.example/x.ics', CalendarSync::normalizeUrl(' webcal://schulnetz.example/x.ics '));

        foreach (['http://127.0.0.1/x.ics', 'http://10.1.2.3/x.ics', 'http://[::1]/x.ics', 'ftp://example.org/x.ics', 'kein-link'] as $url) {
            try {
                CalendarSync::assertPublicUrl($url);
                $this->fail("{$url} hätte abgelehnt werden müssen");
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }

        Http::fake(['https://93.184.216.34/*' => Http::sequence()
            ->push($this->ics([$this->pruefungsEvent()]), 200, ['Content-Type' => 'text/calendar'])
            ->push('<html>Login</html>', 200)]);
        $this->feed->update(['url' => 'webcal://93.184.216.34/cal.ics']);
        $stats = app(CalendarSync::class)->sync($this->feed->fresh());

        $this->assertSame(1, $stats['exams']);
        $this->assertSame(CalendarFeed::OK, $this->feed->fresh()->last_status);

        $fehler = null;
        try {
            app(CalendarSync::class)->sync($this->feed->fresh());
        } catch (\Throwable $e) {
            $fehler = $e;
        }
        $this->assertInstanceOf(RuntimeException::class, $fehler);
        $this->assertStringContainsString('keinen iCal-Kalender', $fehler->getMessage());
        $this->assertSame(CalendarFeed::ERROR, $this->feed->fresh()->last_status);
    }

    #[Test]
    public function url_wird_verschluesselt_gespeichert(): void
    {
        $roh = DB::table('calendar_feeds')->where('id', $this->feed->id)->value('url');

        $this->assertStringNotContainsString('geheim', $roh);
        $this->assertSame('schulnetz.example', $this->feed->fresh()->host());
    }
}
