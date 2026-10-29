<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Feedback;
use App\Models\FeedbackAnhang;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * app/Console/Commands/FeedbackAufraeumen.php: Dateien unter feedback/ ohne Datenbankzeile verschwinden,
 * Dateien mit Zeile und frische Uploads (jünger als ein Tag) bleiben; --vorschau löscht nichts.
 */
class FeedbackAufraeumenTest extends TestCase
{
    #[Test]
    public function loescht_nur_alte_dateien_ohne_datenbankzeile(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');
        $melder = User::factory()->lernender()->create();
        $feedback = Feedback::factory()->create(['benutzer_id' => $melder->benutzer_id, 'screenshot_pfad' => 'feedback/2026/bekannt.jpg']);
        FeedbackAnhang::create(['feedback_id' => $feedback->feedback_id, 'dateiname' => 'a.txt', 'pfad' => 'feedback/anhaenge/2026/bekannt.txt', 'mime' => 'text/plain', 'groesse' => 1]);

        $alt = ['feedback/2026/bekannt.jpg', 'feedback/anhaenge/2026/bekannt.txt', 'feedback/2026/verwaist.jpg', 'feedback/anhaenge/2026/verwaist.txt'];
        foreach ([...$alt, 'feedback/2026/frisch.jpg'] as $pfad) {
            $disk->put($pfad, 'x');
        }
        foreach ($alt as $pfad) {
            touch($disk->path($pfad), time() - 2 * 86400);
        }

        $this->artisan('notenportal:feedback-aufraeumen', ['--vorschau' => true])
            ->expectsOutputToContain('2 Dateien verwaist')
            ->assertSuccessful();
        $disk->assertExists('feedback/2026/verwaist.jpg');

        $this->artisan('notenportal:feedback-aufraeumen')
            ->expectsOutputToContain('2 Dateien gelöscht')
            ->assertSuccessful();
        $disk->assertMissing('feedback/2026/verwaist.jpg');
        $disk->assertMissing('feedback/anhaenge/2026/verwaist.txt');
        $disk->assertExists('feedback/2026/bekannt.jpg');
        $disk->assertExists('feedback/anhaenge/2026/bekannt.txt');
        $disk->assertExists('feedback/2026/frisch.jpg');

        $this->artisan('notenportal:feedback-aufraeumen')->expectsOutputToContain('Keine verwaisten Dateien')->assertSuccessful();
    }
}
