<?php

namespace Database\Factories;

use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Lernender;
use App\Models\Note;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Standard: BMS-Fachnote im laufenden Semester, erfasst vom Lernenden selbst.
 *
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lernender_id' => Lernender::factory(),
            'kategorie_id' => fn () => Kategorie::where('code', 'BMS')->value('kategorie_id'),
            'semester_id' => Semester::factory(),
            'fach_id' => Fach::factory(),
            'modul_belegung_id' => null,
            'titel' => fake()->sentence(3),
            'pruefungsdatum' => now()->toDateString(),
            'note_wert' => 4.5,
            'gewichtung_prozent' => 100,
            'erfasst_von_benutzer_id' => fn (array $note) => Lernender::find($note['lernender_id'])->benutzer_id,
        ];
    }
}
