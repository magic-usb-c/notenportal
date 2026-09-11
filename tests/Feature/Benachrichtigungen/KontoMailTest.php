<?php

declare(strict_types=1);

namespace Tests\Feature\Benachrichtigungen;

use App\Models\Rolle;
use App\Models\User;
use App\Services\Benutzer\LernendeErfassungService;
use App\Services\Notifications\NotificationCatalog;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KontoMailTest extends TestCase
{
    #[Test]
    public function admin_legt_konto_an_und_versendet_account_created(): void
    {
        $admin = User::factory()->admin()->create();
        $rolleId = Rolle::where('name', 'Berufsbildner')->value('rolle_id');

        $this->actingAs($admin)->post(route('admin.benutzer.store'), [
            'vorname' => 'Neue',
            'nachname' => 'Person',
            'email' => 'neue.person@firma.ch',
            'benutzername' => 'neueperson',
            'passwort' => 'Startpasswort!2027',
            'passwort_confirmation' => 'Startpasswort!2027',
            'rolle_id' => $rolleId,
        ])->assertRedirect(route('admin.benutzer.index'));

        $this->assertDatabaseHas('mail_log', [
            'type' => NotificationCatalog::ACCOUNT_CREATED,
            'recipient' => 'neue.person@firma.ch',
        ]);
    }

    #[Test]
    public function lernende_anlegen_versendet_account_created(): void
    {
        $lehrberufId = DB::table('lehrberufe')->insertGetId([
            'kuerzel' => 'TEST', 'name' => 'Testberuf', 'aktiv' => 1,
        ]);

        app(LernendeErfassungService::class)->erstellen([
            'vorname' => 'Lara', 'nachname' => 'Lernend',
            'email' => 'lara.lernend@firma.ch', 'benutzername' => 'laralernend',
            'passwort' => 'Startpasswort!2027',
            'lehrberuf_id' => $lehrberufId, 'lehrbeginn' => '2026-08-01',
        ]);

        $this->assertDatabaseHas('mail_log', [
            'type' => NotificationCatalog::ACCOUNT_CREATED,
            'recipient' => 'lara.lernend@firma.ch',
        ]);
    }

    #[Test]
    public function admin_reset_versendet_password_reset(): void
    {
        $admin = User::factory()->admin()->create();
        $bb = User::factory()->berufsbildner()->create(['email' => 'bb@firma.ch']);
        $rollen = $bb->rollen()->pluck('name')->all();

        $this->actingAs($admin)->put(route('admin.benutzer.update', $bb->benutzer_id), [
            'vorname' => $bb->vorname,
            'nachname' => $bb->nachname,
            'email' => $bb->email,
            'benutzername' => $bb->benutzername,
            'rollen' => $rollen,
            'passwort' => 'NeuesStartpasswort!2027',
            'passwort_confirmation' => 'NeuesStartpasswort!2027',
        ])->assertRedirect(route('admin.benutzer.edit', $bb->benutzer_id));

        $this->assertDatabaseHas('mail_log', [
            'type' => NotificationCatalog::PASSWORD_RESET,
            'recipient' => 'bb@firma.ch',
        ]);
    }
}
