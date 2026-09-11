<?php

namespace Tests\Feature\Verwaltung;

use App\Models\Betreuung;
use App\Models\Lernender;
use App\Models\Note;
use App\Models\User;
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

        foreach ($this->routen() as [$methode, $name, $parameter]) {
            $status = $this->actingAs($this->bb)->call($methode, route('trainer.'.$name, $parameter))->getStatusCode();
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

    /** @return list<array{0: string, 1: string, 2: list<int>}> */
    private function routen(): array
    {
        $id = $this->lernender->lernender_id;
        $noteId = $this->note->note_id;

        return [
            ['GET', 'learners.show', [$id]],
            ['GET', 'learners.edit', [$id]],
            ['PUT', 'learners.update', [$id]],
            ['POST', 'learners.account.password', [$id]],
            ['POST', 'learners.account.active', [$id]],
            ['POST', 'learners.supervision.store', [$id]],
            ['POST', 'supervisions.end', [$id, $this->betreuungId]],
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
        ];
    }
}
