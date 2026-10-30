<?php

declare(strict_types=1);

namespace Tests\Feature\Lernender;

use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

/** Eine Modul-Note bleibt in ihrem Versuch; die offene Belegung rutscht nie vor einen abgeschlossenen Versuch. */
class ModulBelegungZuordnungTest extends TestCase
{
    use VerwaltungTestHilfen;

    private int $modul;

    private int $lehrberuf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $this->lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $this->modul = DB::table('module')->insertGetId(['modul_nummer' => '908', 'titel' => 'Testmodul Theta durchführen']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $this->lehrberuf, 'modul_id' => $this->modul,
            'kategorie_id' => DB::table('kategorien')->where('code', 'FACH')->value('kategorie_id')]);
        DB::table('semester')->insertGetId(['bezeichnung' => '25/26-2', 'start_datum' => '2026-02-01', 'end_datum' => '2026-07-31', 'sortierung' => 10]);
        Konfiguration::vergessen();
    }

    private function formular(string $datum, string $wert): array
    {
        return ['typ' => 'modul', 'modul_id' => $this->modul, 'pruefungsdatum' => $datum, 'note_wert' => $wert, 'gewichtung_prozent' => 100];
    }

    private function erfasse(string $datum, string $wert): void
    {
        $this->post(route('learner.grades.store'), $this->formular($datum, $wert))->assertSessionHasNoErrors();
    }

    #[Test]
    public function bearbeiten_einer_alten_note_nach_wiederholung_bleibt_im_alten_versuch(): void
    {
        $this->actingAs(User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create());
        $this->erfasse('2026-03-02', '3.0');
        $this->post(route('learner.grades.module.repeat', $this->modul));
        $alt = DB::table('noten')->first();

        $this->put(route('learner.grades.update', $alt->note_id), $this->formular('2026-03-02', '3.5'))->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('modul_belegungen')->count());
        $this->assertSame((int) $alt->modul_belegung_id, (int) DB::table('noten')->where('note_id', $alt->note_id)->value('modul_belegung_id'));
    }

    #[Test]
    public function verwaltung_bearbeitet_alte_note_ohne_neuen_versuch(): void
    {
        $user = User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create();
        $lernender = $user->lernender;
        $this->actingAs($user);
        $this->erfasse('2026-03-02', '3.0');
        $this->post(route('learner.grades.module.repeat', $this->modul));
        $alt = DB::table('noten')->first();

        $admin = $this->verwalter('admin', $lernender);
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs($admin)
            ->put(route('admin.learners.grades.update', [$lernender->lernender_id, $alt->note_id]), $this->formular('2026-03-02', '4.0'))
            ->assertSessionHasNoErrors();

        $this->assertEquals(4.0, DB::table('noten')->where('note_id', $alt->note_id)->value('note_wert'));

        $this->assertSame(1, DB::table('modul_belegungen')->count());
        $this->assertSame((int) $alt->modul_belegung_id, (int) DB::table('noten')->where('note_id', $alt->note_id)->value('modul_belegung_id'));
    }

    #[Test]
    public function wechsel_des_moduls_beim_bearbeiten_ordnet_der_offenen_belegung_des_neuen_moduls_zu(): void
    {
        $zweites = DB::table('module')->insertGetId(['modul_nummer' => '909', 'titel' => 'Testmodul Zeta']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $this->lehrberuf, 'modul_id' => $zweites,
            'kategorie_id' => DB::table('kategorien')->where('code', 'FACH')->value('kategorie_id')]);
        $this->actingAs(User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create());
        $this->erfasse('2026-03-02', '3.0');
        $note = DB::table('noten')->first();

        $this->put(route('learner.grades.update', $note->note_id), ['modul_id' => $zweites] + $this->formular('2026-03-02', '3.0'))->assertSessionHasNoErrors();

        $belegung = DB::table('modul_belegungen')->where('modul_belegung_id', DB::table('noten')->value('modul_belegung_id'))->first();
        $this->assertSame($zweites, (int) $belegung->modul_id);
    }

    #[Test]
    public function offene_belegung_rutscht_nie_vor_einen_abgeschlossenen_versuch(): void
    {
        $this->actingAs(User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create());
        $this->erfasse('2026-03-02', '3.0');
        $this->post(route('learner.grades.module.repeat', $this->modul));
        $this->erfasse('2026-06-01', '5.0');
        $this->erfasse('2026-02-10', '4.0');

        $offen = DB::table('modul_belegungen')->whereNull('end_datum')->first();
        $geschlossen = DB::table('modul_belegungen')->whereNotNull('end_datum')->first();
        $this->assertSame(2, DB::table('modul_belegungen')->count());
        $this->assertGreaterThanOrEqual((string) $geschlossen->end_datum, substr((string) $offen->start_datum, 0, 10));
    }

    #[Test]
    public function neue_offene_belegung_beginnt_nie_vor_dem_ende_des_alten_versuchs(): void
    {
        $this->actingAs(User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create());
        $this->erfasse('2026-03-02', '3.0');
        $this->post(route('learner.grades.module.repeat', $this->modul));
        $this->erfasse('2026-02-10', '4.0');

        $offen = DB::table('modul_belegungen')->whereNull('end_datum')->first();
        $geschlossen = DB::table('modul_belegungen')->whereNotNull('end_datum')->first();
        $this->assertSame(2, DB::table('modul_belegungen')->count());
        $this->assertGreaterThanOrEqual((string) $geschlossen->end_datum, substr((string) $offen->start_datum, 0, 10));
    }

    #[Test]
    public function leerer_typ_beim_bearbeiten_zeigt_die_bezugsmeldung_bei_lernenden_und_verwaltung(): void
    {
        $user = User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create();
        $lernender = $user->lernender;
        $this->actingAs($user);
        $this->erfasse('2026-03-02', '3.0');
        $noteId = (int) DB::table('noten')->value('note_id');
        $leer = ['typ' => '', 'pruefungsdatum' => '2026-03-02', 'note_wert' => '4.0', 'gewichtung_prozent' => 100];

        $this->from('/grades')->put(route('learner.grades.update', $noteId), $leer)
            ->assertSessionHasErrors(['typ' => 'Bitte ein Fach oder Modul wählen.']);

        $admin = $this->verwalter('admin', $lernender);
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs($admin)->from('/admin')
            ->put(route('admin.learners.grades.update', [$lernender->lernender_id, $noteId]), $leer)
            ->assertSessionHasErrors(['typ' => 'Bitte ein Fach oder Modul wählen.']);
        $this->actingAs($admin)->from('/admin')
            ->post(route('admin.learners.grades.store', $lernender->lernender_id), $leer)
            ->assertSessionHasErrors(['typ' => 'Bitte ein Fach oder Modul wählen.']);
    }

    #[Test]
    public function offene_belegung_ohne_abgeschlossenen_versuch_rutscht_weiter_auf_frueheres_datum(): void
    {
        $this->actingAs(User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create());
        $this->erfasse('2026-06-01', '5.0');
        $this->erfasse('2026-03-02', '4.0');

        $this->assertSame('2026-03-02', substr((string) DB::table('modul_belegungen')->value('start_datum'), 0, 10));
        $this->assertSame(1, DB::table('modul_belegungen')->count());
    }

    #[Test]
    public function modul_hinzufuegen_nach_wiederholung_legt_eine_offene_belegung_an(): void
    {
        $this->actingAs(User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create());
        $this->erfasse('2026-03-02', '3.0');
        $this->post(route('learner.grades.module.repeat', $this->modul));

        $this->post(route('modules.enroll', $this->modul))->assertSessionHas('success');

        $this->assertSame(1, DB::table('modul_belegungen')->whereNull('end_datum')->count());
        $this->assertSame(1, DB::table('modul_belegungen')->whereNotNull('end_datum')->count());
    }
}
