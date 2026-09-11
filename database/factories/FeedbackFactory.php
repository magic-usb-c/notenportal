<?php

namespace Database\Factories;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feedback>
 */
class FeedbackFactory extends Factory
{
    protected $model = Feedback::class;

    public function definition(): array
    {
        return [
            'benutzer_id' => User::factory()->lernender(),
            'rolle' => 'Lernender',
            'kategorie' => fake()->randomElement(array_keys(Feedback::KATEGORIEN)),
            'text' => fake()->realText(200),
            'route_name' => 'lernender.noten.index',
            'url' => '/noten',
            'user_agent' => 'Mozilla/5.0 (Testsuite)',
            'browser' => 'Chrome 128 · Windows',
            'viewport' => '1440x900',
            'js_fehler' => null,
            'status' => Feedback::STATUS_OFFEN,
            'admin_notiz' => null,
            'erledigt_am' => null,
        ];
    }
}
