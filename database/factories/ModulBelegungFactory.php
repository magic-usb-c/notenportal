<?php

namespace Database\Factories;

use App\Models\Lernender;
use App\Models\Modul;
use App\Models\ModulBelegung;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModulBelegung>
 */
class ModulBelegungFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lernender_id' => Lernender::factory(),
            'modul_id' => Modul::factory(),
            'start_datum' => '2024-08-01',
            'end_datum' => null,
        ];
    }
}
