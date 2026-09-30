<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Http\Middleware\SetLocale;
use App\Models\Betreuung;
use App\Models\CalendarEvent;
use App\Models\CalendarFeed;
use App\Models\Kategorie;
use App\Models\Modul;
use App\Models\Note;
use App\Models\Pruefung;
use App\Models\User;
use App\Services\Calendar\CalendarExport;
use App\Support\Einstellungen;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Sabre\VObject\Reader;
use Tests\TestCase;

class CalendarExportTest extends TestCase
{
    private User $lernender;

    private Pruefung $pruefung;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lernender = User::factory()->lernender()->create(['vorname' => 'Lea', 'nachname' => 'Muster']);
        $modul = Modul::factory()->create(['modul_nummer' => '902', 'titel' => 'Testmodul Beta']);
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $this->lernender->lernender->lehrberuf_id,
            'modul_id' => $modul->modul_id,
            'kategorie_id' => Kategorie::value('kategorie_id'),
        ]);
        $this->pruefung = Pruefung::create([
            'lernender_id' => $this->lernender->lernender->lernender_id, 'modul_id' => $modul->modul_id, 'titel' => 'LB1',
            'datum' => now()->addDays(5)->toDateString(), 'uhrzeit' => '13:15:00', 'dauer_minuten' => 45,
            'pruefungsart' => 'Onlineprüfung', 'stoff' => "- DNS\n- DHCP", 'gewichtung_prozent' => 33.33,
        ]);
    }

    #[Test]
    public function lernender_exportiert_eigene_pruefungen_mit_details(): void
    {
        $ics = app(CalendarExport::class)->forUser($this->lernender);
        $event = Reader::read($ics)->VEVENT;

        $this->assertStringContainsString('Prüfung 902 Testmodul Beta – LB1', (string) $event->SUMMARY);
        $this->assertStringContainsString('Dauer: 45 Minuten', (string) $event->DESCRIPTION);
        $this->assertStringContainsString('- DHCP', (string) $event->DESCRIPTION);
        $this->assertSame('13:15', $event->DTSTART->getDateTime()->setTimezone(new \DateTimeZone('Europe/Zurich'))->format('H:i'));
        $this->assertSame(45 * 60, $event->DTEND->getDateTime()->getTimestamp() - $event->DTSTART->getDateTime()->getTimestamp());
    }

    #[Test]
    public function erfasste_stufe_erscheint_als_stufe_nicht_als_null(): void
    {
        $note = Note::factory()->create(['lernender_id' => $this->lernender->lernender->lernender_id, 'note_wert' => null, 'note_stufe' => 'A']);
        $this->pruefung->update(['note_id' => $note->note_id]);

        $beschreibung = (string) Reader::read(app(CalendarExport::class)->forUser($this->lernender))->VEVENT->DESCRIPTION;

        $this->assertStringContainsString('Note: A', $beschreibung);
        $this->assertStringNotContainsString('Note: 0', $beschreibung);
    }

    #[Test]
    public function berufsbildner_sieht_pruefungen_betreuter_mit_namen_fremde_nicht(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $fremderBb = User::factory()->berufsbildner()->create();
        Betreuung::create([
            'berufsbildner_id' => $bb->berufsbildner->berufsbildner_id,
            'lernender_id' => $this->lernender->lernender->lernender_id,
            'gueltig_von' => now()->subYear()->toDateString(),
        ]);

        $this->assertStringContainsString('Lea Muster: Prüfung', app(CalendarExport::class)->forUser($bb));
        $this->assertStringNotContainsString('BEGIN:VEVENT', app(CalendarExport::class)->forUser($fremderBb));
    }

    #[Test]
    public function token_ist_stabil_bis_zum_zuruecksetzen(): void
    {
        $a = CalendarExport::token($this->lernender);
        $this->assertSame($a, CalendarExport::token($this->lernender->fresh()));
        $this->assertSame(48, strlen($a));
        $this->assertNotSame($a, CalendarExport::resetToken($this->lernender));
    }

    #[Test]
    public function berufsbildner_termin_url_zeigt_auf_cockpit_des_lernenden(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        Betreuung::create([
            'berufsbildner_id' => $bb->berufsbildner->berufsbildner_id,
            'lernender_id' => $this->lernender->lernender->lernender_id,
            'gueltig_von' => now()->subYear()->toDateString(),
        ]);

        $event = Reader::read(app(CalendarExport::class)->forUser($bb))->VEVENT;

        $this->assertSame(
            route('trainer.learners.show', ['lernender_id' => $this->lernender->lernender->lernender_id]),
            (string) $event->URL,
        );
    }

    #[Test]
    public function admin_termin_url_zeigt_auf_cockpit_des_lernenden(): void
    {
        $admin = User::factory()->admin()->create();

        $event = Reader::read(app(CalendarExport::class)->forUser($admin))->VEVENT;

        $this->assertSame(
            route('admin.learners.show', ['lernender_id' => $this->lernender->lernender->lernender_id]),
            (string) $event->URL,
        );
    }

    #[Test]
    public function abo_texte_folgen_der_sprache_des_empfaengers(): void
    {
        $bb = User::factory()->berufsbildner()->create(['locale' => 'en']);
        Betreuung::create([
            'berufsbildner_id' => $bb->berufsbildner->berufsbildner_id,
            'lernender_id' => $this->lernender->lernender->lernender_id,
            'gueltig_von' => now()->subYear()->toDateString(),
        ]);
        Einstellungen::set(SetLocale::WAHL_AKTIV, '1');

        $ics = app(CalendarExport::class)->forUser($bb);
        $event = Reader::read($ics)->VEVENT;

        $this->assertStringContainsString('Duration: 45 minutes', (string) $event->DESCRIPTION);
        $this->assertStringContainsString('Weighting', (string) $event->DESCRIPTION);
        $this->assertStringContainsString('Exam', (string) $event->SUMMARY);
        $this->assertSame('de', app()->getLocale(), 'Locale muss nach dem Export wieder zurückgesetzt sein.');
    }

    #[Test]
    public function neuer_abo_link_macht_alten_ungueltig(): void
    {
        $altesToken = CalendarExport::token($this->lernender);
        $this->get(route('calendar.export', ['token' => $altesToken]))->assertOk();

        $this->actingAs($this->lernender)->post(route('settings.calendar.token.reset'))->assertRedirect();

        $this->get(route('calendar.export', ['token' => $altesToken]))->assertNotFound();
        $neuesToken = CalendarExport::token($this->lernender->fresh());
        $this->assertNotSame($altesToken, $neuesToken);
        $this->get(route('calendar.export', ['token' => $neuesToken]))->assertOk();
    }

    #[Test]
    public function raum_wird_ort_und_abgesagte_pruefung_wird_als_abgesagt_markiert(): void
    {
        $this->pruefung->forceFill(['raum' => 'Zimmer 204', 'abgesagt_am' => now()])->save();

        $event = Reader::read(app(CalendarExport::class)->forUser($this->lernender))->VEVENT;

        $this->assertSame('Zimmer 204', (string) $event->LOCATION);
        $this->assertSame('CANCELLED', (string) $event->STATUS);
    }

    #[Test]
    public function lernender_exportiert_importierte_termine_aber_keine_lektionen(): void
    {
        $lernenderId = $this->lernender->lernender->lernender_id;
        $feed = CalendarFeed::create(['lernender_id' => $lernenderId, 'url' => 'https://schulnetz.example/geheim']);
        $termin = fn (array $werte) => CalendarEvent::create([
            'lernender_id' => $lernenderId, 'calendar_feed_id' => $feed->id, 'uid' => uniqid('t', true),
            'kind' => CalendarEvent::APPOINTMENT, 'starts_at' => now()->addDays(3)->setTime(8, 0), ...$werte,
        ]);
        $termin(['summary' => 'Elternabend', 'description' => 'Aula', 'location' => 'Chur', 'ends_at' => now()->addDays(3)->setTime(9, 30)]);
        $termin(['summary' => 'Sporttag', 'all_day' => true, 'starts_at' => now()->addDays(4)->startOfDay()]);
        $termin(['summary' => 'Lager', 'all_day' => true, 'starts_at' => now()->addDays(5)->startOfDay(), 'ends_at' => now()->addDays(8)->startOfDay()]);
        $termin(['summary' => 'Lektion Mathe', 'kind' => CalendarEvent::LESSON]);
        $termin(['summary' => 'Alter Termin', 'starts_at' => now()->subDays(90)]);

        $events = collect(Reader::read(app(CalendarExport::class)->forUser($this->lernender))->select('VEVENT'))
            ->keyBy(fn ($e) => (string) $e->SUMMARY);

        $this->assertEqualsCanonicalizing(
            ['Elternabend', 'Sporttag', 'Lager'],
            $events->keys()->reject(fn ($s) => str_contains($s, 'Prüfung'))->values()->all(),
        );
        $elternabend = $events['Elternabend'];
        $this->assertSame('Aula', (string) $elternabend->DESCRIPTION);
        $this->assertSame('Chur', (string) $elternabend->LOCATION);
        $this->assertSame(90 * 60, $elternabend->DTEND->getDateTime()->getTimestamp() - $elternabend->DTSTART->getDateTime()->getTimestamp());
        $this->assertFalse($events['Sporttag']->DTSTART->hasTime(), 'Ganztägige Termine als DATE ohne Uhrzeit.');
        $this->assertSame(now()->addDays(5)->format('Ymd'), (string) $events['Sporttag']->DTEND);
        $this->assertSame(now()->addDays(8)->format('Ymd'), (string) $events['Lager']->DTEND, 'Mehrtägige Ganztagestermine behalten ihr Ende.');

        // Berufsbildner-Abo enthält nur Prüfungen, nie die privaten Termine der Lernenden.
        $bb = User::factory()->berufsbildner()->create();
        Betreuung::create(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $lernenderId, 'gueltig_von' => now()->subYear()->toDateString()]);
        $this->assertStringNotContainsString('Elternabend', app(CalendarExport::class)->forUser($bb));
    }

    #[Test]
    public function export_enthaelt_last_modified_sequenz_und_termin_url(): void
    {
        $this->pruefung->forceFill(['aktualisiert_am' => now()])->save();
        $lernenderId = $this->lernender->lernender->lernender_id;
        $feed = CalendarFeed::create(['lernender_id' => $lernenderId, 'url' => 'https://schulnetz.example/geheim2']);
        CalendarEvent::create([
            'lernender_id' => $lernenderId, 'calendar_feed_id' => $feed->id, 'uid' => uniqid('t', true),
            'kind' => CalendarEvent::APPOINTMENT, 'starts_at' => now()->addDays(3)->setTime(8, 0), 'summary' => 'Elternabend',
        ]);

        $events = collect(Reader::read(app(CalendarExport::class)->forUser($this->lernender))->select('VEVENT'))
            ->keyBy(fn ($e) => (string) $e->SUMMARY);
        $pruefungEvent = $events->first(fn ($e, $k) => str_contains($k, 'Prüfung'));
        $terminEvent = $events['Elternabend'];

        foreach ([$pruefungEvent, $terminEvent] as $event) {
            $this->assertNotSame('', (string) $event->{'LAST-MODIFIED'}, 'LAST-MODIFIED fehlt');
            $this->assertGreaterThanOrEqual(0, (int) (string) $event->SEQUENCE);
        }
        $this->assertSame(route('learner.exams.index'), (string) $terminEvent->URL);
    }
}
