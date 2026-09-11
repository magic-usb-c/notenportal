<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\FeedbackStimme;
use App\Models\User;
use App\Notifications\PortalMail;
use App\Services\Notifications\NotificationCatalog;
use App\Support\Protokoll;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Duplikat-Markierung: Stimmen/Absender-Übernahme aufs Original, Ketten-Auflösung,
 * Aufheben, Benachrichtigung beim Erledigen des Originals, Protokolleintrag.
 */
class FeedbackDuplikatTest extends TestCase
{
    #[Test]
    public function duplikat_markieren_uebertraegt_stimmen_und_absender_aufs_original_und_protokolliert(): void
    {
        $admin = User::factory()->admin()->create();
        $absenderOriginal = User::factory()->lernender()->create();
        $absenderDuplikat = User::factory()->lernender()->create();
        $stimmender = User::factory()->lernender()->create();

        $original = Feedback::factory()->create([
            'benutzer_id' => $absenderOriginal->benutzer_id,
            'status' => Feedback::STATUS_OFFEN,
        ]);
        $duplikat = Feedback::factory()->create([
            'benutzer_id' => $absenderDuplikat->benutzer_id,
            'status' => Feedback::STATUS_IN_ARBEIT,
        ]);

        FeedbackStimme::query()->insert([
            'feedback_id' => $duplikat->feedback_id,
            'benutzer_id' => $stimmender->benutzer_id,
            'erstellt_am' => now(),
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.feedback.duplicate', $duplikat->feedback_id), ['duplikat_von' => $original->feedback_id])
            ->assertOk()
            ->assertJson(['ok' => true, 'duplikat_von' => $original->feedback_id]);

        $duplikat->refresh();
        $original->refresh();

        $this->assertSame($original->feedback_id, $duplikat->duplikat_von);
        $this->assertSame(Feedback::STATUS_OFFEN, $duplikat->status);

        // Stimme des Unterstützers und der Absender des Duplikats landen aufs Original, keine Doppelten.
        $this->assertSame(1, FeedbackStimme::where('feedback_id', $original->feedback_id)
            ->where('benutzer_id', $stimmender->benutzer_id)->count());
        $this->assertSame(1, FeedbackStimme::where('feedback_id', $original->feedback_id)
            ->where('benutzer_id', $absenderDuplikat->benutzer_id)->count());

        $this->assertDatabaseHas('aktivitaeten', [
            'aktion' => Protokoll::ADMIN_FEEDBACK_DUPLIKAT,
            'benutzer_id' => $admin->benutzer_id,
            'ziel_id' => $duplikat->feedback_id,
        ]);
    }

    #[Test]
    public function duplikat_markieren_dupliziert_keine_stimme_wenn_absender_das_original_selbst_gemeldet_hat(): void
    {
        $admin = User::factory()->admin()->create();
        $melder = User::factory()->lernender()->create();

        $original = Feedback::factory()->create(['benutzer_id' => $melder->benutzer_id, 'status' => Feedback::STATUS_OFFEN]);
        $duplikat = Feedback::factory()->create(['benutzer_id' => $melder->benutzer_id, 'status' => Feedback::STATUS_OFFEN]);

        $this->actingAs($admin)
            ->patchJson(route('admin.feedback.duplicate', $duplikat->feedback_id), ['duplikat_von' => $original->feedback_id])
            ->assertOk();

        $this->assertSame(0, FeedbackStimme::where('feedback_id', $original->feedback_id)->count());
    }

    #[Test]
    public function duplikat_ketten_werden_aufgeloest(): void
    {
        $admin = User::factory()->admin()->create();

        $a = Feedback::factory()->create(['status' => Feedback::STATUS_OFFEN]);
        $b = Feedback::factory()->create(['status' => Feedback::STATUS_OFFEN]);
        $c = Feedback::factory()->create(['status' => Feedback::STATUS_OFFEN]);

        $this->actingAs($admin)
            ->patchJson(route('admin.feedback.duplicate', $a->feedback_id), ['duplikat_von' => $b->feedback_id])
            ->assertOk();

        $this->actingAs($admin)
            ->patchJson(route('admin.feedback.duplicate', $b->feedback_id), ['duplikat_von' => $c->feedback_id])
            ->assertOk();

        $a->refresh();
        $b->refresh();

        $this->assertSame($c->feedback_id, $a->duplikat_von, 'A muss auf das aufgelöste Original C umgehängt werden, keine Kette über B.');
        $this->assertSame($c->feedback_id, $b->duplikat_von);
    }

    #[Test]
    public function selbstverweis_und_unbekannte_id_ergeben_422(): void
    {
        $admin = User::factory()->admin()->create();
        $feedback = Feedback::factory()->create(['status' => Feedback::STATUS_OFFEN]);

        $this->actingAs($admin)
            ->patchJson(route('admin.feedback.duplicate', $feedback->feedback_id), ['duplikat_von' => $feedback->feedback_id])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->patchJson(route('admin.feedback.duplicate', $feedback->feedback_id), ['duplikat_von' => 999999])
            ->assertStatus(422);
    }

    #[Test]
    public function aufheben_setzt_duplikat_von_auf_null(): void
    {
        $admin = User::factory()->admin()->create();
        $original = Feedback::factory()->create(['status' => Feedback::STATUS_OFFEN]);
        $duplikat = Feedback::factory()->duplikatVon($original->feedback_id)->create(['status' => Feedback::STATUS_OFFEN]);

        $this->actingAs($admin)
            ->patchJson(route('admin.feedback.duplicate', $duplikat->feedback_id), ['duplikat_von' => null])
            ->assertOk()
            ->assertJson(['ok' => true, 'duplikat_von' => null]);

        $this->assertNull($duplikat->refresh()->duplikat_von);

        $this->assertDatabaseHas('aktivitaeten', [
            'aktion' => Protokoll::ADMIN_FEEDBACK_DUPLIKAT_AUFGEHOBEN,
            'ziel_id' => $duplikat->feedback_id,
        ]);
    }

    #[Test]
    public function original_erledigen_benachrichtigt_absender_von_original_und_duplikat(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create(['email' => 'admin@firma.ch']);
        $absenderOriginal = User::factory()->lernender()->create(['email' => 'original@firma.ch']);
        $absenderDuplikat = User::factory()->lernender()->create(['email' => 'duplikat@firma.ch']);

        $original = Feedback::factory()->create([
            'benutzer_id' => $absenderOriginal->benutzer_id,
            'status' => Feedback::STATUS_OFFEN,
            'admin_notiz' => null,
        ]);
        $duplikat = Feedback::factory()->duplikatVon($original->feedback_id)->create([
            'benutzer_id' => $absenderDuplikat->benutzer_id,
            'status' => Feedback::STATUS_OFFEN,
            'admin_notiz' => null,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.feedback.update', $original->feedback_id), [
                'status' => Feedback::STATUS_ERLEDIGT,
                'admin_notiz' => 'Danke, behoben.',
            ])
            ->assertOk();

        Notification::assertSentTo($absenderOriginal, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::FEEDBACK_ANSWERED);
        Notification::assertSentTo($absenderDuplikat, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::FEEDBACK_ANSWERED);

        $this->assertSame(Feedback::STATUS_ERLEDIGT, $duplikat->refresh()->status);
        $this->assertNotNull($duplikat->refresh()->erledigt_am);
    }

    #[Test]
    public function absender_der_auch_das_original_gemeldet_hat_bekommt_nur_eine_mail(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create(['email' => 'admin@firma.ch']);
        $melder = User::factory()->lernender()->create(['email' => 'melder@firma.ch']);

        $original = Feedback::factory()->create(['benutzer_id' => $melder->benutzer_id, 'status' => Feedback::STATUS_OFFEN, 'admin_notiz' => null]);
        Feedback::factory()->duplikatVon($original->feedback_id)->create([
            'benutzer_id' => $melder->benutzer_id,
            'status' => Feedback::STATUS_OFFEN,
            'admin_notiz' => null,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.feedback.update', $original->feedback_id), ['status' => Feedback::STATUS_ERLEDIGT])
            ->assertOk();

        Notification::assertSentToTimes($melder, PortalMail::class, 1);
    }
}
