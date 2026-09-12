<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use App\Support\Einstellungen;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Rendert die Einstiegsseiten auf Englisch und sammelt jeden Schlüssel, für den keine
 * EN-Übersetzung existiert. Erlaubt sind nur Schlüssel aus offenen Bereichen (offen.php).
 */
class LaufzeitTest extends TestCase
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
    public function login_auf_englisch_fuer_gaeste_mit_englischem_browser(): void
    {
        $this->withHeader('Accept-Language', 'en-GB,en;q=0.9')->get('/login')
            ->assertOk()
            ->assertSee('<html lang="en"', false)
            ->assertSee('Forgotten your password?');

        $this->assertNurOffeneSchluesselFehlen();
    }

    #[Test]
    public function dashboards_der_drei_rollen_auf_englisch(): void
    {
        foreach (['laura.frei' => 'admin', 'michael.baumann' => 'trainer', 'nina.huber' => 'learner'] as $name => $rolle) {
            $benutzer = User::where('email', "{$name}@demo.example")->firstOrFail();
            $benutzer->update(['locale' => 'en']);

            $this->actingAs($benutzer)->get(route("{$rolle}.dashboard"))
                ->assertOk()
                ->assertSee('<html lang="en"', false)
                ->assertSee('window.npI18n', false)
                ->assertSee('Log out');

            $this->actingAs($benutzer)->get(route('settings.profile'))->assertOk()->assertSee('Save language');
        }

        $this->assertNurOffeneSchluesselFehlen();
    }

    private function assertNurOffeneSchluesselFehlen(): void
    {
        $verwendet = Schluessel::verwendet();
        $verboten = [];

        foreach (array_keys($this->fehlend) as $schluessel) {
            $dateien = array_filter(
                $verwendet[$schluessel] ?? [],
                fn (string $datei) => ! Schluessel::istOffen($datei) && ! str_starts_with($datei, 'resources/views/vendor/'),
            );
            if ($dateien !== []) {
                $verboten[] = "«{$schluessel}» (".implode(', ', $dateien).')';
            }
        }

        $this->assertSame([], $verboten, "EN-Übersetzung fehlt in geschlossenen Dateien:\n".implode("\n", $verboten));
    }
}
