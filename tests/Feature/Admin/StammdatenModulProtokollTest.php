<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StammdatenModulProtokollTest extends TestCase
{
    use DatabaseTransactions;

    public function test_modul_anlegen_und_aendern_in_den_stammdaten_steht_im_aktivitaetsprotokoll(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.master-data.modules.store'), [
            'modul_nummer' => 'x997', 'titel' => 'Testmodul', 'ziel_gewicht_summe_default' => 100,
        ])->assertRedirect();

        $id = (int) DB::table('module')->where('modul_nummer', 'X997')->value('modul_id');
        $this->assertGreaterThan(0, $id);
        $this->assertDatabaseHas('aktivitaeten', [
            'benutzer_id' => $admin->benutzer_id,
            'aktion' => 'modul.angelegt',
            'ziel_typ' => 'Modul',
            'ziel_id' => $id,
        ]);

        $this->actingAs($admin)->put(route('admin.master-data.modules.update', $id), [
            'modul_nummer' => 'X998', 'titel' => 'Testmodul2', 'ziel_gewicht_summe_default' => 90,
        ])->assertRedirect();

        $this->assertDatabaseHas('module', ['modul_id' => $id, 'modul_nummer' => 'X998']);
        $this->assertDatabaseHas('aktivitaeten', [
            'benutzer_id' => $admin->benutzer_id,
            'aktion' => 'modul.geaendert',
            'ziel_typ' => 'Modul',
            'ziel_id' => $id,
        ]);
    }
}
