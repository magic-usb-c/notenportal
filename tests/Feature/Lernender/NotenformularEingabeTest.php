<?php

declare(strict_types=1);

namespace Tests\Feature\Lernender;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotenformularEingabeTest extends TestCase
{
    #[Test]
    public function leeres_formular_verlangt_fach_oder_modul_statt_typ(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->from('/grades')
            ->post(route('learner.grades.store'), ['_drawer' => 'neu', 'typ' => '', 'pruefungsdatum' => now()->toDateString()])
            ->assertSessionHasErrors(['typ' => 'Bitte ein Fach oder Modul wählen.']);

        $this->actingAs($user)->from('/grades')
            ->post(route('learner.grades.store'), ['_drawer' => 'neu', 'typ' => 'unbekannt', 'pruefungsdatum' => now()->toDateString()])
            ->assertSessionHasErrors(['typ' => 'Bitte ein Fach oder Modul wählen.']);
    }

    #[Test]
    public function array_parameter_in_der_adresse_fuehren_nicht_zu_fehler_500(): void
    {
        $this->actingAs(User::factory()->lernender()->create());

        foreach (['/exams?monat[]=1', '/exams?monat[]=1&ansicht[]=x', '/grades/create?bezug[]=1', '/modules?suche[]=x'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->getJson('/search?q[]=x')->assertOk()->assertExactJson([]);
    }
}
