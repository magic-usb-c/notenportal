<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Note;
use App\Models\NotenGesehen;
use App\Models\NotenKommentar;
use App\Models\Pruefung;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** app/Models/Note.php: Beziehungen zu Kommentaren/gesehen/Erfassern, Löschen öffnet die Prüfung wieder. */
class NoteModelTest extends TestCase
{
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
