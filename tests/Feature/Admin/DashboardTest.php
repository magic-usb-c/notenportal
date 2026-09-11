<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Feedback;
use App\Models\Lernender;
use App\Models\User;
use App\Services\Betrieb\Sicherung;
use App\Support\Einstellungen;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Carbon;
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
}
