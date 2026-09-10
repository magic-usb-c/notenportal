<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    public static function rollen(): array
    {
        return [['admin'], ['berufsbildner'], ['lernender']];
    }

    #[Test]
    #[DataProvider('rollen')]
    public function jede_rolle_kann_feedback_melden(string $rolle): void
    {
        $user = User::factory()->{$rolle}()->create();

        $response = $this->actingAs($user)
            ->postJson(route('feedback.store'), [
                'kategorie' => 'idee',
                'text' => 'Es wäre toll, wenn man Noten filtern könnte.',
                'route_name' => 'dashboard',
                'url' => '/dashboard',
                'viewport' => '1280x800',
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('feedback', [
            'benutzer_id' => $user->benutzer_id,
            'kategorie' => 'idee',
            'route_name' => 'dashboard',
        ]);

        $eintrag = Feedback::where('benutzer_id', $user->benutzer_id)->firstOrFail();
        $this->assertNotNull($eintrag->user_agent);
        $this->assertSame(Feedback::STATUS_OFFEN, $eintrag->status);
    }

    #[Test]
    public function validierung_lehnt_ungueltige_eingaben_ab(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->postJson(route('feedback.store'), [
                'kategorie' => 'unsinn',
                'text' => 'ab',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['kategorie', 'text']);
    }

    #[Test]
    public function lernender_sieht_in_eigener_liste_nur_eigene_meldungen(): void
    {
        $eigener = User::factory()->lernender()->create();
        $fremder = User::factory()->lernender()->create();

        Feedback::factory()->create([
            'benutzer_id' => $eigener->benutzer_id,
            'text' => 'Meine eigene Meldung 12345',
        ]);
        Feedback::factory()->create([
            'benutzer_id' => $fremder->benutzer_id,
            'text' => 'Fremde Meldung die nicht sichtbar sein darf 98765',
        ]);

        $response = $this->actingAs($eigener)->get(route('feedback.index'));

        $response->assertOk();
        $response->assertSee('Meine eigene Meldung 12345');
        $response->assertDontSee('Fremde Meldung die nicht sichtbar sein darf 98765');
    }

    #[Test]
    public function admin_index_zeigt_alle_meldungen_und_filtert(): void
    {
        $admin = User::factory()->admin()->create();
        $lernender = User::factory()->lernender()->create();

        Feedback::factory()->create([
            'benutzer_id' => $lernender->benutzer_id,
            'kategorie' => Feedback::KATEGORIE_BUG,
            'status' => Feedback::STATUS_OFFEN,
            'text' => 'Bug-Meldung im Rechner',
        ]);
        Feedback::factory()->create([
            'benutzer_id' => $lernender->benutzer_id,
            'kategorie' => Feedback::KATEGORIE_IDEE,
            'status' => Feedback::STATUS_ERLEDIGT,
            'text' => 'Ideen-Meldung fuer Dashboard',
        ]);

        $alle = $this->actingAs($admin)->get(route('admin.feedback.index'));
        $alle->assertOk();
        $alle->assertSee('Bug-Meldung im Rechner');
        $alle->assertSee('Ideen-Meldung fuer Dashboard');

        $gefiltert = $this->actingAs($admin)->get(route('admin.feedback.index', ['status' => Feedback::STATUS_OFFEN]));
        $gefiltert->assertOk();
        $gefiltert->assertSee('Bug-Meldung im Rechner');
        $gefiltert->assertDontSee('Ideen-Meldung fuer Dashboard');
    }

    #[Test]
    public function statuswechsel_setzt_und_leert_erledigt_am(): void
    {
        $admin = User::factory()->admin()->create();
        $feedback = Feedback::factory()->create(['status' => Feedback::STATUS_OFFEN, 'erledigt_am' => null]);

        $this->actingAs($admin)
            ->patchJson(route('admin.feedback.update', $feedback->feedback_id), [
                'status' => Feedback::STATUS_ERLEDIGT,
                'admin_notiz' => 'Erledigt im Release 2.',
            ])
            ->assertOk();

        $feedback->refresh();
        $this->assertSame(Feedback::STATUS_ERLEDIGT, $feedback->status);
        $this->assertNotNull($feedback->erledigt_am);

        $this->actingAs($admin)
            ->patchJson(route('admin.feedback.update', $feedback->feedback_id), [
                'status' => Feedback::STATUS_IN_ARBEIT,
            ])
            ->assertOk();

        $feedback->refresh();
        $this->assertSame(Feedback::STATUS_IN_ARBEIT, $feedback->status);
        $this->assertNull($feedback->erledigt_am);
    }

    #[Test]
    public function nicht_admins_bekommen_403_auf_admin_feedback_routen(): void
    {
        $feedback = Feedback::factory()->create();

        foreach (['lernender', 'berufsbildner'] as $rolle) {
            $user = User::factory()->{$rolle}()->create();

            $this->actingAs($user)->get(route('admin.feedback.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.feedback.export'))->assertForbidden();
            $this->actingAs($user)
                ->patchJson(route('admin.feedback.update', $feedback->feedback_id), ['status' => Feedback::STATUS_ERLEDIGT])
                ->assertForbidden();
        }
    }

    #[Test]
    public function throttle_greift_nach_zehn_meldungen(): void
    {
        $user = User::factory()->lernender()->create();

        for ($i = 1; $i <= 10; $i++) {
            $this->actingAs($user)
                ->postJson(route('feedback.store'), [
                    'kategorie' => 'feedback',
                    'text' => 'Meldung Nummer '.$i,
                ])
                ->assertCreated();
        }

        $this->actingAs($user)
            ->postJson(route('feedback.store'), [
                'kategorie' => 'feedback',
                'text' => 'Meldung Nummer 11',
            ])
            ->assertStatus(429);
    }

    #[Test]
    public function csv_export_liefert_kopfzeile_und_daten(): void
    {
        $admin = User::factory()->admin()->create();
        Feedback::factory()->create(['text' => 'Export-Testmeldung']);

        $response = $this->actingAs($admin)->get(route('admin.feedback.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $inhalt = $response->streamedContent();
        $this->assertStringContainsString('Datum;Nachname;Vorname', $inhalt);
        $this->assertStringContainsString('Export-Testmeldung', $inhalt);
    }
}
