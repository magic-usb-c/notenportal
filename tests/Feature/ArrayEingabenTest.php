<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Ein Array an Stelle eines Textparameters (?q[]=x) ist kein 500: Filter fallen auf leer zurück, Formulare melden einen Fehler. */
class ArrayEingabenTest extends TestCase
{
    #[Test]
    public function admin_filter_nehmen_arrays_als_leere_eingabe(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('admin.feedback.index', ['status' => ['x'], 'kategorie' => ['x'], 'rolle' => ['x'], 'suche' => ['x']]))->assertOk();
        $this->get(route('admin.mail-log.index', ['status' => ['x'], 'type' => ['x'], 'q' => ['x']]))->assertOk();
        $this->get(route('admin.master-data.modules.index', ['suche' => ['x']]))->assertOk();
        $this->get(route('admin.users.index', ['suche' => ['x'], 'rolle_id' => ['1'], 'status' => ['aktiv']]))->assertOk();
        $this->post(route('admin.master-data.subjects.store'), ['name' => 'Fach', 'kurzname' => 'F', 'track_typ' => ['BMS', 'ABU']])
            ->assertSessionHasErrors('track_typ');
    }

    #[Test]
    public function systemhinweis_und_notenimport_lehnen_arrays_ohne_serverfehler_ab(): void
    {
        $lernender = User::factory()->lernender()->create();
        $this->actingAs($lernender);

        $this->assertLessThan(500, $this->postJson(route('system-notice.dismiss'), ['version' => ['x']])->status());
        $this->postJson(route('learner.grades.import.validate'), ['zeilen' => ['x']])->assertUnprocessable();
    }
}
