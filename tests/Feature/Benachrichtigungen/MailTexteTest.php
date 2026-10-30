<?php

declare(strict_types=1);

namespace Tests\Feature\Benachrichtigungen;

use App\Models\Feedback;
use App\Models\Kategorie;
use App\Models\Modul;
use App\Models\Pruefung;
use App\Models\User;
use App\Services\Notifications\Messages\BelowThreshold;
use App\Services\Notifications\Messages\ExamReminder;
use App\Services\Notifications\Messages\FeedbackAnswered;
use App\Services\Notifications\Messages\FeedbackReceived;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Wortlaut der Mails: keine Entwicklersprache, Einzahl/Mehrzahl, ganze Prozentwerte, ehrliche Statusmails. */
class MailTexteTest extends TestCase
{
    #[Test]
    public function feedback_mail_zeigt_den_pfad_der_seite_und_nie_den_routennamen(): void
    {
        $melder = User::factory()->lernender()->create();
        $feedback = Feedback::factory()->create([
            'benutzer_id' => $melder->benutzer_id,
            'route_name' => 'learner.grades.index',
            'url' => 'https://portal.example/grades?token=geheim',
        ]);

        $facts = FeedbackReceived::content($feedback, $melder)->facts;

        $this->assertSame('/grades', $facts['Seite']);
        $this->assertStringNotContainsString('learner.', implode(' ', $facts));
        $this->assertStringNotContainsString('geheim', implode(' ', $facts));
    }

    #[Test]
    public function feedback_mail_laesst_die_seitenzeile_ohne_url_weg(): void
    {
        $melder = User::factory()->lernender()->create();
        $feedback = Feedback::factory()->create(['benutzer_id' => $melder->benutzer_id, 'route_name' => 'learner.grades.index', 'url' => null]);

        $this->assertArrayNotHasKey('Seite', FeedbackReceived::content($feedback, $melder)->facts);
    }

    #[Test]
    public function unterschreitungsmail_unterscheidet_einzahl_und_mehrzahl(): void
    {
        $lernender = User::factory()->lernender()->create()->lernender;
        $eins = [['label' => 'Mathematik', 'alt' => 4.5, 'neu' => 3.8]];
        $zwei = [...$eins, ['label' => 'Deutsch', 'alt' => null, 'neu' => 3.5]];

        $this->assertStringContainsString('folgender Schnitt', BelowThreshold::content($lernender, $eins, false)->lines[0]);
        $mehrzahl = BelowThreshold::content($lernender, $zwei, false)->lines[0];
        $this->assertStringContainsString('folgende Schnitte', $mehrzahl);
        $this->assertStringNotContainsString('folgender Schnitt', $mehrzahl);

        $betreuer = BelowThreshold::content($lernender, $zwei, true)->lines[0];
        $this->assertStringNotContainsString('bzw.', $betreuer);
        $this->assertStringContainsString('2 Schnitte', $betreuer);
        $this->assertStringNotContainsString('bzw.', BelowThreshold::content($lernender, $eins, true)->lines[0]);
    }

    #[Test]
    public function pruefungserinnerung_zeigt_ganze_gewichtung_ohne_dezimalstelle(): void
    {
        $user = User::factory()->lernender()->create();
        $modul = Modul::factory()->create();
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $user->lernender->lehrberuf_id,
            'modul_id' => $modul->modul_id,
            'kategorie_id' => Kategorie::where('code', 'UEK')->value('kategorie_id') ?? Kategorie::value('kategorie_id'),
        ]);

        $erstelle = fn (float $gewicht) => Pruefung::create([
            'lernender_id' => $user->lernender->lernender_id,
            'modul_id' => $modul->modul_id,
            'titel' => 'LB',
            'datum' => now()->addWeek()->toDateString(),
            'gewichtung_prozent' => $gewicht,
            'quelle' => Pruefung::MANUELL,
        ]);

        $this->assertSame('100 %', ExamReminder::content($erstelle(100.0)->fresh())->facts['Gewichtung']);
        $this->assertSame('62.5 %', ExamReminder::content($erstelle(62.5)->fresh())->facts['Gewichtung']);
    }

    #[Test]
    public function statusmail_ohne_antwort_behauptet_keine_antwort(): void
    {
        $feedback = Feedback::factory()->create(['status' => Feedback::STATUS_ERLEDIGT, 'admin_notiz' => null]);

        $c = FeedbackAnswered::content($feedback);

        $this->assertStringNotContainsString('beantwortet', $c->subject);
        $this->assertStringContainsString('Erledigt', $c->subject);
        $this->assertStringContainsString('Erledigt', $c->lines[0]);
        $this->assertSame([], $c->sections);
    }

    #[Test]
    public function statusmail_mit_antwort_zeigt_die_antwort(): void
    {
        $feedback = Feedback::factory()->create(['status' => Feedback::STATUS_IN_ARBEIT, 'admin_notiz' => 'Wir kümmern uns darum.']);

        $c = FeedbackAnswered::content($feedback);

        $this->assertSame('Deine Meldung wurde beantwortet', $c->subject);
        $this->assertSame('Wir kümmern uns darum.', $c->sections[0]['text']);
    }

    #[Test]
    public function statusmail_zeigt_den_status_auf_englisch_uebersetzt(): void
    {
        $feedback = Feedback::factory()->create(['status' => Feedback::STATUS_ERLEDIGT, 'admin_notiz' => null]);

        App::setLocale('en');
        $c = FeedbackAnswered::content($feedback);

        $this->assertStringContainsString('Done', $c->subject);
        $this->assertStringNotContainsString('Erledigt', $c->lines[0]);
    }
}
