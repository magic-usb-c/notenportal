<?php

declare(strict_types=1);

namespace Tests\Feature\Lernender;

use App\Models\CalendarEvent;
use App\Models\CalendarFeed;
use App\Models\Dokument;
use App\Models\Kategorie;
use App\Models\Modul;
use App\Models\Pruefung;
use App\Models\User;
use App\Services\Calendar\CalendarExport;
use App\Services\Notifications\Messages\ExamReminder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Agenda des Lernenden: Ansichten (Liste/Monat), Drawer-Speichern mit allen Feldern, Anhänge,
 * Kalender-Abo (Feed speichern/abgleichen), Übernahme erkannter Prüfungen, Export-Token.
 */
class AgendaTest extends TestCase
{
    private User $user;

    private Modul $modul;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->lernender()->create();
        $this->modul = Modul::factory()->create(['modul_nummer' => '159', 'titel' => 'Directory Services']);
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $this->user->lernender->lehrberuf_id,
            'modul_id' => $this->modul->modul_id,
            'kategorie_id' => Kategorie::where('code', 'UEK')->value('kategorie_id') ?? Kategorie::value('kategorie_id'),
        ]);
    }

    private function feed(): CalendarFeed
    {
        return CalendarFeed::create(['lernender_id' => $this->user->lernender->lernender_id, 'url' => 'https://schulnetz.example/geheim']);
    }

    private function planen(array $daten = []): Pruefung
    {
        $this->actingAs($this->user)->post(route('learner.exams.store'), [
            'bezug' => 'modul:'.$this->modul->modul_id,
            'datum' => now()->addWeek()->toDateString(),
            'gewichtung_prozent' => 40,
            'titel' => 'LB2',
            ...$daten,
        ])->assertSessionHasNoErrors();

        return Pruefung::sole();
    }

    #[Test]
    public function monatsansicht_zeigt_monatsnamen_einmal_und_tage_mit_eintraegen(): void
    {
        $this->planen(['datum' => '2026-02-12']);

        $this->actingAs($this->user)->get(route('learner.exams.index', ['ansicht' => 'monat', 'monat' => '2026-02']))
            ->assertOk()
            ->assertSee('Februar 2026')
            ->assertDontSee('FebruarFebruar')
            ->assertSee('12. Feb')
            ->assertSee('LB2');
    }

    #[Test]
    public function agenda_zeigt_liste_und_unterscheidet_pruefungen_termine_und_erkanntes(): void
    {
        $this->planen();
        $feedId = $this->feed()->id;
        CalendarEvent::create([
            'lernender_id' => $this->user->lernender->lernender_id, 'calendar_feed_id' => $feedId, 'kind' => CalendarEvent::APPOINTMENT,
            'uid' => 'termin-1', 'summary' => 'Elterngespräch', 'starts_at' => now()->addDays(2), 'all_day' => false,
        ]);
        CalendarEvent::create([
            'lernender_id' => $this->user->lernender->lernender_id, 'calendar_feed_id' => $feedId, 'kind' => CalendarEvent::EXAM,
            'uid' => 'exam-x', 'summary' => 'Unbekannte Prüfung', 'starts_at' => now()->addDays(3), 'all_day' => false,
        ]);
        CalendarEvent::create([
            'lernender_id' => $this->user->lernender->lernender_id, 'calendar_feed_id' => $feedId, 'kind' => CalendarEvent::LESSON,
            'uid' => 'lektion-1', 'summary' => '159-INPE 24 B', 'starts_at' => now()->addDay(), 'all_day' => false,
        ]);

        $response = $this->actingAs($this->user)->get(route('learner.exams.index'));
        $response->assertOk()->assertSee('LB2')->assertSee('Elterngespräch')->assertSee('Unbekannte Prüfung');
        $response->assertDontSee('159-INPE 24 B');

        $mitLektionen = $this->actingAs($this->user)->get(route('learner.exams.index', ['lektionen' => 1]));
        $mitLektionen->assertOk()->assertSee('159-INPE 24 B');
    }

    #[Test]
    public function monatsansicht_rendert_ohne_fehler(): void
    {
        $this->planen();

        $this->actingAs($this->user)->get(route('learner.exams.index', ['ansicht' => 'monat']))
            ->assertOk()->assertSee('LB2');
    }

    #[Test]
    public function pruefung_planen_speichert_alle_neuen_felder(): void
    {
        $p = $this->planen([
            'uhrzeit' => '09:30',
            'pruefungsart' => 'Schriftlich',
            'dauer_minuten' => 45,
            'hilfsmittel' => 'Taschenrechner',
            'stoff' => 'Kapitel 1-3',
            'notizen' => 'Gut vorbereiten',
        ]);

        $this->assertSame('09:30:00', substr((string) $p->uhrzeit, 0, 8));
        $this->assertSame('Schriftlich', $p->pruefungsart);
        $this->assertSame(45, $p->dauer_minuten);
        $this->assertSame('Taschenrechner', $p->hilfsmittel);
        $this->assertSame('Kapitel 1-3', $p->stoff);
        $this->assertSame('Gut vorbereiten', $p->notizen);

        $this->actingAs($this->user)->get(route('learner.exams.index', ['bearbeiten' => $p->pruefung_id]))
            ->assertOk()->assertSee('Kapitel 1-3')->assertSee('Gut vorbereiten')->assertSee('Taschenrechner');
    }

    #[Test]
    public function pruefung_bearbeiten_aktualisiert_die_felder(): void
    {
        $p = $this->planen();

        $this->actingAs($this->user)->put(route('learner.exams.update', $p->pruefung_id), [
            'bezug' => 'modul:'.$this->modul->modul_id,
            'datum' => now()->addWeek()->toDateString(),
            'gewichtung_prozent' => 40,
            'pruefungsart' => 'Mündlich',
            'dauer_minuten' => 20,
            'hilfsmittel' => 'Keine',
            'stoff' => 'Alles',
            'notizen' => 'Ruhig bleiben',
        ])->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame('Mündlich', $p->pruefungsart);
        $this->assertSame(20, $p->dauer_minuten);
        $this->assertSame('Keine', $p->hilfsmittel);
        $this->assertSame('Alles', $p->stoff);
        $this->assertSame('Ruhig bleiben', $p->notizen);
    }

    #[Test]
    public function anhang_kann_hochgeladen_und_nur_vom_eigenen_lernenden_heruntergeladen_werden(): void
    {
        $p = $this->planen();
        $datei = UploadedFile::fake()->create('unterlagen.pdf', 100, 'application/pdf');

        $upload = $this->actingAs($this->user)->post(route('learner.documents.store'), [
            'art' => 'pruefung', 'pruefung_id' => $p->pruefung_id, 'datei' => $datei,
        ]);
        $upload->assertSessionHasNoErrors()->assertRedirect();

        $dokument = Dokument::sole();
        $this->assertSame($p->pruefung_id, $dokument->pruefung_id);

        $this->actingAs($this->user)->get(route('learner.documents.show', $dokument->dokument_id))->assertOk();

        $andere = User::factory()->lernender()->create();
        $this->actingAs($andere)->get(route('learner.documents.show', $dokument->dokument_id))->assertNotFound();
        $this->actingAs($andere)->delete(route('learner.documents.destroy', $dokument->dokument_id))->assertNotFound();

        $this->actingAs($this->user)->delete(route('learner.documents.destroy', $dokument->dokument_id))->assertRedirect();
        $this->assertDatabaseMissing('dokumente', ['dokument_id' => $dokument->dokument_id]);
    }

    #[Test]
    public function anhang_an_fremde_pruefung_wird_abgelehnt(): void
    {
        $p = $this->planen();
        $andere = User::factory()->lernender()->create();
        $datei = UploadedFile::fake()->create('unterlagen.pdf', 50, 'application/pdf');

        $this->actingAs($andere)->post(route('learner.documents.store'), [
            'art' => 'pruefung', 'pruefung_id' => $p->pruefung_id, 'datei' => $datei,
        ])->assertSessionHasErrors('pruefung_id');
    }

    #[Test]
    public function kalender_feed_wird_gespeichert_und_kann_abgeglichen_werden(): void
    {
        config(['notenportal.calendar_allow_private' => true]);
        Http::fake(['schulnetz.example/*' => Http::response(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nEND:VCALENDAR\r\n", 200, ['Content-Type' => 'text/calendar']
        )]);

        $this->actingAs($this->user)->post(route('learner.calendar.feed.store'), [
            'label' => 'Schulnetz', 'url' => 'https://schulnetz.example/ical/geheim',
            'import_exams' => '1', 'import_appointments' => '1', 'import_lessons' => '0',
        ])->assertSessionHasNoErrors()->assertRedirect(route('learner.exams.index'));

        $feed = CalendarFeed::sole();
        $this->assertSame('Schulnetz', $feed->label);
        $this->assertTrue($feed->import_exams);
        $this->assertFalse($feed->import_lessons);

        $this->actingAs($this->user)->post(route('learner.calendar.sync'))
            ->assertSessionHasNoErrors()->assertRedirect(route('learner.exams.index'));

        $this->assertSame(CalendarFeed::OK, $feed->fresh()->last_status);
    }

    #[Test]
    public function abgleich_ohne_hinterlegten_kalender_meldet_fehler(): void
    {
        $this->actingAs($this->user)->post(route('learner.calendar.sync'))
            ->assertRedirect(route('learner.exams.index'))
            ->assertSessionHas('error');
    }

    #[Test]
    public function private_adressen_werden_beim_feed_speichern_abgelehnt(): void
    {
        $this->actingAs($this->user)->post(route('learner.calendar.feed.store'), [
            'url' => 'http://127.0.0.1/kalender.ics',
        ])->assertSessionHasErrors('url');
    }

    #[Test]
    public function erkannte_pruefung_kann_uebernommen_werden(): void
    {
        $event = CalendarEvent::create([
            'lernender_id' => $this->user->lernender->lernender_id, 'calendar_feed_id' => $this->feed()->id, 'kind' => CalendarEvent::EXAM,
            'uid' => 'exam-x', 'summary' => 'Unbekannte Prüfung', 'description' => 'Prüfungsstoff: alles',
            'starts_at' => now()->addDays(3), 'all_day' => false,
        ]);

        $this->actingAs($this->user)->post(route('learner.exams.adopt', $event->id), [
            'bezug' => 'modul:'.$this->modul->modul_id,
        ])->assertSessionHasNoErrors()->assertRedirect(route('learner.exams.index'));

        $pruefung = Pruefung::sole();
        $this->assertSame($this->modul->modul_id, $pruefung->modul_id);
        $this->assertSame(Pruefung::ICAL, $pruefung->quelle);
        $this->assertSame('exam-x', $pruefung->extern_uid);
        $this->assertSame($pruefung->pruefung_id, $event->fresh()->pruefung_id);
    }

    #[Test]
    public function fremde_erkannte_pruefung_kann_nicht_uebernommen_werden(): void
    {
        $event = CalendarEvent::create([
            'lernender_id' => $this->user->lernender->lernender_id, 'calendar_feed_id' => $this->feed()->id, 'kind' => CalendarEvent::EXAM,
            'uid' => 'exam-x', 'summary' => 'Unbekannte Prüfung', 'starts_at' => now()->addDays(3), 'all_day' => false,
        ]);
        $andere = User::factory()->lernender()->create();

        $this->actingAs($andere)->post(route('learner.exams.adopt', $event->id), [
            'bezug' => 'modul:'.$this->modul->modul_id,
        ])->assertNotFound();
    }

    #[Test]
    public function export_token_kann_neu_erzeugt_werden_und_alter_link_verliert_gueltigkeit(): void
    {
        $this->planen();
        $altesToken = CalendarExport::token($this->user);

        $this->get(route('calendar.export', ['token' => $altesToken]))->assertOk();

        $this->actingAs($this->user)->post(route('learner.calendar.token.reset'))
            ->assertSessionHasNoErrors()->assertRedirect(route('learner.exams.index'));

        $this->get(route('calendar.export', ['token' => $altesToken]))->assertNotFound();

        $neuesToken = $this->user->fresh()->kalender_token;
        $this->assertNotSame($altesToken, $neuesToken);
        $response = $this->get(route('calendar.export', ['token' => $neuesToken]));
        $response->assertOk();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $response->getContent());
        $this->assertStringContainsString('LB2', $response->getContent());
    }

    #[Test]
    public function agenda_bietet_abo_link_mit_webcal_knopf_an(): void
    {
        $token = CalendarExport::token($this->user);
        $exportUrl = route('calendar.export', ['token' => $token]);

        $this->actingAs($this->user)->get(route('learner.exams.index'))
            ->assertOk()
            ->assertSee(__('Agenda abonnieren'))
            ->assertSee($exportUrl, false)
            ->assertSee(preg_replace('#^https?://#', 'webcal://', $exportUrl), false)
            ->assertSee(route('learner.calendar.token.reset'), false);
    }

    #[Test]
    public function drawer_oeffnet_per_parameter_serverseitig(): void
    {
        // Ein open-drawer aus dem init() der Seite käme vor dem Listener der Drawer an – deshalb Startzustand vom Server.
        $this->actingAs($this->user)->get(route('learner.exams.index'))
            ->assertOk()->assertDontSee('offen: true', false);

        $this->actingAs($this->user)->get(route('learner.exams.index', ['kalender' => 1]))
            ->assertOk()->assertSee('offen: true', false);
    }

    #[Test]
    public function reminder_inhalt_enthaelt_pruefungsart_dauer_hilfsmittel_stoff_und_notizen(): void
    {
        $p = $this->planen([
            'pruefungsart' => 'Schriftlich', 'dauer_minuten' => 45, 'hilfsmittel' => 'Taschenrechner',
            'stoff' => 'Kapitel 1-3', 'notizen' => 'Gut vorbereiten', 'uhrzeit' => '09:30',
        ]);

        $content = ExamReminder::content($p->fresh());

        $this->assertSame('Schriftlich', $content->facts['Prüfungsart']);
        $this->assertSame('45 Minuten', $content->facts['Dauer']);
        $this->assertSame('Taschenrechner', $content->facts['Erlaubte Hilfsmittel']);
        $this->assertStringContainsString('09:30', $content->facts['Datum']);

        $titles = array_column($content->sections, 'title');
        $this->assertContains('Prüfungsstoff', $titles);
        $this->assertContains('Eigene Notizen', $titles);
        $texts = array_column($content->sections, 'text', 'title');
        $this->assertSame('Kapitel 1-3', $texts['Prüfungsstoff']);
        $this->assertSame('Gut vorbereiten', $texts['Eigene Notizen']);
    }
}
