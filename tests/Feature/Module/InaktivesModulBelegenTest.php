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
        // Knopf im Modul-Detail (nicht das gleichlautende Tastenkürzel im Layout) und Option in der Notenerfassung
        $this->get(route('modules.show', $modulId))->assertOk()
            ->assertSee(e(route('learner.grades.create', ['bezug' => 'modul:'.$modulId, 'rueckziel' => route('modules.show', $modulId, false)])), false);
        $this->get(route('learner.grades.create'))->assertOk()->assertSee('value="modul:'.$modulId.'"', false);
    }

    #[Test]
    public function ausgeschaltetes_modul_ohne_belegung_fehlt_in_der_auswahl(): void
    {
        $modulId = $this->inaktivesLehrberufsmodul('M793');

        $this->actingAs($this->lernender())->get(route('learner.grades.create'))->assertOk()
            ->assertDontSee('value="modul:'.$modulId.'"', false);
    }

    /** Modul im Lehrberuf (lehrberuf_module aktiv), aber vom Admin ausgeschaltet (module.aktiv = 0). */
    private function inaktivesLehrberufsmodul(string $nummer): int
    {
        $modulId = DB::table('module')->insertGetId(['modul_nummer' => $nummer, 'titel' => 'Ausser Betrieb', 'aktiv' => 0]);
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $this->lehrberuf,
            'modul_id' => $modulId,
            'kategorie_id' => DB::table('kategorien')->where('code', 'UEK')->value('kategorie_id'),
        ]);

        return $modulId;
    }

    #[Test]
    public function eine_note_auf_einem_inaktiven_modul_ohne_belegung_wird_abgelehnt(): void
    {
        $modulId = $this->inaktivesLehrberufsmodul('M793');
        $user = $this->lernender();

        $this->actingAs($user)->post(route('learner.grades.store'), [
            'typ' => 'modul', 'modul_id' => $modulId, 'pruefungsdatum' => '2026-03-10',
            'note_wert' => '5', 'gewichtung_prozent' => 100, 'titel' => 'LB1',
        ])->assertSessionHasErrors('modul_id');

        $this->assertSame(0, DB::table('modul_belegungen')->where('modul_id', $modulId)->count());
        $this->assertSame(0, DB::table('noten')->where('lernender_id', $user->lernender->lernender_id)->count());
    }

    #[Test]
    public function eine_pruefung_auf_einem_inaktiven_modul_ohne_belegung_wird_abgelehnt(): void
    {
        $modulId = $this->inaktivesLehrberufsmodul('M794');

        $this->actingAs($this->lernender())->post(route('learner.exams.store'), [
            'bezug' => 'modul:'.$modulId, 'datum' => '2026-03-10', 'gewichtung_prozent' => 40, 'titel' => 'LB2',
        ])->assertSessionHasErrors();

        $this->assertSame(0, DB::table('pruefungen')->where('modul_id', $modulId)->count());
    }

    #[Test]
    public function mit_bestehender_belegung_bleiben_noten_und_pruefungen_auf_dem_inaktiven_modul_moeglich(): void
    {
        $modulId = $this->inaktivesLehrberufsmodul('M795');
        DB::table('module')->where('modul_id', $modulId)->update(['aktiv' => 1]);
        $user = $this->lernender();
        $this->actingAs($user)->post(route('modules.enroll', $modulId))->assertSessionHas('success');
        DB::table('module')->where('modul_id', $modulId)->update(['aktiv' => 0]);

        $this->post(route('learner.grades.store'), [
            'typ' => 'modul', 'modul_id' => $modulId, 'pruefungsdatum' => '2026-03-10',
            'note_wert' => '5', 'gewichtung_prozent' => 100, 'titel' => 'LB1',
        ])->assertSessionHasNoErrors();
        $this->post(route('learner.exams.store'), [
            'bezug' => 'modul:'.$modulId, 'datum' => '2026-03-20', 'gewichtung_prozent' => 40, 'titel' => 'LB2',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('noten')->where('lernender_id', $user->lernender->lernender_id)->count());
        $this->assertSame(1, DB::table('pruefungen')->where('modul_id', $modulId)->count());
    }
}
