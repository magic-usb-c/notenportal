<?php

namespace Database\Factories;

use App\Models\Lehrberuf;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lehrberuf>
 */
class LehrberufFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kuerzel' => strtoupper(fake()->unique()->lexify('????')),
            'name' => fake()->unique()->jobTitle().' EFZ',
            'aktiv' => true,
        ];
    }
}
