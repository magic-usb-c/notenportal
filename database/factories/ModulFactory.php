<?php

namespace Database\Factories;

use App\Models\Modul;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Modul>
 */
class ModulFactory extends Factory
{
    public function definition(): array
    {
        return [
            'modul_nummer' => (string) fake()->unique()->numberBetween(100, 999999),
            'titel' => fake()->sentence(3),
            'ziel_gewicht_summe_default' => 100,
            'aktiv' => 1,
        ];
    }
}
