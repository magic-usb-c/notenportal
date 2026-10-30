<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PortalMail;
use App\Services\Betrieb\Sicherung;
use App\Services\Betrieb\SicherungLaeuftBereits;
use App\Services\Notifications\NotificationCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/** Belegte Sicherungssperre ist kein Fehlschlag: kein Pflichtmail «Sicherung fehlgeschlagen» an die Admins. */
class SicherungLaeuftBereitsTest extends TestCase
{
    #[Test]
    public function laeuft_schon_eine_sicherung_meldet_der_befehl_keinen_fehlschlag(): void
    {
        Notification::fake();
        Process::fake();
        $admin = User::factory()->admin()->create(['email' => 'admin@firma.ch']);

        $sperre = Cache::lock('notenportal-sicherung', 900);
        $this->assertTrue($sperre->get());

        try {
            $this->artisan('notenportal:sicherung')->assertSuccessful();
        } finally {
            $sperre->release();
        }

        Notification::assertNotSentTo($admin, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::BACKUP_FAILED);
        Process::assertNothingRan();
    }

    #[Test]
    public function die_belegte_sperre_bleibt_eine_runtime_exception_fuer_die_betriebsseite(): void
    {
        $sperre = Cache::lock('notenportal-sicherung', 900);
        $this->assertTrue($sperre->get());

        try {
            app(Sicherung::class)->erstellen();
            $this->fail('Erwartet: SicherungLaeuftBereits.');
        } catch (SicherungLaeuftBereits $e) {
            $this->assertInstanceOf(RuntimeException::class, $e);
            $this->assertSame('Es läuft bereits eine Sicherung.', $e->getMessage());
        } finally {
            $sperre->release();
        }
    }
}
