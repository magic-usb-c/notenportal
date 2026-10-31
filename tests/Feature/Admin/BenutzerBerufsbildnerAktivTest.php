<?php

namespace Tests\Feature\Admin;

use App\Models\Berufsbildner;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BenutzerBerufsbildnerAktivTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function speichern(User $user, array $rollen = ['Berufsbildner'], string $vorname = 'Neu')
    {
        return $this->actingAs($this->admin)->put(route('admin.users.update', $user->benutzer_id), [
            'vorname' => $vorname,
            'nachname' => $user->nachname,
            'email' => $user->email,
            'benutzername' => $user->benutzername,
            'rollen' => $rollen,
        ]);
    }

    #[Test]
    public function bearbeiten_eines_deaktivierten_berufsbildners_stellt_die_zeile_nicht_wieder_her(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $this->actingAs($this->admin)->post(route('admin.users.toggle-active', $bb->benutzer_id), ['aktiv' => 0]);
        $this->assertSame(0, Berufsbildner::where('benutzer_id', $bb->benutzer_id)->count());

        $this->speichern($bb)->assertRedirect();

        $this->assertFalse((bool) $bb->fresh()->aktiv);
        $this->assertSame(0, Berufsbildner::where('benutzer_id', $bb->benutzer_id)->count());
        $this->assertSame(1, Berufsbildner::withTrashed()->where('benutzer_id', $bb->benutzer_id)->count());
    }

    #[Test]
    public function deaktivierter_berufsbildner_nach_bearbeiten_nicht_zuteilbar(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $this->actingAs($this->admin)->post(route('admin.users.toggle-active', $bb->benutzer_id), ['aktiv' => 0]);
        $this->speichern($bb);
        $lernender = User::factory()->lernender()->create()->lernender;

        $id = Berufsbildner::withTrashed()->where('benutzer_id', $bb->benutzer_id)->value('berufsbildner_id');
        $this->actingAs($this->admin)
            ->post(route('admin.learners.supervision.store', $lernender->lernender_id), [
                'berufsbildner_id' => $id,
                'gueltig_von' => today()->toDateString(),
            ])
            ->assertSessionHasErrors('berufsbildner_id');
    }

    #[Test]
    public function reaktivieren_stellt_die_zeile_wieder_her(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $this->actingAs($this->admin)->post(route('admin.users.toggle-active', $bb->benutzer_id), ['aktiv' => 0]);
        $this->speichern($bb);

        $this->actingAs($this->admin)->post(route('admin.users.toggle-active', $bb->benutzer_id), ['aktiv' => 1]);

        $this->assertTrue((bool) $bb->fresh()->aktiv);
        $this->assertSame(1, Berufsbildner::where('benutzer_id', $bb->benutzer_id)->count());
    }

    #[Test]
    public function rolle_berufsbildner_bei_inaktivem_konto_legt_erst_beim_reaktivieren_eine_zeile_an(): void
    {
        $user = User::factory()->create(['aktiv' => false]);
        $this->assertSame(0, Berufsbildner::withTrashed()->where('benutzer_id', $user->benutzer_id)->count());

        $this->speichern($user, ['Berufsbildner'])->assertRedirect();
        $this->assertSame(0, Berufsbildner::withTrashed()->where('benutzer_id', $user->benutzer_id)->count());

        $this->actingAs($this->admin)->post(route('admin.users.toggle-active', $user->benutzer_id), ['aktiv' => 1]);

        $this->assertTrue((bool) $user->fresh()->aktiv);
        $this->assertSame(1, Berufsbildner::where('benutzer_id', $user->benutzer_id)->count());
    }

    #[Test]
    public function bearbeiten_eines_aktiven_kontos_legt_die_zeile_weiterhin_an(): void
    {
        $user = User::factory()->create(['aktiv' => true]);

        $this->speichern($user, ['Berufsbildner'])->assertRedirect();

        $this->assertSame(1, Berufsbildner::where('benutzer_id', $user->benutzer_id)->count());
    }
}
