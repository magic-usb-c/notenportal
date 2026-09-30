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
        $this->modul = Modul::factory()->create(['modul_nummer' => '902', 'titel' => 'Testmodul Beta']);
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
            'uid' => 'lektion-1', 'summary' => '902-INXX 99 B', 'starts_at' => now()->addDay(), 'all_day' => false,
        ]);

        $response = $this->actingAs($this->user)->get(route('learner.exams.index'));
        $response->assertOk()->assertSee('LB2')->assertSee('Elterngespräch')->assertSee('Unbekannte Prüfung');
        $response->assertDontSee('902-INXX 99 B');

        $mitLektionen = $this->actingAs($this->user)->get(route('learner.exams.index', ['lektionen' => 1]));
        $mitLektionen->assertOk()->assertSee('902-INXX 99 B');
    }

    #[Test]
    public function monatsansicht_rendert_ohne_fehler(): void
    {
        $this->planen();

        // Ohne Monatsangabe zeigt die Ansicht den laufenden Monat und muss auch dann fehlerfrei rendern.
        $this->actingAs($this->user)->get(route('learner.exams.index', ['ansicht' => 'monat']))->assertOk();

        // Der Eintrag wird im Monat der Prüfung erwartet, nicht im laufenden: `planen()` legt sie eine
        // Woche in die Zukunft, was je nach Kalendertag im Folgemonat liegt. Ohne diese Angabe hing der
        // Test am Datum des Testlaufs und kippte am Monatsende.
        $this->actingAs($this->user)
            ->get(route('learner.exams.index', ['ansicht' => 'monat', 'monat' => now()->addWeek()->format('Y-m')]))
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
    public function bearbeiten_formular_mit_anhang_und_sperre_verschachtelt_keine_formulare(): void
    {
        // Ein <form> in einem <form> verwirft der Browser: das innere </form> schliesst das äussere,
        // «Speichern» steht danach ausserhalb jedes Formulars und das Entfernen eines Anhangs schickt
        // DELETE an die Prüfung selbst.
        $p = $this->planen();
        $this->actingAs($this->user)->post(route('learner.documents.store'), [
            'art' => 'pruefung', 'pruefung_id' => $p->pruefung_id,
            'datei' => UploadedFile::fake()->create('unterlagen.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $p->update(['quelle' => Pruefung::ICAL, 'extern_uid' => 'exam-x', 'calendar_feed_id' => $this->feed()->id, 'lokal_gesperrt' => ['titel' => 'Alt']]);

        $html = $this->actingAs($this->user)->get(route('learner.exams.index', ['bearbeiten' => $p->pruefung_id]))
            ->assertOk()->getContent();

        $tiefe = 0;
        preg_match_all('#<form\b|</form>#i', $html, $treffer);
        foreach ($treffer[0] as $tag) {
            $tiefe += str_starts_with($tag, '</') ? -1 : 1;
            $this->assertLessThanOrEqual(1, $tiefe, 'Verschachteltes <form> im Bearbeiten-Drawer');
        }
        $this->assertStringContainsString('form="anhang-entfernen-'.Dokument::sole()->dokument_id.'"', $html);
        $this->assertStringContainsString('form="pruefung-entsperren"', $html);
    }

    #[Test]
    public function entfernen_eines_pruefungsanhangs_fuehrt_zurueck_zur_pruefung(): void
    {
        $p = $this->planen();
        $this->actingAs($this->user)->post(route('learner.documents.store'), [
            'art' => 'pruefung', 'pruefung_id' => $p->pruefung_id,
            'datei' => UploadedFile::fake()->create('unterlagen.pdf', 100, 'application/pdf'),
        ]);
        $zurueck = route('learner.exams.index', ['bearbeiten' => $p->pruefung_id]);

        $this->actingAs($this->user)->from($zurueck)->delete(route('learner.documents.destroy', Dokument::sole()->dokument_id))
            ->assertRedirect($zurueck)->assertSessionHas('success');
        $this->assertModelExists($p);
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
        ])->assertSessionHasNoErrors()->assertRedirect(route('settings.calendar'));

        $feed = CalendarFeed::sole();
        $this->assertSame('Schulnetz', $feed->label);
        $this->assertTrue($feed->import_exams);
        $this->assertFalse($feed->import_lessons);

        $this->actingAs($this->user)->post(route('learner.calendar.sync'))
            ->assertSessionHasNoErrors()->assertRedirect(route('settings.calendar'));

        $this->assertSame(CalendarFeed::OK, $feed->fresh()->last_status);
    }

    #[Test]
    public function abgleich_ohne_hinterlegten_kalender_meldet_fehler(): void
    {
        $this->actingAs($this->user)->post(route('learner.calendar.sync'))
            ->assertRedirect(route('settings.calendar'))
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
    public function bis_zu_fuenf_kalender_sind_erlaubt_der_sechste_wird_abgelehnt(): void
    {
        config(['notenportal.calendar_allow_private' => true]);
        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($this->user)->post(route('learner.calendar.feed.store'), [
                'url' => "https://schulnetz.example/geheim{$i}",
            ])->assertSessionHasNoErrors();
        }
        $this->assertSame(5, CalendarFeed::count());

        $this->actingAs($this->user)->post(route('learner.calendar.feed.store'), [
            'url' => 'https://schulnetz.example/geheim6',
        ])->assertSessionHasErrors('url');
        $this->assertSame(5, CalendarFeed::count());
    }

    #[Test]
    public function feed_id_aktualisiert_bestehenden_feed_statt_einen_neuen_anzulegen(): void
    {
        config(['notenportal.calendar_allow_private' => true]);
        $feed = $this->feed();

        $this->actingAs($this->user)->post(route('learner.calendar.feed.store'), [
            'feed_id' => $feed->id, 'label' => 'Neu benannt', 'url' => 'https://schulnetz.example/geheim',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, CalendarFeed::count());
        $this->assertSame('Neu benannt', $feed->fresh()->label);
    }

    #[Test]
    public function etag_wird_bei_url_wechsel_des_feeds_geleert(): void
    {
        config(['notenportal.calendar_allow_private' => true]);
        $feed = $this->feed();
        $feed->forceFill(['etag' => '"abc"', 'last_modified' => 'Wed, 01 Sep 2026 12:00:00 GMT'])->save();

        $this->actingAs($this->user)->post(route('learner.calendar.feed.store'), [
            'feed_id' => $feed->id, 'url' => 'https://schulnetz.example/andere-adresse',
        ])->assertSessionHasNoErrors();

        $feed->refresh();
        $this->assertNull($feed->etag);
        $this->assertNull($feed->last_modified);
    }

    #[Test]
    public function fremder_feed_kann_nicht_ueber_feed_id_uebernommen_werden(): void
    {
        config(['notenportal.calendar_allow_private' => true]);
        $andere = User::factory()->lernender()->create();
        $fremderFeed = CalendarFeed::create(['lernender_id' => $andere->lernender->lernender_id, 'url' => 'https://schulnetz.example/fremd']);

        $this->actingAs($this->user)->post(route('learner.calendar.feed.store'), [
            'feed_id' => $fremderFeed->id, 'url' => 'https://schulnetz.example/uebernahme',
        ])->assertNotFound();
    }

    #[Test]
    public function feed_loeschen_entfernt_events_importierte_pruefung_bleibt_mit_leerem_feed(): void
    {
        $feed = $this->feed();
        CalendarEvent::create([
            'lernender_id' => $this->user->lernender->lernender_id, 'calendar_feed_id' => $feed->id, 'kind' => CalendarEvent::APPOINTMENT,
            'uid' => 'termin-1', 'summary' => 'Termin', 'starts_at' => now()->addDay(),
        ]);
        $p = Pruefung::create([
            'lernender_id' => $this->user->lernender->lernender_id, 'modul_id' => $this->modul->modul_id,
            'titel' => 'LB1', 'datum' => now()->addWeek()->toDateString(), 'gewichtung_prozent' => 40,
            'quelle' => Pruefung::ICAL, 'extern_uid' => 'exam-1', 'calendar_feed_id' => $feed->id,
        ]);

        $this->actingAs($this->user)->delete(route('learner.calendar.feed.destroy', $feed->id))
            ->assertSessionHasNoErrors()->assertRedirect(route('settings.calendar'));

        $this->assertDatabaseMissing('calendar_feeds', ['id' => $feed->id]);
        $this->assertDatabaseMissing('calendar_events', ['uid' => 'termin-1']);
        $this->assertNotNull($p->fresh(), 'importierte Prüfung bleibt bestehen (kann Note/Notizen tragen)');
        $this->assertNull($p->fresh()->calendar_feed_id);
    }

    #[Test]
    public function einzelner_feed_kann_gezielt_abgeglichen_werden(): void
    {
        config(['notenportal.calendar_allow_private' => true]);
        Http::fake(['schulnetz.example/*' => Http::response(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nEND:VCALENDAR\r\n", 200, ['Content-Type' => 'text/calendar']
        )]);
        $feed = $this->feed();

        $this->actingAs($this->user)->post(route('learner.calendar.feed.sync', $feed->id))
            ->assertSessionHasNoErrors()->assertRedirect(route('settings.calendar'));

        $this->assertSame(CalendarFeed::OK, $feed->fresh()->last_status);
    }

    #[Test]
    public function einstellungen_zeigen_mehrere_kalender_in_der_liste(): void
    {
        CalendarFeed::create(['lernender_id' => $this->user->lernender->lernender_id, 'label' => 'Schulnetz', 'url' => 'https://schulnetz.example/a']);
        CalendarFeed::create(['lernender_id' => $this->user->lernender->lernender_id, 'label' => 'Ausbildner-Nextcloud', 'url' => 'https://cloud.example/b']);

        $this->actingAs($this->user)->get(route('settings.calendar'))
            ->assertOk()
            ->assertSee('Schulnetz')
            ->assertSee('Ausbildner-Nextcloud');
    }

    #[Test]
    public function bearbeiten_mit_leerem_url_feld_behaelt_die_bestehende_adresse(): void
    {
        $feed = $this->feed();
        $ursprung = $feed->url;

        $this->actingAs($this->user)->post(route('learner.calendar.feed.store'), [
            'feed_id' => $feed->id, 'label' => 'Neu benannt', 'url' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, CalendarFeed::count());
        $feed->refresh();
        $this->assertSame('Neu benannt', $feed->label);
        $this->assertSame($ursprung, $feed->url, 'leeres URL-Feld lässt die hinterlegte Adresse unverändert');
    }

    #[Test]
    public function kalender_entfernen_verschwindet_aus_der_liste(): void
    {
        $bleibt = CalendarFeed::create(['lernender_id' => $this->user->lernender->lernender_id, 'label' => 'Bleibt', 'url' => 'https://schulnetz.example/bleibt']);
        $weg = CalendarFeed::create(['lernender_id' => $this->user->lernender->lernender_id, 'label' => 'Weg', 'url' => 'https://schulnetz.example/weg']);

        $this->actingAs($this->user)->delete(route('learner.calendar.feed.destroy', $weg->id))
            ->assertSessionHasNoErrors()->assertRedirect(route('settings.calendar'));

        $this->actingAs($this->user)->get(route('settings.calendar'))
            ->assertOk()->assertSee('Bleibt')->assertDontSee('Weg');
    }

    #[Test]
    public function die_ical_adresse_erscheint_nie_im_html_der_einstellungsseite(): void
    {
        $feed = CalendarFeed::create([
            'lernender_id' => $this->user->lernender->lernender_id,
            'url' => 'https://schulnetz.example/sehr-geheimes-token-xyz',
        ]);

        $this->actingAs($this->user)->get(route('settings.calendar'))
            ->assertOk()
            ->assertDontSee($feed->url, false)
            ->assertDontSee('sehr-geheimes-token-xyz', false);
    }

    #[Test]
    public function bearbeiten_einer_kalender_pruefung_sperrt_nur_tatsaechlich_geaenderte_felder(): void
    {
        $feed = $this->feed();
        $p = Pruefung::create([
            'lernender_id' => $this->user->lernender->lernender_id, 'modul_id' => $this->modul->modul_id,
            'titel' => 'LB1', 'datum' => now()->addWeek()->toDateString(), 'uhrzeit' => '09:00:00',
            'gewichtung_prozent' => 40, 'quelle' => Pruefung::ICAL, 'extern_uid' => 'exam-1', 'calendar_feed_id' => $feed->id,
        ]);
        $altesDatum = $p->datum->toDateString();

        $this->actingAs($this->user)->put(route('learner.exams.update', $p->pruefung_id), [
            'bezug' => 'modul:'.$this->modul->modul_id,
            'titel' => 'LB1',
            'datum' => now()->addWeeks(2)->toDateString(),
            'gewichtung_prozent' => 40,
            'uhrzeit' => '09:00',
        ])->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame(now()->addWeeks(2)->toDateString(), $p->datum->toDateString());
        $this->assertSame($altesDatum, $p->lokal_gesperrt['datum'] ?? null, 'geändertes Feld wird mit dem alten Wert gesperrt');
        $this->assertArrayNotHasKey('uhrzeit', $p->lokal_gesperrt ?? [], 'unveränderte Felder werden nicht gesperrt');
    }

    #[Test]
    public function raum_ist_nicht_sperrbar_weil_es_kein_formular_erfasst(): void
    {
        // Regressionstest: raum stand früher in SPERRBARE_FELDER, obwohl validiere() nie einen
        // Schlüssel `raum` zurückgibt – die Sperre griff dadurch nie. raum kommt ausschliesslich
        // aus dem Kalenderabgleich, darum darf es hier nicht mehr auftauchen.
        $this->assertNotContains('raum', Pruefung::SPERRBARE_FELDER);
    }

    #[Test]
    public function bearbeiten_einer_manuellen_pruefung_sperrt_nichts(): void
    {
        $p = $this->planen();

        $this->actingAs($this->user)->put(route('learner.exams.update', $p->pruefung_id), [
            'bezug' => 'modul:'.$this->modul->modul_id,
            'datum' => now()->addWeeks(3)->toDateString(),
            'gewichtung_prozent' => 40,
        ])->assertSessionHasNoErrors();

        $this->assertNull($p->fresh()->lokal_gesperrt);
    }

    #[Test]
    public function unlock_leert_die_sperre_einer_kalender_pruefung(): void
    {
        $feed = $this->feed();
        $p = Pruefung::create([
            'lernender_id' => $this->user->lernender->lernender_id, 'modul_id' => $this->modul->modul_id,
            'titel' => 'LB1', 'datum' => now()->addWeek()->toDateString(), 'gewichtung_prozent' => 40,
            'quelle' => Pruefung::ICAL, 'extern_uid' => 'exam-1', 'calendar_feed_id' => $feed->id,
            'lokal_gesperrt' => ['raum' => 'A5'],
        ]);

        $this->actingAs($this->user)->post(route('learner.exams.unlock', $p->pruefung_id))
            ->assertSessionHasNoErrors()->assertRedirect(route('learner.exams.index'));

        $this->assertNull($p->fresh()->lokal_gesperrt);
    }

    #[Test]
    public function unlock_auf_manueller_pruefung_schlaegt_fehl(): void
    {
        $p = $this->planen();

        $this->actingAs($this->user)->post(route('learner.exams.unlock', $p->pruefung_id))->assertNotFound();
    }

    #[Test]
    public function lokal_angepasste_pruefung_zeigt_hinweis_kalenderwert_und_uebernehmen_knopf(): void
    {
        $feed = $this->feed();
        $p = Pruefung::create([
            'lernender_id' => $this->user->lernender->lernender_id, 'modul_id' => $this->modul->modul_id,
            'titel' => 'Neuer Titel', 'datum' => now()->addWeek()->toDateString(), 'gewichtung_prozent' => 40,
            'quelle' => Pruefung::ICAL, 'extern_uid' => 'exam-1', 'calendar_feed_id' => $feed->id,
            'lokal_gesperrt' => ['titel' => 'Alter Titel', 'datum' => '2026-01-15'],
        ]);

        $this->actingAs($this->user)->get(route('learner.exams.index', ['bearbeiten' => $p->pruefung_id]))
            ->assertOk()
            ->assertSee(__('Lokal angepasst'))
            ->assertSee(__('Kalender meldet: :wert', ['wert' => 'Alter Titel']))
            ->assertSee(__('Kalender meldet: :wert', ['wert' => '15.01.2026']))
            ->assertSee(__('Wieder vom Kalender übernehmen'))
            ->assertSee(route('learner.exams.unlock', $p->pruefung_id), false);
    }

    #[Test]
    public function unveraenderte_kalender_pruefung_zeigt_keinen_hinweis(): void
    {
        $p = $this->planen();

        $this->actingAs($this->user)->get(route('learner.exams.index', ['bearbeiten' => $p->pruefung_id]))
            ->assertOk()
            ->assertDontSee(__('Lokal angepasst'));
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
    public function uebernommene_pruefung_kuerzt_lange_felder_und_begrenzt_gewichtung(): void
    {
        $event = CalendarEvent::create([
            'lernender_id' => $this->user->lernender->lernender_id, 'calendar_feed_id' => $this->feed()->id, 'kind' => CalendarEvent::EXAM,
            'uid' => 'exam-lang', 'summary' => 'Prüfung', 'description' => "Gewichtung: 250%\nRaum: ".str_repeat('R', 90)."\nHilfsmittel: ".str_repeat('H', 300),
            'starts_at' => now()->addDays(3), 'all_day' => false,
        ]);

        $this->actingAs($this->user)->post(route('learner.exams.adopt', $event->id), [
            'bezug' => 'modul:'.$this->modul->modul_id,
        ])->assertSessionHasNoErrors()->assertRedirect(route('learner.exams.index'));

        $pruefung = Pruefung::sole();
        $this->assertEquals(100, $pruefung->gewichtung_prozent);
        $this->assertSame(60, mb_strlen($pruefung->raum));
        $this->assertSame(255, mb_strlen($pruefung->hilfsmittel));
    }

    #[Test]
    public function nicht_verfuegbarer_bezug_bei_uebernahme_meldet_fehler_als_toast(): void
    {
        $event = CalendarEvent::create([
            'lernender_id' => $this->user->lernender->lernender_id, 'calendar_feed_id' => $this->feed()->id, 'kind' => CalendarEvent::EXAM,
            'uid' => 'exam-y', 'summary' => 'Prüfung', 'starts_at' => now()->addDays(3), 'all_day' => false,
        ]);

        $this->actingAs($this->user)->from(route('learner.exams.index'))->post(route('learner.exams.adopt', $event->id), [
            'bezug' => 'modul:999999',
        ])->assertRedirect(route('learner.exams.index'))->assertSessionHas('error');

        $this->assertSame(0, Pruefung::count());
    }

    #[Test]
    public function planen_drawer_oeffnet_nach_fehler_mit_eingaben_wieder(): void
    {
        $this->actingAs($this->user)->from(route('learner.exams.index'))
            ->post(route('learner.exams.store'), ['_drawer' => 'pruefung', 'titel' => 'Ohne Datum'])
            ->assertSessionHasErrors('datum');

        $this->followingRedirects()->actingAs($this->user)->from(route('learner.exams.index'))
            ->post(route('learner.exams.store'), ['_drawer' => 'pruefung', 'titel' => 'Ohne Datum'])
            ->assertSee('offen: true', false)
            ->assertSee('value="Ohne Datum"', false);
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
    public function export_link_eines_deaktivierten_kontos_liefert_nichts(): void
    {
        $this->planen();
        $token = CalendarExport::token($this->user);
        $this->user->update(['aktiv' => false]);
        $this->app['auth']->forgetGuards();

        $this->get(route('calendar.export', ['token' => $token]))->assertNotFound();
    }

    #[Test]
    public function export_token_kann_neu_erzeugt_werden_und_alter_link_verliert_gueltigkeit(): void
    {
        $this->planen();
        $altesToken = CalendarExport::token($this->user);

        $this->get(route('calendar.export', ['token' => $altesToken]))->assertOk();

        $this->actingAs($this->user)->post(route('settings.calendar.token.reset'))
            ->assertSessionHasNoErrors()->assertRedirect(route('settings.calendar'));

        $this->get(route('calendar.export', ['token' => $altesToken]))->assertNotFound();

        $neuesToken = $this->user->fresh()->kalender_token;
        $this->assertNotSame($altesToken, $neuesToken);
        $response = $this->get(route('calendar.export', ['token' => $neuesToken]));
        $response->assertOk();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $response->getContent());
        $this->assertStringContainsString('LB2', $response->getContent());
    }

    #[Test]
    public function einstellungen_kalender_bietet_abo_link_mit_webcal_knopf_an(): void
    {
        $token = CalendarExport::token($this->user);
        $exportUrl = route('calendar.export', ['token' => $token]);

        $this->actingAs($this->user)->get(route('settings.calendar'))
            ->assertOk()
            ->assertSee(__('Kalender-Abo'))
            ->assertSee($exportUrl, false)
            ->assertSee(preg_replace('#^https?://#', 'webcal://', $exportUrl), false)
            ->assertSee(route('settings.calendar.token.reset'), false);
    }

    #[Test]
    public function kalender_parameter_leitet_serverseitig_auf_einstellungen_um(): void
    {
        $this->actingAs($this->user)->get(route('learner.exams.index', ['kalender' => 1]))
            ->assertRedirect(route('settings.calendar'));
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
