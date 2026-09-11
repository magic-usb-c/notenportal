<?php

declare(strict_types=1);

namespace Tests\Feature\Benachrichtigungen;

use App\Models\User;
use App\Services\Notifications\NotificationCatalog;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AbbestellenTest extends TestCase
{
    #[Test]
    public function gueltige_signatur_setzt_die_praeferenz_auf_nie(): void
    {
        $user = User::factory()->lernender()->create();
        $url = URL::signedRoute('notifications.unsubscribe', ['user' => $user->benutzer_id, 'type' => NotificationCatalog::COMMENT_ADDED]);

        $response = $this->post($url);

        $response->assertOk();
        $response->assertSee('Abbestellt');
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->benutzer_id,
            'type' => NotificationCatalog::COMMENT_ADDED,
            'frequency' => NotificationCatalog::NEVER,
        ]);
    }

    #[Test]
    public function ungueltige_signatur_wird_abgelehnt(): void
    {
        $user = User::factory()->lernender()->create();

        $this->get('/notifications/unsubscribe/'.$user->benutzer_id.'/'.NotificationCatalog::COMMENT_ADDED)
            ->assertForbidden();

        $this->get('/notifications/unsubscribe/'.$user->benutzer_id.'/'.NotificationCatalog::COMMENT_ADDED.'?signature=falsch')
            ->assertForbidden();
    }

    #[Test]
    public function verpflichtender_anlass_bleibt_bestehen(): void
    {
        $user = User::factory()->lernender()->create();
        $url = URL::signedRoute('notifications.unsubscribe', ['user' => $user->benutzer_id, 'type' => NotificationCatalog::PASSWORD_CHANGED]);

        $this->post($url)->assertOk();

        $this->assertDatabaseMissing('notification_preferences', [
            'user_id' => $user->benutzer_id,
            'type' => NotificationCatalog::PASSWORD_CHANGED,
        ]);
    }

    #[Test]
    public function one_click_post_ohne_csrf_antwortet_200(): void
    {
        $user = User::factory()->lernender()->create();
        $url = URL::signedRoute('notifications.unsubscribe', ['user' => $user->benutzer_id, 'type' => NotificationCatalog::COMMENT_ADDED]);

        $response = $this->post($url, ['List-Unsubscribe' => 'One-Click']);

        $response->assertOk();
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->benutzer_id,
            'type' => NotificationCatalog::COMMENT_ADDED,
            'frequency' => NotificationCatalog::NEVER,
        ]);
    }
}
