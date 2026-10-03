<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Lehrberuf;
use App\Models\Modul;
use App\Models\Pruefung;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Stammdaten-CRUD, das nicht schon anderswo (StammdatenNotenlogikTest, StammdatenLueckenTest) abgedeckt ist. */
class StammdatenCrudTest extends TestCase
{
    #[Test]
    public function lehrberuf_wird_angelegt_und_dubletten_abgewiesen(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.master-data.professions.store'), [
            'kuerzel' => 'itf', 'name' => 'Informatiker/in EFZ',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.master-data.professions.index'));

        $lehrberuf = DB::table('lehrberufe')->where('name', 'Informatiker/in EFZ')->sole();
        $this->assertSame('ITF', $lehrberuf->kuerzel);

        $this->actingAs($admin)->post(route('admin.master-data.professions.store'), [
            'kuerzel' => 'itf2', 'name' => 'Informatiker/in EFZ',
        ])->assertSessionHasErrors('name');
    }

    #[Test]
    public function lehrberuf_wird_aktualisiert(): void
    {
        $admin = User::factory()->admin()->create();
        $lehrberuf = Lehrberuf::factory()->create(['kuerzel' => 'ALT', 'name' => 'Alter Beruf']);

        $this->actingAs($admin)->put(route('admin.master-data.professions.update', $lehrberuf->lehrberuf_id), [
            'kuerzel' => 'neu', 'name' => 'Neuer Beruf', 'aktiv' => 0,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.master-data.professions.index'));

        $lehrberuf->refresh();
        $this->assertSame(['NEU', 'Neuer Beruf', false], [$lehrberuf->kuerzel, $lehrberuf->name, $lehrberuf->aktiv]);
    }

    #[Test]
    public function die_modulliste_nimmt_gefiltert_die_version_des_lehrberufs(): void
    {
        // Das Modul selbst führt V5, der gewählte Lehrberuf steht laut Katalog noch auf V4.
        // Ist nach ihm gefiltert, ist seine Version eindeutig – der Verweis muss ihr folgen.
        $admin = User::factory()->admin()->create();
        $lehrberuf = Lehrberuf::factory()->create();
        $modul = Modul::factory()->create(['modul_nummer' => '905', 'version' => '5']);
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $lehrberuf->lehrberuf_id, 'modul_id' => $modul->modul_id,
            'kategorie_id' => Kategorie::where('code', 'FACH')->value('kategorie_id'), 'version' => '4',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.master-data.modules.index', ['lehrberuf_id' => $lehrberuf->lehrberuf_id]))
            ->assertOk()
            ->assertSee('modulbaukasten.ch/module/905/4/de-DE', false)
            ->assertDontSee('modulbaukasten.ch/module/905/5/de-DE', false);

        // Ohne Filter fasst die Liste mehrere Lehrberufe je Modul zusammen; dann bleibt es bei der
        // Version am Modul, weil keine Zuordnung eindeutig massgebend ist.
        $this->actingAs($admin)
            ->get(route('admin.master-data.modules.index'))
            ->assertOk()
            ->assertSee('modulbaukasten.ch/module/905/5/de-DE', false);
    }

    #[Test]
    public function modul_wird_vom_lehrberuf_entfernt(): void
    {
        $admin = User::factory()->admin()->create();
        $lehrberuf = Lehrberuf::factory()->create();
        $modul = Modul::factory()->create();
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $lehrberuf->lehrberuf_id, 'modul_id' => $modul->modul_id,
            'kategorie_id' => Kategorie::where('code', 'FACH')->value('kategorie_id'),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.master-data.professions.modules.remove', [$lehrberuf->lehrberuf_id, $modul->modul_id]))
            ->assertSessionHas('success');

        $this->assertFalse(DB::table('lehrberuf_module')->where('lehrberuf_id', $lehrberuf->lehrberuf_id)->where('modul_id', $modul->modul_id)->exists());
    }

    #[Test]
    public function fach_ohne_track_wird_zugewiesen_und_wieder_entfernt(): void
    {
        $admin = User::factory()->admin()->create();
        $lehrberuf = Lehrberuf::factory()->create();
        $fach = Fach::factory()->create(['track_typ' => null]);

        $this->actingAs($admin)
            ->post(route('admin.master-data.professions.subjects.assign', $lehrberuf->lehrberuf_id), ['fach_id' => $fach->fach_id])
            ->assertSessionHas('success');
        $this->assertTrue(DB::table('lehrberuf_faecher')->where('lehrberuf_id', $lehrberuf->lehrberuf_id)->where('fach_id', $fach->fach_id)->exists());

        $this->actingAs($admin)
            ->delete(route('admin.master-data.professions.subjects.remove', [$lehrberuf->lehrberuf_id, $fach->fach_id]))
            ->assertSessionHas('success');
        $this->assertFalse(DB::table('lehrberuf_faecher')->where('lehrberuf_id', $lehrberuf->lehrberuf_id)->where('fach_id', $fach->fach_id)->exists());
    }

    #[Test]
    public function fach_mit_track_kann_nicht_direkt_zugewiesen_werden(): void
    {
        // Track-Fächer werden über den Lernenden-Track freigegeben, nicht hier (siehe StammdatenLehrberufeController::assignFach).
        $admin = User::factory()->admin()->create();
        $lehrberuf = Lehrberuf::factory()->create();
        $fach = Fach::factory()->create(['track_typ' => 'BMS']);

        $this->actingAs($admin)
            ->post(route('admin.master-data.professions.subjects.assign', $lehrberuf->lehrberuf_id), ['fach_id' => $fach->fach_id])
            ->assertSessionHasErrorsIn('fach', 'fach_id');
    }

    #[Test]
    public function modul_wird_angelegt_und_dubletten_abgewiesen(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.master-data.modules.store'), [
            'modul_nummer' => 'm500', 'titel' => 'Testmodul 500',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.master-data.modules.index'));

        $modul = DB::table('module')->where('titel', 'Testmodul 500')->sole();
        $this->assertSame('M500', $modul->modul_nummer);

        $this->actingAs($admin)->post(route('admin.master-data.modules.store'), [
            'modul_nummer' => 'm500', 'titel' => 'Anderer Titel',
        ])->assertSessionHasErrors('modul_nummer');
    }

    #[Test]
    public function modul_wird_aktualisiert(): void
    {
        $admin = User::factory()->admin()->create();
        $modul = Modul::factory()->create(['modul_nummer' => '111', 'titel' => 'Alt']);

        $this->actingAs($admin)->put(route('admin.master-data.modules.update', $modul->modul_id), [
            'modul_nummer' => '222', 'titel' => 'Neu', 'aktiv' => 0,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.master-data.modules.index'));

        $modul->refresh();
        $this->assertSame(['222', 'Neu', 0], [$modul->modul_nummer, $modul->titel, (int) $modul->aktiv]);
    }

    #[Test]
    public function semester_wird_angelegt_und_ueberlappung_abgewiesen(): void
    {
        $admin = User::factory()->admin()->create();
        Semester::factory()->create(['bezeichnung' => 'S1', 'start_datum' => '2030-08-01', 'end_datum' => '2031-01-31']);

        $this->actingAs($admin)->post(route('admin.master-data.semesters.store'), [
            'bezeichnung' => 'S2', 'start_datum' => '2031-02-01', 'end_datum' => '2031-07-31',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.master-data.semesters.index'));
        $this->assertTrue(DB::table('semester')->where('bezeichnung', 'S2')->exists());

        $this->actingAs($admin)->post(route('admin.master-data.semesters.store'), [
            'bezeichnung' => 'S3', 'start_datum' => '2030-09-01', 'end_datum' => '2030-12-01',
        ])->assertSessionHasErrors('start_datum');
        $this->assertFalse(DB::table('semester')->where('bezeichnung', 'S3')->exists());
    }

    #[Test]
    public function semester_wird_aktualisiert(): void
    {
        $admin = User::factory()->admin()->create();
        $semester = Semester::factory()->create(['bezeichnung' => 'Alt']);

        $this->actingAs($admin)->put(route('admin.master-data.semesters.update', $semester->semester_id), [
            'bezeichnung' => 'Neu',
            'start_datum' => $semester->start_datum->toDateString(),
            'end_datum' => $semester->end_datum->toDateString(),
            'sortierung' => 5,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.master-data.semesters.index'));

        $this->assertSame('Neu', DB::table('semester')->where('semester_id', $semester->semester_id)->value('bezeichnung'));
    }

    #[Test]
    public function fach_wird_aktualisiert(): void
    {
        $admin = User::factory()->admin()->create();
        $fach = Fach::factory()->create(['name' => 'Alt', 'kurzname' => 'AL']);
        $uek = Kategorie::where('code', 'UEK')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.master-data.subjects.update', $fach->fach_id), [
            'name' => 'Neu', 'kurzname' => 'ne', 'kategorie_id' => $uek->kategorie_id, 'track_typ' => '',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.master-data.subjects.index'));

        $fach->refresh();
        $this->assertSame(['Neu', 'NE', $uek->kategorie_id], [$fach->name, $fach->kurzname, $fach->kategorie_id]);
    }

    #[Test]
    public function kategorie_wird_angelegt_und_dublette_abgewiesen(): void
    {
        $admin = User::factory()->admin()->create();
        $felder = ['rundung_element' => '0.25', 'rundung_schnitt' => '0.1', 'gewicht_gesamt' => '1'];

        $this->actingAs($admin)->post(route('admin.master-data.categories.store'), [
            'code' => 'ZUSATZ', 'name' => 'Zusatzkategorie', 'sortierung' => 50, ...$felder,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.master-data.categories.index'));
        $this->assertTrue(DB::table('kategorien')->where('code', 'ZUSATZ')->exists());

        $this->actingAs($admin)->post(route('admin.master-data.categories.store'), [
            'code' => 'ZUSATZ', 'name' => 'Andere', 'sortierung' => 51, ...$felder,
        ])->assertSessionHasErrors('code');
    }

    #[Test]
    public function fach_mit_pruefung_zeigt_loeschsperre_und_wird_nicht_geloescht(): void
    {
        $admin = User::factory()->admin()->create();
        $fach = Fach::factory()->create();
        $lernender = User::factory()->lernender()->create()->lernender;
        Pruefung::create([
            'lernender_id' => $lernender->lernender_id, 'fach_id' => $fach->fach_id, 'titel' => 'LB1',
            'datum' => now()->addDays(5)->toDateString(), 'gewichtung_prozent' => 20, 'quelle' => Pruefung::MANUELL,
        ]);

        // Hinweis und Sperre im Formular folgen derselben Regel wie destroy(): auch ohne Note ist das Fach in Gebrauch.
        $this->actingAs($admin)->get(route('admin.master-data.subjects.edit', $fach->fach_id))->assertOk()
            ->assertSee('Das Fach hat bereits Noten oder Prüfungen.')
            ->assertDontSee('value="DELETE"', false);

        $frei = Fach::factory()->create();
        $this->actingAs($admin)->get(route('admin.master-data.subjects.edit', $frei->fach_id))->assertOk()
            ->assertDontSee('Das Fach hat bereits Noten oder Prüfungen.')
            ->assertSee('value="DELETE"', false);

        // Zuletzt, weil die Fehlermeldung danach als Flash in der Sitzung liegt.
        $this->actingAs($admin)->delete(route('admin.master-data.subjects.destroy', $fach->fach_id))->assertSessionHas('error');
        $this->assertTrue(DB::table('faecher')->where('fach_id', $fach->fach_id)->exists());
    }
}
