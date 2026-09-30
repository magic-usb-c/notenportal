<?php

namespace Tests\Feature;

use App\Http\Controllers\FeedbackController;
use App\Models\Feedback;
use App\Models\FeedbackStimme;
use App\Models\NotificationMark;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

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
        $this->assertSame($user->rollen()->pluck('name')->first(), $eintrag->rolle);
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
            'kategorie' => Feedback::KATEGORIE_FEHLER,
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
    public function admin_index_sortiert_nach_stimmen_absteigend_und_faellt_bei_ungueltigem_sort_auf_datum_zurueck(): void
    {
        $admin = User::factory()->admin()->create();

        $wenig = Feedback::factory()->create(['text' => 'Wenig Stimmen Meldung']);
        $viel = Feedback::factory()->create(['text' => 'Viele Stimmen Meldung']);

        FeedbackStimme::query()->insert([
            ['feedback_id' => $viel->feedback_id, 'benutzer_id' => User::factory()->lernender()->create()->benutzer_id, 'erstellt_am' => now()],
            ['feedback_id' => $viel->feedback_id, 'benutzer_id' => User::factory()->lernender()->create()->benutzer_id, 'erstellt_am' => now()],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.feedback.index', ['sort' => 'stimmen']));
        $response->assertOk();
        $inhalt = $response->getContent();
        $this->assertLessThan(strpos($inhalt, 'Wenig Stimmen Meldung'), strpos($inhalt, 'Viele Stimmen Meldung'));

        // Ungültiger sort-Wert fällt auf «datum» zurück, keine 500er.
        $this->actingAs($admin)->get(route('admin.feedback.index', ['sort' => 'unsinn']))->assertOk();
    }

    #[Test]
    public function admin_index_blendet_duplikate_standardmaessig_aus(): void
    {
        $admin = User::factory()->admin()->create();

        $original = Feedback::factory()->create(['text' => 'Original-Meldung-Text']);
        Feedback::factory()->duplikatVon($original->feedback_id)->create(['text' => 'Duplikat-Meldung-Text']);

        $ohneDuplikate = $this->actingAs($admin)->get(route('admin.feedback.index'));
        $ohneDuplikate->assertOk();
        $ohneDuplikate->assertSee('Original-Meldung-Text');
        $ohneDuplikate->assertDontSee('Duplikat-Meldung-Text');

        $mitDuplikaten = $this->actingAs($admin)->get(route('admin.feedback.index', ['duplikate' => 1]));
        $mitDuplikaten->assertOk();
        $mitDuplikaten->assertSee('Original-Meldung-Text');
        $mitDuplikaten->assertSee('Duplikat-Meldung-Text');
    }

    #[Test]
    public function admin_index_badge_zaehlt_duplikate_nicht_mit_als_offene_meldung(): void
    {
        $admin = User::factory()->admin()->create();

        $original = Feedback::factory()->create(['status' => Feedback::STATUS_OFFEN]);
        Feedback::factory()->duplikatVon($original->feedback_id)->create(['status' => Feedback::STATUS_OFFEN]);

        $this->assertSame(1, Feedback::hauptmeldungen()->where('status', Feedback::STATUS_OFFEN)->count());
    }

    #[Test]
    public function admin_index_leerzustand_ohne_meldungen_und_ohne_treffer_fuer_filter(): void
    {
        $admin = User::factory()->admin()->create();

        $leer = $this->actingAs($admin)->get(route('admin.feedback.index'));
        $leer->assertOk();
        $leer->assertSee(__('Noch keine Meldungen'));

        Feedback::factory()->create(['status' => Feedback::STATUS_OFFEN]);

        $ohneTreffer = $this->actingAs($admin)->get(route('admin.feedback.index', ['status' => Feedback::STATUS_ERLEDIGT]));
        $ohneTreffer->assertOk();
        $ohneTreffer->assertSee(__('Keine Meldungen für diese Filter.'));
        $ohneTreffer->assertSee(__('Filter zurücksetzen'));
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
            $this->actingAs($user)
                ->patchJson(route('admin.feedback.duplicate', $feedback->feedback_id), ['duplikat_von' => null])
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
                    'kategorie' => 'idee',
                    'text' => 'Meldung Nummer '.$i,
                ])
                ->assertCreated();
        }

        $this->actingAs($user)
            ->postJson(route('feedback.store'), [
                'kategorie' => 'idee',
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

    #[Test]
    public function meldung_mit_screenshot_und_js_fehlern_wird_gespeichert(): void
    {
        $user = User::factory()->lernender()->create();
        $bild = UploadedFile::fake()->image('screenshot.jpg', 1200, 800)->size(500);

        $response = $this->actingAs($user)->post(route('feedback.store'), [
            'kategorie' => 'fehler',
            'text' => 'Der Speichern-Knopf reagiert nicht.',
            'route_name' => 'learner.grades.index',
            'url' => '/grades',
            'viewport' => '1280x800',
            'js_fehler' => json_encode(['Fehler 1', 'Fehler 2', 'Fehler 3', 'Fehler 4 (wird verworfen, nur die letzten 3 zählen)']),
            'screenshot' => $bild,
        ], ['Accept' => 'application/json']);

        $response->assertCreated();

        $eintrag = Feedback::where('benutzer_id', $user->benutzer_id)->firstOrFail();
        $this->assertTrue($eintrag->hatScreenshot());
        Storage::disk('local')->assertExists($eintrag->screenshot_pfad);
        $this->assertCount(3, $eintrag->js_fehler);
        $this->assertSame(['Fehler 2', 'Fehler 3', 'Fehler 4 (wird verworfen, nur die letzten 3 zählen)'], $eintrag->js_fehler);
    }

    #[Test]
    public function screenshot_mit_falschem_dateityp_wird_abgelehnt(): void
    {
        $user = User::factory()->lernender()->create();
        $datei = UploadedFile::fake()->create('schadcode.php', 10, 'application/x-php');

        $this->actingAs($user)->post(route('feedback.store'), [
            'kategorie' => 'fehler',
            'text' => 'Screenshot mit falschem Typ.',
            'screenshot' => $datei,
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['screenshot']);
    }

    #[Test]
    public function zu_grosser_screenshot_wird_abgelehnt(): void
    {
        $user = User::factory()->lernender()->create();
        $zuGross = UploadedFile::fake()->image('gross.jpg')->size(2500); // > 1536 KB

        $this->actingAs($user)->post(route('feedback.store'), [
            'kategorie' => 'fehler',
            'text' => 'Screenshot ist zu gross.',
            'screenshot' => $zuGross,
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['screenshot']);
    }

    #[Test]
    public function nur_admin_sieht_screenshot_fremder_meldungen(): void
    {
        $admin = User::factory()->admin()->create();
        $melder = User::factory()->lernender()->create();
        $anderer = User::factory()->lernender()->create();

        $bild = UploadedFile::fake()->image('screenshot.jpg', 800, 600)->size(200);
        $feedback = Feedback::factory()->create(['benutzer_id' => $melder->benutzer_id]);
        $feedback->update([
            'screenshot_pfad' => $bild->storeAs('feedback/2026', 'test.jpg', 'local'),
            'screenshot_mime' => 'image/jpeg',
            'screenshot_groesse' => $bild->getSize(),
        ]);

        $this->actingAs($admin)->get(route('admin.feedback.screenshot', $feedback->feedback_id))->assertOk();
        $this->actingAs($anderer)->get(route('admin.feedback.screenshot', $feedback->feedback_id))->assertForbidden();
        $this->actingAs($melder)->get(route('admin.feedback.screenshot', $feedback->feedback_id))->assertForbidden();
    }

    #[Test]
    public function screenshot_route_liefert_404_ohne_screenshot(): void
    {
        $admin = User::factory()->admin()->create();
        $feedback = Feedback::factory()->create();

        $this->actingAs($admin)->get(route('admin.feedback.screenshot', $feedback->feedback_id))->assertNotFound();
    }

    #[Test]
    public function login_hinweis_erscheint_einmal_und_verschwindet_nach_dem_schliessen(): void
    {
        $user = User::factory()->lernender()->create();

        $seite = $this->actingAs($user)->get(route('learner.dashboard'));
        $seite->assertSee('erreichst du uns jederzeit');

        $this->actingAs($user)->postJson(route('feedback.hint.dismiss'))->assertOk();

        $this->assertDatabaseHas('notification_marks', [
            'user_id' => $user->benutzer_id,
            'type' => FeedbackController::HINWEIS_TYP,
            'subject_key' => FeedbackController::HINWEIS_SCHLUESSEL,
        ]);

        $nachDemSchliessen = $this->actingAs($user)->get(route('learner.dashboard'));
        $nachDemSchliessen->assertOk();
        $nachDemSchliessen->assertDontSee('erreichst du uns jederzeit');
        $this->assertSame(
            1,
            NotificationMark::where('user_id', $user->benutzer_id)->where('type', FeedbackController::HINWEIS_TYP)->count(),
        );
    }

    #[Test]
    public function login_hinweis_laesst_am_seitenende_platz_und_faellt_nach_dem_schliessen_weg(): void
    {
        $user = User::factory()->lernender()->create();

        // Solange der Hinweis schwebt, braucht das Seitenende mehr Platz, sonst verdeckt er die letzten Knöpfe
        $this->actingAs($user)->get(route('learner.dashboard'))
            ->assertSee('np-feedback-hinweis-weg.window', false)
            ->assertSee("hinweis ? 'lg:pb-60' : ''", false);

        $this->actingAs($user)->postJson(route('feedback.hint.dismiss'))->assertOk();

        $this->actingAs($user)->get(route('learner.dashboard'))
            ->assertDontSee('np-feedback-hinweis-weg.window', false);
    }

    #[Test]
    public function login_hinweis_erscheint_nicht_waehrend_der_einrichtung(): void
    {
        $admin = User::factory()->admin()->create();

        // Die Einrichtung ist ein geführter Ablauf; der Hinweis lag dort über «Speichern und weiter»
        $this->actingAs($admin)->get(route('admin.setup'))
            ->assertOk()
            ->assertDontSee('erreichst du uns jederzeit');
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertSee('erreichst du uns jederzeit');
    }
}
