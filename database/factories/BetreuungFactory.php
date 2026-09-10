<?php

namespace Database\Factories;

use App\Models\Berufsbildner;
use App\Models\Betreuung;
use App\Models\Lernender;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Betreuung>
 */
class BetreuungFactory extends Factory
{
    public function definition(): array
    {
        return [
            'berufsbildner_id' => Berufsbildner::factory(),
            'lernender_id' => Lernender::factory(),
            'gueltig_von' => now()->subYear()->toDateString(),
            'gueltig_bis' => null,
        ];
    }

    public function abgelaufen(): static
    {
        return $this->state([
            'gueltig_von' => now()->subYears(2)->toDateString(),
            'gueltig_bis' => now()->subMonth()->toDateString(),
        ]);
    }

    public function zukuenftig(): static
    {
        return $this->state(['gueltig_von' => now()->addMonth()->toDateString()]);
    }
}
