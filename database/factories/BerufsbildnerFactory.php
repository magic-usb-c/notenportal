<?php

namespace Database\Factories;

use App\Models\Berufsbildner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Nur der berufsbildner-Datensatz. Für ein Login mit Rolle: User::factory()->berufsbildner().
 *
 * @extends Factory<Berufsbildner>
 */
class BerufsbildnerFactory extends Factory
{
    public function definition(): array
    {
        return ['benutzer_id' => User::factory()];
    }
}
