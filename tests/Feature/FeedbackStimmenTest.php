<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\FeedbackStimme;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Stimmen «Betrifft mich auch»: idempotentes Voten, Seitenrecht, Ausschlüsse (eigene/erledigte/
 * Duplikat-Meldungen), Enumeration-Schutz von `similar`, Throttle, Gastzugriff.
 */
class FeedbackStimmenTest extends TestCase
{
    #[Test]
    public function stimme_ist_idempotent_und_delete_entfernt_sie(): void
    {
        $melder = User::factory()->lernender()->create();
        $feedback = Feedback::factory()->create([
            'benutzer_id' => $melder->benutzer_id,
            'route_name' => 'learner.grades.index',
            'status' => Feedback::STATUS_OFFEN,
        ]);
        $anderer = User::factory()->lernender()->create();

        $this->actingAs($anderer)->postJson(route('feedback.vote', $feedback->feedback_id))
            ->assertOk()->assertJson(['stimmen' => 1, 'meine' => true]);
        $this->actingAs($anderer)->postJson(route('feedback.vote', $feedback->feedback_id))
            ->assertOk()->assertJson(['stimmen' => 1, 'meine' => true]);

        $this->assertSame(1, FeedbackStimme::where('feedback_id', $feedback->feedback_id)->count());

        $this->actingAs($anderer)->deleteJson(route('feedback.vote.destroy', $feedback->feedback_id))
            ->assertOk()->assertJson(['stimmen' => 0, 'meine' => false]);

        $this->assertSame(0, FeedbackStimme::where('feedback_id', $feedback->feedback_id)->count());
    }

    #[Test]
    public function eigene_meldung_ergibt_422(): void
    {
        $melder = User::factory()->lernender()->create();
        $feedback = Feedback::factory()->create([
            'benutzer_id' => $melder->benutzer_id,
            'route_name' => 'learner.grades.index',
            'status' => Feedback::STATUS_OFFEN,
        ]);

        $this->actingAs($melder)->postJson(route('feedback.vote', $feedback->feedback_id))
            ->assertStatus(422);
    }

    #[Test]
    public function erledigte_meldung_duplikat_und_unbekannte_id_ergeben_404(): void
    {
        $anderer = User::factory()->lernender()->create();

        $erledigt = Feedback::factory()->create([
            'route_name' => 'learner.grades.index',
            'status' => Feedback::STATUS_ERLEDIGT,
        ]);
        $this->actingAs($anderer)->postJson(route('feedback.vote', $erledigt->feedback_id))->assertNotFound();

        $original = Feedback::factory()->create([
            'route_name' => 'learner.grades.index',
            'status' => Feedback::STATUS_OFFEN,
        ]);
        $duplikat = Feedback::factory()->duplikatVon($original->feedback_id)->create([
            'route_name' => 'learner.grades.index',
            'status' => Feedback::STATUS_OFFEN,
        ]);
        $this->actingAs($anderer)->postJson(route('feedback.vote', $duplikat->feedback_id))->assertNotFound();

        $this->actingAs($anderer)->postJson(route('feedback.vote', 999999))->assertNotFound();
    }

    #[Test]
    public function lernender_stimmt_nicht_fuer_meldung_auf_admin_route(): void
    {
        $anderer = User::factory()->lernender()->create();
        $feedback = Feedback::factory()->create([
            'route_name' => 'admin.feedback.index',
            'status' => Feedback::STATUS_OFFEN,
        ]);

        $this->actingAs($anderer)->postJson(route('feedback.vote', $feedback->feedback_id))->assertNotFound();

        $this->actingAs($anderer)
            ->getJson(route('feedback.similar', ['route_name' => 'admin.feedback.index']))
            ->assertOk()
            ->assertJson(['anzahl' => 0, 'meldungen' => []]);
    }

    #[Test]
    public function similar_verraet_keine_privaten_daten_und_schliesst_eigene_duplikate_und_erledigte_aus(): void
    {
        $melder = User::factory()->lernender()->create();
        $anderer = User::factory()->lernender()->create();

        $offen = Feedback::factory()->create([
            'benutzer_id' => $melder->benutzer_id,
            'route_name' => 'learner.grades.index',
            'status' => Feedback::STATUS_OFFEN,
            'text' => 'Streng geheimer Text, darf nicht ausgeliefert werden',
            'url' => '/geheim',
        ]);
        $eigene = Feedback::factory()->create([
            'benutzer_id' => $anderer->benutzer_id,
            'route_name' => 'learner.grades.index',
            'status' => Feedback::STATUS_OFFEN,
        ]);
        $erledigt = Feedback::factory()->create([
            'benutzer_id' => $melder->benutzer_id,
            'route_name' => 'learner.grades.index',
            'status' => Feedback::STATUS_ERLEDIGT,
        ]);
        $duplikat = Feedback::factory()->duplikatVon($offen->feedback_id)->create([
            'benutzer_id' => $melder->benutzer_id,
            'route_name' => 'learner.grades.index',
            'status' => Feedback::STATUS_OFFEN,
        ]);

        $response = $this->actingAs($anderer)
            ->getJson(route('feedback.similar', ['route_name' => 'learner.grades.index']));

        $response->assertOk();
        $response->assertJsonCount(1, 'meldungen');
        $response->assertJsonPath('meldungen.0.id', $offen->feedback_id);
        $response->assertDontSee('Streng geheimer Text');
        $response->assertDontSee('/geheim');
        $response->assertDontSee($melder->email);

        $eintrag = $response->json('meldungen.0');
        $this->assertSame(['id', 'kategorie_label', 'datum', 'stimmen', 'meine'], array_keys($eintrag));
    }

    #[Test]
    public function throttle_greift_bei_stimme_und_bei_similar(): void
    {
        $anderer = User::factory()->lernender()->create();
        $this->actingAs($anderer);

        for ($i = 1; $i <= 30; $i++) {
            $feedback = Feedback::factory()->create([
                'route_name' => 'learner.grades.index',
                'status' => Feedback::STATUS_OFFEN,
            ]);
            $this->postJson(route('feedback.vote', $feedback->feedback_id))->assertOk();
        }
        $letzte = Feedback::factory()->create([
            'route_name' => 'learner.grades.index',
            'status' => Feedback::STATUS_OFFEN,
        ]);
        $this->postJson(route('feedback.vote', $letzte->feedback_id))->assertStatus(429);

        $andererFuerSimilar = User::factory()->lernender()->create();
        $this->actingAs($andererFuerSimilar);
        for ($i = 1; $i <= 60; $i++) {
            $this->getJson(route('feedback.similar', ['route_name' => 'learner.grades.index']))->assertOk();
        }
        $this->getJson(route('feedback.similar', ['route_name' => 'learner.grades.index']))->assertStatus(429);
    }

    #[Test]
    public function gast_bekommt_401_oder_redirect(): void
    {
        $feedback = Feedback::factory()->create([
            'route_name' => 'learner.grades.index',
            'status' => Feedback::STATUS_OFFEN,
        ]);

        $this->postJson(route('feedback.vote', $feedback->feedback_id))->assertUnauthorized();
        $this->deleteJson(route('feedback.vote.destroy', $feedback->feedback_id))->assertUnauthorized();
        $this->getJson(route('feedback.similar', ['route_name' => 'learner.grades.index']))->assertUnauthorized();

        $this->get(route('feedback.similar', ['route_name' => 'learner.grades.index']))->assertRedirect(route('login'));
    }
}
