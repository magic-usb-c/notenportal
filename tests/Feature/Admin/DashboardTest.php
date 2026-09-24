<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\CalendarFeed;
use App\Models\Feedback;
use App\Models\Lernender;
use App\Models\MailLog;
use App\Models\User;
use App\Services\Betrieb\Sicherung;
use App\Support\Einstellungen;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new DemoSeeder)->run();
    }

    #[Test]
    public function zeigt_statuszeile_und_handlungsbedarf(): void
    {
        $admin = User::whereHas('rollen', fn ($q) => $q->where('name', 'Admin'))->firstOrFail();
        Feedback::factory()->create(['status' => Feedback::STATUS_OFFEN]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Handlungsbedarf');
        $response->assertSee((string) Lernender::count());
        $response->assertSee('offene Meldung', false);
    }

    #[Test]
    public function handlungsbedarf_zeigt_alte_sicherung_und_verschwindet_nach_frischer_sicherung(): void
    {
        $admin = User::whereHas('rollen', fn ($q) => $q->where('name', 'Admin'))->firstOrFail();

        $alt = $this->actingAs($admin)->get(route('admin.dashboard'));
        $alt->assertOk()->assertSee('Sicherung');

        Einstellungen::set(Sicherung::LETZTE, Carbon::now()->toIso8601String());

        $frisch = $this->actingAs($admin)->get(route('admin.dashboard'));
        $frisch->assertOk()->assertDontSee('Noch keine Sicherung erstellt');
    }

    #[Test]
    public function handlungsbedarf_zeigt_fehlgeschlagene_jobs_mit_link_zum_versandprotokoll(): void
    {
        $admin = User::whereHas('rollen', fn ($q) => $q->where('name', 'Admin'))->firstOrFail();
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
            'payload' => '{}', 'exception' => 'Fehler', 'failed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee(__('1 fehlgeschlagener Job'))
            ->assertSee(route('admin.mail-log.index'), false);
    }

    #[Test]
    public function handlungsbedarf_zeigt_fehlgeschlagene_mails_der_letzten_7_tage(): void
    {
        $admin = User::whereHas('rollen', fn ($q) => $q->where('name', 'Admin'))->firstOrFail();
        // MailLog::created_at ist absichtlich nicht in #[Fillable] (Model setzt es selbst) – für ein
        // gezieltes Testdatum direkt über die Tabelle einfügen statt über MailLog::create().
        DB::table('mail_log')->insert([
            'type' => 'test', 'recipient' => 'jemand@beispiel.local', 'subject' => 'Test',
            'status' => MailLog::FAILED, 'error' => 'SMTP-Fehler', 'created_at' => now()->subDays(2),
        ]);
        DB::table('mail_log')->insert([
            'type' => 'test', 'recipient' => 'alt@beispiel.local', 'subject' => 'Alt',
            'status' => MailLog::FAILED, 'error' => 'SMTP-Fehler', 'created_at' => now()->subDays(30),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()->assertSee(__('1 fehlgeschlagene Mail (7 Tage)'));
    }

    #[Test]
    public function handlungsbedarf_zeigt_kalenderabgleich_fehler_mit_link_zum_lernenden(): void
    {
        $admin = User::whereHas('rollen', fn ($q) => $q->where('name', 'Admin'))->firstOrFail();
        $lernender = User::factory()->lernender()->create(['vorname' => 'Lea', 'nachname' => 'Muster']);
        CalendarFeed::create([
            'lernender_id' => $lernender->lernender->lernender_id, 'url' => 'https://schulnetz.example/ical/geheim',
            'last_status' => CalendarFeed::ERROR, 'last_error' => 'Zeitüberschreitung',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee(__('1 Kalenderabgleich mit Fehler'))
            ->assertSee('Lea Muster')
            ->assertSee(route('admin.learners.show', $lernender->lernender->lernender_id), false);
    }

    #[Test]
    public function kalenderabgleich_fehler_mehrerer_lernender_verlinken_jede_person(): void
    {
        $admin = User::whereHas('rollen', fn ($q) => $q->where('name', 'Admin'))->firstOrFail();
        $lea = User::factory()->lernender()->create(['vorname' => 'Lea', 'nachname' => 'Muster']);
        $tim = User::factory()->lernender()->create(['vorname' => 'Tim', 'nachname' => 'Beispiel']);
        foreach ([$lea, $tim] as $user) {
            CalendarFeed::create([
                'lernender_id' => $user->lernender->lernender_id, 'url' => 'https://schulnetz.example/ical/'.$user->benutzer_id,
                'last_status' => CalendarFeed::ERROR, 'last_error' => 'Zeitüberschreitung',
            ]);
        }

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Lea Muster')->assertSee('Tim Beispiel')
            ->assertSee(route('admin.learners.show', $lea->lernender->lernender_id), false)
            ->assertSee(route('admin.learners.show', $tim->lernender->lernender_id), false);
    }
}
