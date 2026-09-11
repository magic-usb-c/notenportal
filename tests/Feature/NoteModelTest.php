<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Kategorie;
use App\Models\Note;
use App\Models\NotenGesehen;
use App\Models\NotenKommentar;
use App\Models\Pruefung;
use App\Models\Semester;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** app/Models/Note.php: Query-Scopes für Listen, Beziehungen zu Kommentaren/gesehen/Erfassern. */
class NoteModelTest extends TestCase
{
    #[Test]
    public function scope_for_lernender_liefert_nur_dessen_noten(): void
    {
        $eigene = Note::factory()->create();
        Note::factory()->create();

        $gefunden = Note::query()->forLernender($eigene->lernender_id)->get();

        $this->assertSame([$eigene->note_id], $gefunden->pluck('note_id')->all());
    }

    #[Test]
    public function scope_filter_kategorie_lässt_null_durch_und_filtert_sonst(): void
    {
        $bms = Kategorie::where('code', 'BMS')->value('kategorie_id') ?? Kategorie::factory()->create()->kategorie_id;
        $andere = Kategorie::where('kategorie_id', '!=', $bms)->value('kategorie_id') ?? Kategorie::factory()->create()->kategorie_id;
        $note = Note::factory()->create(['kategorie_id' => $bms]);
        Note::factory()->create(['kategorie_id' => $andere]);

        $this->assertSame(2, Note::query()->filterKategorie(null)->count());
        $gefiltert = Note::query()->filterKategorie($bms)->get();
        $this->assertSame([$note->note_id], $gefiltert->pluck('note_id')->all());
    }

    #[Test]
    public function scope_filter_semester_lässt_null_durch_und_filtert_sonst(): void
    {
        $sem1 = Semester::factory()->create();
        $sem2 = Semester::factory()->create();
        $note = Note::factory()->create(['semester_id' => $sem1->semester_id]);
        Note::factory()->create(['semester_id' => $sem2->semester_id]);

        $this->assertSame(2, Note::query()->filterSemester(null)->count());
        $gefiltert = Note::query()->filterSemester($sem1->semester_id)->get();
        $this->assertSame([$note->note_id], $gefiltert->pluck('note_id')->all());
    }

    #[Test]
    public function scope_ordered_sortiert_nach_pruefungsdatum_dann_note_id_absteigend(): void
    {
        $alt = Note::factory()->create(['pruefungsdatum' => '2026-01-10']);
        $neu = Note::factory()->create(['pruefungsdatum' => '2026-02-01']);
        $neuZweite = Note::factory()->create(['pruefungsdatum' => '2026-02-01']);

        $reihenfolge = Note::query()->ordered()->pluck('note_id')->all();

        $this->assertSame([$neuZweite->note_id, $neu->note_id, $alt->note_id], $reihenfolge);
    }

    #[Test]
    public function scope_with_overview_laedt_die_standard_relationen(): void
    {
        Note::factory()->create();

        $note = Note::query()->withOverview()->sole();

        foreach (Note::OVERVIEW_RELATIONS as $relation) {
            $this->assertTrue($note->relationLoaded(explode('.', $relation)[0]), "{$relation} sollte geladen sein");
        }
    }

    #[Test]
    public function kommentare_werden_nach_erstellungsdatum_aufsteigend_geladen(): void
    {
        // erstellt_am ist nicht fillable (automatischer Timestamp) – nach dem Anlegen gezielt zurückdatieren.
        $note = Note::factory()->create();
        $spaeter = NotenKommentar::create(['note_id' => $note->note_id, 'autor_benutzer_id' => $note->erfasst_von_benutzer_id, 'kommentar_text' => 'zweiter']);
        $frueher = NotenKommentar::create(['note_id' => $note->note_id, 'autor_benutzer_id' => $note->erfasst_von_benutzer_id, 'kommentar_text' => 'erster']);
        $spaeter->forceFill(['erstellt_am' => now()->addMinute()])->save();
        $frueher->forceFill(['erstellt_am' => now()])->save();

        $this->assertSame([$frueher->kommentar_id, $spaeter->kommentar_id], $note->kommentare()->pluck('kommentar_id')->all());
    }

    #[Test]
    public function gesehen_erfasst_von_und_aktualisiert_von_beziehungen(): void
    {
        $ersteller = User::factory()->lernender()->create();
        $aktualisierer = User::factory()->admin()->create();
        $note = Note::factory()->create([
            'erfasst_von_benutzer_id' => $ersteller->benutzer_id,
            'aktualisiert_von_benutzer_id' => $aktualisierer->benutzer_id,
        ]);
        NotenGesehen::create(['note_id' => $note->note_id, 'viewer_benutzer_id' => $aktualisierer->benutzer_id, 'gesehen_am' => now()]);

        $this->assertTrue($note->erfasstVonBenutzer->is($ersteller));
        $this->assertTrue($note->aktualisiertVonBenutzer->is($aktualisierer));
        $this->assertSame(1, $note->gesehen()->count());
    }

    #[Test]
    public function loeschen_der_note_oeffnet_die_verknuepfte_pruefung_wieder(): void
    {
        $note = Note::factory()->create();
        $pruefung = Pruefung::create([
            'lernender_id' => $note->lernender_id,
            'fach_id' => $note->fach_id,
            'titel' => 'LB1',
            'datum' => now()->toDateString(),
            'note_id' => $note->note_id,
        ]);

        $note->delete();

        $this->assertNull($pruefung->fresh()->note_id);
    }
}
