<?php

declare(strict_types=1);

namespace Tests\Feature\Lernender;

use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\NotenQuelle;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModulWiederholenTest extends TestCase
{
    private int $modul;

    private int $lehrberuf;

    private int $semester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $this->lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $this->modul = DB::table('module')->insertGetId(['modul_nummer' => '431', 'titel' => 'Aufträge durchführen']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $this->lehrberuf, 'modul_id' => $this->modul,
            'kategorie_id' => DB::table('kategorien')->where('code', 'FACH')->value('kategorie_id')]);
        $this->semester = DB::table('semester')->insertGetId(['bezeichnung' => '25/26-2', 'start_datum' => '2026-02-01', 'end_datum' => '2026-07-31', 'sortierung' => 10]);
        Konfiguration::vergessen();
    }

    #[Test]
    public function wiederholtes_modul_zaehlt_nur_mit_dem_neuen_versuch(): void
    {
        $user = User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create();
        $this->actingAs($user);
        $note = fn (string $datum, string $wert) => $this->post(route('lernender.noten.store'), [
            'typ' => 'modul', 'modul_id' => $this->modul, 'pruefungsdatum' => $datum, 'note_wert' => $wert, 'gewichtung_prozent' => 100,
        ])->assertSessionHasNoErrors();

        $note('2026-03-02', '3.0');
        $this->post(route('lernender.noten.modul.fortsetzen', $this->modul))->assertNotFound();

        $this->post(route('lernender.noten.modul.wiederholen', $this->modul))->assertSessionHas('success');
        $this->assertSame('2026-03-02', (string) DB::table('modul_belegungen')->where('modul_id', $this->modul)->value('end_datum'));
        $this->post(route('lernender.noten.modul.fortsetzen', $this->modul))->assertSessionHas('success');
        $this->post(route('lernender.noten.modul.wiederholen', $this->modul))->assertSessionHas('success');

        $note('2026-06-01', '5.0');

        $lernenderId = (int) $user->lernender->lernender_id;
        $this->assertSame(2, DB::table('modul_belegungen')->where('lernender_id', $lernenderId)->count());
        $this->assertEquals(5.0, app(NotenQuelle::class)->auswertung($lernenderId)->elemente['m'.$this->modul]->note);
        $this->get(route('lernender.noten.index', ['semester_id' => $this->semester]))->assertOk()->assertSee('2. Versuch');
    }

    #[Test]
    public function fremde_modulbelegung_bleibt_unberuehrt(): void
    {
        $eigentuemer = User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create();
        $this->actingAs($eigentuemer)->post(route('lernender.noten.store'), [
            'typ' => 'modul', 'modul_id' => $this->modul, 'pruefungsdatum' => '2026-03-02', 'note_wert' => '4.5', 'gewichtung_prozent' => 100,
        ])->assertSessionHasNoErrors();

        $this->actingAs(User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create())
            ->post(route('lernender.noten.modul.wiederholen', $this->modul))
            ->assertNotFound();

        $this->assertNull(DB::table('modul_belegungen')->where('modul_id', $this->modul)->value('end_datum'));
    }
}
