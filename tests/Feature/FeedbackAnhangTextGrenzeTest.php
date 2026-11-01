<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Feedback-Anhang: Textprüfung an der 64-KB-Lesegrenze (Mehrbyte-Zeichen auf dem Schnitt). */
class FeedbackAnhangTextGrenzeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function senden(UploadedFile $datei): TestResponse
    {
        $user = User::factory()->lernender()->create();

        return $this->actingAs($user)->post(route('feedback.store'), [
            'kategorie' => 'fehler',
            'text' => 'Grosses Log.',
            'anhaenge' => [$datei],
        ], ['Accept' => 'application/json']);
    }

    #[Test]
    public function textdatei_ueber_64_kb_mit_umlaut_auf_der_pruefgrenze_wird_angenommen(): void
    {
        $inhalt = str_repeat('a', 65535).'ä'.str_repeat('b', 4000);

        $this->senden(UploadedFile::fake()->createWithContent('server.log', $inhalt))->assertCreated();
    }

    #[Test]
    public function textdatei_unter_64_kb_mit_ungueltigem_utf8_am_ende_wird_weiter_abgelehnt(): void
    {
        $inhalt = str_repeat('a', 1000)."\xC3";

        $this->senden(UploadedFile::fake()->createWithContent('server.log', $inhalt))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['anhaenge.0']);
    }

    #[Test]
    public function textdatei_ueber_64_kb_mit_ungueltigem_utf8_vor_der_grenze_wird_abgelehnt(): void
    {
        $inhalt = str_repeat('a', 100)."\xFF".str_repeat('b', 70000);

        $this->senden(UploadedFile::fake()->createWithContent('server.log', $inhalt))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['anhaenge.0']);
    }
}
