<?php

namespace Database\Factories;

use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Standard: ein Semester, das heute läuft. Das Enddatum wird pro Instanz
 * versetzt, weil (start_datum, end_datum) eindeutig sein muss.
 *
 * @extends Factory<Semester>
 */
class SemesterFactory extends Factory
{
    public function definition(): array
    {
        $versatz = fake()->unique()->numberBetween(0, 3000);

        return [
            'bezeichnung' => 'Semester '.fake()->unique()->numberBetween(1, 99999),
            'start_datum' => now()->subMonths(2)->toDateString(),
            'end_datum' => now()->addMonths(3)->addDays($versatz)->toDateString(),
            'sortierung' => fake()->unique()->numberBetween(1, 60000),
        ];
    }
}
