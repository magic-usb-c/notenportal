<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use App\Http\Middleware\SetLocale;
use App\Models\Betreuung;
use App\Models\User;
use App\Support\Einstellungen;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Paket D (Berufsbildner/Verwaltung): rendert die eigenen Seiten auf Englisch und prüft,
 * dass keine Übersetzung aus den eigenen Dateien fehlt (docs/i18n-plan.md).
 */
class BerufsbildnerEnglischTest extends TestCase
{
    /** @var array<string, true> */
    private array $fehlend = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-08 10:00:00');
        (new DemoSeeder)->run();
        Einstellungen::set(SetLocale::WAHL_AKTIV, '1');

        Lang::handleMissingKeysUsing(function (string $schluessel, array $ersetzungen, ?string $locale) {
            if ($locale === 'en') {
                $this->fehlend[$schluessel] = true;
            }

            return $schluessel;
        });
    }

    protected function tearDown(): void
    {
        Lang::handleMissingKeysUsing(null);
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function berufsbildner_seiten_auf_englisch(): void
    {
        $bb = User::where('email', 'michael.baumann@demo.example')->firstOrFail();
        $bb->update(['locale' => 'en']);

        $betreuung = Betreuung::query()
            ->whereHas('berufsbildner', fn ($q) => $q->where('benutzer_id', $bb->benutzer_id))
            ->whereNull('gueltig_bis')
            ->firstOrFail();
        $lernenderId = (int) $betreuung->lernender_id;

        $this->actingAs($bb);

        $this->get(route('trainer.dashboard'))
            ->assertOk()
            ->assertSee('<html lang="en"', false)
            ->assertSee('My apprentices')
            ->assertSee('Needs attention');

        $this->get(route('trainer.learners.index'))
            ->assertOk()
            ->assertSee('Add apprentice');

        $this->get(route('trainer.learners.show', $lernenderId).'?tab=overview')
            ->assertOk()
            ->assertSee('Overview');

        $this->get(route('trainer.learners.show', $lernenderId).'?tab=profil')
            ->assertOk()
            ->assertSee('Profile & supervision');

        $this->get(route('trainer.learners.grades.index', $lernenderId))
            ->assertOk()
            ->assertSee('Grades');

        $this->actingAs($bb)->get(route('feedback.index'))
            ->assertOk()
            ->assertSee('My feedback');

        $this->actingAs($bb)->get(route('notifications.settings'))
            ->assertOk()
            ->assertSee('Notifications');

        $this->assertNurEigeneSchluesselFehlen();
    }

    /** Nur Schlüssel aus Paket D (offen.php: trainer) dürfen (vorerst) fehlen; alle anderen sind ein Fehler. */
    private function assertNurEigeneSchluesselFehlen(): void
    {
        $verwendet = Schluessel::verwendet();
        $meineDateien = Schluessel::offen()['trainer'] ?? [];
        $verboten = [];

        foreach (array_keys($this->fehlend) as $schluessel) {
            $inMeinenDateien = array_filter(
                $verwendet[$schluessel] ?? [],
                fn (string $datei) => count(array_filter($meineDateien, fn (string $praefix) => str_starts_with($datei, $praefix))) > 0,
            );
            if ($inMeinenDateien !== []) {
                $verboten[] = "«{$schluessel}» (".implode(', ', $inMeinenDateien).')';
            }
        }

        $this->assertSame([], $verboten, "EN-Übersetzung fehlt in Paket-D-Dateien:\n".implode("\n", $verboten));
    }
}
