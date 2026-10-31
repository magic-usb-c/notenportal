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
use Illuminate\Database\QueryException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Sabre\VObject\Recur\MaxInstancesExceededException;
use Sabre\VObject\Settings;
use Tests\TestCase;

/**
 * Befunde der Funktionsprüfung zum Kalenderabgleich: gzip-Antworten, lange Terminserien,
 * Schalterwechsel trotz ETag, nicht entschlüsselbare Adressen, überlange Beschreibungen.
 */
class KalenderFunktionspruefungTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['notenportal.calendar_allow_private' => true]);
        $this->user = User::factory()->lernender()->create();
    }

    private function lid(): int
    {
        return (int) $this->user->lernender->lernender_id;
    }

    private function feed(string $url = 'https://cal.example.org/x.ics'): CalendarFeed
    {
        return CalendarFeed::create(['lernender_id' => $this->lid(), 'url' => $url]);
    }

    private function ics(string ...$events): string
    {
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//t//EN\r\n".implode('', $events)."END:VCALENDAR\r\n";
    }

    private function termin(string $uid, string $summary = 'Elternabend', int $tage = 5, string $beschreibung = ''): string
    {
        $d = date('Ymd', strtotime("+{$tage} days"));
        $desc = $beschreibung !== '' ? "DESCRIPTION:{$beschreibung}\r\n" : '';

        return "BEGIN:VEVENT\r\nUID:{$uid}\r\nDTSTART:{$d}T080000\r\nDTEND:{$d}T090000\r\nSUMMARY:{$summary}\r\n{$desc}END:VEVENT\r\n";
    }

    // ------------------------------------------------------------------ Befund 16: gzip

    #[Test]
    public function abgleich_fragt_komprimierung_selbst_an_und_entpackt_gzip_antworten_mit_obergrenze(): void
    {
        $ics = $this->ics($this->termin('g1'));
        Http::fake(['*' => Http::response(gzencode($ics), 200, ['Content-Encoding' => 'gzip'])]);

        $stats = app(CalendarSync::class)->sync($this->feed());

        $this->assertSame(1, $stats['events']);
        Http::assertSent(fn (Request $r) => $r->header('Accept-Encoding') === ['gzip, deflate']);
    }

    #[Test]
    public function deflate_antwort_wird_entpackt(): void
    {
        Http::fake(['*' => Http::response(gzcompress($this->ics($this->termin('d1'))), 200, ['Content-Encoding' => 'deflate'])]);

        $this->assertSame(1, app(CalendarSync::class)->sync($this->feed())['events']);
    }

    #[Test]
    public function gzip_bombe_ueber_der_grenze_wird_beim_entpacken_abgewiesen(): void
    {
        // 8 MB Nullen sind komprimiert wenige KB gross – die Prüfung auf die komprimierte Länge allein greift nicht.
        $bombe = gzencode("BEGIN:VCALENDAR\r\n".str_repeat('A', 8_000_000), 9);
        $this->assertLessThan(100_000, strlen($bombe));
        Http::fake(['*' => Http::response($bombe, 200, ['Content-Encoding' => 'gzip'])]);
        $feed = $this->feed();

        try {
            app(CalendarSync::class)->sync($feed);
            $this->fail('Die Bombe wurde angenommen.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('grösser als 5 MB', $e->getMessage());
        }
        $this->assertStringContainsString('grösser als 5 MB', (string) $feed->fresh()->last_error);
    }

    #[Test]
    public function unbekannte_kodierung_wird_mit_eigener_meldung_abgelehnt(): void
    {
        Http::fake(['*' => Http::response('irgendwas', 200, ['Content-Encoding' => 'br'])]);
        $feed = $this->feed();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Übertragungsformat');
        app(CalendarSync::class)->sync($feed);
    }

    #[Test]
    public function beschaedigte_gzip_antwort_meldet_keinen_i_cal_fehler(): void
    {
        Http::fake(['*' => Http::response('kein gzip', 200, ['Content-Encoding' => 'gzip'])]);

        try {
            app(CalendarSync::class)->sync($this->feed());
            $this->fail('Beschädigte Antwort wurde angenommen.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('lässt sich nicht entpacken', $e->getMessage());
        }
    }

    // ------------------------------------------------------------------ Befund 17: lange Serien

    #[Test]
    public function lange_taegliche_serie_laesst_den_kalender_nicht_scheitern(): void
    {
        $tag = date('Ymd', strtotime('+3 days'));
        $serie = "BEGIN:VEVENT\r\nUID:alt\r\nDTSTART:20150101T080000Z\r\nDTEND:20150101T081000Z\r\nRRULE:FREQ=DAILY\r\nSUMMARY:Taegliche Erinnerung\r\nEND:VEVENT\r\n"
            // Eine verschobene Einzelinstanz der Serie (RECURRENCE-ID) muss weiter gelten.
            ."BEGIN:VEVENT\r\nUID:alt\r\nRECURRENCE-ID:{$tag}T080000Z\r\nDTSTART:{$tag}T120000Z\r\nDTEND:{$tag}T121000Z\r\nSUMMARY:Verschoben\r\nEND:VEVENT\r\n";
        Http::fake(['*' => Http::response($this->ics($serie, $this->termin('normal', 'Normaler Termin')), 200)]);
        $feed = $this->feed();

        $stats = app(CalendarSync::class)->sync($feed);

        $this->assertSame('ok', $feed->fresh()->last_status);
        $this->assertGreaterThan(400, $stats['events']);
        $this->assertTrue(CalendarEvent::where('summary', 'Normaler Termin')->exists());
        $this->assertTrue(CalendarEvent::where('summary', 'Verschoben')->exists());
        $this->assertSame(0, CalendarEvent::where('summary', 'Taegliche Erinnerung')->whereDate('starts_at', date('Y-m-d', strtotime('+3 days')))->count(), 'Verschobene Instanz darf nicht doppelt erscheinen');
    }

    #[Test]
    public function die_wiederholungsgrenze_von_sabre_bleibt_nach_dem_abgleich_unveraendert(): void
    {
        $vorher = Settings::$maxRecurrences;
        Http::fake(['*' => Http::response($this->ics($this->termin('x1')), 200)]);
        app(CalendarSync::class)->sync($this->feed());
        $this->assertSame($vorher, Settings::$maxRecurrences);
    }

    #[Test]
    public function auch_bei_zu_vielen_wiederholungen_wird_die_grenze_zurueckgesetzt_und_die_meldung_stimmt(): void
    {
        $vorher = Settings::$maxRecurrences;
        // minütliche Serie seit 1990 überschreitet jede Grenze
        $ueberlang = "BEGIN:VEVENT\r\nUID:viel\r\nDTSTART:19900101T080000Z\r\nDTEND:19900101T081000Z\r\nRRULE:FREQ=MINUTELY\r\nSUMMARY:Dauerlauf\r\nEND:VEVENT\r\n";
        Http::fake(['*' => Http::response($this->ics($ueberlang), 200)]);
        $feed = $this->feed('https://cal.example.org/y.ics');
        try {
            app(CalendarSync::class)->sync($feed);
            $this->fail('Erwartet: Fehler wegen zu vieler Wiederholungen.');
        } catch (MaxInstancesExceededException $e) {
            $this->assertStringContainsString('Wiederholungen', CalendarSync::fehlertext($e));
            $this->assertStringNotContainsString('nicht erreichbar', CalendarSync::fehlertext($e));
        }
        $this->assertSame($vorher, Settings::$maxRecurrences);
    }

    // ------------------------------------------------------------------ Befund 18: ETag und Schalter

    #[Test]
    public function schalter_wechsel_holt_den_kalender_trotz_e_tag_neu(): void
    {
        $ics = $this->ics($this->termin('t1'));
        Http::fake(function ($req) use ($ics) {
            if ($req->hasHeader('If-None-Match') && $req->header('If-None-Match')[0] === '"v1"') {
                return Http::response('', 304);
            }

            return Http::response($ics, 200, ['ETag' => '"v1"']);
        });
        $this->actingAs($this->user);
        $this->post(route('learner.calendar.feed.store'), ['url' => 'https://cal.example.org/x.ics', 'import_lessons' => 0, 'import_appointments' => 0, 'import_exams' => 1]);
        $fid = (int) DB::table('calendar_feeds')->value('id');
        $this->post(route('learner.calendar.feed.sync', $fid));
        $this->assertSame(0, CalendarEvent::count());
        $this->assertSame('"v1"', DB::table('calendar_feeds')->value('etag'));

        // Termine einschalten: der Stempel gehört zum alten Schalterstand und muss weg
        $this->post(route('learner.calendar.feed.store'), ['feed_id' => $fid, 'url' => '', 'import_lessons' => 1, 'import_appointments' => 1, 'import_exams' => 1]);
        $this->assertNull(DB::table('calendar_feeds')->value('etag'));
        $this->post(route('learner.calendar.feed.sync', $fid));
        $this->assertSame(1, CalendarEvent::count());

        // Ausschalten wirkt ebenso beim nächsten Abgleich
        $this->post(route('learner.calendar.feed.store'), ['feed_id' => $fid, 'url' => '', 'import_lessons' => 1, 'import_appointments' => 0, 'import_exams' => 1]);
        $this->post(route('learner.calendar.feed.sync', $fid));
        $this->assertSame(0, CalendarEvent::count());
    }

    #[Test]
    public function speichern_ohne_aenderung_der_schalter_behaelt_den_e_tag(): void
    {
        $this->actingAs($this->user);
        $this->post(route('learner.calendar.feed.store'), ['url' => 'https://cal.example.org/x.ics', 'import_lessons' => 1, 'import_appointments' => 1, 'import_exams' => 1]);
        $fid = (int) DB::table('calendar_feeds')->value('id');
        DB::table('calendar_feeds')->where('id', $fid)->update(['etag' => '"v9"', 'last_modified' => 'Mon, 01 Jan 2029 00:00:00 GMT']);

        $this->post(route('learner.calendar.feed.store'), ['feed_id' => $fid, 'url' => '', 'label' => 'Neuer Name', 'import_lessons' => 1, 'import_appointments' => 1, 'import_exams' => 1]);

        $this->assertSame('"v9"', DB::table('calendar_feeds')->where('id', $fid)->value('etag'));
    }

    // ------------------------------------------------------------------ Befund 19: fremder APP_KEY

    private function fremdVerschluesseln(CalendarFeed $feed): void
    {
        $fremd = new Encrypter(random_bytes(32), config('app.cipher'));
        DB::table('calendar_feeds')->where('id', $feed->id)->update(['url' => $fremd->encryptString((string) $feed->url)]);
    }

    #[Test]
    public function nicht_entschluesselbare_adresse_laesst_die_einstellungen_laden_und_den_kalender_entfernen(): void
    {
        $feed = $this->feed();
        $this->fremdVerschluesseln($feed);

        $this->actingAs($this->user)->get(route('settings.calendar'))
            ->assertOk()
            ->assertSee(__('Adresse nicht lesbar'));

        $this->actingAs($this->user)->delete(route('learner.calendar.feed.destroy', $feed->id))->assertRedirect();
        $this->assertSame(0, CalendarFeed::count());
    }

    #[Test]
    public function nicht_entschluesselbare_adresse_laesst_sich_durch_neue_eingabe_reparieren(): void
    {
        $feed = $this->feed();
        $this->fremdVerschluesseln($feed);
        Http::fake(['*' => Http::response($this->ics($this->termin('r1')), 200)]);

        $this->actingAs($this->user)->post(route('learner.calendar.feed.store'), [
            'feed_id' => $feed->id, 'url' => 'https://cal.example.org/neu.ics',
            'import_lessons' => 1, 'import_appointments' => 1, 'import_exams' => 1,
        ])->assertRedirect(route('settings.calendar'));

        $this->assertSame('https://cal.example.org/neu.ics', $feed->fresh()->url);
    }

    #[Test]
    public function cron_gleicht_nach_einem_nicht_entschluesselbaren_kalender_die_weiteren_ab(): void
    {
        $kaputt = $this->feed('https://cal.example.org/a.ics');
        $this->fremdVerschluesseln($kaputt);
        Http::fake(['*' => Http::response($this->ics($this->termin('g1')), 200)]);
        $u2 = User::factory()->lernender()->create();
        $gut = CalendarFeed::create(['lernender_id' => $u2->lernender->lernender_id, 'url' => 'https://cal.example.org/b.ics']);

        $code = Artisan::call('calendar:sync');

        $this->assertSame(0, $code);
        $this->assertSame(1, CalendarEvent::where('calendar_feed_id', $gut->id)->count());
        $kaputt = $kaputt->fresh();
        $this->assertSame(CalendarFeed::ERROR, $kaputt->last_status);
        $this->assertStringContainsString('Adresse', (string) $kaputt->last_error);
        $this->assertStringNotContainsString('nicht erreichbar', (string) $kaputt->last_error);
    }

    #[Test]
    public function sammelabgleich_ueberlebt_eine_nicht_entschluesselbare_adresse(): void
    {
        $kaputt = $this->feed();
        $this->fremdVerschluesseln($kaputt);

        $this->assertSame('', $kaputt->fresh()->host());

        $this->actingAs($this->user)->post(route('learner.calendar.sync'))
            ->assertRedirect(route('settings.calendar'))
            ->assertSessionHas('error');
    }

    // ------------------------------------------------------------------ Befund 21: überlange Felder

    #[Test]
    public function termin_mit_ueberlanger_beschreibung_laesst_den_rest_des_kalenders_nicht_scheitern(): void
    {
        foreach ([str_repeat('x', 70_000), str_repeat('ä', 40_000)] as $i => $text) {
            DB::table('calendar_events')->delete();
            Http::fake(['*' => Http::response($this->ics($this->termin('n1', 'Normal', 3), $this->termin('n2', 'Newsletter', 3, $text)), 200)]);
            $feed = $this->feed("https://cal.example.org/lang{$i}.ics");

            $stats = app(CalendarSync::class)->sync($feed);

            $this->assertSame(2, $stats['events']);
            $this->assertSame(2, CalendarEvent::count());
            $this->assertLessThanOrEqual(65_535, strlen((string) CalendarEvent::where('summary', 'Newsletter')->value('description')));
        }
    }

    #[Test]
    public function pruefung_mit_ueberlangem_stoff_wird_gekuerzt_statt_den_abgleich_abzubrechen(): void
    {
        $modul = Modul::factory()->create(['modul_nummer' => '902', 'titel' => 'Testmodul Beta betreiben']);
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $this->user->lernender->lehrberuf_id,
            'modul_id' => $modul->modul_id,
            'kategorie_id' => Kategorie::where('code', 'FACH')->value('kategorie_id') ?? Kategorie::value('kategorie_id'),
        ]);
        $start = CarbonImmutable::now('Europe/Zurich')->addDays(14)->setTime(13, 15);
        $text = 'Prüfung\\n902-INXX 99 B-musmax LB1: Beta\\nPrüfungsstoff\\nSie können:\\n- '.str_repeat('ä', 40_000);
        $event = "BEGIN:VEVENT\r\nUID:ex1\r\nDTSTART:{$start->format('Ymd\THis')}\r\nDTEND:{$start->addMinutes(45)->format('Ymd\THis')}\r\nSUMMARY:902-INXX 99 B-musmax\r\nDESCRIPTION:{$text}\r\nEND:VEVENT\r\n";
        Http::fake(['*' => Http::response($this->ics($event, $this->termin('n1')), 200)]);

        $stats = app(CalendarSync::class)->sync($this->feed());

        $this->assertSame(1, $stats['exams']);
        $this->assertLessThanOrEqual(65_535, strlen((string) Pruefung::sole()->stoff));
    }

    #[Test]
    public function datenbankfehler_beim_abgleich_wird_nicht_als_erreichbarkeitsproblem_gemeldet(): void
    {
        $e = new QueryException('mysql', 'insert into x', [], new \PDOException('Data too long'));

        $this->assertStringNotContainsString('nicht erreichbar', CalendarSync::fehlertext($e));
        $this->assertStringContainsString('nicht gespeichert', CalendarSync::fehlertext($e));
    }
}
