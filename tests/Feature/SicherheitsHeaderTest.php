<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\SicherheitsHeader;
use App\Models\User;
use App\Services\Betrieb\Sicherung;
use App\Services\Calendar\CalendarExport;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SicherheitsHeaderTest extends TestCase
{
    #[Test]
    public function login_seite_sendet_sicherheits_header(): void
    {
        $antwort = $this->get(route('login'))->assertOk();

        foreach (SicherheitsHeader::HEADER as $name => $wert) {
            $antwort->assertHeader($name, $wert);
        }
    }

    #[Test]
    public function angemeldete_seite_sendet_sicherheits_header(): void
    {
        $this->actingAs(User::factory()->lernender()->create())
            ->get(route('dashboard'))
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    #[Test]
    public function redirect_und_fehlerseiten_aus_exceptions_senden_sicherheits_header(): void
    {
        // Nicht angemeldet: AuthenticationException → 302 auf /login.
        $this->get(route('dashboard'))->assertRedirect(route('login'))
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        // Unbekannte Adresse: NotFoundHttpException → 404-Seite.
        $this->get('/gibt-es-nicht-'.uniqid())->assertNotFound()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    #[Test]
    public function vorhandene_header_der_antwort_werden_nicht_ueberschrieben(): void
    {
        $antwort = (new SicherheitsHeader)->handle(
            request(),
            fn () => response('x')->header('X-Frame-Options', 'DENY'),
        );

        $this->assertSame('DENY', $antwort->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $antwort->headers->get('X-Content-Type-Options'));
    }

    #[Test]
    public function angemeldete_seite_sendet_no_store(): void
    {
        $this->actingAs(User::factory()->lernender()->create())
            ->get(route('dashboard'))
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    #[Test]
    public function gast_erhaelt_kein_no_store(): void
    {
        $antwort = $this->get(route('login'))->assertOk();

        $this->assertNotSame('no-store, private', $antwort->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('no-store', (string) $antwort->headers->get('Cache-Control'));
    }

    #[Test]
    public function manifest_und_offline_bleiben_cachebar_auch_fuer_angemeldete(): void
    {
        $user = User::factory()->admin()->create();

        $manifest = $this->actingAs($user)->get(route('manifest'))->assertOk();
        $this->assertSame('max-age=86400, public', $manifest->headers->get('Cache-Control'));

        $offline = $this->actingAs($user)->get(route('offline'))->assertOk();
        $this->assertStringNotContainsString('no-store', (string) $offline->headers->get('Cache-Control'));
    }

    #[Test]
    public function ics_kalenderabo_bleibt_cachebar_auch_fuer_angemeldete(): void
    {
        $lernender = User::factory()->lernender()->create();
        $token = CalendarExport::token($lernender);

        $antwort = $this->actingAs($lernender)->get(route('calendar.export', ['token' => $token]))->assertOk();

        $this->assertSame('max-age=900, private', $antwort->headers->get('Cache-Control'));
    }

    #[Test]
    public function download_einer_sicherung_wird_nicht_mit_no_store_verschlechtert(): void
    {
        Storage::fake('local');
        Process::fake(function (PendingProcess $prozess) {
            foreach ((array) $prozess->command as $teil) {
                if (str_starts_with((string) $teil, '--result-file=')) {
                    file_put_contents(substr((string) $teil, 14), str_repeat("-- Dump\n", 30));
                }
            }

            return Process::result();
        });
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.operations.backups.store'));
        $name = app(Sicherung::class)->liste()[0]['name'];

        $antwort = $this->actingAs($admin)->get(route('admin.operations.backups.show', $name))->assertOk();

        $this->assertStringNotContainsString('no-store', (string) $antwort->headers->get('Cache-Control'));
    }

    #[Test]
    public function csv_export_wird_nicht_mit_no_store_verschlechtert(): void
    {
        $lernender = User::factory()->lernender()->create();

        $antwort = $this->actingAs($lernender)->get(route('learner.grades.export'))->assertOk();

        $this->assertStringNotContainsString('no-store', (string) $antwort->headers->get('Cache-Control'));
    }
}
