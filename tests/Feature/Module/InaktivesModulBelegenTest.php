<?php

declare(strict_types=1);

namespace Tests\Feature\Module;

use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Ein vom Admin ausgeschaltetes Modul lässt sich nicht neu belegen; bestehende Belegungen bleiben. */
class InaktivesModulBelegenTest extends TestCase
{
    private int $lehrberuf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $this->lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        DB::table('semester')->insert(['bezeichnung' => '25/26-2', 'start_datum' => '2026-02-01',
            'end_datum' => '2026-07-31', 'sortierung' => 10]);
        Konfiguration::vergessen();
    }

    private function lernender(): User
    {
        return User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create();
    }

    #[Test]
    public function ein_inaktives_modul_wird_nicht_belegt_und_der_knopf_fehlt(): void
    {
        $modulId = DB::table('module')->insertGetId(['modul_nummer' => 'M790', 'titel' => 'Ausser Betrieb', 'aktiv' => 0]);
        $user = $this->lernender();

        $this->actingAs($user)->get(route('modules.show', $modulId))->assertOk()
            ->assertDontSee('Zu meinen Modulen hinzufügen');

        $this->post(route('modules.enroll', $modulId))->assertRedirect()->assertSessionHas('error')
            ->assertSessionMissing('success');

        $this->assertSame(0, DB::table('modul_belegungen')->where('modul_id', $modulId)->count());
    }

    #[Test]
    public function ein_aktives_modul_bleibt_belegbar(): void
    {
        $modulId = DB::table('module')->insertGetId(['modul_nummer' => 'M791', 'titel' => 'Im Betrieb', 'aktiv' => 1]);

        $this->actingAs($this->lernender())->post(route('modules.enroll', $modulId))->assertSessionHas('success');

        $this->assertSame(1, DB::table('modul_belegungen')->where('modul_id', $modulId)->count());
    }

    #[Test]
    public function eine_bestehende_belegung_bleibt_nach_der_deaktivierung_erhalten(): void
    {
        $modulId = DB::table('module')->insertGetId(['modul_nummer' => 'M792', 'titel' => 'Später aus', 'aktiv' => 1]);
        $user = $this->lernender();
        $this->actingAs($user)->post(route('modules.enroll', $modulId))->assertSessionHas('success');

        DB::table('module')->where('modul_id', $modulId)->update(['aktiv' => 0]);

        $this->post(route('modules.enroll', $modulId))->assertSessionHas('error');
        $this->assertSame(1, DB::table('modul_belegungen')->where('modul_id', $modulId)->whereNull('end_datum')->count());
        $this->get(route('modules.show', $modulId))->assertOk()->assertSee('Note erfassen');
    }
}
