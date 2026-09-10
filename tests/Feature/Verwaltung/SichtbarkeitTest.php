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
            $status = $this->actingAs($this->bb)->call($methode, route('berufsbildner.'.$name, $parameter))->getStatusCode();
            $this->assertSame(404, $status, "{$methode} {$name}");
        }

        $this->actingAs($this->bb)
            ->post(route('noten.kommentare.store', $this->note->note_id), ['kommentar_text' => 'Hallo'])
            ->assertNotFound();

        $this->actingAs($this->bb)->get(route('berufsbildner.lernende.index'))
            ->assertOk()
            ->assertDontSee('Unsichtbarius');

        $export = $this->actingAs($this->bb)->get(route('berufsbildner.noten.export_alle'));
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
            $this->actingAs($this->bb)->get(route('berufsbildner.'.$name, $parameter))->assertOk();
        }

        $this->actingAs($this->bb)->get(route('berufsbildner.lernende.index'))->assertSee('Unsichtbarius');
        $this->assertStringContainsString(
            'Geheime Pruefung',
            $this->actingAs($this->bb)->get(route('berufsbildner.noten.export_alle'))->streamedContent(),
        );
    }

    #[Test]
    public function admin_sieht_alle_lernenden_auch_ohne_betreuung(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ($this->leseRouten() + ['lernende.noten.create' => [$this->lernender->lernender_id]] as $name => $parameter) {
            $this->actingAs($admin)->get(route('admin.'.$name, $parameter))->assertOk();
        }

        $this->actingAs($admin)->get(route('admin.lernende.index'))->assertSee('Unsichtbarius');
        $this->assertStringContainsString(
            'Geheime Pruefung',
            $this->actingAs($admin)->get(route('admin.noten.export_alle'))->streamedContent(),
        );
    }

    #[Test]
    public function berufsbildner_sieht_im_export_nur_eigene_lernende(): void
    {
        $eigener = $this->neuerLernender();
        $this->betreue($this->bb, $eigener);
        Note::factory()->create(['lernender_id' => $eigener->lernender_id, 'titel' => 'Eigene Pruefung']);

        $csv = $this->actingAs($this->bb)->get(route('berufsbildner.noten.export_alle'))->streamedContent();

        $this->assertStringContainsString('Eigene Pruefung', $csv);
        $this->assertStringNotContainsString('Geheime Pruefung', $csv);
    }

    /** @return array<string, list<int>> */
    private function leseRouten(): array
    {
        $id = $this->lernender->lernender_id;

        return [
            'lernende.show' => [$id],
            'lernende.edit' => [$id],
            'lernende.noten.index' => [$id],
            'lernende.noten.edit' => [$id, $this->note->note_id],
            'lernende.noten.drucken' => [$id],
            'lernende.noten.export' => [$id],
        ];
    }

    /** @return list<array{0: string, 1: string, 2: list<int>}> */
    private function routen(): array
    {
        $id = $this->lernender->lernender_id;
        $noteId = $this->note->note_id;

        return [
            ['GET', 'lernende.show', [$id]],
            ['GET', 'lernende.edit', [$id]],
            ['PUT', 'lernende.update', [$id]],
            ['POST', 'lernende.konto.passwort', [$id]],
            ['POST', 'lernende.konto.aktiv', [$id]],
            ['POST', 'lernende.betreuung.store', [$id]],
            ['POST', 'betreuungen.beenden', [$id, $this->betreuungId]],
            ['POST', 'lernende.tracks.store', [$id]],
            ['POST', 'tracks.beenden', [$id, $this->trackId]],
            ['GET', 'lernende.noten.index', [$id]],
            ['GET', 'lernende.noten.create', [$id]],
            ['POST', 'lernende.noten.store', [$id]],
            ['GET', 'lernende.noten.edit', [$id, $noteId]],
            ['PUT', 'lernende.noten.update', [$id, $noteId]],
            ['DELETE', 'lernende.noten.destroy', [$id, $noteId]],
            ['GET', 'lernende.noten.drucken', [$id]],
            ['GET', 'lernende.noten.export', [$id]],
            ['POST', 'lernende.noten.gesehen', [$id, $noteId]],
            ['POST', 'lernende.noten.alle_gesehen', [$id]],
        ];
    }
}
