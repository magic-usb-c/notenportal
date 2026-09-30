<?php

declare(strict_types=1);

namespace Tests\Feature\Auswertung;

use App\Models\Lernender;
use App\Models\NotenbaumPosition;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Notenbaum\BaumVorlage;
use App\Services\Auswertung\NotenQuelle;
use Database\Seeders\BasisSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

/**
 * Abschluss-Seite (Positionen von Hand, Ergebnis je Baum) und die Notenbäume in den Stammdaten.
 */
class AbschlussTest extends TestCase
{
    use VerwaltungTestHilfen;

    private int $lehrberuf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $this->lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'ABT', 'name' => 'Abschluss-Test EFZ']);
        DB::table('semester')->insert(['bezeichnung' => 'AB-1', 'start_datum' => '2024-08-01', 'end_datum' => now()->addYears(3)->toDateString(), 'sortierung' => 1]);
        Konfiguration::vergessen();
    }

    // --- Lernende ------------------------------------------------------------------------------

    #[Test]
    public function lernender_sieht_abschluss_erst_mit_baum_im_menue_und_erfasst_seine_ipa(): void
    {
        $lernender = $this->lernenderMitBeruf();
        $user = $lernender->benutzer;

        $this->actingAs($user)->get(route('learner.dashboard'))->assertOk()->assertDontSee(route('learner.qualification.index'));

        $this->efzBaum();
        $this->get(route('learner.dashboard'))->assertOk()->assertSee(route('learner.qualification.index'));
        $this->get(route('learner.qualification.index'))->assertOk()
            ->assertSee('Informatiker/in EFZ (BiVo 2020)')->assertSee('Praktische Arbeit (IPA)')->assertSee(__('Offen'));

        $ipa = $this->knoten('ipa');
        $this->put(route('learner.qualification.update'), ['werte' => [$ipa => '5,5']])
            ->assertRedirect(route('learner.qualification.index'))->assertSessionHas('success');

        $position = NotenbaumPosition::sole();
        $this->assertSame((int) $lernender->lernender_id, (int) $position->lernender_id);
        $this->assertEqualsWithDelta(5.5, $position->note_wert, 1e-9);
        $this->assertSame((int) $user->benutzer_id, (int) $position->erfasst_von_benutzer_id);

        $a = app(NotenQuelle::class)->auswertung((int) $lernender->lernender_id);
        $this->assertEqualsWithDelta(5.5, array_values($a->baeume)[0]->knoten('ipa')->note, 1e-9);
        // Nur die IPA liegt vor: die Prognose ist die IPA selbst (fehlende Teile werden übersprungen)
        $this->assertEqualsWithDelta(5.5, $a->gesamtNote, 1e-9);
        $this->get(route('learner.dashboard'))->assertSee(__('QV-Prognose'));

        // Leeren entfernt die Position wieder
        $this->put(route('learner.qualification.update'), ['werte' => [$ipa => '']])->assertSessionHas('success');
        $this->assertSame(0, NotenbaumPosition::count());
    }

    #[Test]
    public function ungueltige_werte_und_fremde_knoten_werden_abgelehnt(): void
    {
        $lernender = $this->lernenderMitBeruf();
        $this->efzBaum();
        $bm = app(BaumVorlage::class)->importieren(BaumVorlage::laden('bm-tals1-bmv2025'));
        $bmKnoten = (int) DB::table('notenbaum_knoten')->where('baum_id', $bm)->where('code', 'idpa_arbeit')->value('knoten_id');
        $gruppe = $this->knoten('ab');
        $ipa = $this->knoten('ipa');
        $this->actingAs($lernender->benutzer);

        $this->put(route('learner.qualification.update'), ['werte' => [$ipa => '6.5']])->assertSessionHasErrors("werte.$ipa");
        $this->put(route('learner.qualification.update'), ['werte' => [$ipa => '4.33']])->assertSessionHasErrors("werte.$ipa");
        $this->put(route('learner.qualification.update'), ['werte' => [$ipa => 'gut']])->assertSessionHasErrors("werte.$ipa");
        // Gruppe (rechnet selbst) und Knoten eines Bildungsgangs ohne laufenden Track
        $this->put(route('learner.qualification.update'), ['werte' => [$gruppe => '5']])->assertSessionHasErrors("werte.$gruppe");
        $this->put(route('learner.qualification.update'), ['werte' => [$bmKnoten => '5']])->assertSessionHasErrors("werte.$bmKnoten");
        $this->put(route('learner.qualification.update'), ['werte' => [$ipa => '5', $bmKnoten => '5']])->assertSessionHasErrors("werte.$bmKnoten");

        $this->assertSame(0, NotenbaumPosition::count(), 'Ein Fehler verwirft das ganze Formular');
    }

    #[Test]
    public function lernender_kann_keine_fremde_id_unterschieben(): void
    {
        $ich = $this->lernenderMitBeruf();
        $andere = $this->lernenderMitBeruf();
        $this->efzBaum();

        $this->actingAs($ich->benutzer)->put(route('learner.qualification.update'), [
            'lernender_id' => $andere->lernender_id,
            'werte' => [$this->knoten('ipa') => '5'],
        ])->assertSessionHas('success');

        $this->assertSame([(int) $ich->lernender_id], NotenbaumPosition::pluck('lernender_id')->map(fn ($v) => (int) $v)->all());
    }

    #[Test]
    public function bm_baum_erscheint_nur_mit_laufendem_track(): void
    {
        $lernender = $this->lernenderMitBeruf();
        $this->efzBaum();
        app(BaumVorlage::class)->importieren(BaumVorlage::laden('bm-tals1-bmv2025'));
        $this->actingAs($lernender->benutzer);

        $this->get(route('learner.qualification.index'))->assertDontSee('BM Technik');
        $this->bmsTrack($lernender, (int) DB::table('semester')->value('semester_id'));
        Konfiguration::vergessen();
        $this->get(route('learner.qualification.index'))->assertSee('BM Technik')->assertSee('Mathematik erweitert');
    }

    #[Test]
    public function allgemeinbildung_entfaellt_nach_dem_zuletzt_begonnenen_track(): void
    {
        $bm = $this->lernenderMitBeruf();
        $wechsel = $this->lernenderMitBeruf();
        $this->efzBaum();
        DB::table('notenbaum_knoten')->where('code', 'ab')->update(['entfaellt_mit_track' => 'BMS']);
        $semester = (int) DB::table('semester')->value('semester_id');
        $this->bmsTrack($bm, $semester);
        $this->bmsTrack($wechsel, $semester);
        DB::table('lernender_tracks')->where('lernender_id', $wechsel->lernender_id)->update(['end_datum' => '2025-01-31']);
        DB::table('lernender_tracks')->insert(['lernender_id' => $wechsel->lernender_id, 'track_typ' => 'ABU', 'start_datum' => '2025-02-01', 'start_semester_id' => $semester]);
        Konfiguration::vergessen();

        foreach ([[$bm, 5.0], [$wechsel, 5.0]] as [$l, $ipa]) {
            NotenbaumPosition::create(['lernender_id' => $l->lernender_id, 'knoten_id' => $this->knoten('ipa'), 'note_wert' => $ipa, 'erfasst_von_benutzer_id' => $l->benutzer_id]);
        }
        $alle = app(NotenQuelle::class)->auswertungen([(int) $bm->lernender_id, (int) $wechsel->lernender_id]);

        $this->assertTrue(array_values($alle[(int) $bm->lernender_id]->baeume)[0]->knoten('ab')->knoten->entfaellt);
        $this->assertFalse(array_values($alle[(int) $wechsel->lernender_id]->baeume)[0]->knoten('ab')->knoten->entfaellt);

        $this->actingAs($bm->benutzer)->get(route('learner.qualification.index'))->assertSee(__('entfällt'))->assertDontSee('Schlussarbeit');
        $ab = $this->knoten('ab_schlussarbeit');
        $this->put(route('learner.qualification.update'), ['werte' => [$ab => '5']])->assertSessionHasErrors("werte.$ab");

        $this->actingAs($wechsel->benutzer)->get(route('learner.qualification.index'))->assertSee('Schlussarbeit');
        $this->put(route('learner.qualification.update'), ['werte' => [$ab => '5']])->assertSessionHasNoErrors();
    }

    #[Test]
    public function ohne_baum_zeigt_die_seite_den_leerzustand(): void
    {
        $lernender = $this->lernenderMitBeruf();

        $this->actingAs($lernender->benutzer)->get(route('learner.qualification.index'))->assertOk()
            ->assertSee(__('Für diese Ausbildung ist noch keine Gewichtung bis zur Gesamtnote hinterlegt.'))
            ->assertDontSee(route('admin.master-data.grade-trees.index'));
    }

    // --- Verwaltung ----------------------------------------------------------------------------

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function verwalter_erfasst_abschlussnoten_fuer_sichtbare_lernende(string $rolle): void
    {
        $lernender = $this->lernenderMitBeruf();
        $this->efzBaum();
        $verwalter = $this->verwalter($rolle, $lernender);

        $this->actingAs($verwalter)->get(route("$rolle.learners.qualification", $lernender->lernender_id))->assertOk()
            ->assertSee('Praktische Arbeit (IPA)');
        $this->get(route("$rolle.learners.show", $lernender->lernender_id))->assertOk()
            ->assertSee(route("$rolle.learners.qualification", $lernender->lernender_id));

        $this->put(route("$rolle.learners.qualification.update", $lernender->lernender_id), ['werte' => [$this->knoten('ipa') => '4.5']])
            ->assertRedirect(route("$rolle.learners.qualification", $lernender->lernender_id));
        $this->assertSame((int) $verwalter->benutzer_id, (int) NotenbaumPosition::sole()->erfasst_von_benutzer_id);
    }

    #[Test]
    public function berufsbildner_ohne_betreuung_sieht_nichts(): void
    {
        $lernender = $this->lernenderMitBeruf();
        $this->efzBaum();
        $fremd = User::factory()->berufsbildner()->create();

        $this->actingAs($fremd)->get(route('trainer.learners.qualification', $lernender->lernender_id))->assertNotFound();
        $this->put(route('trainer.learners.qualification.update', $lernender->lernender_id), ['werte' => [$this->knoten('ipa') => '4']])
            ->assertNotFound();
        $this->assertSame(0, NotenbaumPosition::count());

        $abgelaufen = User::factory()->berufsbildner()->create();
        $this->betreue($abgelaufen, $lernender, 'abgelaufen');
        $this->actingAs($abgelaufen)->get(route('trainer.learners.qualification', $lernender->lernender_id))->assertNotFound();
    }

    // --- Stammdaten: Notenbäume ----------------------------------------------------------------

    #[Test]
    public function admin_laedt_vorlage_passt_gewicht_an_und_die_rechnung_folgt(): void
    {
        $admin = User::factory()->admin()->create();
        $lernender = $this->lernenderMitBeruf();
        $this->actingAs($admin)->get(route('admin.master-data.grade-trees.index'))->assertOk()->assertSee('Informatiker/in EFZ (BiVo 2020)');

        $this->post(route('admin.master-data.grade-trees.template'), ['vorlage' => 'informatiker-efz-bivo2020'])
            ->assertSessionHasErrors('lehrberuf_id');
        $antwort = $this->post(route('admin.master-data.grade-trees.template'), ['vorlage' => 'informatiker-efz-bivo2020', 'lehrberuf_id' => $this->lehrberuf]);
        $baum = (int) DB::table('notenbaeume')->value('baum_id');
        $antwort->assertRedirect(route('admin.master-data.grade-trees.show', $baum));

        $this->get(route('admin.master-data.grade-trees.show', $baum))->assertOk()->assertSee('Informatikkompetenzen')->assertSee('40 %');

        NotenbaumPosition::create(['lernender_id' => $lernender->lernender_id, 'knoten_id' => $this->knoten('ipa'), 'note_wert' => 6.0, 'erfasst_von_benutzer_id' => $admin->benutzer_id]);
        NotenbaumPosition::create(['lernender_id' => $lernender->lernender_id, 'knoten_id' => $this->knoten('ab_schlussarbeit'), 'note_wert' => 3.0, 'erfasst_von_benutzer_id' => $admin->benutzer_id]);
        // IPA 6.0 (40) und Allgemeinbildung 3.0 (20) → 5.0
        $this->assertEqualsWithDelta(5.0, app(NotenQuelle::class)->auswertung((int) $lernender->lernender_id)->gesamtNote, 1e-9);

        $knoten = DB::table('notenbaum_knoten')->where('baum_id', $baum)->get()->mapWithKeys(fn ($k) => [$k->knoten_id => [
            'name' => $k->name, 'gewicht' => $k->code === 'ab' ? '40' : (string) (float) $k->gewicht,
            'rundung' => $k->rundung === null ? '' : (string) (float) $k->rundung,
            'fallnote' => $k->fallnote === null ? '' : (string) (float) $k->fallnote,
            'zaehlt' => '1',
        ]])->all();
        $this->put(route('admin.master-data.grade-trees.update', $baum), ['name' => 'QV Informatik', 'knoten' => $knoten])
            ->assertRedirect(route('admin.master-data.grade-trees.show', $baum))->assertSessionHasNoErrors();

        // Jetzt 40:40 → 4.5
        $this->assertSame('QV Informatik', DB::table('notenbaeume')->where('baum_id', $baum)->value('name'));
        $this->assertEqualsWithDelta(4.5, app(NotenQuelle::class)->auswertung((int) $lernender->lernender_id)->gesamtNote, 1e-9);

        // Fremde Knoten-ID wird nicht angenommen
        $this->put(route('admin.master-data.grade-trees.update', $baum), ['name' => 'x', 'knoten' => $knoten + [999999 => ['name' => 'x', 'gewicht' => 1]]])
            ->assertStatus(422);
        // Unsinnige Rundung wird abgelehnt
        $falsch = $knoten;
        $falsch[array_key_first($falsch)]['rundung'] = '0.25';
        $this->put(route('admin.master-data.grade-trees.update', $baum), ['name' => 'x', 'knoten' => $falsch])->assertSessionHasErrors();

        // Mit erfassten Noten lässt sich der Baum nicht löschen, nur deaktivieren
        $this->delete(route('admin.master-data.grade-trees.destroy', $baum))->assertSessionHas('error');
        $this->post(route('admin.master-data.grade-trees.activate', $baum), ['aktiv' => 0])->assertSessionHas('success');
        $this->assertSame([], app(NotenQuelle::class)->auswertung((int) $lernender->lernender_id)->baeume);
    }

    #[Test]
    public function aktivieren_loest_den_anderen_baum_desselben_lehrberufs_ab_und_loeschen_raeumt_auf(): void
    {
        $admin = User::factory()->admin()->create();
        $erster = $this->efzBaum();
        $zweiter = $this->efzBaum();
        $this->assertFalse((bool) DB::table('notenbaeume')->where('baum_id', $erster)->value('aktiv'));

        $this->actingAs($admin)->post(route('admin.master-data.grade-trees.activate', $erster), ['aktiv' => 1]);
        $this->assertTrue((bool) DB::table('notenbaeume')->where('baum_id', $erster)->value('aktiv'));
        $this->assertFalse((bool) DB::table('notenbaeume')->where('baum_id', $zweiter)->value('aktiv'));

        $this->delete(route('admin.master-data.grade-trees.destroy', $zweiter))->assertRedirect(route('admin.master-data.grade-trees.index'));
        $this->assertNull(DB::table('notenbaeume')->where('baum_id', $zweiter)->first());
        $this->assertSame(0, DB::table('notenbaum_knoten')->where('baum_id', $zweiter)->count());
    }

    #[Test]
    public function export_liefert_eine_importierbare_datei_und_kaputter_import_meldet_den_grund(): void
    {
        $admin = User::factory()->admin()->create();
        $baum = $this->efzBaum();

        $antwort = $this->actingAs($admin)->get(route('admin.master-data.grade-trees.export', $baum))->assertOk();
        $this->assertStringContainsString('attachment;', (string) $antwort->headers->get('Content-Disposition'));
        $json = $antwort->getContent();
        $this->assertSame([], BaumVorlage::pruefen(json_decode((string) $json, true)));

        $this->post(route('admin.master-data.grade-trees.import'), [
            'datei' => UploadedFile::fake()->createWithContent('baum.json', (string) $json),
        ])->assertSessionHasErrors('datei_lehrberuf_id');
        $this->post(route('admin.master-data.grade-trees.import'), [
            'datei' => UploadedFile::fake()->createWithContent('baum.json', (string) $json), 'datei_lehrberuf_id' => $this->lehrberuf,
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(2, DB::table('notenbaeume')->count());

        $kaputt = json_decode((string) $json, true);
        $kaputt['wurzel']['kinder'][0]['code'] = 'ab';
        $this->post(route('admin.master-data.grade-trees.import'), [
            'datei' => UploadedFile::fake()->createWithContent('baum.json', (string) json_encode($kaputt)), 'datei_lehrberuf_id' => $this->lehrberuf,
        ])->assertSessionHasErrors(['datei' => 'Code «ab» kommt mehrfach vor.']);
        $this->assertSame(2, DB::table('notenbaeume')->count());
    }

    #[Test]
    public function fach_im_notenbaum_laesst_sich_nicht_loeschen(): void
    {
        $admin = User::factory()->admin()->create();
        $this->efzBaum();
        $mathe = (int) DB::table('faecher')->where('name', 'Mathematik')->value('fach_id');

        $this->actingAs($admin)->delete(route('admin.master-data.subjects.destroy', $mathe))->assertSessionHas('error');
        $this->assertNotNull(DB::table('faecher')->where('fach_id', $mathe)->first());
    }

    #[Test]
    public function admin_sieht_im_menue_die_notenbaeume(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertOk()
            ->assertSee(route('admin.master-data.grade-trees.index'));
    }

    // ---------------------------------------------------------------------------------------------

    private function lernenderMitBeruf(): Lernender
    {
        return $this->neuerLernender([], ['lehrberuf_id' => $this->lehrberuf]);
    }

    private function efzBaum(): int
    {
        return app(BaumVorlage::class)->importieren(BaumVorlage::laden('informatiker-efz-bivo2020'), $this->lehrberuf);
    }

    /** Knoten des aktiven EFZ-Baums. */
    private function knoten(string $code): int
    {
        return (int) DB::table('notenbaum_knoten as k')->join('notenbaeume as b', 'b.baum_id', '=', 'k.baum_id')
            ->where('b.aktiv', true)->where('b.bezug', 'lehrberuf')->where('k.code', $code)->value('k.knoten_id');
    }
}
