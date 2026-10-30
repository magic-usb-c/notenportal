<?php

namespace Tests\Feature\Verwaltung;

use App\Models\Aktivitaet;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KontoTest extends TestCase
{
    use VerwaltungTestHilfen;

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function passwort_zuruecksetzen_erzeugt_startpasswort(string $rolle): void
    {
        $lernender = $this->neuerLernender(['passwort_wechsel_noetig' => false]);
        $verwalter = $this->verwalter($rolle, $lernender);

        $this->actingAs($verwalter)
            ->post(route("{$rolle}.learners.account.password", $lernender->lernender_id))
            ->assertRedirect(route("{$rolle}.learners.show", $lernender->lernender_id))
            ->assertSessionHas('startpasswort');

        $passwort = session('startpasswort');
        $benutzer = $lernender->benutzer->fresh();

        $this->assertTrue($benutzer->passwort_wechsel_noetig);
        $this->assertTrue(Hash::check($passwort, $benutzer->passwort_hash));
        $this->assertFalse(Hash::check(UserFactory::PASSWORT, $benutzer->passwort_hash));
        $this->assertTrue(Validator::make(['p' => $passwort], ['p' => Password::defaults()])->passes());

        // Einmalig angezeigt
        $this->actingAs($verwalter)->get(route("{$rolle}.learners.show", $lernender->lernender_id))->assertSee($passwort);
        $this->actingAs($verwalter)->get(route("{$rolle}.learners.show", $lernender->lernender_id))->assertDontSee($passwort);
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function deaktivieren_sperrt_login(string $rolle): void
    {
        $lernender = $this->neuerLernender();
        $verwalter = $this->verwalter($rolle, $lernender);

        $this->actingAs($verwalter)
            ->post(route("{$rolle}.learners.account.active", $lernender->lernender_id), ['aktiv' => '0'])
            ->assertRedirect(route("{$rolle}.learners.show", $lernender->lernender_id));

        $this->assertFalse($lernender->benutzer->fresh()->aktiv);

        $this->app['auth']->forgetGuards();
        $this->post(route('login'), ['email' => $lernender->benutzer->email, 'password' => UserFactory::PASSWORT]);
        $this->assertGuest();

        // Wieder aktivieren
        $this->actingAs($verwalter)->post(route("{$rolle}.learners.account.active", $lernender->lernender_id), ['aktiv' => '1']);
        $this->assertTrue($lernender->benutzer->fresh()->aktiv);
    }

    #[Test]
    public function doppeltes_deaktivieren_laesst_das_konto_inaktiv(): void
    {
        $lernender = $this->neuerLernender();
        $verwalter = $this->verwalter('admin', $lernender);
        $url = route('admin.learners.account.active', $lernender->lernender_id);

        // Zweiter identischer POST (erneutes Senden, zweiter Tab) darf nichts zurückschalten
        $this->actingAs($verwalter)->post($url, ['aktiv' => '0'])->assertSessionHas('success', 'Konto deaktiviert.');
        $this->actingAs($verwalter)->post($url, ['aktiv' => '0'])->assertSessionHas('success', 'Konto deaktiviert.');
        $this->assertFalse($lernender->benutzer->fresh()->aktiv);

        $this->actingAs($verwalter)->post($url, ['aktiv' => '1']);
        $this->actingAs($verwalter)->post($url, ['aktiv' => '1'])->assertSessionHas('success', 'Konto aktiviert.');
        $this->assertTrue($lernender->benutzer->fresh()->aktiv);
    }

    #[Test]
    public function unveraenderter_zustand_schreibt_keinen_protokolleintrag(): void
    {
        $lernender = $this->neuerLernender();
        $verwalter = $this->verwalter('admin', $lernender);
        $url = route('admin.learners.account.active', $lernender->lernender_id);

        $this->actingAs($verwalter)->post($url, ['aktiv' => '0']);
        $vorher = Aktivitaet::count();
        $this->actingAs($verwalter)->post($url, ['aktiv' => '0']);

        $this->assertSame($vorher, Aktivitaet::count());
    }

    #[Test]
    public function ohne_zielzustand_wird_nichts_geschaltet(): void
    {
        $lernender = $this->neuerLernender();
        $verwalter = $this->verwalter('admin', $lernender);

        $this->actingAs($verwalter)
            ->post(route('admin.learners.account.active', $lernender->lernender_id))
            ->assertSessionHasErrors('aktiv');
        $this->assertTrue($lernender->benutzer->fresh()->aktiv);
    }
}
