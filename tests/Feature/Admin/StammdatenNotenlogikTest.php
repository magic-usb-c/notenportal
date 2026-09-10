<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Lehrberuf;
use App\Models\Modul;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StammdatenNotenlogikTest extends TestCase
{
    #[Test]
    public function fach_wird_mit_kategorie_und_ohne_track_angelegt(): void
    {
        $admin = User::factory()->admin()->create();
        $uek = Kategorie::where('code', 'UEK')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.stammdaten.faecher.store'), [
                'name' => 'Projektmanagement',
                'kurzname' => 'PM',
                'kategorie_id' => $uek->kategorie_id,
                'track_typ' => '',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.stammdaten.faecher.index'));

        $fach = Fach::where('kurzname', 'PM')->sole();
        $this->assertSame($uek->kategorie_id, $fach->kategorie_id);
        $this->assertNull($fach->track_typ);
    }

    #[Test]
    public function fach_ohne_kategorie_wird_abgewiesen(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.stammdaten.faecher.store'), [
                'name' => 'Ohne Kategorie',
                'kurzname' => 'OK',
                'track_typ' => '',
            ])
            ->assertSessionHasErrors('kategorie_id');

        $this->assertFalse(Fach::where('kurzname', 'OK')->exists());
    }

    #[Test]
    public function modul_zuweisen_setzt_kategorie_id(): void
    {
        $admin = User::factory()->admin()->create();
        $lehrberuf = Lehrberuf::factory()->create(['aktiv' => true]);
        $modul = Modul::factory()->create();
        $uek = Kategorie::where('code', 'UEK')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.stammdaten.lehrberufe.module.assign', $lehrberuf->lehrberuf_id), [
                'modul_id' => $modul->modul_id,
                'kategorie_id' => $uek->kategorie_id,
                'pflicht' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $uek->kategorie_id,
            DB::table('lehrberuf_module')
                ->where('lehrberuf_id', $lehrberuf->lehrberuf_id)
                ->where('modul_id', $modul->modul_id)
                ->value('kategorie_id')
        );
    }

    #[Test]
    public function lernort_eines_zugewiesenen_moduls_kann_geaendert_werden(): void
    {
        $admin = User::factory()->admin()->create();
        $lehrberuf = Lehrberuf::factory()->create(['aktiv' => true]);
        $modul = Modul::factory()->create();
        $fach = Kategorie::where('code', 'FACH')->firstOrFail();
        $uek = Kategorie::where('code', 'UEK')->firstOrFail();

        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $lehrberuf->lehrberuf_id,
            'modul_id' => $modul->modul_id,
            'kategorie_id' => $fach->kategorie_id,
            'pflicht' => 1,
            'aktiv' => 1,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.stammdaten.lehrberufe.module.update', [$lehrberuf->lehrberuf_id, $modul->modul_id]), [
                'kategorie_id' => $uek->kategorie_id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $uek->kategorie_id,
            DB::table('lehrberuf_module')
                ->where('lehrberuf_id', $lehrberuf->lehrberuf_id)
                ->where('modul_id', $modul->modul_id)
                ->value('kategorie_id')
        );
    }

    #[Test]
    public function kategorie_regeln_werden_gespeichert_und_leere_promotion_wird_null(): void
    {
        $admin = User::factory()->admin()->create();
        $kategorie = Kategorie::where('code', 'BMS')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.stammdaten.kategorien.update', $kategorie->kategorie_id), [
                'code' => $kategorie->code,
                'name' => $kategorie->name,
                'sortierung' => $kategorie->sortierung,
                'aktiv' => 1,
                'rundung_element' => '0.25',
                'rundung_schnitt' => '0.1',
                'gewicht_gesamt' => '2.5',
                'promotion_min_schnitt' => '',
                'promotion_max_ungenuegend' => '',
                'promotion_max_minuspunkte' => '',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.stammdaten.kategorien.index'));

        $kategorie->refresh();
        $this->assertEquals(0.25, (float) $kategorie->rundung_element);
        $this->assertEquals(2.5, (float) $kategorie->gewicht_gesamt);
        $this->assertNull($kategorie->promotion_min_schnitt);
        $this->assertNull($kategorie->promotion_max_ungenuegend);
        $this->assertNull($kategorie->promotion_max_minuspunkte);
    }

    #[Test]
    public function ungueltige_rundung_wird_abgewiesen(): void
    {
        $admin = User::factory()->admin()->create();
        $kategorie = Kategorie::where('code', 'FACH')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.stammdaten.kategorien.update', $kategorie->kategorie_id), [
                'code' => $kategorie->code,
                'name' => $kategorie->name,
                'sortierung' => $kategorie->sortierung,
                'aktiv' => 1,
                'rundung_element' => '2',
                'rundung_schnitt' => '0.1',
                'gewicht_gesamt' => '1',
            ])
            ->assertSessionHasErrors('rundung_element');
    }
}
