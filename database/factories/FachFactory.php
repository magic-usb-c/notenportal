<?php

namespace Database\Factories;

use App\Models\Fach;
use App\Models\Kategorie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Standard: BMS-Fach; die Kategorie folgt dem Track (ohne Track: Fachunterricht).
 *
 * @extends Factory<Fach>
 */
class FachFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Fach '.fake()->unique()->numberBetween(1, 99999);

        return [
            'track_typ' => 'BMS',
            'kategorie_id' => fn (array $fach) => Kategorie::where('code', $fach['track_typ'] ?? 'FACH')->value('kategorie_id'),
            'name' => $name,
            'kurzname' => $name,
            'aktiv' => 1,
        ];
    }
}
