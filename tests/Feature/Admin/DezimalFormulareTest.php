<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Kategorie;
use App\Models\User;
use App\Support\Einstellungen;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Dezimalfelder der Verwaltung sind Textfelder (inputmode="decimal"): der Browser stellt vorbelegte Werte sonst
 * nach seiner Sprache mit Komma dar, während der Rest der Seite den Punkt zeigt. Ein getipptes Komma gilt als Punkt.
 */
class DezimalFormulareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
    }

    /** Das Eingabefeld mit diesem Namen aus dem HTML (erstes Tag, das name="…" trägt). */
    private function feld(string $html, string $name): string
    {
        $this->assertSame(1, preg_match('/<input\b[^>]*\bname="'.preg_quote($name, '/').'"[^>]*>/s', $html, $m), "Feld $name fehlt");

        return $m[0];
    }

    private function assertDezimalfeld(string $html, string $name): void
    {
        $tag = $this->feld($html, $name);
        $this->assertStringContainsString('type="text"', $tag, "$name ist kein Textfeld");
        $this->assertStringContainsString('inputmode="decimal"', $tag, "$name ohne inputmode=decimal");
        $this->assertStringNotContainsString('type="number"', $tag);
    }

    #[Test]
    public function stammdaten_kategorie_zeigt_dezimalfelder_als_text_und_versteht_das_komma(): void
    {
        $admin = User::factory()->admin()->create();
        $kategorie = Kategorie::where('code', 'BMS')->firstOrFail();

        $html = $this->actingAs($admin)->get(route('admin.master-data.categories.edit', $kategorie->kategorie_id))->assertOk()->getContent();
        foreach (['gewicht_gesamt', 'promotion_min_schnitt', 'promotion_max_minuspunkte'] as $name) {
            $this->assertDezimalfeld($html, $name);
        }
        $this->assertStringContainsString('type="number"', $this->feld($html, 'promotion_max_ungenuegend'));

        $this->put(route('admin.master-data.categories.update', $kategorie->kategorie_id), [
            'code' => $kategorie->code, 'name' => $kategorie->name, 'sortierung' => $kategorie->sortierung, 'aktiv' => 1,
            'rundung_element' => '0.25', 'rundung_schnitt' => '0.1',
            'gewicht_gesamt' => '1,25', 'promotion_min_schnitt' => '4,5', 'promotion_max_ungenuegend' => '', 'promotion_max_minuspunkte' => '2,5',
        ])->assertSessionHasNoErrors();

        $kategorie->refresh();
        $this->assertEquals(1.25, (float) $kategorie->gewicht_gesamt);
        $this->assertEquals(4.5, (float) $kategorie->promotion_min_schnitt);
        $this->assertEquals(2.5, (float) $kategorie->promotion_max_minuspunkte);
    }

    #[Test]
    public function einrichtung_kategorien_zeigt_dezimalfelder_als_text_und_versteht_das_komma(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->update(['passwort_wechsel_noetig' => false]);
        $this->actingAs($admin);

        $html = $this->get(route('admin.setup', 'categories'))->assertOk()->getContent();
        $kategorien = DB::table('kategorien')->get();
        $id = $kategorien->first()->kategorie_id;
        foreach (['gewicht_gesamt', 'promotion_min_schnitt', 'promotion_max_minuspunkte'] as $name) {
            $this->assertDezimalfeld($html, "kategorien[$id][$name]");
        }
        $this->assertStringContainsString('type="number"', $this->feld($html, "kategorien[$id][promotion_max_ungenuegend]"));

        $daten = $kategorien->mapWithKeys(fn ($k) => [$k->kategorie_id => [
            'name' => $k->name, 'aktiv' => 1, 'rundung_element' => '0.5', 'rundung_schnitt' => '0.1', 'gewicht_gesamt' => '1,25',
            'promotion_min_schnitt' => '4,5', 'promotion_max_ungenuegend' => null, 'promotion_max_minuspunkte' => '1,5',
        ]])->all();
        $this->post(route('admin.setup.categories'), ['kategorien' => $daten])->assertSessionHasNoErrors();

        $zeile = DB::table('kategorien')->where('kategorie_id', $id)->first();
        $this->assertEquals(1.25, (float) $zeile->gewicht_gesamt);
        $this->assertEquals(4.5, (float) $zeile->promotion_min_schnitt);
        $this->assertEquals(1.5, (float) $zeile->promotion_max_minuspunkte);
    }

    #[Test]
    public function betrieb_zeigt_notengrenzen_als_text_und_versteht_das_komma(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $html = $this->get(route('admin.operations.edit'))->assertOk()->getContent();
        foreach (['note_gut', 'note_genuegend', 'note_kritisch'] as $name) {
            $this->assertDezimalfeld($html, $name);
            $this->assertStringContainsString('x-model="', $this->feld($html, $name));
            $this->assertStringNotContainsString('x-model.number', $this->feld($html, $name));
        }

        $this->put(route('admin.operations.update'), [
            'betrieb_name' => 'Test AG', 'note_gut' => '5,25', 'note_genuegend' => '4,5', 'note_kritisch' => '3,75',
            'rundung_gesamt' => '0.1', 'frist_inaktiv_tage' => 30, 'frist_lehrende_tage' => 60,
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('5.25', Einstellungen::get(Einstellungen::NOTE_GUT));
        $this->assertSame('4.5', Einstellungen::get(Einstellungen::NOTE_GENUEGEND));
        $this->assertSame('3.75', Einstellungen::get(Einstellungen::NOTE_KRITISCH));
    }

    #[Test]
    public function modul_zeigt_ziel_gewicht_summe_als_text_und_versteht_das_komma(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $html = $this->get(route('admin.master-data.modules.create'))->assertOk()->getContent();
        $this->assertDezimalfeld($html, 'ziel_gewicht_summe_default');

        $this->post(route('admin.master-data.modules.store'), [
            'modul_nummer' => '917', 'titel' => 'Testmodul Kappa betreiben', 'ziel_gewicht_summe_default' => '99,5', 'version' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertEquals(99.5, (float) DB::table('module')->where('modul_nummer', '917')->value('ziel_gewicht_summe_default'));
    }

    #[Test]
    public function einrichtung_module_zeigt_die_gewichtssumme_als_text_und_versteht_das_komma(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->update(['passwort_wechsel_noetig' => false]);
        $this->actingAs($admin);
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);

        $html = $this->get(route('admin.setup', 'modules'))->assertOk()->getContent();
        $this->assertDezimalfeld($html, 'ziel');

        $this->post(route('admin.setup.modules'), ['lehrberuf_id' => $lehrberuf, 'schule' => '918 Testmodul Lambda planen', 'ziel' => '99,5'])
            ->assertSessionHasNoErrors();

        $this->assertEquals(99.5, (float) DB::table('module')->where('modul_nummer', '918')->value('ziel_gewicht_summe_default'));
    }
}
