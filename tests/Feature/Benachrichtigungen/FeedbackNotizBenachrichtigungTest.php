<?php

declare(strict_types=1);

namespace Tests\Feature\Benachrichtigungen;

use App\Models\DigestItem;
use App\Models\Feedback;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\PortalMail;
use App\Services\Notifications\NotificationCatalog;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeedbackNotizBenachrichtigungTest extends TestCase
{
    #[Test]
    public function notiz_leeren_ohne_statuswechsel_schickt_keine_mail(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create(['email' => 'admin@firma.ch']);
        $melder = User::factory()->lernender()->create(['email' => 'melder@firma.ch']);
        $feedback = Feedback::factory()->create(['benutzer_id' => $melder->benutzer_id, 'status' => Feedback::STATUS_OFFEN, 'admin_notiz' => 'Wir schauen es an.']);

        $this->actingAs($admin)
            ->patchJson(route('admin.feedback.update', $feedback->feedback_id), ['status' => Feedback::STATUS_OFFEN, 'admin_notiz' => ''])
            ->assertOk();

        Notification::assertNotSentTo($melder, PortalMail::class);
        $this->assertNull($feedback->fresh()->admin_notiz);
    }

    #[Test]
    public function notiz_leeren_ohne_statuswechsel_legt_keinen_digest_eintrag_an(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@firma.ch']);
        $melder = User::factory()->lernender()->create(['email' => 'melder@firma.ch']);
        NotificationPreference::create(['user_id' => $melder->benutzer_id, 'type' => NotificationCatalog::FEEDBACK_ANSWERED, 'frequency' => NotificationCatalog::DAILY]);
        $feedback = Feedback::factory()->create(['benutzer_id' => $melder->benutzer_id, 'status' => Feedback::STATUS_OFFEN, 'admin_notiz' => 'Wir schauen es an.']);

        $this->actingAs($admin)
            ->patchJson(route('admin.feedback.update', $feedback->feedback_id), ['status' => Feedback::STATUS_OFFEN, 'admin_notiz' => ''])
            ->assertOk();

        $this->assertSame(0, DigestItem::query()->where('user_id', $melder->benutzer_id)->count());
    }

    #[Test]
    public function neue_antwort_ohne_statuswechsel_schickt_mail_mit_antwort(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create(['email' => 'admin@firma.ch']);
        $melder = User::factory()->lernender()->create(['email' => 'melder@firma.ch']);
        $feedback = Feedback::factory()->create(['benutzer_id' => $melder->benutzer_id, 'status' => Feedback::STATUS_OFFEN, 'admin_notiz' => null]);

        $this->actingAs($admin)
            ->patchJson(route('admin.feedback.update', $feedback->feedback_id), ['status' => Feedback::STATUS_OFFEN, 'admin_notiz' => 'Das ist bekannt.'])
            ->assertOk();

        Notification::assertSentTo($melder, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::FEEDBACK_ANSWERED
            && $m->content->subject === 'Deine Meldung wurde beantwortet'
            && $m->content->sections[0]['text'] === 'Das ist bekannt.');
    }

    #[Test]
    public function digest_eintrag_nennt_bei_neuer_antwort_die_antwort(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@firma.ch']);
        $melder = User::factory()->lernender()->create(['email' => 'melder@firma.ch']);
        NotificationPreference::create(['user_id' => $melder->benutzer_id, 'type' => NotificationCatalog::FEEDBACK_ANSWERED, 'frequency' => NotificationCatalog::DAILY]);
        $feedback = Feedback::factory()->create(['benutzer_id' => $melder->benutzer_id, 'status' => Feedback::STATUS_OFFEN, 'admin_notiz' => null]);

        $this->actingAs($admin)
            ->patchJson(route('admin.feedback.update', $feedback->feedback_id), ['status' => Feedback::STATUS_OFFEN, 'admin_notiz' => 'Das ist bekannt.'])
            ->assertOk();

        $eintrag = DigestItem::query()->where('user_id', $melder->benutzer_id)->sole();
        $this->assertSame('Deine Meldung wurde beantwortet', $eintrag->title);
        $this->assertSame('Das ist bekannt.', $eintrag->body);
    }

    #[Test]
    public function reiner_statuswechsel_nennt_im_digest_den_status(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@firma.ch']);
        $melder = User::factory()->lernender()->create(['email' => 'melder@firma.ch']);
        NotificationPreference::create(['user_id' => $melder->benutzer_id, 'type' => NotificationCatalog::FEEDBACK_ANSWERED, 'frequency' => NotificationCatalog::DAILY]);
        $feedback = Feedback::factory()->create(['benutzer_id' => $melder->benutzer_id, 'status' => Feedback::STATUS_OFFEN, 'admin_notiz' => null]);

        $this->actingAs($admin)
            ->patchJson(route('admin.feedback.update', $feedback->feedback_id), ['status' => Feedback::STATUS_ERLEDIGT, 'admin_notiz' => ''])
            ->assertOk();

        $eintrag = DigestItem::query()->where('user_id', $melder->benutzer_id)->sole();
        $this->assertStringContainsString('Erledigt', $eintrag->title);
        $this->assertNull($eintrag->body);
    }

    #[Test]
    public function statuswechsel_mit_leerer_notiz_meldet_den_status(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create(['email' => 'admin@firma.ch']);
        $melder = User::factory()->lernender()->create(['email' => 'melder@firma.ch']);
        $feedback = Feedback::factory()->create(['benutzer_id' => $melder->benutzer_id, 'status' => Feedback::STATUS_IN_ARBEIT, 'admin_notiz' => 'Alt.']);

        $this->actingAs($admin)
            ->patchJson(route('admin.feedback.update', $feedback->feedback_id), ['status' => Feedback::STATUS_ERLEDIGT, 'admin_notiz' => ''])
            ->assertOk();

        Notification::assertSentTo($melder, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::FEEDBACK_ANSWERED
            && str_contains($m->content->subject, 'Erledigt') && $m->content->sections === []);
    }
}
