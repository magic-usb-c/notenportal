<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Models\CalendarEvent;
use App\Models\CalendarFeed;
use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Modul;
use App\Models\Pruefung;
use App\Models\User;
use App\Services\Calendar\CalendarSync;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
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
        return $this->pruefungsEventVariante($uid, $start ?? CarbonImmutable::now('Europe/Zurich')->addDays(14)->setTime(13, 15), 'A5', 'Verzeichnisdienste erklären.');
    }

    /** Wie pruefungsEvent(), aber mit wählbarem Raum/Stoff – zum Simulieren eines geänderten Quellzustands. */
    private function pruefungsEventVariante(string $uid, CarbonImmutable $start, string $ort, string $stoffZeile): string
    {
        $text = "Prüfung\n159-INPE 24 B-diemar LB1: Verzeichnisdienste und DNS\nPrüfungsstoff\nPrüfungsart: Onlineprüfung in Microsoft Teams\n"
            ."Dauer: 45 Minuten\nHilfsmittel: Keine Unterlagen erlaubt\nSie können:\n- {$stoffZeile}\nGewichtung\n0.33333333\n"
            ."Prüfungsdatum festgelegt am\n01.09.2026 13:54";

        return $this->event($uid, $start, '159-INPE 24 B-diemar', $text, $ort);
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

        foreach (['http://127.0.0.1/x.ics', 'http://10.1.2.3/x.ics', 'http://[::1]/x.ics', 'http://[::ffff:169.254.169.254]/x.ics',
            'http://[::ffff:127.0.0.1]/x.ics', 'http://[64:ff9b::a00:1]/x.ics', 'ftp://example.org/x.ics', 'kein-link'] as $url) {
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

    #[Test]
    public function termin_ohne_beginn_wird_uebersprungen_und_dauer_ersetzt_das_ende(): void
    {
        $stats = app(CalendarSync::class)->apply($this->feed, $this->ics([
            "BEGIN:VEVENT\r\nUID:ohne-beginn\r\nDTSTAMP:20260901T120000Z\r\nSUMMARY:Kaputt\r\nEND:VEVENT\r\n",
            "BEGIN:VEVENT\r\nUID:mit-dauer\r\nDTSTAMP:20260901T120000Z\r\nDTSTART;TZID=Europe/Zurich:"
                .CarbonImmutable::now('Europe/Zurich')->addDays(2)->setTime(19, 0)->format('Ymd\THis')
                ."\r\nDURATION:PT90M\r\nSUMMARY:Elternabend\r\nEND:VEVENT\r\n",
        ]));

        $this->assertSame(1, $stats['events']);
        $termin = CalendarEvent::sole();
        $this->assertSame('Elternabend', $termin->summary);
        $this->assertSame(90, (int) $termin->starts_at->diffInMinutes($termin->ends_at));
    }

    #[Test]
    public function abruf_folgt_weiterleitung_und_meldet_http_fehler_und_uebergroesse(): void
    {
        Http::fake(['https://93.184.216.34/*' => Http::sequence()
            ->push('', 302, ['Location' => '/neu.ics'])
            ->push($this->ics([$this->pruefungsEvent()]), 200)
            ->push('Serverfehler', 500)
            ->push('BEGIN:VCALENDAR'.str_repeat('x', 5 * 1024 * 1024 + 1), 200)]);
        $this->feed->update(['url' => 'https://93.184.216.34/cal.ics']);

        $this->assertSame(1, app(CalendarSync::class)->sync($this->feed->fresh())['exams']);
        Http::assertSent(fn ($anfrage) => $anfrage->url() === 'https://93.184.216.34/neu.ics');

        foreach (['HTTP 500', 'grösser als 5 MB'] as $erwartet) {
            try {
                app(CalendarSync::class)->sync($this->feed->fresh());
                $this->fail("Fehler «{$erwartet}» erwartet");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString($erwartet, $e->getMessage());
            }
        }
        $this->assertSame(CalendarFeed::ERROR, $this->feed->fresh()->last_status);
    }

    #[Test]
    public function pruefung_ohne_modulnummer_wird_ueber_das_fachkuerzel_zugeordnet(): void
    {
        $fach = Fach::factory()->create(['name' => 'Mathematik', 'kurzname' => 'MATH']);
        $start = CarbonImmutable::now('Europe/Zurich')->addDays(10)->setTime(10, 0);

        $stats = app(CalendarSync::class)->apply($this->feed, $this->ics([
            $this->event('exam-math', $start, 'MATH-INPE 24 B-diemar', "Prüfung\nMATH-INPE 24 B-diemar LB2: Algebra"),
        ]));

        $this->assertSame(1, $stats['exams']);
        $this->assertSame($fach->fach_id, Pruefung::sole()->fach_id);
        $this->assertNull(Pruefung::sole()->modul_id);
    }

    #[Test]
    public function unaufloesbare_adresse_wird_abgelehnt_oeffentliche_ipv6_erlaubt(): void
    {
        try {
            CalendarSync::assertPublicUrl('https://gibt-es-nicht.invalid/x.ics');
            $this->fail('Nicht auflösbare Adresse hätte abgelehnt werden müssen');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('nicht auflösbar', $e->getMessage());
        }

        $this->assertSame(['2001:4860:4860::8888'], CalendarSync::assertPublicUrl('https://[2001:4860:4860::8888]/x.ics'));
    }

    #[Test]
    public function gesperrte_felder_ueberleben_den_abgleich_und_der_gemerkte_quellwert_wird_aktualisiert(): void
    {
        $sync = app(CalendarSync::class);
        $altesDatum = CarbonImmutable::now('Europe/Zurich')->addDays(14)->setTime(13, 15);
        $sync->apply($this->feed, $this->ics([$this->pruefungsEventVariante('exam-1', $altesDatum, 'A5', 'Verzeichnisdienste erklären.')]));

        $p = Pruefung::where('extern_uid', 'exam-1')->sole();
        $eigenesDatum = $p->datum->toDateString();
        // Lernender ändert Raum und Datum von Hand – wie es PruefungenController::update() täte.
        $p->update(['raum' => 'B12', 'datum' => $altesDatum->addDay()->toDateString(), 'lokal_gesperrt' => ['raum' => 'A5', 'datum' => $eigenesDatum]]);

        // Quelle liefert neuen Raum, neues Datum, neuen Stoff.
        $neuesDatum = $altesDatum->addDays(2);
        $sync->apply($this->feed, $this->ics([$this->pruefungsEventVariante('exam-1', $neuesDatum, 'A9', 'Neues Kapitel gelernt.')]));

        $p->refresh();
        $this->assertSame('B12', $p->raum, 'gesperrtes Feld bleibt beim eigenen Wert');
        $this->assertSame($altesDatum->addDay()->toDateString(), $p->datum->toDateString(), 'gesperrtes Datum bleibt beim eigenen Wert');
        $this->assertSame('A9', $p->lokal_gesperrt['raum'], 'gemerkter Quellwert wird trotz Sperre aktualisiert');
        $this->assertSame($neuesDatum->toDateString(), $p->lokal_gesperrt['datum']);
        $this->assertStringContainsString('Neues Kapitel gelernt.', $p->stoff, 'ungesperrtes Feld wird ganz normal übernommen');
    }

    #[Test]
    public function absage_der_quelle_kommt_trotz_sperre_durch(): void
    {
        $sync = app(CalendarSync::class);
        $sync->apply($this->feed, $this->ics([$this->pruefungsEvent('exam-1')]));
        $p = Pruefung::where('extern_uid', 'exam-1')->sole();
        // notizen macht die Prüfung «angefasst» → wird bei Absage statt gelöscht.
        $p->update(['raum' => 'B12', 'notizen' => 'Wichtig', 'lokal_gesperrt' => ['raum' => 'A5']]);

        $sync->apply($this->feed, $this->ics([]));

        $p->refresh();
        $this->assertNotNull($p->abgesagt_am, 'Absage der Quelle muss trotz Sperre durchkommen');
        $this->assertSame('B12', $p->raum, 'gesperrtes Feld bleibt trotz Absage erhalten');
    }

    #[Test]
    public function entsperren_stellt_beim_naechsten_abgleich_den_quellwert_wieder_her(): void
    {
        $sync = app(CalendarSync::class);
        $sync->apply($this->feed, $this->ics([$this->pruefungsEvent('exam-1')]));
        $p = Pruefung::where('extern_uid', 'exam-1')->sole();
        $p->update(['raum' => 'B12', 'lokal_gesperrt' => ['raum' => 'A5']]);

        // Entspricht PruefungenController::unlock(): Sperre leeren, kein Sofort-Abgleich.
        $p->update(['lokal_gesperrt' => null]);
        $sync->apply($this->feed, $this->ics([$this->pruefungsEvent('exam-1')]));

        $this->assertSame('A5', $p->fresh()->raum);
        $this->assertNull($p->fresh()->lokal_gesperrt);
    }

    #[Test]
    public function zwei_feeds_beeinflussen_sich_beim_aufraeumen_nicht(): void
    {
        $sync = app(CalendarSync::class);
        $feedB = CalendarFeed::create(['lernender_id' => $this->user->lernender->lernender_id, 'url' => 'https://schulnetz.example/zweiter']);

        $sync->apply($this->feed, $this->ics([$this->pruefungsEvent('exam-a')]));
        $sync->apply($feedB, $this->ics([$this->pruefungsEvent('exam-b', CarbonImmutable::now('Europe/Zurich')->addDays(20)->setTime(9, 0))]));

        // Feed B gleicht erneut ab, ohne seine Prüfung erneut zu liefern: darf nur exam-b betreffen.
        $sync->apply($feedB, $this->ics([]));

        $this->assertNull(Pruefung::where('extern_uid', 'exam-a')->sole()->abgesagt_am, 'Feed A bleibt von Feed Bs Abgleich unberührt');
        $this->assertNull(Pruefung::where('extern_uid', 'exam-b')->first(), 'unberührte Prüfung des abgleichenden Feeds wird trotzdem aufgeräumt');
    }

    #[Test]
    public function unveraenderter_kalender_liefert_304_und_schreibt_keine_pruefungen(): void
    {
        // Beide Antworten müssen in EINEM Http::fake()-Aufruf registriert werden: ein zweiter
        // Http::fake()-Aufruf für dasselbe URL-Muster würde den ersten Stub nicht ersetzen,
        // sondern nur ergänzen – Laravel liefert dann weiterhin die zuerst registrierte Antwort.
        Http::fake(['https://93.184.216.34/*' => Http::sequence()
            ->push($this->ics([$this->pruefungsEvent()]), 200, [
                'Content-Type' => 'text/calendar', 'ETag' => '"abc"', 'Last-Modified' => 'Wed, 01 Sep 2026 12:00:00 GMT',
            ])
            ->push('', 304),
        ]);
        $this->feed->update(['url' => 'https://93.184.216.34/cal.ics']);

        $stats1 = app(CalendarSync::class)->sync($this->feed->fresh());
        $this->assertFalse($stats1['unchanged']);
        $this->assertSame('"abc"', $this->feed->fresh()->etag);
        $this->assertSame('Wed, 01 Sep 2026 12:00:00 GMT', $this->feed->fresh()->last_modified);

        $vorher = Pruefung::count();
        $stats2 = app(CalendarSync::class)->sync($this->feed->fresh());

        $this->assertTrue($stats2['unchanged']);
        $this->assertSame(CalendarFeed::OK, $this->feed->fresh()->last_status);
        $this->assertSame($vorher, Pruefung::count(), 'ein 304 darf keine Prüfungen schreiben');
        Http::assertSent(fn ($anfrage) => $anfrage->hasHeader('If-None-Match', '"abc"')
            && $anfrage->hasHeader('If-Modified-Since', 'Wed, 01 Sep 2026 12:00:00 GMT'));
    }

    #[Test]
    public function nextcloud_freigabelink_wird_zur_exportadresse_umgeschrieben(): void
    {
        $faelle = [
            'https://cloud.example.org/apps/calendar/p/AbCd1234' => 'https://cloud.example.org/remote.php/dav/public-calendars/AbCd1234?export',
            'https://cloud.example.org/index.php/apps/calendar/p/AbCd1234' => 'https://cloud.example.org/index.php/remote.php/dav/public-calendars/AbCd1234?export',
            'https://cloud.example.org/apps/calendar/p/AbCd1234/Ferienplan' => 'https://cloud.example.org/remote.php/dav/public-calendars/AbCd1234?export',
            'webcal://cloud.example.org/apps/calendar/p/AbCd1234' => 'https://cloud.example.org/remote.php/dav/public-calendars/AbCd1234?export',
        ];
        foreach ($faelle as $eingabe => $erwartet) {
            $this->assertSame($erwartet, CalendarSync::normalizeUrl($eingabe), $eingabe);
        }
    }

    /**
     * Die iCal-Adresse ist ein Schlüssel – wer sie kennt, liest den Kalender mit. Guzzle hängt sie an
     * jede Verbindungsmeldung an; sie darf danach weder in der Datenbank noch in der Rückmeldung noch
     * im ausgelieferten HTML stehen. Der Host allein ist in Ordnung, den zeigt die Seite ohnehin.
     */
    #[Test]
    public function fehlermeldung_verraet_die_kalenderadresse_nicht(): void
    {
        $feed = CalendarFeed::create([
            'lernender_id' => $this->user->lernender->lernender_id,
            'url' => 'https://schulnetz.example/ical/GEHEIM-ABC123XYZ',
        ]);
        Http::fake(fn () => throw new ConnectionException(
            'cURL error 6: Could not resolve host: schulnetz.example (see https://curl.se/libcurl/c/libcurl-errors.html)'
            .' for https://schulnetz.example/ical/GEHEIM-ABC123XYZ'
        ));

        try {
            app(CalendarSync::class)->sync($feed);
            $this->fail('Der Abgleich hätte scheitern müssen.');
        } catch (\Throwable) {
            // erwartet – geprüft wird, was dabei gespeichert und angezeigt wird
        }

        $gespeichert = (string) $feed->fresh()->last_error;
        $this->assertSame(CalendarFeed::ERROR, $feed->fresh()->last_status);
        $this->assertStringNotContainsString('GEHEIM-ABC123XYZ', $gespeichert);
        $this->assertStringNotContainsString('/ical/', $gespeichert);

        $antwort = $this->actingAs($this->user)->post(route('learner.calendar.feed.sync', $feed->id));
        $this->assertStringNotContainsString('GEHEIM-ABC123XYZ', (string) $antwort->getSession()->get('error'));

        $this->actingAs($this->user)->get(route('settings.calendar'))
            ->assertOk()
            ->assertDontSee('GEHEIM-ABC123XYZ');
    }

    /**
     * Verschiebt die Schule eine Prüfung unter neuer UID, verschwindet die alte aus dem Feed. Eine Prüfung,
     * an der der Lernende ein Feld korrigiert (und damit gesperrt) hat, darf dabei nicht still verschwinden –
     * auch dann nicht, wenn sie weder Notiz noch Note noch Anhang hat.
     */
    #[Test]
    public function gesperrte_pruefung_wird_abgesagt_statt_geloescht(): void
    {
        $sync = app(CalendarSync::class);
        $sync->apply($this->feed, $this->ics([$this->pruefungsEvent('exam-1')]));

        $p = Pruefung::where('extern_uid', 'exam-1')->sole();
        $this->assertNull($p->notizen, 'Vorbedingung: sonst greift der bestehende Schutz');
        $p->lokal_gesperrt = ['datum' => $p->datum->toDateString()];
        $p->save();

        $sync->apply($this->feed, $this->ics([]));

        $danach = Pruefung::where('extern_uid', 'exam-1')->first();
        $this->assertNotNull($danach, 'gesperrte Prüfung darf nicht gelöscht werden');
        $this->assertNotNull($danach->abgesagt_am, 'sie gehört als abgesagt markiert');
    }

    /** Eigene, nachweislich adressfreie Meldungen bleiben erhalten – sonst wäre jeder Fehler gleich nichtssagend. */
    #[Test]
    public function eigene_fehlertexte_bleiben_verstaendlich(): void
    {
        $this->assertSame('Kalenderdatei ist grösser als 5 MB.', CalendarSync::fehlertext(new RuntimeException('Kalenderdatei ist grösser als 5 MB.')));
        $this->assertSame('schulnetz.example', CalendarSync::fehlertext(new RuntimeException('https://schulnetz.example/ical/GEHEIM-ABC123XYZ')));
    }
}
