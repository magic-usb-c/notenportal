<?php

namespace Tests\Feature\Admin;

use App\Models\Berufsbildner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BenutzerControllerTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    #[Test]
    public function deaktivieren_setzt_die_loeschspalte_des_berufsbildners(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $berufsbildnerId = $bb->berufsbildner->berufsbildner_id;

        $this->actingAs($this->admin)
            ->post(route('admin.users.toggle-active', $bb->benutzer_id))
            ->assertSessionHas('success');

        $this->assertNotNull(Berufsbildner::withTrashed()->find($berufsbildnerId)->geloescht_am);
        $this->assertNull($bb->fresh()->berufsbildner);
    }

    #[Test]
    public function reaktivieren_stellt_den_berufsbildner_wieder_her(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $berufsbildnerId = $bb->berufsbildner->berufsbildner_id;

        $this->actingAs($this->admin)->post(route('admin.users.toggle-active', $bb->benutzer_id));
        $this->actingAs($this->admin)->post(route('admin.users.toggle-active', $bb->benutzer_id));

        $this->assertNull(Berufsbildner::withTrashed()->find($berufsbildnerId)->geloescht_am);
        $this->assertNotNull($bb->fresh()->berufsbildner);
    }

    #[Test]
    public function rolle_entfernen_setzt_die_loeschspalte_des_berufsbildners(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $berufsbildnerId = $bb->berufsbildner->berufsbildner_id;

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $bb->benutzer_id), [
                'vorname' => $bb->vorname,
                'nachname' => $bb->nachname,
                'email' => $bb->email,
                'benutzername' => $bb->benutzername,
                'rollen' => ['Admin'],
            ])
            ->assertSessionHas('success');

        $this->assertNotNull(Berufsbildner::withTrashed()->find($berufsbildnerId)->geloescht_am);
    }

    #[Test]
    public function ungueltige_eingabe_speichert_nichts(): void
    {
        $bb = User::factory()->berufsbildner()->create(['vorname' => 'Ursprung']);

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $bb->benutzer_id), [
                'vorname' => '',
                'nachname' => $bb->nachname,
                'email' => $bb->email,
                'benutzername' => $bb->benutzername,
                'rollen' => ['Berufsbildner'],
            ])
            ->assertSessionHasErrors('vorname');

        $this->assertSame('Ursprung', $bb->fresh()->vorname);
    }

    #[Test]
    public function verletzung_der_eigenen_admin_rolle_speichert_nichts(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->admin->benutzer_id), [
                'vorname' => 'Geaendert',
                'nachname' => $this->admin->nachname,
                'email' => $this->admin->email,
                'benutzername' => $this->admin->benutzername,
                'rollen' => ['Berufsbildner'],
            ])
            ->assertSessionHasErrors('rollen');

        $this->assertNotSame('Geaendert', $this->admin->fresh()->vorname);
    }

    #[Test]
    public function rollenfilter_zeigt_weiterhin_alle_rollen_eines_kontos(): void
    {
        $beide = User::factory()->berufsbildner()->create();
        $adminRolle = DB::table('rollen')->where('name', 'Admin')->value('rolle_id');
        DB::table('benutzer_rollen')->insert(['benutzer_id' => $beide->benutzer_id, 'rolle_id' => $adminRolle]);

        $zeilen = $this->actingAs($this->admin)->get(route('admin.users.index', ['rolle_id' => $adminRolle]))
            ->assertOk()->viewData('benutzer');

        $this->assertSame('Admin, Berufsbildner', $zeilen->firstWhere('benutzer_id', $beide->benutzer_id)->rollen);
    }

    #[Test]
    public function konto_deaktivieren_liegt_im_konto_mit_rueckfrage_und_fehlt_beim_eigenen(): void
    {
        $bb = User::factory()->berufsbildner()->create();

        $this->actingAs($this->admin)->get(route('admin.users.edit', $bb->benutzer_id))->assertOk()
            ->assertSee(route('admin.users.toggle-active', $bb->benutzer_id))
            ->assertSee('data-bestaetigen="'.__('Konto deaktivieren?').'"', false);

        $this->actingAs($this->admin)->get(route('admin.users.edit', $this->admin->benutzer_id))->assertOk()
            ->assertDontSee(route('admin.users.toggle-active', $this->admin->benutzer_id));

        $bb->forceFill(['aktiv' => false])->save();
        $this->actingAs($this->admin)->get(route('admin.users.edit', $bb->benutzer_id))->assertOk()
            ->assertSee(__('Aktivieren'))
            ->assertDontSee('data-bestaetigen=', false);
    }
}
