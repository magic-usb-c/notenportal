<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Models\CalendarEvent;
use App\Models\CalendarFeed;
use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Pruefung;
use App\Models\User;
use App\Services\Calendar\CalendarSync;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Funktionsprüfung Runde 15, Gruppe «jobs»: Ein 304 darf Prüfungen nicht aussperren, die erst durch geänderte
 * Stammdaten (Fach freigeschaltet) zuordenbar werden.
 */
class Kalender304StammdatenTest extends TestCase
{
    private User $user;

    private Fach $fach;

    private CalendarFeed $feed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->lernender()->create();
        $this->fach = Fach::factory()->create([
            'track_typ' => null,
            'kategorie_id' => Kategorie::where('code', 'FACH')->value('kategorie_id'),
            'kurzname' => 'MAT', 'name' => 'Mathematik',
        ]);
        $this->feed = CalendarFeed::create(['lernender_id' => $this->user->lernender->lernender_id, 'url' => 'https://93.184.216.34/x.ics']);
    }

    private function ics(int $tageAb): string
    {
        $start = CarbonImmutable::now('Europe/Zurich')->addDays($tageAb)->setTime(10, 0);
        $f = fn (CarbonImmutable $d) => $d->format('Ymd\THis');
        $text = "Prüfung\nMAT-INXX 99 B-lehrer LB1: Algebra\nPrüfungsart: schriftlich\nDauer: 45 Minuten\nGewichtung\n1\n";

        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Schulnetz//DE\r\nBEGIN:VEVENT\r\nUID:e1\r\nDTSTAMP:20260901T120000Z\r\n"
            ."DTSTART;TZID=Europe/Zurich:{$f($start)}\r\nDTEND;TZID=Europe/Zurich:{$f($start->addMinutes(45))}\r\nSUMMARY:MAT-INXX 99 B-lehrer\r\n"
            .'DESCRIPTION:'.str_replace(["\n", ','], ['\\n', '\\,'], $text)."\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
    }

    /** Server wie in Wirklichkeit: 304 nur bei passendem If-None-Match, sonst die ganze Datei. */
    private function server(int $tageAb): void
    {
        Http::fake(function ($anfrage) use ($tageAb) {
            if ($anfrage->header('If-None-Match') === ['"v1"']) {
                return Http::response('', 304);
            }

            return Http::response($this->ics($tageAb), 200, ['ETag' => '"v1"']);
        });
    }

    private function fachFreigeben(): void
    {
        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $this->user->lernender->lehrberuf_id, 'fach_id' => $this->fach->fach_id, 'aktiv' => 1]);
    }

    #[Test]
    public function nach_freigabe_des_fachs_kommt_die_offene_pruefung_trotz_etag(): void
    {
        $this->server(10);
        $erster = app(CalendarSync::class)->sync($this->feed->fresh());
        $this->assertSame(1, $erster['unmatched']);
        $this->assertSame(0, Pruefung::count());
        $this->assertSame('"v1"', $this->feed->fresh()->etag);

        $this->fachFreigeben();
        $zweiter = app(CalendarSync::class)->sync($this->feed->fresh());

        $this->assertFalse($zweiter['unchanged'], 'mit offenem künftigem Termin wird voll abgerufen');
        $this->assertSame(1, Pruefung::count(), 'Prüfung nach Freigabe übernommen');
        $this->assertNotNull(CalendarEvent::where('uid', 'e1')->value('pruefung_id'));
    }

    #[Test]
    public function ohne_offene_termine_greift_der_304_weiterhin(): void
    {
        $this->fachFreigeben();
        $this->server(10);
        $erster = app(CalendarSync::class)->sync($this->feed->fresh());
        $this->assertSame(1, $erster['exams']);

        $zweiter = app(CalendarSync::class)->sync($this->feed->fresh());

        $this->assertTrue($zweiter['unchanged']);
        Http::assertSent(fn ($anfrage) => $anfrage->hasHeader('If-None-Match', '"v1"'));
    }

    #[Test]
    public function vergangener_unzugeordneter_termin_erzwingt_keinen_dauerhaften_vollabruf(): void
    {
        $this->server(10);
        app(CalendarSync::class)->sync($this->feed->fresh());
        // Der Termin liegt inzwischen in der Vergangenheit und bleibt unzugeordnet liegen.
        CalendarEvent::where('uid', 'e1')->update(['starts_at' => now()->subDays(3)]);

        $zweiter = app(CalendarSync::class)->sync($this->feed->fresh());

        $this->assertTrue($zweiter['unchanged']);
    }
}
