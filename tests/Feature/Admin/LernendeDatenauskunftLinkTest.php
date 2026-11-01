<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Betreuung;
use App\Models\User;
use App\Support\Einrichtung;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Funktionsprüfung Runde 16, Gruppe «auskunft-admin»: BenutzerController::edit leitet Lernende auf die
 * Lernenden-Ansicht um, der Auskunfts-Link stand aber nur auf der Bearbeiten-Seite.
 */
class LernendeDatenauskunftLinkTest extends TestCase
{
    #[Test]
    public function der_admin_findet_die_datenauskunft_im_menue_der_lernenden_ansicht(): void
    {
        Einrichtung::abschliessen();
        $lernender = User::factory()->lernender()->create();
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)
            ->get(route('admin.learners.show', $lernender->lernender->lernender_id))
            ->assertOk()->getContent();

        $link = route('admin.users.data-export', $lernender->benutzer_id);
        $this->assertStringContainsString('href="'.$link.'"', $html);
        $this->assertGreaterThan(
            strpos($html, 'aria-label="Weitere Aktionen"'),
            strpos($html, 'href="'.$link.'"'),
            'Der Eintrag liegt im Menü «Weitere Aktionen», nicht als eigener Knopf'
        );
    }

    #[Test]
    public function der_berufsbildner_sieht_den_auskunftslink_nicht(): void
    {
        Einrichtung::abschliessen();
        $bb = User::factory()->berufsbildner()->create();
        $lernender = User::factory()->lernender()->create();
        Betreuung::factory()->create([
            'berufsbildner_id' => $bb->berufsbildner->berufsbildner_id,
            'lernender_id' => $lernender->lernender->lernender_id,
        ]);

        $html = $this->actingAs($bb)
            ->get(route('trainer.learners.show', $lernender->lernender->lernender_id))
            ->assertOk()->getContent();

        $this->assertStringNotContainsString(route('admin.users.data-export', $lernender->benutzer_id), $html);
    }
}
