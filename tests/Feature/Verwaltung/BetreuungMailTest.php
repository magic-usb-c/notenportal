<?php

namespace Tests\Feature\Verwaltung;

use App\Models\Lehrberuf;
use App\Models\User;
use App\Notifications\PortalMail;
use App\Services\Notifications\NotificationCatalog;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BetreuungMailTest extends TestCase
{
    use VerwaltungTestHilfen;

    #[Test]
    public function mail_bei_zukuenftiger_betreuung_nennt_das_datum_und_fuehrt_nicht_ins_gesperrte_cockpit(): void
    {
        Notification::fake();
        $alt = User::factory()->berufsbildner()->create(['email' => 'alt@firma.ch']);
        $neu = User::factory()->berufsbildner()->create(['email' => 'neu@firma.ch']);
        $lernender = $this->neuerLernender();
        $this->betreue($alt, $lernender);
        $von = today()->addDays(30);

        $this->actingAs($alt)
            ->post(route('trainer.learners.supervision.store', $lernender->lernender_id), [
                'berufsbildner_id' => $neu->berufsbildner->berufsbildner_id,
                'gueltig_von' => $von->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $url = null;
        Notification::assertSentTo($neu, PortalMail::class, function (PortalMail $m) use ($von, &$url) {
            $url = $m->content->actionUrl;
            $this->assertStringNotContainsString('ab sofort', $m->content->lines[0]);
            $this->assertStringContainsString($von->format('d.m.Y'), $m->content->lines[0]);
            $this->assertSame($von->format('d.m.Y'), $m->content->facts['Betreuung ab']);

            return $m->type === NotificationCatalog::LEARNER_ASSIGNED;
        });

        $this->app['auth']->forgetGuards();
        $this->actingAs($neu)->get(parse_url($url, PHP_URL_PATH))->assertOk();
    }

    #[Test]
    public function mail_bei_sofortiger_betreuung_bleibt_bei_ab_sofort_und_fuehrt_ins_cockpit(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $neu = User::factory()->berufsbildner()->create(['email' => 'neu@firma.ch']);
        $lernender = $this->neuerLernender();

        $this->actingAs($admin)
            ->post(route('admin.learners.supervision.store', $lernender->lernender_id), [
                'berufsbildner_id' => $neu->berufsbildner->berufsbildner_id,
                'gueltig_von' => today()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($neu, PortalMail::class, function (PortalMail $m) use ($lernender) {
            $this->assertStringContainsString('ab sofort', $m->content->lines[0]);
            $this->assertArrayNotHasKey('Betreuung ab', $m->content->facts);
            $this->assertSame(route('trainer.learners.show', $lernender->lernender_id), $m->content->actionUrl);

            return true;
        });
    }

    #[Test]
    public function neuanlage_mit_berufsbildner_durch_admin_meldet_dem_berufsbildner(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $bb = User::factory()->berufsbildner()->create(['email' => 'bb@firma.ch']);

        $this->actingAs($admin)->post(route('admin.learners.store'), [
            'vorname' => 'Test', 'nachname' => 'Neuling', 'email' => 'neuling@firma.ch', 'benutzername' => 'neuling',
            'lehrberuf_id' => Lehrberuf::factory()->create(['aktiv' => true])->lehrberuf_id,
            'lehrbeginn' => '2025-08-01', 'berufsbildner_id' => $bb->berufsbildner->berufsbildner_id,
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($bb, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::LEARNER_ASSIGNED
            && str_contains($m->content->lines[0], 'Neuling'));
    }

    #[Test]
    public function neuanlage_durch_den_berufsbildner_selbst_schickt_ihm_keine_mail(): void
    {
        Notification::fake();
        $bb = User::factory()->berufsbildner()->create(['email' => 'bb@firma.ch']);

        $this->actingAs($bb)->post(route('trainer.learners.store'), [
            'vorname' => 'Test', 'nachname' => 'Neuling', 'email' => 'neuling@firma.ch', 'benutzername' => 'neuling',
            'lehrberuf_id' => Lehrberuf::factory()->create(['aktiv' => true])->lehrberuf_id,
            'lehrbeginn' => '2025-08-01',
        ])->assertSessionHasNoErrors();

        Notification::assertNothingSentTo($bb);
    }

    #[Test]
    public function deaktivierter_berufsbildner_ist_nicht_zuteilbar(): void
    {
        $admin = User::factory()->admin()->create();
        $bb = User::factory()->berufsbildner()->create();
        $bbId = $bb->berufsbildner->berufsbildner_id;
        // Konto deaktivieren, Berufsbildner-Zeile bleibt (Altbestand, z. B. direkt in der DB gesperrt).
        $bb->forceFill(['aktiv' => false])->save();
        $lernender = $this->neuerLernender();

        $this->actingAs($admin)
            ->post(route('admin.learners.supervision.store', $lernender->lernender_id), [
                'berufsbildner_id' => $bbId,
                'gueltig_von' => today()->toDateString(),
            ])
            ->assertSessionHasErrors('berufsbildner_id');

        $this->assertDatabaseMissing('betreuungen', ['berufsbildner_id' => $bbId]);

        $this->actingAs($admin)->post(route('admin.learners.store'), [
            'vorname' => 'Test', 'nachname' => 'Neuling', 'email' => 'neuling@firma.ch', 'benutzername' => 'neuling',
            'lehrberuf_id' => Lehrberuf::factory()->create(['aktiv' => true])->lehrberuf_id,
            'lehrbeginn' => '2025-08-01', 'berufsbildner_id' => $bbId,
        ])->assertSessionHasErrors('berufsbildner_id');
    }
}
