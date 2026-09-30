<?php

declare(strict_types=1);

namespace Tests\Feature\Lernender;

use App\Models\CalendarEvent;
use App\Models\CalendarFeed;
use App\Models\Dokument;
use App\Models\Fach;
use App\Models\Lernender;
use App\Models\Note;
use App\Models\NotenKommentar;
use App\Models\Pruefung;
use App\Models\User;
use App\Models\Ziel;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Jede Route, die ein Objekt eines Lernenden über seine ID anspricht, weist Lernende mit einem
 * fremden Objekt ab (403/404) und verändert nichts. Die Vollständigkeitsprüfung sorgt dafür, dass
 * neue Routen dieser Art nicht still aus der Prüfung fallen (Gegenstück zu Verwaltung\SichtbarkeitTest).
 */
class FremdeObjekteTest extends TestCase
{
    /** Routenparameter, die ein Objekt eines Lernenden bezeichnen */
    private const string OBJEKTPARAMETER = '/\{(note_id|kommentar_id|pruefung_id|calendar_event_id|dokument_id|ziel_id)\}|calendar\/feed\/\{id\}/';

    private User $a;

    private Lernender $b;

    private Note $note;

    private NotenKommentar $kommentar;

    private Pruefung $pruefung;

    private Dokument $dokument;

    private Ziel $ziel;

    private CalendarFeed $feed;

    private CalendarEvent $termin;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Storage::fake('local');

        $this->a = User::factory()->lernender()->create();
        $b = User::factory()->lernender()->create();
        $this->b = $b->lernender;
        $id = $this->b->lernender_id;
        $fach = Fach::factory()->create();

