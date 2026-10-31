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
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/** Eine unterwegs abgeschnittene Kalenderdatei darf weder Termine noch künftige Prüfungen löschen. */
class CalendarSyncAbgeschnittenTest extends TestCase
{
    private CalendarFeed $feed;

    private CarbonImmutable $start;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->lernender()->create();
        $modul = Modul::factory()->create(['modul_nummer' => '902', 'titel' => 'Testmodul Beta betreiben']);
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $user->lernender->lehrberuf_id,
            'modul_id' => $modul->modul_id,
            'kategorie_id' => Kategorie::where('code', 'FACH')->value('kategorie_id') ?? Kategorie::value('kategorie_id'),
        ]);
        $this->feed = CalendarFeed::create(['lernender_id' => $user->lernender->lernender_id, 'url' => 'https://93.184.216.34/x.ics']);
        $this->start = CarbonImmutable::now('Europe/Zurich')->addDays(3)->setTime(8, 0);
    }

    private function termin(string $uid, CarbonImmutable $start, bool $pruefung = true): string
    {
        $fmt = fn (CarbonImmutable $d) => $d->format('Ymd\THis');
        $text = $pruefung ? 'Prüfung\n902-INXX 99 B-musmax LB1: Beta-Grundlagen\nPrüfungsstoff\nPrüfungsart: Onlineprüfung' : 'Gruppenraum';

        return "BEGIN:VEVENT\r\nUID:{$uid}\r\nDTSTAMP:20260901T120000Z\r\nDTSTART;TZID=Europe/Zurich:{$fmt($start)}\r\n"
            ."DTEND;TZID=Europe/Zurich:{$fmt($start->addMinutes(45))}\r\nSUMMARY:902-INXX 99 B-musmax\r\nDESCRIPTION:{$text}\r\nEND:VEVENT\r\n";
    }

    private function kopf(): string
    {
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Schulnetz//DE\r\n";
    }

    private function abgleichen(string $body): void
    {
        // Ein zweites Http::fake() ergänzt nur; die erste passende Antwort gewönne. Darum zuerst zurücksetzen.
        app()->forgetInstance(Factory::class);
        Http::clearResolvedInstance(Factory::class);
        Http::fake(['*' => Http::response($body, 200)]);
        app(CalendarSync::class)->sync($this->feed->fresh());
    }

    /** @return array{0: int, 1: int} Anzahl Termine und Prüfungen */
    private function bestand(): array
    {
        return [CalendarEvent::count(), Pruefung::count()];
    }

    #[Test]
    public function abgeschnittene_datei_laesst_termine_und_pruefungen_unberuehrt_und_meldet_fehler(): void
    {
        $voll = $this->kopf().$this->termin('exam-1', $this->start).$this->termin('lesson-2', $this->start->addDay(), false)."END:VCALENDAR\r\n";
        $this->abgleichen($voll);
        $vorher = $this->bestand();
        $this->assertSame([2, 1], [$vorher[0], Pruefung::count()]);

        $mitten = $this->termin('exam-1', $this->start);
        $varianten = [
            'nur Kopf' => $this->kopf(),
            'nach dem ersten Termin' => $this->kopf().$mitten,
            'mitten im Termin' => $this->kopf().$mitten.substr($this->termin('lesson-2', $this->start->addDay(), false), 0, 120),
        ];
        foreach ($varianten as $name => $body) {
            try {
                $this->abgleichen($body);
                $this->fail("«{$name}» hätte einen Fehler melden müssen");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('unvollständig', $e->getMessage(), $name);
            }
            $this->assertSame($vorher, $this->bestand(), $name);
            $feed = $this->feed->fresh();
            $this->assertSame(CalendarFeed::ERROR, $feed->last_status, $name);
            $this->assertStringContainsString('unvollständig', (string) $feed->last_error, $name);
        }
    }

    #[Test]
    public function vollstaendiger_leerer_kalender_bleibt_gueltig(): void
    {
        $this->abgleichen($this->kopf().$this->termin('exam-1', $this->start)."END:VCALENDAR\r\n");
        $this->abgleichen($this->kopf()."END:VCALENDAR\r\n");

        $this->assertSame(CalendarFeed::OK, $this->feed->fresh()->last_status);
        $this->assertSame(0, CalendarEvent::count());
    }

    #[Test]
    public function abschluss_in_kleinschreibung_und_ohne_letzten_zeilenumbruch_zaehlt_als_vollstaendig(): void
    {
        $this->abgleichen($this->kopf().$this->termin('exam-1', $this->start).'end:vcalendar');

        $this->assertSame(CalendarFeed::OK, $this->feed->fresh()->last_status);
        $this->assertSame(1, CalendarEvent::count());
    }
}
