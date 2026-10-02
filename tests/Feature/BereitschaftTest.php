<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CalendarFeed;
use App\Models\MailLog;
use App\Models\User;
use App\Services\Betrieb\Sicherung;
use App\Services\Betrieb\SicherungKopie;
use App\Services\Notifications\MailSettings;
use App\Support\Bereitschaft;
use App\Support\Einstellungen;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `php artisan notenportal:bereitschaft` (App\Support\Bereitschaft): rein lesende
 * Betriebsbereitschaftsprüfung vor dem Go-Live. Jeder Prüfpunkt einzeln rot (Ausgangslage
 * bzw. bewusst verschlechtert) und grün, dazu Exit-Code, --json und dass keine Geheimniswerte
 * (Passwörter, Mailziele, URLs) in der Ausgabe stehen.
 */
class BereitschaftTest extends TestCase
{
    private function statusVon(array $pruefungen, string $schluessel): string
    {
        foreach ($pruefungen as $p) {
            if ($p['schluessel'] === $schluessel) {
                return $p['status'];
            }
        }

        $this->fail("Prüfpunkt «{$schluessel}» fehlt.");
    }

    #[Test]
    public function app_env(): void
    {
        config(['app.env' => 'local']);
        $this->assertSame(Bereitschaft::FEHLER, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'app_env'));

        config(['app.env' => 'production']);
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'app_env'));
    }

    #[Test]
    public function app_debug(): void
    {
        config(['app.debug' => true]);
        $this->assertSame(Bereitschaft::FEHLER, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'app_debug'));

        config(['app.debug' => false]);
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'app_debug'));
    }

    #[Test]
    public function app_url(): void
    {
        config(['app.url' => 'http://172.26.14.101']);
        $this->assertSame(Bereitschaft::WARNUNG, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'app_url'));

        config(['app.url' => 'https://portal.example.ch']);
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'app_url'));
    }

    #[Test]
    public function trusted_hosts(): void
    {
        config(['app.trusted_hosts' => ' , ']);
        $this->assertSame(Bereitschaft::WARNUNG, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'trusted_hosts'));

        config(['app.trusted_hosts' => '172.26.14.100,srv-lab-dva-003']);
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'trusted_hosts'));
    }

    #[Test]
    public function session_secure(): void
    {
        config(['session.secure' => null]);
        $this->assertSame(Bereitschaft::WARNUNG, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'session_secure'));

        config(['session.secure' => true]);
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'session_secure'));
    }

    #[Test]
    public function mail_redirect(): void
    {
        Einstellungen::set(MailSettings::REDIRECT_TO, 'test@example.local');
        $this->assertSame(Bereitschaft::WARNUNG, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'mail_redirect'));

        Einstellungen::set(MailSettings::REDIRECT_TO, null);
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'mail_redirect'));
    }

    #[Test]
    public function kopie_ausser_haus(): void
    {
        $this->assertSame(Bereitschaft::WARNUNG, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'kopie_ausser_haus'));

        Einstellungen::set(SicherungKopie::ZIEL, 'ordner');
        Einstellungen::set(SicherungKopie::PFAD, '/var/backups/notenportal');
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'kopie_ausser_haus'));
    }

    #[Test]
    public function sicherung(): void
    {
        $this->assertSame(Bereitschaft::FEHLER, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'sicherung'));

        Einstellungen::set(Sicherung::LETZTE, now()->subDays(3)->toIso8601String());
        $this->assertSame(Bereitschaft::FEHLER, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'sicherung'));

        Einstellungen::set(Sicherung::LETZTE, now()->subHours(2)->toIso8601String());
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'sicherung'));
    }

    #[Test]
    public function failed_jobs(): void
    {
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'failed_jobs'));

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
            'payload' => '{}', 'exception' => 'Fehler', 'failed_at' => now(),
        ]);
        $this->assertSame(Bereitschaft::FEHLER, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'failed_jobs'));
    }

    #[Test]
    public function fehlgeschlagene_mails(): void
    {
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'mails_fehlgeschlagen'));

        // MailLog::created_at ist absichtlich nicht in #[Fillable] – für ein gezieltes Testdatum
        // direkt über die Tabelle einfügen statt über MailLog::create().
        DB::table('mail_log')->insert([
            'type' => 'test', 'recipient' => 'jemand@beispiel.local', 'subject' => 'Test',
            'status' => MailLog::FAILED, 'error' => 'SMTP-Fehler', 'created_at' => now()->subDays(2),
        ]);
        $this->assertSame(Bereitschaft::WARNUNG, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'mails_fehlgeschlagen'));
    }

    #[Test]
    public function kalenderabgleich(): void
    {
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'kalenderabgleich'));

        $lernender = User::factory()->lernender()->create();
        CalendarFeed::create([
            'lernender_id' => $lernender->lernender->lernender_id, 'url' => 'https://schulnetz.example/ical/geheim',
            'last_status' => CalendarFeed::ERROR, 'last_error' => 'Zeitüberschreitung',
        ]);
        $this->assertSame(Bereitschaft::WARNUNG, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'kalenderabgleich'));
    }

    #[Test]
    public function offene_migrationen(): void
    {
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'migrationen'));

        $letzte = DB::table('migrations')->orderByDesc('id')->first();
        DB::table('migrations')->where('id', $letzte->id)->delete();

        $this->assertSame(Bereitschaft::FEHLER, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'migrationen'));
    }

    #[Test]
    public function exit_code_folgt_dem_schlimmsten_status(): void
    {
        config(['app.env' => 'production', 'app.debug' => false]);
        Einstellungen::set(Sicherung::LETZTE, now()->toIso8601String());
        $this->assertTrue(app(Bereitschaft::class)->bereit());
        $this->artisan('notenportal:bereitschaft')->assertSuccessful();

        config(['app.env' => 'local']);
        $this->assertFalse(app(Bereitschaft::class)->bereit());
        $this->artisan('notenportal:bereitschaft')->assertFailed();
    }

    #[Test]
    public function json_ausgabe_enthaelt_alle_pruefpunkte(): void
    {
        Artisan::call('notenportal:bereitschaft', ['--json' => true]);
        $ausgabe = json_decode(Artisan::output(), true);

        $this->assertArrayHasKey('bereit', $ausgabe);
        $this->assertCount(12, $ausgabe['pruefungen']);
        foreach ($ausgabe['pruefungen'] as $p) {
            $this->assertContains($p['status'], [Bereitschaft::OK, Bereitschaft::WARNUNG, Bereitschaft::FEHLER]);
        }
    }

    #[Test]
    public function ausgabe_enthaelt_keine_geheimniswerte(): void
    {
        Einstellungen::set(MailSettings::REDIRECT_TO, 'geheim@beispielfirma.ch');
        Einstellungen::set(SicherungKopie::ZIEL, 'ssh');
        Einstellungen::set(SicherungKopie::HOST, 'backup.beispielfirma.ch');
        Einstellungen::set(SicherungKopie::PFAD, '/geheim/pfad');

        Artisan::call('notenportal:bereitschaft', ['--json' => true]);
        $ausgabe = Artisan::output();

        $this->assertStringNotContainsString('geheim@beispielfirma.ch', $ausgabe);
        $this->assertStringNotContainsString('backup.beispielfirma.ch', $ausgabe);
        $this->assertStringNotContainsString('/geheim/pfad', $ausgabe);

        $passwort = (string) config('database.connections.'.config('database.default').'.password');
        if ($passwort !== '') {
            $this->assertStringNotContainsString($passwort, $ausgabe);
        }
    }
}
