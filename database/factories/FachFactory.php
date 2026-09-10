<?php

namespace Database\Factories;

use App\Models\Fach;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fach>
 */
class FachFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Fach '.fake()->unique()->numberBetween(1, 99999);

        return [
            'track_typ' => 'BMS',
            'name' => $name,
            'kurzname' => $name,
            'aktiv' => 1,
        ];
    }
}
