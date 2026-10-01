<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Lehrberuf;
use App\Models\Lernender;
use App\Models\Modul;
use App\Models\ModulBelegung;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Module und Fächer eines Lehrberufs zuweisen, ändern und entfernen (admin.master-data.professions.show). */
class LehrberufZuweisungTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private Lehrberuf $lehrberuf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->lehrberuf = Lehrberuf::factory()->create(['aktiv' => true]);
    }

    private function kategorie(string $code): int
    {
        return (int) Kategorie::where('code', $code)->value('kategorie_id');
    }

    private function zuweisen(Modul $modul, string $lernort = 'FACH', array $mehr = []): void
    {
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $this->lehrberuf->lehrberuf_id, 'modul_id' => $modul->modul_id,
            'kategorie_id' => $this->kategorie($lernort), 'pflicht' => 1, 'aktiv' => 1, ...$mehr,
        ]);
    }

    private function zuweisung(Modul $modul): ?object
    {
        return DB::table('lehrberuf_module')->where('lehrberuf_id', $this->lehrberuf->lehrberuf_id)->where('modul_id', $modul->modul_id)->first();
    }

    private function modulnote(Lernender $lernender, Modul $modul, string $lernort = 'FACH'): Note
    {
        $belegung = ModulBelegung::factory()->create(['lernender_id' => $lernender->lernender_id, 'modul_id' => $modul->modul_id]);

        return Note::factory()->create([
            'lernender_id' => $lernender->lernender_id, 'fach_id' => null, 'modul_belegung_id' => $belegung->modul_belegung_id,
            'kategorie_id' => $this->kategorie($lernort),
        ]);
    }

    private function seite(): string
    {
        return route('admin.master-data.professions.show', $this->lehrberuf->lehrberuf_id);
    }

    #[Test]
    public function ohne_haken_im_sheet_wird_das_modul_wahlpflicht(): void
    {
        // Das Sheet schickt «pflicht=0» vor dem Haken mit; früher fehlte das Feld und der Standard «Pflicht» griff
        $modul = Modul::factory()->create();

        $this->actingAs($this->admin)->from($this->seite())
            ->post(route('admin.master-data.professions.modules.assign', $this->lehrberuf->lehrberuf_id), [
                'modul_id' => $modul->modul_id, 'kategorie_id' => $this->kategorie('UEK'), 'pflicht' => '0',
            ])
            ->assertRedirect($this->seite())
            ->assertSessionHas('success');

        $this->assertSame(0, (int) $this->zuweisung($modul)->pflicht);
    }

    #[Test]
    public function fehler_beim_hinzufuegen_oeffnen_das_sheet_mit_meldung_beim_feld(): void
    {
        $this->actingAs($this->admin)->from($this->seite())
            ->post(route('admin.master-data.professions.modules.assign', $this->lehrberuf->lehrberuf_id), ['kategorie_id' => $this->kategorie('FACH')])
            ->assertRedirect($this->seite())
            ->assertSessionHasErrorsIn('modul', 'modul_id');

        Modul::factory()->create();
        $this->actingAs($this->admin)->get($this->seite())
            ->assertOk()
            ->assertSee('id="modul_id-fehler"', false)
            ->assertSee('show: true', false);
    }

    #[Test]
    public function ein_fehler_in_der_zeile_kommt_als_meldung_und_aendert_nichts(): void
    {
        $modul = Modul::factory()->create();
        $this->zuweisen($modul, mehr: ['empfohlenes_lehrsemester_nr' => 2]);

        $this->actingAs($this->admin)->from($this->seite())
            ->patch(route('admin.master-data.professions.modules.update', [$this->lehrberuf->lehrberuf_id, $modul->modul_id]), [
                'kategorie_id' => $this->kategorie('FACH'), 'empfohlenes_lehrsemester_nr' => '99',
            ])
            ->assertRedirect($this->seite())
            ->assertSessionHas('error')
            ->assertSessionHasNoErrors();

        $this->assertSame(2, (int) $this->zuweisung($modul)->empfohlenes_lehrsemester_nr);
    }

    #[Test]
    public function ein_neuer_lernort_nimmt_die_modulnoten_der_lernenden_dieses_berufs_mit(): void
    {
        $modul = Modul::factory()->create();
        $this->zuweisen($modul);
        $eigene = $this->modulnote(Lernender::factory()->create(['lehrberuf_id' => $this->lehrberuf->lehrberuf_id]), $modul);
        $fremde = $this->modulnote(Lernender::factory()->create(), $modul);

        $this->actingAs($this->admin)
            ->patch(route('admin.master-data.professions.modules.update', [$this->lehrberuf->lehrberuf_id, $modul->modul_id]), [
                'kategorie_id' => $this->kategorie('UEK'),
            ])
            ->assertSessionHas('success');

        $this->assertSame($this->kategorie('UEK'), (int) $eigene->fresh()->kategorie_id);
        $this->assertSame($this->kategorie('FACH'), (int) $fremde->fresh()->kategorie_id);
    }

    #[Test]
    public function ein_inaktiver_lernort_bleibt_in_seiner_zeile_waehlbar(): void
    {
        $modul = Modul::factory()->create();
        $alt = Kategorie::where('code', 'UEK')->firstOrFail();
        $this->zuweisen($modul, 'UEK');
        DB::table('kategorien')->where('kategorie_id', $alt->kategorie_id)->update(['aktiv' => 0]);

        $this->actingAs($this->admin)->get($this->seite())
            ->assertOk()
            ->assertSee(__(':name (inaktiv)', ['name' => $alt->name]));

        $this->actingAs($this->admin)
            ->patch(route('admin.master-data.professions.modules.update', [$this->lehrberuf->lehrberuf_id, $modul->modul_id]), [
                'kategorie_id' => $alt->kategorie_id, 'pflicht' => '0',
            ])
            ->assertSessionHas('success');

        $zeile = $this->zuweisung($modul);
        $this->assertSame([(int) $alt->kategorie_id, 0], [(int) $zeile->kategorie_id, (int) $zeile->pflicht]);
    }

    #[Test]
    public function ein_modul_mit_noten_bleibt_und_laesst_sich_nur_deaktivieren(): void
    {
        // Ein freies Modul davor: jede Zeile bekommt ihren eigenen Stand, nicht den der vorigen
        $frei = Modul::factory()->create(['modul_nummer' => '0100']);
        $modul = Modul::factory()->create(['modul_nummer' => '0200']);
        $this->zuweisen($frei);
        $this->zuweisen($modul);
        $this->modulnote(Lernender::factory()->create(['lehrberuf_id' => $this->lehrberuf->lehrberuf_id]), $modul);
        $entfernen = route('admin.master-data.professions.modules.remove', [$this->lehrberuf->lehrberuf_id, $modul->modul_id]);

        $this->actingAs($this->admin)->get($this->seite())
            ->assertOk()
            ->assertSee(__('Modul :nummer entfernen?', ['nummer' => $frei->modul_nummer]))
            ->assertSee($modul->titel)
            ->assertDontSee(__('Modul :nummer entfernen?', ['nummer' => $modul->modul_nummer]));

        $this->actingAs($this->admin)->delete($entfernen)->assertSessionHas('error');
        $this->assertNotNull($this->zuweisung($modul));
    }

    #[Test]
    public function ein_modul_ohne_noten_dieses_berufs_laesst_sich_entfernen(): void
    {
        // Noten im selben Modul aus einem anderen Beruf sperren nicht
        $modul = Modul::factory()->create();
        $this->zuweisen($modul);
        $this->modulnote(Lernender::factory()->create(), $modul);
        $entfernen = route('admin.master-data.professions.modules.remove', [$this->lehrberuf->lehrberuf_id, $modul->modul_id]);

        $this->actingAs($this->admin)->get($this->seite())->assertOk()->assertSee(__('Modul :nummer entfernen?', ['nummer' => $modul->modul_nummer]));
        $this->actingAs($this->admin)->delete($entfernen)->assertSessionHas('success');
        $this->assertNull($this->zuweisung($modul));
    }

    #[Test]
    public function die_freigabe_eines_fachs_laesst_sich_ausschalten(): void
    {
        $fach = Fach::factory()->create(['track_typ' => null]);
        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $this->lehrberuf->lehrberuf_id, 'fach_id' => $fach->fach_id, 'aktiv' => 1]);

        $this->actingAs($this->admin)
            ->patch(route('admin.master-data.professions.subjects.update', [$this->lehrberuf->lehrberuf_id, $fach->fach_id]), ['aktiv' => '0'])
            ->assertSessionHas('success');

        $this->assertSame(0, (int) DB::table('lehrberuf_faecher')->where('lehrberuf_id', $this->lehrberuf->lehrberuf_id)->where('fach_id', $fach->fach_id)->value('aktiv'));
        $this->actingAs($this->admin)->get($this->seite())->assertOk()->assertSee(__('Inaktiv'));
    }

    #[Test]
    public function ein_fach_mit_noten_dieses_berufs_bleibt_zugewiesen(): void
    {
        $fach = Fach::factory()->create(['track_typ' => null]);
        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $this->lehrberuf->lehrberuf_id, 'fach_id' => $fach->fach_id, 'aktiv' => 1]);
        Note::factory()->create([
            'lernender_id' => Lernender::factory()->create(['lehrberuf_id' => $this->lehrberuf->lehrberuf_id])->lernender_id,
            'fach_id' => $fach->fach_id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.master-data.professions.subjects.remove', [$this->lehrberuf->lehrberuf_id, $fach->fach_id]))
            ->assertSessionHas('error');

        $this->assertTrue(DB::table('lehrberuf_faecher')->where('lehrberuf_id', $this->lehrberuf->lehrberuf_id)->where('fach_id', $fach->fach_id)->exists());
    }

    #[Test]
    public function zuweisungen_an_einen_unbekannten_lehrberuf_enden_mit_404(): void
    {
        $modul = Modul::factory()->create();
        $fremd = (int) DB::table('lehrberufe')->max('lehrberuf_id') + 1;

        $this->actingAs($this->admin)
            ->post(route('admin.master-data.professions.modules.assign', $fremd), ['modul_id' => $modul->modul_id, 'kategorie_id' => $this->kategorie('FACH')])
            ->assertNotFound();
        $this->actingAs($this->admin)
            ->patch(route('admin.master-data.professions.modules.update', [$fremd, $modul->modul_id]), ['kategorie_id' => $this->kategorie('FACH')])
            ->assertNotFound();
    }

    #[Test]
    public function zeilen_speichern_ohne_inline_skript_und_inaktive_zeilen_ohne_transparenz(): void
    {
        $modul = Modul::factory()->create();
        $this->zuweisen($modul, mehr: ['aktiv' => 0]);

        $this->actingAs($this->admin)->get($this->seite())
            ->assertOk()
            ->assertSee('data-sofort', false)
            ->assertDontSee('onchange=', false)
            ->assertDontSee('opacity-60', false)
            ->assertSee(__('Inaktiv'));
    }
}
