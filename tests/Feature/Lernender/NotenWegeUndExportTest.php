<?php

declare(strict_types=1);

namespace Tests\Feature\Lernender;

use App\Models\Kategorie;
use App\Models\Note;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Support\Datenauskunft;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

/** Funktionsprüfung «noten»: Leerzustand des Formulars, Rückwege nach dem Erfassen, Druckansicht, Begriff Titel, CSV-Schutz. */
class NotenWegeUndExportTest extends TestCase
{
    private int $lehrberuf;

    private int $modul;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $this->lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TW3', 'name' => 'Wege EFZ']);
        $this->modul = DB::table('module')->insertGetId(['modul_nummer' => '917', 'titel' => 'Wegemodul']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $this->lehrberuf, 'modul_id' => $this->modul,
            'kategorie_id' => DB::table('kategorien')->where('code', 'FACH')->value('kategorie_id')]);
        DB::table('semester')->insert(['bezeichnung' => 'W-1', 'start_datum' => now()->subMonths(2)->toDateString(), 'end_datum' => now()->addMonths(4)->toDateString(), 'sortierung' => 12]);
        Konfiguration::vergessen();
    }

    private function ohneBezug(): User
    {
        $leer = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'LEE', 'name' => 'Leer EFZ']);

        return User::factory()->lernender(['lehrberuf_id' => $leer])->create();
    }

    private function mitBezug(): User
    {
        return User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create();
    }

    /** @return array<string, mixed> */
    private function notenDaten(array $mehr = []): array
    {
        return $mehr + [
            'typ' => 'modul', 'modul_id' => $this->modul, 'pruefungsdatum' => now()->subDays(3)->toDateString(),
            'note_wert' => '5.0', 'gewichtung_prozent' => 100,
        ];
    }

    // --- Leerzustand ohne Fächer und Module ------------------------------------------------

    #[Test]
    public function formularseite_ohne_bezugsoptionen_zeigt_leerzustand_mit_weg_zu_den_modulen_statt_formular(): void
    {
        $this->actingAs($this->ohneBezug());

        $this->get(route('learner.grades.create'))->assertOk()
            ->assertSee('Keine Fächer oder Module')
            ->assertSee('href="'.route('modules.index').'"', false)
            ->assertDontSee('name="note_wert"', false)
            ->assertDontSee('text-note-knapp');
    }

    #[Test]
    public function drawer_ohne_bezugsoptionen_zeigt_leerzustand_mit_weg_zu_den_modulen_statt_formular(): void
    {
        $this->actingAs($this->ohneBezug());

        $this->get(route('learner.grades.create', ['drawer' => 1]))->assertOk()
            ->assertSee('Keine Fächer oder Module')
            ->assertSee('href="'.route('modules.index').'"', false)
            ->assertDontSee('name="note_wert"', false);
    }

    #[Test]
    public function formular_mit_bezugsoptionen_bleibt_unveraendert(): void
    {
        $this->actingAs($this->mitBezug());

        $this->get(route('learner.grades.create'))->assertOk()
            ->assertSee('name="note_wert"', false)
            ->assertDontSee('Keine Fächer oder Module');
    }

    #[Test]
    public function notenrechner_drawer_ohne_bezugsoptionen_bietet_keinen_leeren_rechner_an(): void
    {
        $this->actingAs($this->ohneBezug());

        $this->get(route('learner.grades.index'))->assertOk()
            ->assertSee('Keine Fächer oder Module')
            ->assertDontSee('npNotenrechnerDrawer', false);

        $this->actingAs($this->mitBezug());
        $this->get(route('learner.grades.index'))->assertOk()
            ->assertSee('npNotenrechnerDrawer', false);
    }

    // --- Rückweg nach dem Erfassen -------------------------------------------------------------

    #[Test]
    public function erfassen_kehrt_zum_validierten_rueckziel_zurueck(): void
    {
        $this->actingAs($this->mitBezug());
        $ziel = route('modules.show', $this->modul, false);

        $this->post(route('learner.grades.store'), $this->notenDaten(['rueckziel' => $ziel]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(url($ziel))
            ->assertSessionHas('success', 'Note gespeichert.');

        $this->post(route('learner.grades.store'), $this->notenDaten(['rueckziel' => route('learner.dashboard', [], false)]))
            ->assertRedirect(route('learner.dashboard'));
    }

    #[Test]
    public function fremde_oder_unbekannte_rueckziele_fuehren_auf_die_notenseite(): void
    {
        $this->actingAs($this->mitBezug());

        foreach (['https://boese.example/x', '//boese.example/x', '/\\boese.example', 'javascript:alert(1)', '/gibt-es-nicht', "/modules\r\nLocation: https://boese.example", '/grades?semester_id=1'] as $ziel) {
            $antwort = $this->post(route('learner.grades.store'), $this->notenDaten(['rueckziel' => $ziel]));
            $antwort->assertSessionHasNoErrors();
            $ort = (string) $antwort->headers->get('Location');
            $this->assertStringStartsWith(route('learner.grades.index'), $ort, "Rückziel «{$ziel}» wurde nicht abgewiesen");
            $this->assertStringContainsString('semester_id=', $ort, 'Standardweg zeigt das Semester der neuen Note');
            $this->assertStringNotContainsString('boese', $ort);
        }
    }

    #[Test]
    public function ohne_rueckziel_bleibt_der_standardweg_die_notenseite(): void
    {
        $this->actingAs($this->mitBezug());

        $antwort = $this->post(route('learner.grades.store'), $this->notenDaten())
            ->assertSessionHas('success');
        $this->assertStringStartsWith(route('learner.grades.index'), (string) $antwort->headers->get('Location'));
    }

    #[Test]
    public function modul_weg_traegt_das_modul_als_rueckziel_und_abbrechen_fuehrt_zum_modul(): void
    {
        $this->actingAs($this->mitBezug());
        $modulUrl = route('modules.show', $this->modul);

        $this->get(route('learner.grades.create', ['bezug' => 'modul:'.$this->modul, 'rueckziel' => route('modules.show', $this->modul, false)]))->assertOk()
            ->assertSee('name="rueckziel" value="'.route('modules.show', $this->modul, false).'"', false)
            ->assertSee('href="'.$modulUrl.'"', false);

        // Der Knopf im Modul gibt das Rückziel mit
        $this->post(route('modules.enroll', $this->modul))->assertSessionHasNoErrors();
        $this->get(route('modules.show', $this->modul))->assertOk()
            ->assertSee('rueckziel=', false);
    }

    #[Test]
    public function formular_uebernimmt_keine_fremden_rueckziele_in_das_versteckte_feld(): void
    {
        $this->actingAs($this->mitBezug());

        $this->get(route('learner.grades.create', ['rueckziel' => 'https://boese.example/x']))->assertOk()
            ->assertDontSee('name="rueckziel"', false)
            ->assertDontSee('href="https://boese.example', false);
        $this->get(route('learner.grades.create', ['drawer' => 1, 'rueckziel' => '/dashboard']))->assertOk()
            ->assertSee('name="rueckziel" value="/dashboard"', false);
    }

    // --- Druckansicht im selben Tab ---------------------------------------------------------------

    #[Test]
    public function drucken_oeffnet_das_notenblatt_im_selben_tab_damit_zurueck_stimmt(): void
    {
        $this->actingAs($this->mitBezug());

        $html = $this->get(route('learner.grades.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('learner.grades.print'), '#').'"[^>]*>#', $html);
        preg_match('#<a href="'.preg_quote(route('learner.grades.print'), '#').'"[^>]*>#', $html, $m);
        $this->assertStringNotContainsString('_blank', $m[0]);
    }

    #[Test]
    public function drucken_in_der_verwaltung_oeffnet_im_selben_tab(): void
    {
        $bb = User::factory()->admin()->create();
        $lernender = $this->mitBezug();
        $id = $lernender->lernender->lernender_id;
        $this->actingAs($bb);

        foreach ([route('admin.learners.show', $id), route('admin.learners.grades.index', $id)] as $seite) {
            $html = $this->get($seite)->assertOk()->getContent();
            preg_match_all('#<a [^>]*href="'.preg_quote(route('admin.learners.grades.print', $id), '#').'"[^>]*>#', $html, $m);
            $this->assertNotEmpty($m[0], "Drucken-Link fehlt auf {$seite}");
            foreach ($m[0] as $tag) {
                $this->assertStringNotContainsString('_blank', $tag);
            }
        }
    }

    // --- Begriff Titel ------------------------------------------------------------------------------

    #[Test]
    public function notenzeile_nennt_das_feld_ueberall_titel_und_nie_notiz(): void
    {
        $user = $this->mitBezug();
        $this->actingAs($user);
        $this->post(route('learner.grades.store'), $this->notenDaten(['titel' => 'Klausur Eins']))->assertSessionHasNoErrors();

        $html = $this->get(route('learner.grades.index', ['ansicht' => 'alle']))->assertOk()->getContent();
        $this->assertStringContainsString('aria-label="Titel bearbeiten"', $html);
        $this->assertStringNotContainsString('Notiz', $html);
        $this->assertStringNotContainsString('>OK<', $html);
    }

    // --- CSV-Schutz ------------------------------------------------------------------------------------

    #[Test]
    public function csv_export_schuetzt_kategorie_gegen_formeln(): void
    {
        $user = $this->mitBezug();
        $this->actingAs($user);
        $kategorie = Kategorie::query()->where('code', 'FACH')->firstOrFail();
        DB::table('kategorien')->where('kategorie_id', $kategorie->kategorie_id)->update(['name' => '=1+1']);
        Note::factory()->create([
            'lernender_id' => $user->lernender->lernender_id, 'kategorie_id' => $kategorie->kategorie_id,
            'titel' => 'Plain', 'note_wert' => 5.0, 'erfasst_von_benutzer_id' => $user->benutzer_id,
        ]);

        $csv = $this->get(route('learner.grades.export'))->assertOk()->streamedContent();

        $this->assertStringContainsString(";'=1+1;", $csv);
        $this->assertStringNotContainsString(';=1+1;', $csv);
    }

    #[Test]
    public function datenauskunft_schuetzt_semester_und_kategorie_gegen_formeln(): void
    {
        Storage::fake('local');
        $user = $this->mitBezug();
        $kategorie = Kategorie::query()->where('code', 'FACH')->firstOrFail();
        DB::table('kategorien')->where('kategorie_id', $kategorie->kategorie_id)->update(['name' => '@SUMME(1)']);
        $semester = (int) DB::table('semester')->where('bezeichnung', 'W-1')->value('semester_id');
        DB::table('semester')->where('semester_id', $semester)->update(['bezeichnung' => '-2+3']);
        Note::factory()->create([
            'lernender_id' => $user->lernender->lernender_id, 'kategorie_id' => $kategorie->kategorie_id, 'semester_id' => $semester,
            'titel' => 'Plain', 'note_wert' => 5.0, 'erfasst_von_benutzer_id' => $user->benutzer_id,
        ]);

        $pfad = app(Datenauskunft::class)->erzeugen($user);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($pfad));
        $csv = (string) $zip->getFromName('noten.csv');
        $zip->close();
        @unlink($pfad);

        $this->assertStringContainsString(";'-2+3;", $csv);
        $this->assertStringContainsString(";'@SUMME(1);", $csv);
        $this->assertStringNotContainsString(';-2+3;', $csv);
        $this->assertStringNotContainsString(';@SUMME(1);', $csv);
    }
}
