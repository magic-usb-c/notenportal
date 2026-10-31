<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Betreuung;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Funktionsprüfung admin: Aktiv-Schalter übernimmt den Zielzustand aus dem Formular. */
class BenutzerAktivZielzustandTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    #[Test]
    public function doppeltes_absenden_des_deaktivierens_aktiviert_das_konto_nicht_wieder(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($this->admin)->post(route('admin.users.toggle-active', $user->benutzer_id), ['aktiv' => 0])
            ->assertSessionHas('success', __('Benutzer deaktiviert.'));
        $this->actingAs($this->admin)->post(route('admin.users.toggle-active', $user->benutzer_id), ['aktiv' => 0])
            ->assertSessionHas('success', __('Benutzer deaktiviert.'))
            ->assertSessionMissing('error');

        $this->assertFalse((bool) $user->fresh()->aktiv);
    }

    #[Test]
    public function zielzustand_ist_pflicht(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($this->admin)->post(route('admin.users.toggle-active', $user->benutzer_id))
            ->assertSessionHasErrors('aktiv');

        $this->assertTrue((bool) $user->fresh()->aktiv);
    }

    #[Test]
    public function berufsbildner_mit_aktiver_betreuung_wird_nicht_deaktiviert(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $lernender = User::factory()->lernender()->create();
        Betreuung::factory()->create([
            'berufsbildner_id' => $bb->berufsbildner->berufsbildner_id,
            'lernender_id' => $lernender->lernender->lernender_id,
            'gueltig_bis' => null,
        ]);

        $this->actingAs($this->admin)->post(route('admin.users.toggle-active', $bb->benutzer_id), ['aktiv' => 0])
            ->assertSessionHas('error', __('Aktive Betreuungen zuerst übergeben.'));

        $this->assertTrue((bool) $bb->fresh()->aktiv);
        $this->assertNotNull($bb->fresh()->berufsbildner);
    }

    #[Test]
    public function berufsbildner_ohne_aktive_betreuung_wird_deaktiviert(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $lernender = User::factory()->lernender()->create();
        Betreuung::factory()->create([
            'berufsbildner_id' => $bb->berufsbildner->berufsbildner_id,
            'lernender_id' => $lernender->lernender->lernender_id,
            'gueltig_von' => '2024-01-01',
            'gueltig_bis' => '2024-12-31',
        ]);

        $this->actingAs($this->admin)->post(route('admin.users.toggle-active', $bb->benutzer_id), ['aktiv' => 0])
            ->assertSessionHas('success', __('Benutzer deaktiviert.'));

        $this->assertFalse((bool) $bb->fresh()->aktiv);
    }

    #[Test]
    public function formular_sendet_den_zielzustand_mit(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($this->admin)->get(route('admin.users.edit', $user->benutzer_id))->assertOk()
            ->assertSee('<input type="hidden" name="aktiv" value="0">', false);

        $user->forceFill(['aktiv' => false])->save();
        $this->actingAs($this->admin)->get(route('admin.users.edit', $user->benutzer_id))->assertOk()
            ->assertSee('<input type="hidden" name="aktiv" value="1">', false);
    }
}
