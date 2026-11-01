<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Aktivitaet;
use App\Models\User;
use App\Support\Protokoll;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Die Personensuche im Aktivitätsprotokoll findet auch die betroffene Person (Spalte «Ziel»). */
class AktivitaetsprotokollZielSucheTest extends TestCase
{
    private function eintrag(?int $handelnder, string $aktion, ?string $ziel): void
    {
        Aktivitaet::create([
            'benutzer_id' => $handelnder,
            'aktion' => $aktion,
            'ziel_typ' => 'benutzer',
            'ziel_bezeichnung' => $ziel,
        ]);
    }

    #[Test]
    public function suche_findet_eintraege_ueber_den_namen_der_betroffenen_person(): void
    {
        $admin = User::factory()->admin()->create(['vorname' => 'Adrian', 'nachname' => 'Adminer']);
        $this->eintrag($admin->benutzer_id, Protokoll::ADMIN_KONTO_ANGELEGT, 'Yvonne Plokijuh');
        $this->eintrag($admin->benutzer_id, Protokoll::ADMIN_KONTO_ANGELEGT, 'Berta Unbeteiligt');

        $html = $this->actingAs($admin)->get('/admin/activity?person=Plokijuh')->assertOk()->getContent();

        $this->assertStringContainsString('Yvonne Plokijuh', $html);
        $this->assertStringNotContainsString('Berta Unbeteiligt', $html);
    }

    #[Test]
    public function suche_nach_dem_handelnden_findet_weiterhin_dessen_eintraege(): void
    {
        $admin = User::factory()->admin()->create(['vorname' => 'Adrian', 'nachname' => 'Adminer']);
        $anderer = User::factory()->admin()->create(['vorname' => 'Carla', 'nachname' => 'Fremdling']);
        $this->eintrag($admin->benutzer_id, Protokoll::ADMIN_KONTO_ANGELEGT, 'Yvonne Plokijuh');
        $this->eintrag($anderer->benutzer_id, Protokoll::ADMIN_KONTO_ANGELEGT, 'Berta Unbeteiligt');

        $html = $this->actingAs($admin)->get('/admin/activity?person=Adminer')->assertOk()->getContent();

        $this->assertStringContainsString('Yvonne Plokijuh', $html);
        $this->assertStringNotContainsString('Berta Unbeteiligt', $html);
    }

    #[Test]
    public function personensuche_bleibt_mit_dem_aktionsfilter_verundet(): void
    {
        $admin = User::factory()->admin()->create(['vorname' => 'Adrian', 'nachname' => 'Adminer']);
        $this->eintrag($admin->benutzer_id, Protokoll::ADMIN_KONTO_ANGELEGT, 'Yvonne Plokijuh');
        $this->eintrag($admin->benutzer_id, Protokoll::AUTH_PASSWORT_GEAENDERT, 'Zweitkonto Plokijuh');
        $this->eintrag($admin->benutzer_id, Protokoll::AUTH_PASSWORT_GEAENDERT, 'Berta Unbeteiligt');

        $abfrage = '/admin/activity?person=Plokijuh&aktion='.Protokoll::ADMIN_KONTO_ANGELEGT;
        $html = $this->actingAs($admin)->get($abfrage)->assertOk()->getContent();

        $this->assertStringContainsString('Yvonne Plokijuh', $html);
        $this->assertStringNotContainsString('Berta Unbeteiligt', $html);
        // Die fremde Aktion (Passwort geändert) darf trotz Namenstreffer nicht erscheinen.
        $this->assertStringNotContainsString('Zweitkonto', $html);

        // Auch Treffer über den Handelnden bleiben an den Aktionsfilter gebunden.
        $html = $this->actingAs($admin)->get('/admin/activity?person=Adminer&aktion='.Protokoll::AUTH_PASSWORT_GEAENDERT)->getContent();
        $this->assertStringContainsString('Zweitkonto', $html);
        $this->assertStringNotContainsString('Yvonne Plokijuh', $html);
    }

    #[Test]
    public function like_sonderzeichen_in_der_suche_gelten_woertlich(): void
    {
        $admin = User::factory()->admin()->create(['vorname' => 'Adrian', 'nachname' => 'Adminer']);
        $this->eintrag($admin->benutzer_id, Protokoll::ADMIN_KONTO_ANGELEGT, 'Yvonne Plokijuh');

        $html = $this->actingAs($admin)->get('/admin/activity?person=%25')->assertOk()->getContent();

        $this->assertStringNotContainsString('Yvonne Plokijuh', $html);
    }
}
