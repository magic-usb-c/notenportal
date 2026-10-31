<?php

declare(strict_types=1);

namespace Tests\Feature\Module;

use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Ein hinzugefügtes Modul lässt sich wieder entfernen – solange nichts daran hängt. */
class ModulEntfernenTest extends TestCase
{
    private int $lehrberuf;

    private int $modul;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $this->lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $this->modul = DB::table('module')->insertGetId(['modul_nummer' => 'M810', 'titel' => 'Entfernbares Modul', 'aktiv' => 1]);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $this->lehrberuf, 'modul_id' => $this->modul,
            'kategorie_id' => DB::table('kategorien')->where('code', 'FACH')->value('kategorie_id')]);
        DB::table('semester')->insert(['bezeichnung' => '25/26-2', 'start_datum' => '2026-02-01',
            'end_datum' => '2026-07-31', 'sortierung' => 10]);
        Konfiguration::vergessen();
    }

    private function lernender(): User
    {
        return User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create();
    }

    private function belegungen(User $user): int
    {
        return DB::table('modul_belegungen')->where('modul_id', $this->modul)
            ->where('lernender_id', $user->lernender->lernender_id)->count();
    }

    private function note(): void
    {
        $this->post(route('learner.grades.store'), [
            'typ' => 'modul', 'modul_id' => $this->modul, 'pruefungsdatum' => '2026-03-02',
            'note_wert' => '4.5', 'gewichtung_prozent' => 100,
        ])->assertSessionHasNoErrors();
    }

    #[Test]
    public function ein_belegtes_modul_ohne_noten_wird_entfernt(): void
    {
        $user = $this->lernender();
        $this->actingAs($user)->post(route('modules.enroll', $this->modul))->assertSessionHas('success');
        $this->assertSame(1, $this->belegungen($user));

        $this->delete(route('modules.unenroll', $this->modul))->assertRedirect()
            ->assertSessionHas('success', 'Modul aus deinen Modulen entfernt.');

        $this->assertSame(0, $this->belegungen($user));
        // Und lässt sich jederzeit wieder hinzufügen
        $this->post(route('modules.enroll', $this->modul))->assertSessionHas('success');
        $this->assertSame(1, $this->belegungen($user));
    }

    #[Test]
    public function mit_einer_note_wird_das_entfernen_abgelehnt(): void
    {
        $user = $this->lernender();
        $this->actingAs($user);
        $this->note();

        $this->delete(route('modules.unenroll', $this->modul))->assertRedirect()
            ->assertSessionHas('error')->assertSessionMissing('success');

        $this->assertSame(1, $this->belegungen($user));
        $this->assertSame(1, DB::table('noten')->count());
    }

    #[Test]
    public function auch_eine_geloeschte_note_haelt_die_belegung_fest(): void
    {
        $user = $this->lernender();
        $this->actingAs($user);
        $this->note();
        DB::table('noten')->update(['geloescht_am' => now()]);

        $this->delete(route('modules.unenroll', $this->modul))->assertSessionHas('error')->assertSessionMissing('success');

        $this->assertSame(1, $this->belegungen($user));
        $this->get(route('modules.show', $this->modul))->assertOk()->assertDontSee('Aus meinen Modulen entfernen');
    }

    #[Test]
    public function mit_einer_geplanten_pruefung_wird_das_entfernen_abgelehnt(): void
    {
        $user = $this->lernender();
        $this->actingAs($user)->post(route('modules.enroll', $this->modul))->assertSessionHas('success');
        DB::table('pruefungen')->insert(['lernender_id' => $user->lernender->lernender_id,
            'modul_id' => $this->modul, 'titel' => 'Test', 'datum' => '2026-06-01']);

        $this->delete(route('modules.unenroll', $this->modul))->assertSessionHas('error')->assertSessionMissing('success');

        $this->assertSame(1, $this->belegungen($user));
        $this->get(route('modules.show', $this->modul))->assertOk()->assertDontSee('Aus meinen Modulen entfernen');
    }

    #[Test]
    public function fremde_belegungen_bleiben_unberuehrt(): void
    {
        $andere = $this->lernender();
        $this->actingAs($andere)->post(route('modules.enroll', $this->modul))->assertSessionHas('success');
        $ich = $this->lernender();

        $this->actingAs($ich)->delete(route('modules.unenroll', $this->modul))->assertSessionMissing('error');

        $this->assertSame(1, $this->belegungen($andere));
    }

    #[Test]
    public function zweites_entfernen_meldet_keinen_fehler_zu_noten(): void
    {
        $user = $this->lernender();
        $this->actingAs($user)->post(route('modules.enroll', $this->modul))->assertSessionHas('success');

        $this->delete(route('modules.unenroll', $this->modul))->assertSessionHas('success')->assertSessionMissing('error');
        $this->delete(route('modules.unenroll', $this->modul))->assertRedirect()
            ->assertSessionHas('success', 'Modul aus deinen Modulen entfernt.')->assertSessionMissing('error');

        $this->assertSame(0, $this->belegungen($user));
        $this->assertSame(0, DB::table('noten')->count());
    }

    #[Test]
    public function ein_nie_belegtes_modul_meldet_keinen_fehler_zu_noten(): void
    {
        $user = $this->lernender();

        $this->actingAs($user)->delete(route('modules.unenroll', $this->modul))->assertRedirect()
            ->assertSessionHas('success')->assertSessionMissing('error');

        $this->assertSame(0, $this->belegungen($user));
    }

    #[Test]
    public function fremde_rollen_erhalten_403(): void
    {
        foreach (['admin', 'berufsbildner'] as $rolle) {
            $this->actingAs(User::factory()->{$rolle}()->create())
                ->delete(route('modules.unenroll', $this->modul))->assertForbidden();
        }
    }

    #[Test]
    public function der_knopf_erscheint_nur_bei_belegtem_und_entfernbarem_modul(): void
    {
        $user = $this->lernender();
        $this->actingAs($user)->get(route('modules.show', $this->modul))->assertOk()
            ->assertDontSee('Aus meinen Modulen entfernen');

        $this->post(route('modules.enroll', $this->modul));
        $this->get(route('modules.show', $this->modul))->assertOk()
            ->assertSee('Aus meinen Modulen entfernen')
            ->assertSee(route('modules.unenroll', $this->modul), false);

        $this->note();
        $this->get(route('modules.show', $this->modul))->assertOk()->assertDontSee('Aus meinen Modulen entfernen');
    }
}
