<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Einstellungen;
use App\Support\Theme;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Installierbare Web-App (Block AC): Manifest, Offline-Seite, Layout-Verknüpfung, Icons.
 * Der Service Worker selbst (public/sw.js) läuft im Browser und wird hier nicht ausgeführt.
 */
class PwaTest extends TestCase
{
    #[Test]
    public function manifest_ist_oeffentlich_erreichbar_und_enthaelt_die_pflichtfelder(): void
    {
        $antwort = $this->get('/manifest.webmanifest')->assertOk();
        $antwort->assertHeader('Content-Type', 'application/manifest+json');
        $antwort->assertHeader('Cache-Control', 'max-age=86400, public');

        $manifest = json_decode($antwort->getContent(), true);

        $this->assertSame('/dashboard', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertStringStartsWith('Notenportal', $manifest['name']);
        $this->assertSame('Notenportal', $manifest['short_name']);
        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $manifest['background_color']);
        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $manifest['theme_color']);
        $this->assertCount(3, $manifest['icons']);

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path($icon['src']));
            // Apache (Debian) belegt /icons/ global per Alias (mod_alias) – dort gäbe es 404,
            // der Service Worker würde beim Vorab-Cachen scheitern und nie aktiv.
            $this->assertStringStartsNotWith('/icons/', $icon['src']);
            $this->assertMatchesRegularExpression('/^\d+x\d+$/', $icon['sizes']);
            $this->assertSame('image/png', $icon['type']);
        }

        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));
    }

    #[Test]
    public function manifest_name_enthaelt_den_betriebsnamen(): void
    {
        Einstellungen::set(Einstellungen::BETRIEB_NAME, 'Muster AG');

        $manifest = json_decode($this->get('/manifest.webmanifest')->getContent(), true);

        $this->assertSame('Notenportal · Muster AG', $manifest['name']);
    }

    #[Test]
    public function manifest_farben_folgen_dem_betriebs_theme(): void
    {
        Einstellungen::set(Einstellungen::THEME, 'wald');

        $manifest = json_decode($this->get('/manifest.webmanifest')->getContent(), true);

        $this->assertSame(Theme::hintergrundHell('wald'), $manifest['background_color']);
        $this->assertSame(Theme::hintergrundHell('wald'), $manifest['theme_color']);
    }

    #[Test]
    public function offline_seite_ist_ohne_login_erreichbar_und_ohne_personendaten(): void
    {
        $lernender = User::factory()->lernender()->create(['vorname' => 'Anna', 'nachname' => 'Beispiel']);

        $antwort = $this->get(route('offline'))->assertOk();
        $antwort->assertDontSee($lernender->email);
        $antwort->assertDontSee('Anna');
        $antwort->assertDontSee('Beispiel');
        $antwort->assertSee(__('Keine Verbindung'));
    }

    #[Test]
    public function gast_layout_verweist_auf_manifest_und_theme_color(): void
    {
        $antwort = $this->get('/login')->assertOk();

        $antwort->assertSee('rel="manifest"', false);
        $antwort->assertSee(route('manifest'), false);
        $antwort->assertSee('name="theme-color"', false);
        $antwort->assertSee('apple-mobile-web-app-capable', false);
    }

    #[Test]
    public function app_layout_verweist_auf_manifest_und_theme_color(): void
    {
        $antwort = $this->actingAs(User::factory()->lernender()->create())
            ->get(route('learner.dashboard'))
            ->assertOk();

        $antwort->assertSee('rel="manifest"', false);
        $antwort->assertSee(route('manifest'), false);
        $antwort->assertSee('name="theme-color"', false);
    }

    #[Test]
    public function fehlerseite_verweist_ebenfalls_auf_das_manifest(): void
    {
        $antwort = $this->actingAs(User::factory()->lernender()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $antwort->assertSee('rel="manifest"', false);
    }

    #[Test]
    public function service_worker_datei_liegt_unter_public_und_beschraenkt_sich_auf_gebaute_assets(): void
    {
        $pfad = public_path('sw.js');

        $this->assertFileExists($pfad);
        $inhalt = file_get_contents($pfad);

        $this->assertStringContainsString('/build/', $inhalt);
        $this->assertStringContainsString('/offline', $inhalt);
        $this->assertStringContainsString('ABMELDEN', $inhalt);
        $this->assertStringNotContainsString('/api/', $inhalt);
    }
}
