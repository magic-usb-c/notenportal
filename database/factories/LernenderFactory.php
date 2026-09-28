<?php

namespace Database\Factories;

use App\Models\Lehrberuf;
use App\Models\Lernender;
use App\Models\User;
use Carbon\Carbon;
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
            // Der Lehrbeginn bleibt fest: viele Tests legen ihre Semester passend
            // dazu an (Start 2024-08-01) und rechnen mit dem Lehrjahr daraus.
            'lehrbeginn' => '2024-08-01',
            // Das Lehrende darf nie in der Vergangenheit liegen, sonst weist
            // NoteService jedes Prüfungsdatum mit `now()` ab und die
            // «aktiv»-Filter der Benachrichtigungen lassen den Lernenden fallen.
            // Darum mindestens ein Jahr voraus – bis 2027 bleibt es 2028-07-31,
            // danach wandert es mit, ohne dass ein Test angepasst werden muss.
            'lehrende' => Carbon::parse('2028-07-31')->max(now()->addYear())->toDateString(),
        ];
    }
}
