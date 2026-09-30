<?php

namespace Tests\Feature\Verwaltung;

use App\Models\Betreuung;
use App\Models\Dokument;
use App\Models\Fach;
use App\Models\Lernender;
use App\Models\Note;
use App\Models\Pruefung;
use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Berufsbildner verwalten nur aktiv betreute Lernende. Alles andere ergibt 404
 * (keine Existenz verraten) und verändert nichts. Admin sieht alle.
 */
class SichtbarkeitTest extends TestCase
{
    use VerwaltungTestHilfen;

    private User $bb;

    private Lernender $lernender;

    private Note $note;

    private int $betreuungId;

    private int $trackId;

    private Dokument $dokument;

    private Pruefung $pruefung;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bb = User::factory()->berufsbildner()->create();
        $this->lernender = $this->neuerLernender(['nachname' => 'Unsichtbarius']);
        $this->note = Note::factory()->create([
            'lernender_id' => $this->lernender->lernender_id,
            'titel' => 'Geheime Pruefung',
        ]);
        $this->trackId = $this->bmsTrack($this->lernender, $this->note->semester_id)->getKey();

        Storage::fake('local');
        $pfad = 'lernende/'.$this->lernender->lernender_id.'/dokumente/zeugnis.pdf';
        Storage::disk('local')->put($pfad, '%PDF-1.4');
        $this->dokument = Dokument::create([
            'lernender_id' => $this->lernender->lernender_id, 'art' => 'zeugnis', 'titel' => 'Geheimes Zeugnis', 'originalname' => 'zeugnis.pdf',
            'pfad' => $pfad, 'mime' => 'application/pdf', 'groesse' => 8, 'sha256' => str_repeat('a', 64),
            'hochgeladen_von_benutzer_id' => $this->lernender->benutzer_id,
        ]);
        $this->pruefung = Pruefung::create([
            'lernender_id' => $this->lernender->lernender_id,
            'fach_id' => Fach::factory()->create()->fach_id,
            'titel' => 'Geheime LB',
            'datum' => now()->addWeek()->toDateString(),
            'art' => Pruefung::ART_ABGABE,
        ]);
    }

    /** @return array<string, array{0: string}> */
    public static function nichtAktivBetreut(): array
    {
        return [
            'fremde Betreuung' => ['fremd'],
            'abgelaufene Betreuung' => ['abgelaufen'],
            'zukünftige Betreuung' => ['zukuenftig'],
        ];
    }

    #[Test]
    #[DataProvider('nichtAktivBetreut')]
    public function berufsbildner_erhaelt_404_fuer_nicht_aktiv_betreute(string $art): void
    {
        $this->betreuungId = $art === 'fremd'
            ? Betreuung::factory()->create(['lernender_id' => $this->lernender->lernender_id])->getKey()
            : $this->betreue($this->bb, $this->lernender, $art)->getKey();

        foreach ($this->routen() as [$methode, $name, $parameter, $daten]) {
            $status = $this->actingAs($this->bb)->call($methode, route('trainer.'.$name, $parameter), $daten)->getStatusCode();
            $this->assertSame(404, $status, "{$methode} {$name}");
        }

        $this->actingAs($this->bb)
            ->post(route('comments.store', $this->note->note_id), ['kommentar_text' => 'Hallo'])
            ->assertNotFound();

        $this->actingAs($this->bb)->get(route('trainer.learners.index'))
            ->assertOk()
            ->assertDontSee('Unsichtbarius');

        $export = $this->actingAs($this->bb)->get(route('trainer.grades.export_all'));
        $export->assertOk();
        $this->assertStringNotContainsString('Geheime Pruefung', $export->streamedContent());

        $this->assertDatabaseCount('noten_kommentare', 0);
        $this->assertDatabaseCount('noten_gesehen', 0);
        $this->assertTrue($this->lernender->benutzer->fresh()->aktiv);
        $this->assertNotSoftDeleted($this->note);
        $this->assertDatabaseHas('lernender_tracks', ['lernender_track_id' => $this->trackId, 'end_datum' => null]);
        $this->assertDatabaseHas('betreuungen', ['betreuung_id' => $this->betreuungId]);
        $this->assertModelExists($this->dokument);
        Storage::disk('local')->assertExists($this->dokument->pfad);
        $this->assertModelExists($this->pruefung);
        $this->assertSame('Geheime LB', $this->pruefung->fresh()->titel);
    }

    #[Test]
    public function jede_route_des_berufsbildners_zu_einem_lernenden_steht_in_der_pruefliste(): void
    {
        // Neue Routen mit Lernenden- oder Prüfungsbezug fallen sonst still aus der 404-Prüfung oben
        $geprueft = array_map(fn (array $r) => 'trainer.'.$r[1], $this->routen());
        $fehlend = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $r) => str_starts_with((string) $r->getName(), 'trainer.')
                && preg_match('/\{(lernender_id|pruefung_id)\}/', $r->uri()))
            ->map(fn (Route $r) => $r->getName())
            ->reject(fn (string $name) => in_array($name, $geprueft, true))
            ->values()->all();

        $this->assertSame([], $fehlend, 'Fehlt in routen(): '.implode(', ', $fehlend));
    }

    #[Test]
    public function berufsbildner_sieht_aktiv_betreute(): void
    {
        $this->betreuungId = $this->betreue($this->bb, $this->lernender)->getKey();

        foreach ($this->leseRouten() as $name => $parameter) {
            $this->actingAs($this->bb)->get(route('trainer.'.$name, $parameter))->assertOk();
        }

        $this->actingAs($this->bb)->get(route('trainer.learners.index'))->assertSee('Unsichtbarius');
        $this->assertStringContainsString(
            'Geheime Pruefung',
            $this->actingAs($this->bb)->get(route('trainer.grades.export_all'))->streamedContent(),
        );

        // Gegenprobe zur 404-Liste: dieselben Aufrufe der Prüfungsrouten kommen bei aktiver Betreuung durch
        $this->actingAs($this->bb)->put(route('trainer.exams.update', $this->pruefung->getKey()), $this->abgabe())
            ->assertRedirect();
        $this->actingAs($this->bb)->delete(route('trainer.exams.destroy', $this->pruefung->getKey()), ['lernender_id' => $this->lernender->lernender_id])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertModelMissing($this->pruefung);
    }

    #[Test]
    public function fremde_pruefung_ueber_einen_eigenen_lernenden_bleibt_unerreichbar(): void
    {
        // Eigener Lernender mit demselben Track: das Fach ist für ihn gültig, es antwortet also die Zugehörigkeit der Prüfung
        $eigener = $this->neuerLernender();
        $this->betreue($this->bb, $eigener);
        $this->bmsTrack($eigener, $this->note->semester_id);
        $daten = ['lernender_id' => $eigener->lernender_id] + $this->abgabe();

        $this->actingAs($this->bb)->put(route('trainer.exams.update', $this->pruefung->getKey()), $daten)->assertNotFound();
        $this->actingAs($this->bb)->delete(route('trainer.exams.destroy', $this->pruefung->getKey()), $daten)->assertNotFound();

        $this->assertSame('Geheime LB', $this->pruefung->fresh()->titel);
    }

    #[Test]
    public function admin_sieht_alle_lernenden_auch_ohne_betreuung(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ($this->leseRouten() + ['learners.grades.create' => [$this->lernender->lernender_id]] as $name => $parameter) {
            $this->actingAs($admin)->get(route('admin.'.$name, $parameter))->assertOk();
        }

        $this->actingAs($admin)->get(route('admin.learners.index'))->assertSee('Unsichtbarius');
        $this->assertStringContainsString(
            'Geheime Pruefung',
            $this->actingAs($admin)->get(route('admin.grades.export_all'))->streamedContent(),
        );
    }

    #[Test]
    public function berufsbildner_sieht_im_export_nur_eigene_lernende(): void
    {
        $eigener = $this->neuerLernender();
        $this->betreue($this->bb, $eigener);
        Note::factory()->create(['lernender_id' => $eigener->lernender_id, 'titel' => 'Eigene Pruefung']);

        $csv = $this->actingAs($this->bb)->get(route('trainer.grades.export_all'))->streamedContent();

        $this->assertStringContainsString('Eigene Pruefung', $csv);
        $this->assertStringNotContainsString('Geheime Pruefung', $csv);
    }

    /** @return array<string, list<int>> */
    private function leseRouten(): array
    {
        $id = $this->lernender->lernender_id;

        return [
            'learners.show' => [$id],
            'learners.edit' => [$id],
            'learners.grades.index' => [$id],
            'learners.grades.edit' => [$id, $this->note->note_id],
            'learners.grades.print' => [$id],
            'learners.grades.export' => [$id],
        ];
    }

    /** @return array<string, mixed> gültige Eingabe für einen Abgabetermin, damit die Sichtbarkeit und nicht die Validierung antwortet */
    private function abgabe(): array
    {
        return [
            'lernender_id' => $this->lernender->lernender_id,
            'bezug' => 'fach:'.$this->pruefung->fach_id,
            'titel' => 'Geaendert',
            'datum' => now()->addWeeks(2)->toDateString(),
        ];
    }

    /** @return list<array{0: string, 1: string, 2: list<int>, 3: array<string, mixed>}> */
    private function routen(): array
    {
        $id = $this->lernender->lernender_id;
        $noteId = $this->note->note_id;
        $betreuungId = $this->betreuungId ?? 0;

        $liste = [
            ['GET', 'learners.show', [$id]],
            ['GET', 'learners.edit', [$id]],
            ['PUT', 'learners.update', [$id]],
            ['POST', 'learners.account.password', [$id]],
            ['POST', 'learners.account.active', [$id]],
            ['POST', 'learners.supervision.store', [$id]],
            ['POST', 'supervisions.end', [$id, $betreuungId]],
            ['POST', 'learners.tracks.store', [$id]],
            ['POST', 'tracks.end', [$id, $this->trackId]],
            ['GET', 'learners.grades.index', [$id]],
            ['GET', 'learners.grades.create', [$id]],
            ['POST', 'learners.grades.store', [$id]],
            ['GET', 'learners.grades.edit', [$id, $noteId]],
            ['PUT', 'learners.grades.update', [$id, $noteId]],
            ['DELETE', 'learners.grades.destroy', [$id, $noteId]],
            ['GET', 'learners.grades.print', [$id]],
            ['GET', 'learners.grades.export', [$id]],
            ['POST', 'learners.grades.seen', [$id, $noteId]],
            ['POST', 'learners.grades.seen_all', [$id]],
            ['GET', 'learners.grades.import.index', [$id]],
            ['GET', 'learners.grades.import.template', [$id]],
            ['POST', 'learners.grades.import.read', [$id]],
            ['POST', 'learners.grades.import.validate', [$id]],
            ['POST', 'learners.grades.import.apply', [$id]],
            ['POST', 'learners.grades.import.discard', [$id]],
            ['GET', 'learners.calculator', [$id]],
            ['POST', 'learners.calculator.calculate', [$id]],
            ['GET', 'learners.qualification', [$id]],
            ['PUT', 'learners.qualification.update', [$id]],
            ['GET', 'learners.documents.index', [$id]],
            ['POST', 'learners.documents.store', [$id]],
            ['GET', 'learners.documents.show', [$id, $this->dokument->getKey()]],
            ['DELETE', 'learners.documents.destroy', [$id, $this->dokument->getKey()]],
            ['GET', 'learners.documents.reconcile', [$id, $this->dokument->getKey()]],
            ['POST', 'learners.documents.reconcile.apply', [$id, $this->dokument->getKey()]],
            ['PUT', 'exams.update', [$this->pruefung->getKey()], $this->abgabe()],
            ['DELETE', 'exams.destroy', [$this->pruefung->getKey()], ['lernender_id' => $id]],
        ];

        return array_map(fn (array $r) => $r + [3 => []], $liste);
    }
}
