<?php

namespace Database\Factories;

use App\Models\Berufsbildner;
use App\Models\Lernender;
use App\Models\Rolle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public const PASSWORT = 'Testpasswort!2026';

    public function definition(): array
    {
        return [
            'benutzername' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'vorname' => fake()->firstName(),
            'nachname' => fake()->lastName(),
            'passwort_hash' => self::PASSWORT,
            'aktiv' => true,
        ];
    }

    public function inaktiv(): static
    {
        return $this->state(['aktiv' => false]);
    }

    public function admin(): static
    {
        return $this->mitRolle('Admin');
    }

    public function berufsbildner(): static
    {
        return $this->mitRolle('Berufsbildner')->afterCreating(
            fn (User $user) => Berufsbildner::factory()->create(['benutzer_id' => $user->benutzer_id])
        );
    }

    /** @param array<string, mixed> $lernender Attribute für den lernende-Datensatz */
    public function lernender(array $lernender = []): static
    {
        return $this->mitRolle('Lernender')->afterCreating(
            fn (User $user) => Lernender::factory()->create(['benutzer_id' => $user->benutzer_id, ...$lernender])
        );
    }

    private function mitRolle(string $rolle): static
    {
        return $this->afterCreating(
            fn (User $user) => $user->rollen()->attach(Rolle::where('name', $rolle)->value('rolle_id'))
        );
    }
}
