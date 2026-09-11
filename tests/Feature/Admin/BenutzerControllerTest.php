<?php

namespace Tests\Feature\Admin;

use App\Models\Berufsbildner;
use App\Models\User;
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
}
