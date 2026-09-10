<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Zustände, die früher nur per SQL herstellbar waren. */
class StammdatenLueckenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
    }

    #[Test]
    public function modulzuordnung_wird_zeilenweise_bearbeitet(): void
    {
        $kategorie = DB::table('kategorien')->pluck('kategorie_id', 'code');
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $modul = DB::table('module')->insertGetId(['modul_nummer' => '999', 'titel' => 'Testmodul']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $lehrberuf, 'modul_id' => $modul, 'kategorie_id' => $kategorie['FACH']]);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.stammdaten.lehrberufe.module.update', [$lehrberuf, $modul]), [
                'kategorie_id' => $kategorie['UEK'], 'pflicht' => '0', 'aktiv' => '0', 'empfohlenes_lehrsemester_nr' => '3',
            ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $zeile = DB::table('lehrberuf_module')->where('lehrberuf_id', $lehrberuf)->where('modul_id', $modul)->first();
        $this->assertSame([(int) $kategorie['UEK'], 0, 0, 3], [(int) $zeile->kategorie_id, (int) $zeile->pflicht, (int) $zeile->aktiv, (int) $zeile->empfohlenes_lehrsemester_nr]);
        $this->get(route('admin.stammdaten.lehrberufe.show', $lehrberuf))->assertOk()->assertSee('Testmodul');
    }

    #[Test]
    public function nur_leere_semester_lassen_sich_loeschen(): void
    {
        $leer = DB::table('semester')->insertGetId(['bezeichnung' => '90/91-1', 'start_datum' => '2090-08-01', 'end_datum' => '2091-01-31', 'sortierung' => 900]);
        $belegt = DB::table('semester')->insertGetId(['bezeichnung' => '91/92-1', 'start_datum' => '2091-08-01', 'end_datum' => '2092-01-31', 'sortierung' => 910]);
        $lernender = User::factory()->lernender()->create()->lernender;
        DB::table('lernender_tracks')->insert(['lernender_id' => $lernender->lernender_id, 'track_typ' => 'BMS', 'start_datum' => '2091-08-01', 'start_semester_id' => $belegt]);

        $this->actingAs(User::factory()->admin()->create());
        $this->delete(route('admin.stammdaten.semester.destroy', $leer))->assertSessionHas('success');
        $this->delete(route('admin.stammdaten.semester.destroy', $belegt))->assertSessionHas('error');

        $this->assertFalse(DB::table('semester')->where('semester_id', $leer)->exists());
        $this->assertTrue(DB::table('semester')->where('semester_id', $belegt)->exists());
    }

    #[Test]
    public function rollen_und_benutzername_sind_im_konto_aenderbar(): void
    {
        $admin = User::factory()->admin()->create();
        $bb = User::factory()->berufsbildner()->create();
        $felder = fn (User $u, array $rollen, ?string $benutzername = null) => [
            'vorname' => $u->vorname, 'nachname' => $u->nachname, 'email' => $u->email,
            'benutzername' => $benutzername ?? $u->benutzername, 'rollen' => $rollen,
        ];
        $this->actingAs($admin);

        $this->put(route('admin.benutzer.update', $bb->benutzer_id), $felder($bb, ['Berufsbildner', 'Admin'], 'neuername'))->assertSessionHasNoErrors();
        $bb = $bb->fresh();
        $this->assertTrue($bb->hasRole('Admin') && $bb->hasRole('Berufsbildner'));
        $this->assertSame('neuername', $bb->benutzername);

        $this->put(route('admin.benutzer.update', $admin->benutzer_id), $felder($admin, ['Berufsbildner']))->assertSessionHasErrors('rollen');

        $lernender = User::factory()->lernender()->create()->lernender;
        DB::table('betreuungen')->insert(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $lernender->lernender_id,
            'gueltig_von' => now()->subMonth()->toDateString()]);
        $this->put(route('admin.benutzer.update', $bb->benutzer_id), $felder($bb, ['Admin']))->assertSessionHasErrors('rollen');
        $this->assertTrue($bb->fresh()->hasRole('Berufsbildner'));
    }
}
