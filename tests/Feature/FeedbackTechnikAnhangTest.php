<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\FeedbackAnhang;
use App\Models\User;
use App\Services\Feedback\Anhang;
use App\Support\Einstellungen;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Block G – Feedback III: Kategorie «Lob» → «Sonstiges», technische Angaben (an/aus) und eigene
 * Anhänge. Die Screenshot-Tests bleiben in FeedbackTest.php, hier nur das Neue.
 */
class FeedbackTechnikAnhangTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    #[Test]
    public function kategorie_sonstiges_wird_gespeichert_und_alte_lob_werte_zeigen_ebenfalls_sonstiges(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->postJson(route('feedback.store'), [
            'kategorie' => 'sonstiges',
            'text' => 'Einfach nur ein Kompliment fürs Team.',
        ])->assertCreated();

        $this->assertDatabaseHas('feedback', [
            'benutzer_id' => $user->benutzer_id,
            'kategorie' => 'sonstiges',
        ]);

        // Der alte Wert «lob» existiert nach der vollständig gelaufenen Migration nicht mehr in der
        // Datenbank-Spalte – geprüft wird hier nur, dass die Anzeige-Funktion ihn weiterhin korrekt
        // auf «Sonstiges» abbildet (wichtig für Datensätze, die vor der Migration entstanden sind).
        $this->assertSame('Sonstiges', Feedback::kategorieLabel(Feedback::KATEGORIE_LOB));
        $this->assertSame('Sonstiges', Feedback::kategorieLabel('sonstiges'));
    }

    #[Test]
    public function technische_angaben_werden_nur_bei_zustimmung_gespeichert(): void
    {
        $user = User::factory()->lernender()->create();
        $technik = json_encode([
            'bildschirm' => '1440x900',
            'pixelverhaeltnis' => '2',
            'sprache' => 'de-CH',
            'zeitzone' => 'Europe/Zurich',
            'darstellung' => 'hell',
            'online' => true,
            'fehlgeschlagene_requests' => [['pfad' => '/api/kaputt', 'status' => 500]],
        ]);

        $this->actingAs($user)->postJson(route('feedback.store'), [
            'kategorie' => 'idee',
            'text' => 'Mit technischen Angaben gesendet.',
            'viewport' => '1440x900',
            'technik' => $technik,
        ])->assertCreated();

        $mit = Feedback::where('benutzer_id', $user->benutzer_id)->firstOrFail();
        $this->assertNotNull($mit->technik_details);
        $this->assertSame('1440x900', $mit->technik_details['bildschirm']);
        $this->assertSame('de-CH', $mit->technik_details['sprache']);
        $this->assertTrue($mit->technik_details['online']);
        $this->assertSame([['pfad' => '/api/kaputt', 'status' => 500]], $mit->technik_details['fehlgeschlagene_requests']);

        $this->actingAs($user)->postJson(route('feedback.store'), [
            'kategorie' => 'idee',
            'text' => 'Ohne technische Angaben gesendet.',
        ])->assertCreated();

        $ohne = Feedback::where('benutzer_id', $user->benutzer_id)
            ->where('text', 'Ohne technische Angaben gesendet.')
            ->firstOrFail();
        $this->assertNull($ohne->technik_details);
    }

    #[Test]
    public function erlaubte_anhaenge_werden_gespeichert_und_neu_kodiert(): void
    {
        $user = User::factory()->lernender()->create();
        $bild = UploadedFile::fake()->image('foto.png', 400, 300);
        $pdf = UploadedFile::fake()->createWithContent('beleg.pdf', "%PDF-1.4\n%…kurzer Testinhalt");
        $bildOriginal = file_get_contents($bild->getRealPath());

        $this->actingAs($user)->post(route('feedback.store'), [
            'kategorie' => 'fehler',
            'text' => 'Mit zwei Anhängen.',
            'anhaenge' => [$bild, $pdf],
        ], ['Accept' => 'application/json'])->assertCreated();

        $feedback = Feedback::where('benutzer_id', $user->benutzer_id)->firstOrFail();
        $this->assertCount(2, $feedback->anhaenge);
        foreach ($feedback->anhaenge as $anhang) {
            Storage::disk('local')->assertExists($anhang->pfad);
        }

        // Der eigentliche Schutz ist die Neukodierung: Das gespeicherte Bild darf nicht mehr Byte
        // für Byte das Hochgeladene sein – sonst überlebt ein Polyglot mit gültiger Bildsignatur –,
        // muss aber ein gültiges Bild derselben Grösse bleiben. Das PDF wird nicht umkodiert.
        $bildAnhang = $feedback->anhaenge->first(fn ($a) => str_starts_with((string) $a->mime, 'image/'));
        $this->assertNotNull($bildAnhang);
        $gespeichert = Storage::disk('local')->get($bildAnhang->pfad);
        $this->assertNotSame($bildOriginal, $gespeichert, 'Das Bild wurde nicht neu kodiert.');
        $masse = getimagesizefromstring($gespeichert);
        $this->assertNotFalse($masse, 'Das gespeicherte Bild ist kein gültiges Bild mehr.');
        $this->assertSame([400, 300], [$masse[0], $masse[1]]);

        $pdfAnhang = $feedback->anhaenge->first(fn ($a) => $a->mime === 'application/pdf');
        $this->assertNotNull($pdfAnhang);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($pdfAnhang->pfad));
    }

    #[Test]
    public function anhang_mit_falschem_inhalt_wird_trotz_erlaubter_endung_abgelehnt(): void
    {
        $user = User::factory()->lernender()->create();
        // .png-Endung, aber weder ein gültiges Bild noch Text (Nullbyte, kein UTF-8) – der Inhalt
        // entscheidet, nicht die Endung.
        $getarnt = UploadedFile::fake()->createWithContent('bild.png', "\x00\x01\xFFkein-bild");

        $this->actingAs($user)->post(route('feedback.store'), [
            'kategorie' => 'fehler',
            'text' => 'Getarnte Datei.',
            'anhaenge' => [$getarnt],
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['anhaenge.0']);
    }

    #[Test]
    public function anhang_mit_zu_hoher_pixelzahl_wird_trotz_kleiner_dateigroesse_abgelehnt(): void
    {
        // Schützt vor einer GD-Dekompressionsbombe (App\Services\Feedback\Anhang::MAX_PX,
        // Anhang::inhaltPruefen()): ein kleines, aber sehr hoch aufgelöstes Bild muss schon an der
        // Validierung scheitern, bevor es je mit imagecreatefromstring() dekodiert wird.
        $user = User::factory()->lernender()->create();
        $riesig = UploadedFile::fake()->image('riesig.png', Anhang::MAX_PX + 1, 10);

        $this->actingAs($user)->post(route('feedback.store'), [
            'kategorie' => 'fehler',
            'text' => 'Zu grosse Pixelzahl.',
            'anhaenge' => [$riesig],
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['anhaenge.0']);
    }

    #[Test]
    public function zu_grosser_anhang_wird_abgelehnt(): void
    {
        $user = User::factory()->lernender()->create();
        $zuGross = UploadedFile::fake()->create('gross.txt', 5200); // > 5120 KB

        $this->actingAs($user)->post(route('feedback.store'), [
            'kategorie' => 'fehler',
            'text' => 'Zu grosser Anhang.',
            'anhaenge' => [$zuGross],
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['anhaenge.0']);
    }

    #[Test]
    public function mehr_als_drei_anhaenge_werden_abgelehnt(): void
    {
        $user = User::factory()->lernender()->create();
        $dateien = [
            UploadedFile::fake()->createWithContent('a.txt', 'eins'),
            UploadedFile::fake()->createWithContent('b.txt', 'zwei'),
            UploadedFile::fake()->createWithContent('c.txt', 'drei'),
            UploadedFile::fake()->createWithContent('d.txt', 'vier'),
        ];

        $this->actingAs($user)->post(route('feedback.store'), [
            'kategorie' => 'fehler',
            'text' => 'Zu viele Anhänge.',
            'anhaenge' => $dateien,
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['anhaenge']);
    }

    #[Test]
    public function nur_absender_und_admin_koennen_einen_anhang_herunterladen(): void
    {
        $admin = User::factory()->admin()->create();
        $melder = User::factory()->lernender()->create();
        $anderer = User::factory()->lernender()->create();

        $feedback = Feedback::factory()->create(['benutzer_id' => $melder->benutzer_id]);
        $pfad = 'feedback/anhaenge/2026/test.txt';
        Storage::disk('local')->put($pfad, 'inhalt');
        $anhang = FeedbackAnhang::create([
            'feedback_id' => $feedback->feedback_id,
            'dateiname' => 'beleg.txt',
            'pfad' => $pfad,
            'mime' => 'text/plain',
            'groesse' => 6,
        ]);

        $this->actingAs($admin)->get(route('feedback.attachment', [$feedback->feedback_id, $anhang->feedback_anhang_id]))->assertOk();
        $this->actingAs($melder)->get(route('feedback.attachment', [$feedback->feedback_id, $anhang->feedback_anhang_id]))->assertOk();
        $this->actingAs($anderer)->get(route('feedback.attachment', [$feedback->feedback_id, $anhang->feedback_anhang_id]))->assertForbidden();
    }

    #[Test]
    public function feedback_knopf_wird_ueber_die_einstellung_ein_und_ausgeschaltet(): void
    {
        $user = User::factory()->lernender()->create();

        Einstellungen::set(Einstellungen::FEEDBACK_KNOPF, '1');
        $this->actingAs($user)->get(route('learner.dashboard'))
            ->assertOk()
            ->assertSee(__('Feedback / Fehler melden'));

        Einstellungen::set(Einstellungen::FEEDBACK_KNOPF, '0');
        $this->actingAs($user)->get(route('learner.dashboard'))
            ->assertOk()
            ->assertDontSee(__('Feedback / Fehler melden'));
    }

    #[Test]
    public function admin_kann_den_feedback_knopf_umschalten(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.operations.feedback-button.update'), [])
            ->assertRedirect(route('admin.operations.edit'));
        $this->assertSame('0', Einstellungen::get(Einstellungen::FEEDBACK_KNOPF));

        $this->actingAs($admin)->put(route('admin.operations.feedback-button.update'), ['feedback_knopf' => '1'])
            ->assertRedirect(route('admin.operations.edit'));
        $this->assertSame('1', Einstellungen::get(Einstellungen::FEEDBACK_KNOPF));
    }
}
