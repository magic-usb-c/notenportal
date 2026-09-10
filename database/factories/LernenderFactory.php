<?php

namespace Database\Factories;

use App\Models\Lehrberuf;
use App\Models\Lernender;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Nur der lernende-Datensatz. Für ein Login mit Rolle: User::factory()->lernender().
 *
 * @extends Factory<Lernender>
 */
class LernenderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'benutzer_id' => User::factory(),
            'lehrberuf_id' => Lehrberuf::factory(),
            'lehrbeginn' => '2024-08-01',
            'lehrende' => '2028-07-31',
        ];
    }
}
