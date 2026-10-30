<?php

declare(strict_types=1);

namespace Tests\Feature\Lernender;

use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\NotenQuelle;
use App\Services\Noten\NoteService;
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
        $this->modul = DB::table('module')->insertGetId(['modul_nummer' => '908', 'titel' => 'Testmodul Theta durchführen']);
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
        $note = fn (string $datum, string $wert) => $this->post(route('learner.grades.store'), [
            'typ' => 'modul', 'modul_id' => $this->modul, 'pruefungsdatum' => $datum, 'note_wert' => $wert, 'gewichtung_prozent' => 100,
        ])->assertSessionHasNoErrors();

        $note('2026-03-02', '3.0');
        $this->post(route('learner.grades.module.resume', $this->modul))->assertNotFound();

        $this->post(route('learner.grades.module.repeat', $this->modul))->assertSessionHas('success');
        $this->assertSame('2026-03-02', (string) DB::table('modul_belegungen')->where('modul_id', $this->modul)->value('end_datum'));
        $this->post(route('learner.grades.module.resume', $this->modul))->assertSessionHas('success');
        $this->post(route('learner.grades.module.repeat', $this->modul))->assertSessionHas('success');

        $note('2026-06-01', '5.0');

        $lernenderId = (int) $user->lernender->lernender_id;
        $this->assertSame(2, DB::table('modul_belegungen')->where('lernender_id', $lernenderId)->count());
        $this->assertEquals(5.0, app(NotenQuelle::class)->auswertung($lernenderId)->elemente['m'.$this->modul]->note);
        $this->get(route('learner.grades.index', ['semester_id' => $this->semester]))->assertOk()->assertSee('2. Versuch');
    }

    #[Test]
    public function fremde_modulbelegung_bleibt_unberuehrt(): void
    {
        $eigentuemer = User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create();
        $this->actingAs($eigentuemer)->post(route('learner.grades.store'), [
            'typ' => 'modul', 'modul_id' => $this->modul, 'pruefungsdatum' => '2026-03-02', 'note_wert' => '4.5', 'gewichtung_prozent' => 100,
        ])->assertSessionHasNoErrors();

        $this->actingAs(User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create())
            ->post(route('learner.grades.module.repeat', $this->modul))
            ->assertNotFound();

        $this->assertNull(DB::table('modul_belegungen')->where('modul_id', $this->modul)->value('end_datum'));
    }

    #[Test]
    public function selbst_belegtes_fremdmodul_bleibt_nach_dem_wiederholen_nutzbar(): void
    {
        $fremd = DB::table('module')->insertGetId(['modul_nummer' => '777', 'titel' => 'Fremdmodul Omega']);
        $user = User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create();
        $this->actingAs($user);
        $daten = fn (string $datum, string $wert) => [
            'typ' => 'modul', 'modul_id' => $fremd, 'pruefungsdatum' => $datum, 'note_wert' => $wert, 'gewichtung_prozent' => 100,
        ];

        $this->post(route('modules.enroll', $fremd))->assertSessionHasNoErrors();
        $this->post(route('learner.grades.store'), $daten('2026-03-02', '3.0'))->assertSessionHasNoErrors();
        $erste = (int) DB::table('noten')->value('note_id');

        $this->post(route('learner.grades.module.repeat', $fremd))->assertSessionHas('success');

        // Die bisherige Note bleibt bearbeitbar ...
        $this->put(route('learner.grades.update', $erste), $daten('2026-03-02', '3.5'))->assertSessionHasNoErrors();
        // ... das Modul steht im Formular weiter zur Wahl ...
        $this->assertContains('modul:'.$fremd, collect(app(NoteService::class)->bezugOptionen((int) $user->lernender->lernender_id))->flatten(1)->pluck('wert')->all());
        // ... und der zweite Versuch lässt sich erfassen.
        $this->post(route('learner.grades.store'), $daten('2026-06-01', '5.0'))->assertSessionHasNoErrors();

        $lernenderId = (int) $user->lernender->lernender_id;
        $this->assertSame(2, DB::table('modul_belegungen')->where('lernender_id', $lernenderId)->where('modul_id', $fremd)->count());
        $this->assertSame(2, DB::table('noten')->count());
    }

    #[Test]
    public function fremdmodul_ohne_eigene_belegung_bleibt_abgelehnt(): void
    {
        $fremd = DB::table('module')->insertGetId(['modul_nummer' => '778', 'titel' => 'Fremdmodul Sigma']);
        $user = User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create();
        $andere = User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create();
        $this->actingAs($andere)->post(route('modules.enroll', $fremd))->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('learner.grades.store'), [
            'typ' => 'modul', 'modul_id' => $fremd, 'pruefungsdatum' => '2026-03-02', 'note_wert' => '4.0', 'gewichtung_prozent' => 100,
        ])->assertSessionHasErrors('modul_id');
        $this->assertSame(0, DB::table('noten')->count());
    }
}
