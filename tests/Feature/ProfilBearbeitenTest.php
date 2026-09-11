<?php

namespace Tests\Feature;

use App\Models\Lehrberuf;
use App\Models\Semester;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfilBearbeitenTest extends TestCase
{
    private function profil(User $user, array $daten): TestResponse
    {
        return $this->actingAs($user)->from(route('profile.edit'))->patch(route('profile.update'), $daten + [
            'email' => $user->email,
            'darstellung' => 'system',
        ]);
    }

    private function bmsTrack(User $user): void
    {
        DB::table('lernender_tracks')->insert([
            'lernender_id' => $user->lernender->lernender_id,
            'track_typ' => 'BMS',
            'start_datum' => '2024-08-01',
            'start_semester_id' => Semester::factory()->create()->semester_id,
        ]);
    }

    #[Test]
    public function lernender_setzt_klassen_und_darstellung(): void
    {
        $user = User::factory()->lernender()->create();
        $this->bmsTrack($user);

        $this->profil($user, ['klasse_schule' => 'INF24b', 'klasse_bms' => 'BM1-24', 'darstellung' => 'dunkel'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('dunkel', $user->refresh()->darstellung);
        $this->assertSame('INF24b', $user->lernender->klasse_schule);
        $this->assertSame('BM1-24', $user->lernender->klasse_bms);
    }

    #[Test]
    public function bms_klasse_nur_mit_aktivem_bms_track(): void
    {
        $user = User::factory()->lernender()->create();

        $this->profil($user, ['klasse_bms' => 'BM1-24'])->assertSessionHasNoErrors();

        $this->assertNull($user->lernender->refresh()->klasse_bms);
        $this->actingAs($user)->get(route('profile.edit'))->assertDontSee('Klasse BMS');
    }

    #[Test]
    public function gesperrte_felder_bleiben_unveraendert(): void
    {
        $user = User::factory()->lernender()->create(['vorname' => 'Nina', 'nachname' => 'Huber']);
        $lehrberuf = $user->lernender->lehrberuf_id;

        $this->profil($user, [
            'vorname' => 'Hacker',
            'nachname' => 'X',
            'aktiv' => 0,
            'lehrberuf_id' => Lehrberuf::factory()->create()->lehrberuf_id,
            'lehrbeginn' => '2020-01-01',
            'bemerkung' => 'selbst gesetzt',
            'passwort_wechsel_noetig' => 1,
            'benutzername' => 'root',
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame(['Nina', 'Huber', true, false], [$user->vorname, $user->nachname, $user->aktiv, $user->passwort_wechsel_noetig]);
        $this->assertSame($lehrberuf, $user->lernender->lehrberuf_id);
        $this->assertNull($user->lernender->bemerkung);
        $this->assertSame('2024-08-01', $user->lernender->lehrbeginn->toDateString());
    }

    #[Test]
    public function email_aendern_verlangt_das_aktuelle_passwort(): void
    {
        $user = User::factory()->lernender()->create();

        $this->profil($user, ['email' => 'neu@example.test'])->assertSessionHasErrors('current_password');
        $this->profil($user, ['email' => 'neu@example.test', 'current_password' => 'falsch'])->assertSessionHasErrors('current_password');
        $this->assertNotSame('neu@example.test', $user->refresh()->email);

        $this->profil($user, ['email' => 'neu@example.test', 'current_password' => UserFactory::PASSWORT])->assertSessionHasNoErrors();
        $this->assertSame('neu@example.test', $user->refresh()->email);
    }

    #[Test]
    public function berufsbildner_aendert_eigenen_namen(): void
    {
        $user = User::factory()->berufsbildner()->create();

        $this->profil($user, ['vorname' => 'Peter', 'nachname' => 'Muster'])->assertSessionHasNoErrors();

        $this->assertSame('Peter Muster', $user->refresh()->vorname.' '.$user->nachname);
    }

    #[Test]
    public function gespeicherte_darstellung_wird_ohne_flackern_gerendert(): void
    {
        $user = User::factory()->lernender()->create(['darstellung' => 'dunkel']);

        $this->actingAs($user)->get(route('learner.dashboard'))->assertSeeHtml('<html lang="de" class="dark"');

        $this->actingAs($user)->patchJson(route('profile.appearance'), ['darstellung' => 'hell'])->assertOk();
        $this->assertSame('hell', $user->refresh()->darstellung);
    }
}
