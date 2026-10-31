<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Note;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Funktionsprüfung admin: Fach in einer deaktivierten Kategorie bleibt bearbeitbar. */
class FachInaktiveKategorieTest extends TestCase
{
    private User $admin;

    private Kategorie $bms;

    private Fach $fach;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->bms = Kategorie::where('code', 'BMS')->firstOrFail();
        $this->fach = Fach::factory()->create(['track_typ' => 'BMS', 'kategorie_id' => $this->bms->kategorie_id]);
        $this->bms->forceFill(['aktiv' => 0])->save();
    }

    private function speichern(array $abweichung = [])
    {
        return $this->actingAs($this->admin)->put(route('admin.master-data.subjects.update', $this->fach->fach_id), array_merge([
            'name' => $this->fach->name.' neu',
            'kurzname' => $this->fach->kurzname,
            'kategorie_id' => $this->bms->kategorie_id,
            'track_typ' => 'BMS',
            'skala' => 'note',
            'zaehlt' => 1,
            'aktiv' => 1,
        ], $abweichung));
    }

    #[Test]
    public function formular_bietet_die_aktuelle_inaktive_kategorie_an(): void
    {
        $this->actingAs($this->admin)->get(route('admin.master-data.subjects.edit', $this->fach->fach_id))
            ->assertOk()
            ->assertSee('<option value="'.$this->bms->kategorie_id.'" selected>', false);
    }

    #[Test]
    public function umbenennen_und_deaktivieren_gelingt_in_inaktiver_kategorie(): void
    {
        $this->speichern(['aktiv' => 0])->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.master-data.subjects.index'));

        $fach = $this->fach->fresh();
        $this->assertSame($this->fach->name.' neu', $fach->name);
        $this->assertFalse((bool) $fach->aktiv);
        $this->assertSame($this->bms->kategorie_id, $fach->kategorie_id);
    }

    #[Test]
    public function wechsel_in_eine_andere_inaktive_kategorie_bleibt_abgelehnt(): void
    {
        $uek = Kategorie::where('code', 'UEK')->firstOrFail();
        $uek->forceFill(['aktiv' => 0])->save();
        $noteVorher = Note::factory()->create(['fach_id' => $this->fach->fach_id]);

        $this->speichern(['kategorie_id' => $uek->kategorie_id])->assertSessionHasErrors('kategorie_id');

        $this->assertSame($this->bms->kategorie_id, $this->fach->fresh()->kategorie_id);
        $this->assertSame($this->bms->kategorie_id, $noteVorher->fresh()->kategorie_id);
    }

    #[Test]
    public function neues_fach_in_inaktiver_kategorie_bleibt_abgelehnt(): void
    {
        $this->actingAs($this->admin)->post(route('admin.master-data.subjects.store'), [
            'name' => 'Neues Fach',
            'kurzname' => 'NF',
            'kategorie_id' => $this->bms->kategorie_id,
            'track_typ' => 'BMS',
        ])->assertSessionHasErrors('kategorie_id');
    }
}
