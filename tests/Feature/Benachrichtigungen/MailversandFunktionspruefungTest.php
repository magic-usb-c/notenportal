<?php

declare(strict_types=1);

namespace Tests\Feature\Benachrichtigungen;

use App\Models\MailLog;
use App\Models\User;
use App\Notifications\PortalMail;
use App\Services\Notifications\MailContent;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Regressionstests der Funktionsprüfung, Gruppe «mailversand». */
class MailversandFunktionspruefungTest extends TestCase
{
    /** @return list<string> */
    private function aufgeloesteMiddleware(string $routenname): array
    {
        app(HttpKernel::class); // übernimmt die Middleware-Gruppen in den Router
        $route = app('router')->getRoutes()->getByName($routenname);
        $this->assertNotNull($route, "Route {$routenname} fehlt");

        return array_map(
            fn ($m) => is_string($m) ? $m : get_debug_type($m),
            app(Router::class)->resolveMiddleware($route->gatherMiddleware(), $route->excludedMiddleware()),
        );
    }

    private function fehlgeschlagen(string $empfaenger = 'gamma@beispielfirma.ch', ?User $user = null): MailLog
    {
        return MailLog::create([
            'user_id' => $user?->benutzer_id, 'type' => 'test', 'recipient' => $empfaenger,
            'subject' => 'Testmail', 'status' => MailLog::FAILED, 'error' => 'SMTP-Fehler',
            'payload' => (new MailContent(subject: 'Testmail'))->toArray(),
        ]);
    }

    #[Test]
    public function one_click_abbestellen_laeuft_ohne_csrf_pruefung_im_middleware_stack(): void
    {
        // PreventRequestForgery überspringt sich in Tests selbst – deshalb den aufgelösten Stack prüfen.
        // withoutMiddleware(ValidateCsrfToken::class) allein lässt die Elternklasse im Stack.
        $this->assertNotContains(PreventRequestForgery::class, $this->aufgeloesteMiddleware('notifications.unsubscribe.store'));
        // Gegenprobe: eine gewöhnliche POST-Route der web-Gruppe behält den Schutz.
        $this->assertContains(PreventRequestForgery::class, $this->aufgeloesteMiddleware('admin.mail.test'));
    }

    #[Test]
    public function erneut_senden_an_testadresse_meldet_fehler_und_legt_keine_zeile_an(): void
    {
        $admin = User::factory()->admin()->create();
        $log = $this->fehlgeschlagen('jemand@beispiel.local');

        $this->actingAs($admin)->post(route('admin.mail-log.retry', $log->id))
            ->assertRedirect(route('admin.mail-log.index'))
            ->assertSessionHas('error')
            ->assertSessionMissing('success');

        $this->assertSame(1, MailLog::count());
        $this->assertSame(MailLog::FAILED, $log->fresh()->status);
    }

    #[Test]
    public function erneut_senden_ist_nur_einmal_moeglich(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $log = $this->fehlgeschlagen();

        $this->actingAs($admin)->post(route('admin.mail-log.retry', $log->id))->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.mail-log.retry', $log->id))->assertSessionHas('error');

        Notification::assertSentOnDemandTimes(PortalMail::class, 1);
        $this->assertSame(2, MailLog::count());
        $this->assertSame(MailLog::RETRIED, $log->fresh()->status);
    }

    #[Test]
    public function deaktiviertes_konto_laesst_die_zeile_erneut_sendbar(): void
    {
        $admin = User::factory()->admin()->create();
        $weg = User::factory()->lernender()->create(['aktiv' => false]);
        $log = $this->fehlgeschlagen($weg->email, $weg);

        $this->actingAs($admin)->post(route('admin.mail-log.retry', $log->id))->assertSessionHas('error');

        $this->assertSame(MailLog::FAILED, $log->fresh()->status);
    }

    #[Test]
    public function erneut_senden_raeumt_den_fehlgeschlagenen_job_der_alten_zeile_ab(): void
    {
        config(['queue.default' => 'database']);
        $admin = User::factory()->admin()->create();
        $user = User::factory()->lernender()->create(['email' => 'l@beispielfirma.ch']);
        $alt = $this->fehlgeschlagen($user->email, $user);
        $fremd = $this->fehlgeschlagen('fremd@beispielfirma.ch');

        // Je ein fehlgeschlagener Job zur alten und zu einer fremden Zeile (echte Queue-Payloads)
        foreach ([$alt, $fremd] as $zeile) {
            DB::table('jobs')->delete();
            $user->notify(new PortalMail('test', new MailContent(subject: 'Testmail'), $zeile->id));
            DB::table('failed_jobs')->insert([
                'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
                'payload' => DB::table('jobs')->value('payload'), 'exception' => 'Verbindung abgelehnt', 'failed_at' => now(),
            ]);
        }
        DB::table('jobs')->delete();
        $this->assertSame(2, DB::table('failed_jobs')->count());

        $this->actingAs($admin)->post(route('admin.mail-log.retry', $alt->id))->assertSessionHas('success');

        $this->assertSame(1, DB::table('failed_jobs')->count(), 'nur der Job der alten Zeile darf verschwinden');
        $this->assertStringContainsString('"logId\\";i:'.$fremd->id.';', (string) DB::table('failed_jobs')->value('payload'));
    }

    #[Test]
    public function warteschlangen_kennzahl_zaehlt_alte_fehlgeschlagene_jobs_nicht(): void
    {
        $admin = User::factory()->admin()->create();
        $this->fehlgeschlagen();
        foreach ([now()->subDays(30), now()->subDay()] as $wann) {
            DB::table('failed_jobs')->insert([
                'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
                'payload' => '{}', 'exception' => 'x', 'failed_at' => $wann,
            ]);
        }

        $kennzahlen = $this->actingAs($admin)->get(route('admin.mail-log.index'))->assertOk()->viewData('kennzahlen');

        $this->assertSame(1, $kennzahlen['warteschlange']);
    }

    #[Test]
    public function testmail_ohne_mailserver_meldet_keinen_erfolg(): void
    {
        $admin = User::factory()->admin()->create();
        config(['mail.default' => 'log']);

        $this->actingAs($admin)->post(route('admin.mail.test'), ['test_to' => 'chef@beispielfirma.ch'])
            ->assertRedirect(route('admin.operations.edit'))
            ->assertSessionHas('error')
            ->assertSessionMissing('success');

        $this->assertSame(0, MailLog::where('status', MailLog::SENT)->count());
    }
}
