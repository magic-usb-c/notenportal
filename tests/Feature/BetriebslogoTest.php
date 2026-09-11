<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Betrieb\Sicherung;
use App\Support\Betriebslogo;
use App\Support\Einstellungen;
use App\Support\Protokoll;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

/** Block AH: Betriebslogo (Upload, öffentliche Auslieferung, Anzeige, Sicherung). */
class BetriebslogoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function hochladen(User $admin, UploadedFile $datei)
    {
        return $this->actingAs($admin)->put(route('admin.operations.logo.update'), ['logo' => $datei]);
    }

    #[Test]
    public function gueltiges_png_wird_gespeichert_und_ausgeliefert(): void
    {
        $admin = User::factory()->admin()->create();

        $this->hochladen($admin, UploadedFile::fake()->image('logo.png', 40, 30))
            ->assertRedirect(route('admin.operations.edit'))
            ->assertSessionHas('success');

        $datei = Einstellungen::get(Einstellungen::LOGO_DATEI);
        $this->assertSame('logo.png', $datei);
        Storage::disk('local')->assertExists('betrieb/logo.png');
        $this->assertTrue(Betriebslogo::vorhanden());

        $this->get(route('branding.logo'))->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    #[Test]
    public function gueltiges_jpg_wird_gespeichert(): void
    {
        $admin = User::factory()->admin()->create();

        $this->hochladen($admin, UploadedFile::fake()->image('logo.jpg', 40, 30))
            ->assertRedirect(route('admin.operations.edit'));

        $this->assertSame('logo.jpg', Einstellungen::get(Einstellungen::LOGO_DATEI));
        Storage::disk('local')->assertExists('betrieb/logo.jpg');
        $this->get(route('branding.logo'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    #[Test]
    public function gueltiges_webp_wird_gespeichert(): void
    {
        $admin = User::factory()->admin()->create();

        $this->hochladen($admin, UploadedFile::fake()->image('logo.webp', 40, 30))
            ->assertRedirect(route('admin.operations.edit'));

        $this->assertSame('logo.webp', Einstellungen::get(Einstellungen::LOGO_DATEI));
        Storage::disk('local')->assertExists('betrieb/logo.webp');
        $this->get(route('branding.logo'))->assertOk()->assertHeader('Content-Type', 'image/webp');
    }

    #[Test]
    public function ein_neuer_upload_ersetzt_eine_datei_mit_anderer_endung(): void
    {
        $admin = User::factory()->admin()->create();

        $this->hochladen($admin, UploadedFile::fake()->image('logo.png', 40, 30));
        Storage::disk('local')->assertExists('betrieb/logo.png');

        $this->hochladen($admin, UploadedFile::fake()->image('logo.jpg', 40, 30));
        Storage::disk('local')->assertMissing('betrieb/logo.png');
        Storage::disk('local')->assertExists('betrieb/logo.jpg');
        $this->assertSame('logo.jpg', Einstellungen::get(Einstellungen::LOGO_DATEI));
    }

    #[Test]
    public function svg_wird_abgelehnt(): void
    {
        $admin = User::factory()->admin()->create();
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->hochladen($admin, $svg)->assertSessionHasErrors('logo');
        $this->assertFalse(Betriebslogo::vorhanden());
    }

    #[Test]
    public function als_bild_getarnte_html_datei_wird_abgelehnt(): void
    {
        $admin = User::factory()->admin()->create();
        $html = UploadedFile::fake()->createWithContent('logo.png', '<html><body><script>alert(1)</script></body></html>');

        $this->hochladen($admin, $html)->assertSessionHasErrors('logo');
        $this->assertFalse(Betriebslogo::vorhanden());
    }

    #[Test]
    public function als_png_getarnte_textdatei_wird_abgelehnt(): void
    {
        $admin = User::factory()->admin()->create();
        $text = UploadedFile::fake()->createWithContent('geheim.png', str_repeat('Das ist nur Text, kein Bild. ', 20));

        $this->hochladen($admin, $text)->assertSessionHasErrors('logo');
        $this->assertFalse(Betriebslogo::vorhanden());
    }

    #[Test]
    public function zu_grosse_datei_wird_abgelehnt(): void
    {
        $admin = User::factory()->admin()->create();
        $zuGross = UploadedFile::fake()->image('logo.png', 40, 30)->size(2048); // > 1024 KB

        $this->hochladen($admin, $zuGross)->assertSessionHasErrors('logo');
        $this->assertFalse(Betriebslogo::vorhanden());
    }

    #[Test]
    public function zu_viele_pixel_werden_abgelehnt(): void
    {
        $admin = User::factory()->admin()->create();
        $zuGross = UploadedFile::fake()->image('logo.png', 2000, 100); // Breite > 1024 px

        $this->hochladen($admin, $zuGross)->assertSessionHasErrors('logo');
        $this->assertFalse(Betriebslogo::vorhanden());
    }

    #[Test]
    public function ein_png_polyglot_mit_angehaengtem_skript_wird_beim_speichern_bereinigt(): void
    {
        $admin = User::factory()->admin()->create();
        $polyglot = UploadedFile::fake()->createWithContent('logo.png', $this->pngBytes().'<script>alert(document.cookie)</script>');

        $this->hochladen($admin, $polyglot)->assertRedirect(route('admin.operations.edit'));

        $this->assertSame('logo.png', Einstellungen::get(Einstellungen::LOGO_DATEI));
        $inhalt = Storage::disk('local')->get('betrieb/logo.png');
        $this->assertStringNotContainsString('<script>', $inhalt);
        $this->assertNotFalse(@getimagesizefromstring($inhalt));
    }

    #[Test]
    public function png_inhalt_unter_der_endung_jpg_wird_am_inhalt_erkannt_und_korrekt_gespeichert(): void
    {
        $admin = User::factory()->admin()->create();
        $getarnt = UploadedFile::fake()->createWithContent('geheim.jpg', $this->pngBytes());

        $this->hochladen($admin, $getarnt)->assertRedirect(route('admin.operations.edit'));

        $this->assertSame('logo.png', Einstellungen::get(Einstellungen::LOGO_DATEI));
        Storage::disk('local')->assertExists('betrieb/logo.png');
        Storage::disk('local')->assertMissing('betrieb/logo.jpg');
        $this->get(route('branding.logo'))->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    /** Echte, dekodierbare PNG-Bytes (für Polyglot-/Endungs-Tests, bei denen UploadedFile::fake()->image() nicht reicht). */
    private function pngBytes(): string
    {
        $bild = imagecreatetruecolor(40, 30);
        imagefill($bild, 0, 0, imagecolorallocate($bild, 10, 20, 30));
        ob_start();
        imagepng($bild);
        $bytes = ob_get_clean();
        imagedestroy($bild);

        return $bytes;
    }

    #[Test]
    public function nicht_admin_darf_kein_logo_hochladen(): void
    {
        $lernender = User::factory()->lernender()->create();

        $this->hochladen($lernender, UploadedFile::fake()->image('logo.png', 40, 30))
            ->assertForbidden();
        $this->assertFalse(Betriebslogo::vorhanden());
    }

    #[Test]
    public function gast_wird_beim_hochladen_zur_anmeldung_umgeleitet(): void
    {
        $this->actingAsGuest()
            ->put(route('admin.operations.logo.update'), ['logo' => UploadedFile::fake()->image('logo.png', 40, 30)])
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function route_liefert_nosniff_etag_und_304_bei_erneuter_anfrage(): void
    {
        $admin = User::factory()->admin()->create();
        $this->hochladen($admin, UploadedFile::fake()->image('logo.png', 40, 30));

        $antwort = $this->get(route('branding.logo'));
        $antwort->assertOk();
        $antwort->assertHeader('Content-Type', 'image/png');
        $antwort->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('public', (string) $antwort->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age', (string) $antwort->headers->get('Cache-Control'));

        $etag = $antwort->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $this->get(route('branding.logo'), ['If-None-Match' => $etag])->assertStatus(304);
    }

    #[Test]
    public function route_liefert_eigene_csp_content_disposition_und_langes_max_age(): void
    {
        $admin = User::factory()->admin()->create();
        $this->hochladen($admin, UploadedFile::fake()->image('logo.png', 40, 30));

        $antwort = $this->get(route('branding.logo'));
        $antwort->assertOk();
        $antwort->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
        $this->assertStringContainsString('inline', (string) $antwort->headers->get('Content-Disposition'));
        $this->assertStringContainsString('logo.png', (string) $antwort->headers->get('Content-Disposition'));
        $this->assertStringContainsString('max-age=86400', (string) $antwort->headers->get('Cache-Control'));
    }

    #[Test]
    public function route_setzt_keinen_cookie_header_und_startet_keine_session(): void
    {
        $admin = User::factory()->admin()->create();
        $this->hochladen($admin, UploadedFile::fake()->image('logo.png', 40, 30));

        foreach ([$this->get(route('branding.logo')), $this->actingAs($admin)->get(route('branding.logo'))] as $antwort) {
            $antwort->assertOk();
            $this->assertFalse($antwort->headers->has('Set-Cookie'), 'Set-Cookie sollte auf dieser Route fehlen.');
            $this->assertSame([], $antwort->headers->getCookies());
        }
    }

    #[Test]
    public function logo_url_traegt_ein_cache_busting_versionsmerkmal(): void
    {
        $admin = User::factory()->admin()->create();
        $this->hochladen($admin, UploadedFile::fake()->image('logo.png', 40, 30));

        $url = Betriebslogo::url();
        $this->assertNotNull($url);
        $this->assertMatchesRegularExpression('/[?&]v=\d+/', $url);
        $this->get($url)->assertOk();
    }

    #[Test]
    public function route_gibt_404_ohne_logo(): void
    {
        $this->get(route('branding.logo'))->assertNotFound();
    }

    #[Test]
    public function verwaiste_einstellung_faellt_still_auf_kein_logo_zurueck(): void
    {
        // Einstellung zeigt auf eine Datei, die es auf der Disk nicht (mehr) gibt.
        Einstellungen::set(Einstellungen::LOGO_DATEI, 'logo.png');
        $admin = User::factory()->admin()->create();

        $this->assertFalse(Betriebslogo::vorhanden());
        $this->get(route('branding.logo'))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('branding.logo'), false);
    }

    #[Test]
    public function ohne_logo_zeigt_die_navigation_den_platzhalter(): void
    {
        $admin = User::factory()->admin()->create();

        $antwort = $this->actingAs($admin)->get(route('admin.dashboard'));
        $antwort->assertOk();
        $antwort->assertSee('<svg', false);
        $antwort->assertDontSee(route('branding.logo'), false);
    }

    #[Test]
    public function mit_logo_zeigen_navigation_und_anmeldeseite_das_bild(): void
    {
        $admin = User::factory()->admin()->create();
        $this->hochladen($admin, UploadedFile::fake()->image('logo.png', 40, 30));

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee(route('branding.logo'), false);
        $this->actingAsGuest()->get('/login')->assertOk()->assertSee(route('branding.logo'), false);
    }

    #[Test]
    public function entfernen_loescht_datei_und_einstellung(): void
    {
        $admin = User::factory()->admin()->create();
        $this->hochladen($admin, UploadedFile::fake()->image('logo.png', 40, 30));
        Storage::disk('local')->assertExists('betrieb/logo.png');

        $this->actingAs($admin)->put(route('admin.operations.logo.update'), ['logo_entfernen' => '1'])
            ->assertRedirect(route('admin.operations.edit'))
            ->assertSessionHas('success');

        $this->assertNull(Einstellungen::get(Einstellungen::LOGO_DATEI));
        Storage::disk('local')->assertMissing('betrieb/logo.png');
        $this->assertFalse(Betriebslogo::vorhanden());
        $this->get(route('branding.logo'))->assertNotFound();
    }

    #[Test]
    public function speichern_und_entfernen_schreiben_je_einen_protokolleintrag(): void
    {
        $admin = User::factory()->admin()->create();
        $this->hochladen($admin, UploadedFile::fake()->image('logo.png', 40, 30));

        $this->assertDatabaseHas('aktivitaeten', [
            'benutzer_id' => $admin->benutzer_id,
            'aktion' => Protokoll::ADMIN_LOGO_GESPEICHERT,
        ]);

        $this->actingAs($admin)->put(route('admin.operations.logo.update'), ['logo_entfernen' => '1']);

        $this->assertDatabaseHas('aktivitaeten', [
            'benutzer_id' => $admin->benutzer_id,
            'aktion' => Protokoll::ADMIN_LOGO_ENTFERNT,
        ]);
    }

    #[Test]
    public function sicherung_enthaelt_das_logo(): void
    {
        $admin = User::factory()->admin()->create();
        $this->hochladen($admin, UploadedFile::fake()->image('logo.png', 40, 30));

        Process::fake(function (PendingProcess $prozess) {
            foreach ((array) $prozess->command as $teil) {
                if (str_starts_with((string) $teil, '--result-file=')) {
                    file_put_contents(substr((string) $teil, 14), str_repeat("-- Dump\n", 30));
                }
            }

            return Process::result();
        });

        $name = app(Sicherung::class)->erstellen();

        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path('sicherungen/'.$name));
        $this->assertNotFalse($zip->locateName('dateien/betrieb/logo.png'));
        $zip->close();
    }
}
