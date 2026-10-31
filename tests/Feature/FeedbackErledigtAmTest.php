<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Funktionsprüfung admin: das Erledigt-Datum bleibt beim Korrigieren der Notiz stehen. */
class FeedbackErledigtAmTest extends TestCase
{
    #[Test]
    public function notizkorrektur_an_erledigter_meldung_behaelt_das_erledigt_datum(): void
    {
        $admin = User::factory()->admin()->create();
        $feedback = Feedback::factory()->create([
            'status' => Feedback::STATUS_ERLEDIGT,
            'admin_notiz' => 'Erster Text.',
            'erledigt_am' => '2026-03-01 10:00:00',
        ]);

        $this->actingAs($admin)->patchJson(route('admin.feedback.update', $feedback->feedback_id), [
            'status' => Feedback::STATUS_ERLEDIGT,
            'admin_notiz' => 'Korrigierter Text.',
        ])->assertOk();

        $this->assertSame('2026-03-01 10:00:00', $feedback->fresh()->erledigt_am->format('Y-m-d H:i:s'));
        $this->assertSame('Korrigierter Text.', $feedback->fresh()->admin_notiz);
    }

    #[Test]
    public function wiedereroeffnen_und_erneut_erledigen_setzt_ein_neues_datum(): void
    {
        $admin = User::factory()->admin()->create();
        $feedback = Feedback::factory()->create(['status' => Feedback::STATUS_ERLEDIGT, 'erledigt_am' => '2026-03-01 10:00:00']);

        $this->actingAs($admin)->patchJson(route('admin.feedback.update', $feedback->feedback_id), ['status' => Feedback::STATUS_OFFEN])->assertOk();
        $this->assertNull($feedback->fresh()->erledigt_am);

        $this->actingAs($admin)->patchJson(route('admin.feedback.update', $feedback->feedback_id), ['status' => Feedback::STATUS_ERLEDIGT])->assertOk();
        $this->assertGreaterThan('2026-03-01 10:00:00', $feedback->fresh()->erledigt_am->format('Y-m-d H:i:s'));
    }
}
