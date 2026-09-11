<?php

namespace Tests\Feature\Verwaltung;

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
            ->post(route("{$rolle}.learners.account.active", $lernender->lernender_id))
            ->assertRedirect(route("{$rolle}.learners.show", $lernender->lernender_id));

        $this->assertFalse($lernender->benutzer->fresh()->aktiv);

        $this->app['auth']->forgetGuards();
        $this->post(route('login'), ['email' => $lernender->benutzer->email, 'password' => UserFactory::PASSWORT]);
        $this->assertGuest();

        // Wieder aktivieren
        $this->actingAs($verwalter)->post(route("{$rolle}.learners.account.active", $lernender->lernender_id));
        $this->assertTrue($lernender->benutzer->fresh()->aktiv);
    }
}
