<?php

declare(strict_types=1);

namespace Tests\Feature\Benachrichtigungen;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Notifications\NotificationCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EinstellungenTest extends TestCase
{
    #[Test]
    public function lernender_sieht_nur_fuer_ihn_relevante_anlaesse(): void
    {
        $lernender = User::factory()->lernender()->create();

        $response = $this->actingAs($lernender)->get(route('notifications.settings'));

        $response->assertOk();
        // Für Lernende relevant:
        $response->assertSee(NotificationCatalog::get(NotificationCatalog::GRADE_CORRECTED)['label']);
        $response->assertSee(NotificationCatalog::get(NotificationCatalog::COMMENT_ADDED)['label']);
        // Nur für Berufsbildner bzw. nur für Admin – dürfen nicht erscheinen:
        $response->assertDontSee(NotificationCatalog::get(NotificationCatalog::GRADE_ADDED)['label']);
        $response->assertDontSee(NotificationCatalog::get(NotificationCatalog::FEEDBACK_RECEIVED)['label']);
    }

    #[Test]
    public function speichert_die_gewaehlte_frequenz(): void
    {
        $lernender = User::factory()->lernender()->create();

        $response = $this->actingAs($lernender)->put(route('notifications.settings.update'), [
            'frequenz' => [NotificationCatalog::COMMENT_ADDED => NotificationCatalog::DAILY],
        ]);

        $response->assertRedirect(route('notifications.settings'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $lernender->benutzer_id,
            'type' => NotificationCatalog::COMMENT_ADDED,
            'frequency' => NotificationCatalog::DAILY,
        ]);
    }

    #[Test]
    public function verpflichtender_anlass_bleibt_unveraendert_auch_bei_manipuliertem_request(): void
    {
        $lernender = User::factory()->lernender()->create();

        // password_changed ist verpflichtend, "never" gehört aber zu den erlaubten Frequenzen – trotzdem gesperrt.
        $this->actingAs($lernender)->put(route('notifications.settings.update'), [
            'frequenz' => [NotificationCatalog::PASSWORD_CHANGED => NotificationCatalog::NEVER],
        ])->assertRedirect(route('notifications.settings'));

        $this->assertDatabaseMissing('notification_preferences', [
            'user_id' => $lernender->benutzer_id,
            'type' => NotificationCatalog::PASSWORD_CHANGED,
        ]);
    }

    #[Test]
    public function fremde_user_id_im_request_ist_wirkungslos(): void
    {
        $eigener = User::factory()->lernender()->create();
        $fremder = User::factory()->lernender()->create();

        $this->actingAs($eigener)->put(route('notifications.settings.update'), [
            'user_id' => $fremder->benutzer_id,
            'frequenz' => [NotificationCatalog::COMMENT_ADDED => NotificationCatalog::DAILY],
        ])->assertRedirect(route('notifications.settings'));

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $eigener->benutzer_id,
            'type' => NotificationCatalog::COMMENT_ADDED,
            'frequency' => NotificationCatalog::DAILY,
        ]);
        $this->assertSame(0, NotificationPreference::query()->where('user_id', $fremder->benutzer_id)->count());
    }
}
