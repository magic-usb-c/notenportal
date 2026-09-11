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

        $this->post('/notifications/unsubscribe/'.$user->benutzer_id.'/'.NotificationCatalog::COMMENT_ADDED)
            ->assertForbidden();
    }

    #[Test]
    public function get_zeigt_nur_die_bestaetigungsseite_ohne_zustandsaenderung(): void
    {
        // Wichtig: Link-Scanner in Mailprogrammen rufen GET-Links automatisch ab – GET darf daher nie abmelden.
        $user = User::factory()->lernender()->create();
        $url = URL::signedRoute('notifications.unsubscribe', ['user' => $user->benutzer_id, 'type' => NotificationCatalog::COMMENT_ADDED]);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('abbestellen?');
        $this->assertDatabaseMissing('notification_preferences', [
            'user_id' => $user->benutzer_id,
            'type' => NotificationCatalog::COMMENT_ADDED,
        ]);
    }

    #[Test]
    public function get_bei_verpflichtendem_anlass_zeigt_hinweis_ohne_abbestellen_formular(): void
    {
        $user = User::factory()->lernender()->create();
        $url = URL::signedRoute('notifications.unsubscribe', ['user' => $user->benutzer_id, 'type' => NotificationCatalog::PASSWORD_CHANGED]);

        $this->get($url)->assertOk()->assertSee('lässt sich nicht abbestellen');
    }

    #[Test]
    public function unbekannter_anlass_ergibt_404(): void
    {
        $user = User::factory()->lernender()->create();
        $url = URL::signedRoute('notifications.unsubscribe', ['user' => $user->benutzer_id, 'type' => 'kein-anlass']);

        $this->get($url)->assertNotFound();
        $this->post($url)->assertNotFound();
    }

    #[Test]
    public function nicht_existierender_benutzer_ergibt_404(): void
    {
        $url = URL::signedRoute('notifications.unsubscribe', ['user' => 999999, 'type' => NotificationCatalog::COMMENT_ADDED]);

        $this->get($url)->assertNotFound();
    }

    #[Test]
    public function zweimaliges_abbestellen_ist_folgenlos_moeglich(): void
    {
        $user = User::factory()->lernender()->create();
        $url = URL::signedRoute('notifications.unsubscribe', ['user' => $user->benutzer_id, 'type' => NotificationCatalog::COMMENT_ADDED]);

        $this->post($url)->assertOk();
        $this->post($url)->assertOk();

        $this->assertDatabaseCount('notification_preferences', 1);
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->benutzer_id,
            'type' => NotificationCatalog::COMMENT_ADDED,
            'frequency' => NotificationCatalog::NEVER,
        ]);
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

    #[Test]
    public function rfc_8058_one_click_body_antwortet_reinen_text_statt_der_seite(): void
    {
        // Mailprogramme senden laut RFC 8058 den rohen Body "List-Unsubscribe=One-Click" ohne Formularfelder.
        $user = User::factory()->lernender()->create();
        $url = URL::signedRoute('notifications.unsubscribe', ['user' => $user->benutzer_id, 'type' => NotificationCatalog::COMMENT_ADDED]);

        $response = $this->call('POST', $url, content: 'List-Unsubscribe=One-Click');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertSame(__('Abbestellt.'), $response->getContent());
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->benutzer_id,
            'type' => NotificationCatalog::COMMENT_ADDED,
            'frequency' => NotificationCatalog::NEVER,
        ]);
    }
}
