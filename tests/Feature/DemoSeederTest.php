<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Betreuung;
use App\Models\Lernender;
use App\Models\Note;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (new DemoSeeder)->run();
    }

    #[Test]
    public function seedet_die_erwarteten_personen_und_rollen(): void
    {
        $this->assertSame(14, Lernender::count());
        $this->assertSame(3, DB::table('berufsbildner')->count());

        $this->assertSame(1, User::whereHas('rollen', fn ($q) => $q->where('name', 'Admin'))->count());
        $this->assertSame(3, User::whereHas('rollen', fn ($q) => $q->where('name', 'Berufsbildner'))->count());
        $this->assertSame(14, User::whereHas('rollen', fn ($q) => $q->where('name', 'Lernender'))->count());
    }

    #[Test]
    public function jede_lernende_person_hat_genau_eine_aktive_betreuung(): void
    {
        foreach (Lernender::query()->pluck('lernender_id') as $lernenderId) {
            $aktive = Betreuung::query()->aktiv()->where('lernender_id', $lernenderId)->count();
            $this->assertSame(1, $aktive, "Lernender {$lernenderId} hat nicht genau eine aktive Betreuung.");
        }
    }

    #[Test]
    public function es_werden_ausreichend_realistische_noten_erzeugt(): void
    {
        $this->assertGreaterThan(200, Note::count());
    }

    #[Test]
    public function keine_note_verletzt_die_fach_xor_modul_regel(): void
    {
        $verletzungen = DB::table('noten')
            ->where(function ($q) {
                $q->whereNull('fach_id')->whereNull('modul_belegung_id');
            })
            ->orWhere(function ($q) {
                $q->whereNotNull('fach_id')->whereNotNull('modul_belegung_id');
            })
            ->count();

        $this->assertSame(0, $verletzungen);
    }

    #[Test]
    public function es_gibt_lernende_mit_und_ohne_bms(): void
    {
        $lernenderIdsMitBms = DB::table('lernender_tracks')->where('track_typ', 'BMS')->pluck('lernender_id');
        $gibtOhneBms = Lernender::query()->whereNotIn('lernender_id', $lernenderIdsMitBms)->exists();

        $this->assertTrue($lernenderIdsMitBms->isNotEmpty(), 'Es sollte mindestens einen Lernenden mit BMS-Track geben.');
        $this->assertTrue($gibtOhneBms, 'Es sollte mindestens einen Lernenden ohne BMS-Track geben.');
    }

    #[Test]
    public function dashboards_und_notenliste_laden_fehlerfrei(): void
    {
        $lernenderUser = User::query()
            ->whereHas('rollen', fn ($q) => $q->where('name', 'Lernender'))
            ->where('email', 'like', '%@demo.example')
            ->firstOrFail();

        $berufsbildnerUser = User::query()
            ->whereHas('rollen', fn ($q) => $q->where('name', 'Berufsbildner'))
            ->where('email', 'like', '%@demo.example')
            ->firstOrFail();

        $adminUser = User::query()
            ->whereHas('rollen', fn ($q) => $q->where('name', 'Admin'))
            ->where('email', 'like', '%@demo.example')
            ->firstOrFail();

        $this->actingAs($lernenderUser)->get(route('learner.dashboard'))->assertOk();
        $this->actingAs($lernenderUser)->get(route('learner.grades.index'))->assertOk();
        $this->actingAs($berufsbildnerUser)->get(route('trainer.dashboard'))->assertOk();
        $this->actingAs($adminUser)->get(route('admin.dashboard'))->assertOk();
    }
}