        $this->note = Note::factory()->create(['lernender_id' => $id, 'titel' => 'GEHEIM-B', 'note_wert' => 3.5]);
        $this->kommentar = NotenKommentar::create(['note_id' => $this->note->note_id, 'autor_benutzer_id' => $b->benutzer_id, 'kommentar_text' => 'Nur fuer B']);
        $this->pruefung = Pruefung::create([
            'lernender_id' => $id, 'fach_id' => $fach->fach_id, 'titel' => 'LB-B', 'datum' => now()->addWeek()->toDateString(),
            'quelle' => Pruefung::ICAL, 'lokal_gesperrt' => ['titel'],
        ]);
        $pfad = 'lernende/'.$id.'/dokumente/zeugnis.pdf';
        Storage::disk('local')->put($pfad, '%PDF-1.4');
        $this->dokument = Dokument::create([
            'lernender_id' => $id, 'art' => 'zeugnis', 'titel' => 'Zeugnis B', 'originalname' => 'zeugnis.pdf',
            'pfad' => $pfad, 'mime' => 'application/pdf', 'groesse' => 8, 'sha256' => str_repeat('b', 64),
            'hochgeladen_von_benutzer_id' => $b->benutzer_id,
        ]);
        $this->ziel = Ziel::create(['lernender_id' => $id, 'ebene' => 'gesamt', 'zielwert' => 5.0]);
        $this->feed = CalendarFeed::create(['lernender_id' => $id, 'url' => 'https://schulnetz.example/b.ics']);
        $this->termin = CalendarEvent::create([
            'lernender_id' => $id, 'calendar_feed_id' => $this->feed->id, 'uid' => 'uid-b', 'kind' => CalendarEvent::EXAM,
            'summary' => 'Prüfung B', 'starts_at' => now()->addWeeks(2), 'ends_at' => now()->addWeeks(2)->addHour(), 'all_day' => false,
        ]);
    }

    #[Test]
    public function fremde_objekte_sind_ueber_keine_route_erreichbar(): void
    {
        foreach ($this->routen() as [$methode, $name, $parameter, $daten]) {
            $status = $this->actingAs($this->a)->call($methode, route($name, $parameter), $daten)->getStatusCode();
            $this->assertContains($status, [403, 404], "{$methode} {$name} lieferte {$status}");
        }

        $note = $this->note->fresh();
        $this->assertSame('GEHEIM-B', $note->titel);
        $this->assertEquals(3.5, $note->note_wert);
        $this->assertNotSoftDeleted($note);
        $this->assertDatabaseCount('noten_gesehen', 0);
        $this->assertSame(1, NotenKommentar::where('note_id', $note->note_id)->count());
        $this->assertModelExists($this->kommentar);
        $this->assertSame('LB-B', $this->pruefung->fresh()->titel);
        $this->assertSame(['titel'], $this->pruefung->fresh()->lokal_gesperrt);
        $this->assertModelExists($this->dokument);
        Storage::disk('local')->assertExists($this->dokument->pfad);
        $this->assertModelExists($this->ziel);
        $this->assertModelExists($this->feed);
        $this->assertNull($this->termin->fresh()->pruefung_id);
        $this->assertSame(1, Pruefung::where('lernender_id', $this->b->lernender_id)->count());
        $this->assertSame(0, Pruefung::where('lernender_id', $this->a->lernender->lernender_id)->count());
    }

    /** @return array<string, array{0: string}> */
    public static function routenNamen(): array
    {
        $namen = ['learner.grades.edit', 'learner.grades.update', 'learner.grades.destroy', 'learner.grades.seen', 'learner.grades.title.update',
            'comments.store', 'comments.destroy', 'learner.exams.update', 'learner.exams.destroy', 'learner.exams.unlock', 'learner.exams.adopt',
            'learner.documents.show', 'learner.documents.destroy', 'learner.documents.reconcile', 'learner.documents.reconcile.apply',
            'learner.goals.destroy', 'learner.calendar.feed.destroy', 'learner.calendar.feed.sync'];

        return array_combine($namen, array_map(fn (string $n) => [$n], $namen));
    }

    #[Test]
    #[DataProvider('routenNamen')]
    public function eigentuemer_kommt_mit_demselben_aufruf_durch(string $name): void
    {
        // Gegenprobe: das 403/404 oben stammt aus der Zugehörigkeit, nicht aus Methode, Daten oder Zustand
        Http::fake(['*' => Http::response('', 304)]);
        [$methode, , $parameter, $daten] = collect($this->routen())->firstWhere(1, $name);

        $status = $this->actingAs($this->b->benutzer)->call($methode, route($name, $parameter), $daten)->getStatusCode();

        $this->assertNotContains($status, [403, 404], "{$methode} {$name}");
        $this->assertLessThan(500, $status, "{$methode} {$name}");
    }

    #[Test]
    public function jede_route_mit_objekt_eines_lernenden_steht_in_der_pruefliste(): void
    {
        $geprueft = array_column($this->routen(), 1);
        $fehlend = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $r) => in_array('role:Lernender', $r->gatherMiddleware(), true) || str_starts_with((string) $r->getName(), 'comments.'))
            ->filter(fn (Route $r) => preg_match(self::OBJEKTPARAMETER, $r->uri()) === 1)
            ->map(fn (Route $r) => (string) $r->getName())
            ->reject(fn (string $name) => in_array($name, $geprueft, true))
            ->values()->all();

        $this->assertSame([], $fehlend, 'Fehlt in routen(): '.implode(', ', $fehlend));
        $this->assertSame(array_keys(self::routenNamen()), $geprueft, 'Gegenprobe und Prüfliste nennen dieselben Routen');
    }

    /** @return list<array{0: string, 1: string, 2: list<int>, 3: array<string, mixed>}> */
    private function routen(): array
    {
        $note = $this->note->note_id;
        $pruefung = $this->pruefung->pruefung_id;
        $dokument = $this->dokument->getKey();

        return [
            ['GET', 'learner.grades.edit', [$note], []],
            ['PUT', 'learner.grades.update', [$note], ['titel' => 'Übernommen', 'note_wert' => 6, 'pruefungsdatum' => now()->toDateString()]],
            ['DELETE', 'learner.grades.destroy', [$note], []],
            ['POST', 'learner.grades.seen', [$note], []],
            ['PATCH', 'learner.grades.title.update', [$note], ['titel' => 'Übernommen']],
            ['POST', 'comments.store', [$note], ['kommentar_text' => 'Eingeschleust']],
            ['DELETE', 'comments.destroy', [$this->kommentar->getKey()], []],
            ['PUT', 'learner.exams.update', [$pruefung], ['titel' => 'Übernommen', 'datum' => now()->addDays(3)->toDateString(), 'gewichtung_prozent' => 50, 'bezug' => 'fach:'.$this->pruefung->fach_id]],
            ['DELETE', 'learner.exams.destroy', [$pruefung], []],
            ['POST', 'learner.exams.unlock', [$pruefung], []],
            ['POST', 'learner.exams.adopt', [$this->termin->getKey()], []],
            ['GET', 'learner.documents.show', [$dokument], []],
            ['DELETE', 'learner.documents.destroy', [$dokument], []],
            ['GET', 'learner.documents.reconcile', [$dokument], []],
            ['POST', 'learner.documents.reconcile.apply', [$dokument], []],
            ['DELETE', 'learner.goals.destroy', [$this->ziel->getKey()], []],
            ['DELETE', 'learner.calendar.feed.destroy', [$this->feed->id], []],
            ['POST', 'learner.calendar.feed.sync', [$this->feed->id], []],
        ];
    }
}
