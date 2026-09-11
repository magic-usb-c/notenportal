<?php

namespace Tests\Feature\Admin;

use App\Models\NotificationPolicy;
use App\Models\User;
use App\Services\Notifications\NotificationCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BenachrichtigungsRegelnTest extends TestCase
{
    protected function tearDown(): void
    {
        NotificationCatalog::forget();
        parent::tearDown();
    }

    #[Test]
    public function admin_sieht_die_uebersicht(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.notifications.index'));

        $response->assertOk();
        $response->assertSee('Neuer Kommentar');
    }

    #[Test]
    public function admin_speichert_regeln_fuer_einen_anlass(): void
    {
        $admin = User::factory()->admin()->create();
        $type = NotificationCatalog::GRADE_ADDED;

        $this->actingAs($admin)
            ->put(route('admin.notifications.update'), [
                'policies' => [
                    $type => ['enabled' => '1', 'mandatory' => '1', 'frequency' => 'immediate'],
                ],
            ])
            ->assertRedirect(route('admin.notifications.index'))
            ->assertSessionHas('success');

        $zeile = NotificationPolicy::find($type);
        $this->assertNotNull($zeile);
        $this->assertTrue($zeile->enabled);
        $this->assertTrue($zeile->mandatory);
        $this->assertSame('immediate', $zeile->frequency);
    }

    #[Test]
    public function verpflichtender_anlass_bleibt_trotz_abschaltversuch_verpflichtend(): void
    {
        $admin = User::factory()->admin()->create();
        $type = NotificationCatalog::ACCOUNT_CREATED; // locked

        $this->actingAs($admin)
            ->put(route('admin.notifications.update'), [
                'policies' => [
                    $type => ['enabled' => '0', 'mandatory' => '0', 'frequency' => 'never'],
                ],
            ])
            ->assertRedirect(route('admin.notifications.index'));

        $this->assertNull(NotificationPolicy::find($type));
        $policy = NotificationCatalog::policy($type);
        $this->assertTrue($policy['enabled']);
        $this->assertTrue($policy['mandatory']);
    }

    #[Test]
    public function ungueltige_frequenz_wird_abgelehnt(): void
    {
        $admin = User::factory()->admin()->create();
        $type = NotificationCatalog::PASSWORD_CHANGED; // frequencies: immediate, never

        $this->actingAs($admin)
            ->put(route('admin.notifications.update'), [
                'policies' => [
                    $type => ['enabled' => '1', 'mandatory' => '1', 'frequency' => 'daily'],
                ],
            ])
            ->assertSessionHasErrors('policies.'.$type.'.frequency');
    }

    #[Test]
    public function ungueltiger_parameterwert_wird_abgelehnt(): void
    {
        $admin = User::factory()->admin()->create();
        $type = NotificationCatalog::INACTIVITY;

        $this->actingAs($admin)
            ->put(route('admin.notifications.update'), [
                'policies' => [
                    $type => ['enabled' => '1', 'mandatory' => '0', 'frequency' => 'immediate', 'params' => ['days' => 999]],
                ],
            ])
            ->assertSessionHasErrors('policies.'.$type.'.params.days');
    }

    #[Test]
    public function nicht_admins_bekommen_403(): void
    {
        foreach (['lernender', 'berufsbildner'] as $rolle) {
            $user = User::factory()->{$rolle}()->create();
            $this->actingAs($user)->get(route('admin.notifications.index'))->assertForbidden();
            $this->actingAs($user)->put(route('admin.notifications.update'), [])->assertForbidden();
        }
    }
}
