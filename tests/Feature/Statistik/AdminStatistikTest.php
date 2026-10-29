<?php

declare(strict_types=1);

namespace Tests\Feature\Statistik;

use App\Models\Kategorie;
use App\Models\Note;
use App\Models\User;
use App\Support\Einrichtung;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * R6-10: Admin-Dashboard (Erfassung je Woche, S9) und Notenbericht (Verteilung in Viertelnoten S10, Lehrjahre S11).
 * Die Zeitraum-Auswahl bestimmt die Zahl der Wochenwerte, die Klassen des Histogramms zählen genau die Zeugnisnoten
 * ohne gelöschte, und Median und Quartile gibt es erst ab drei Werten.
 */
class AdminStatistikTest extends TestCase
{
    private User $admin;

    private int $fach;

    protected function setUp(): void
    {
        parent::setUp();

        Einrichtung::abschliessen();
        $this->admin = User::factory()->admin()->create();
        $this->fach = (int) Kategorie::where('code', 'FACH')->value('kategorie_id');
    }

    /**
     * Eine Person mit einer Zeugnisnote je Wert (jede Note ein eigenes Fach); die Lehrzeit begann vor $monate Monaten.
     *
     * @param  list<float>  $werte
     */
    private function lernende(array $werte, int $monate = 2): User
    {
        $user = User::factory()->lernender(['lehrbeginn' => now()->subMonths($monate)->toDateString(), 'lehrende' => now()->addMonths(40)->toDateString()])->create();
        foreach ($werte as $wert) {
            Note::factory()->create(['lernender_id' => $user->lernender->lernender_id, 'kategorie_id' => $this->fach, 'note_wert' => $wert]);
        }

        return $user;
    }

    #[Test]
    public function der_zeitraum_bestimmt_die_zahl_der_wochenwerte(): void
    {
        foreach ([null => 12, '12w' => 12, '26w' => 26, '52w' => 52, 'xyz' => 12] as $zeitraum => $erwartet) {
            $antwort = $this->actingAs($this->admin)->getJson(route('admin.dashboard', $zeitraum === null ? [] : ['zeitraum' => $zeitraum]))->assertOk();
            $this->assertCount($erwartet, $antwort->json('diagramm.erfassung.werte'), "zeitraum={$zeitraum}");
            $this->assertCount($erwartet, $antwort->json('diagramm.erfassung.labels'));
            $this->assertSame($erwartet, $antwort->json('diagramm.erfassung.wochen'));
        }

        $this->assertSame(['12w', '26w', '52w'], array_keys($this->actingAs($this->admin)->getJson(route('admin.dashboard'))->json('meta.erfassung.optionen.zeitraum')));
    }

    #[Test]
    public function die_seite_zeichnet_je_woche_eine_saeule_mit_segment_und_tabelle(): void
    {
        $this->lernende([4.0, 5.0]);

        $html = $this->actingAs($this->admin)->get(route('admin.dashboard', ['zeitraum' => '26w']))->assertOk()->getContent();

        $this->assertStringContainsString('id="np-erfassung"', $html);
        $this->assertStringContainsString('data-np-baustein="erfassung"', $html);
        $this->assertSame(26, substr_count($html, '<title>'.__('KW').' '), 'eine Beschriftung je Woche');
        $this->assertMatchesRegularExpression('/<input type="radio" name="zeitraum" value="26w" class="sr-only" checked>/', $html);
        $this->assertStringContainsString('value="12w"', $html);
        $this->assertStringContainsString('value="52w"', $html);
        $this->assertStringContainsString(__('Als Tabelle'), $html);
    }

    #[Test]
    public function erfassung_zaehlt_nur_betriebssummen_ohne_geloeschte_noten(): void
    {
        $user = $this->lernende([4.0, 5.0, 3.5]);
        $geloescht = Note::factory()->create(['lernender_id' => $user->lernender->lernender_id, 'kategorie_id' => $this->fach, 'note_wert' => 2.0]);
        $geloescht->delete();

        $paket = $this->actingAs($this->admin)->getJson(route('admin.dashboard'))->assertOk()->json();

        $this->assertSame(3, array_sum($paket['diagramm']['erfassung']['werte']));
        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString($user->nachname, substr($html, (int) strpos($html, 'id="np-erfassung"'), 6000), 'die Säulen tragen keine Personen');
    }

    #[Test]
    public function die_histogrammklassen_summieren_zur_zahl_der_zeugnisnoten_ohne_geloeschte(): void
    {
        $user = $this->lernende([3.2, 4.0, 4.6, 5.9]);
        $this->lernende([6.0, 4.0]);
        $geloescht = Note::factory()->create(['lernender_id' => $user->lernender->lernender_id, 'kategorie_id' => $this->fach, 'note_wert' => 1.5]);
        $geloescht->delete();

        $antwort = $this->actingAs($this->admin)->getJson(route('admin.reports.grades', ['semester' => 'alle']))->assertOk();
        $histogramm = $antwort->json('diagramm.histogramm');

        $this->assertCount(20, $histogramm['werte'], 'Viertelnoten von 1 bis 6');
        $this->assertSame(0.25, $histogramm['breite']);
        $this->assertSame(6, $histogramm['n']);
        $this->assertSame(6, array_sum($histogramm['werte']));
        $this->assertSame(2, $histogramm['werte'][19], '5.9 und 6.0 gehören zur letzten Klasse');
        $this->assertSame(0, $histogramm['werte'][2], 'die gelöschte 1.5 zählt nicht');
        $this->assertSame(6, array_sum(array_map('intval', array_column($antwort->json('tabelle.histogramm.zeilen'), 1))), 'die Tabelle nennt dieselben Zahlen');
    }

    #[Test]
    public function der_median_der_verteilung_ist_unter_drei_noten_null(): void
    {
        $this->lernende([4.0, 5.0]);

        $zwei = $this->actingAs($this->admin)->getJson(route('admin.reports.grades', ['semester' => 'alle']))->assertOk();
        $this->assertSame(2, $zwei->json('diagramm.histogramm.n'));
        $this->assertNull($zwei->json('diagramm.histogramm.median'));
        $this->assertNull($zwei->json('diagramm.histogramm.quartile'));
        $html = $this->actingAs($this->admin)->get(route('admin.reports.grades', ['semester' => 'alle']))->assertOk()->getContent();
        $this->assertStringNotContainsString(__('Median').' ', substr($html, (int) strpos($html, 'id="np-histogramm"'), 1500));

        $this->lernende([6.0]);
        $drei = $this->actingAs($this->admin)->getJson(route('admin.reports.grades', ['semester' => 'alle']))->assertOk();
        $this->assertSame(3, $drei->json('diagramm.histogramm.n'));
        $this->assertEquals(5.0, $drei->json('diagramm.histogramm.median'));
        $this->assertNotNull($drei->json('diagramm.histogramm.quartile'));
    }

    #[Test]
    public function die_lehrjahre_stehen_je_lehrjahr_auf_einer_achse_und_der_median_braucht_drei_personen(): void
    {
        $this->lernende([4.0], 2);
        $this->lernende([5.0], 3);
        foreach ([3.5, 4.5, 5.5] as $wert) {
            $this->lernende([$wert], 20);
        }

        $antwort = $this->actingAs($this->admin)->getJson(route('admin.reports.grades', ['semester' => 'alle']))->assertOk();
        $jahre = collect($antwort->json('diagramm.lehrjahre.jahre'))->keyBy('jahr');

        $this->assertSame([1, 2], $jahre->keys()->all());
        $this->assertSame(2, $jahre[1]['anzahl']);
        $this->assertNull($jahre[1]['median'], 'zwei Personen: kein Median');
        $this->assertSame(3, $jahre[2]['anzahl']);
        $this->assertEquals(4.5, $jahre[2]['median']);
        $this->assertEquals(4.0, $antwort->json('diagramm.lehrjahre.grenze'));

        $html = $this->actingAs($this->admin)->get(route('admin.reports.grades', ['semester' => 'alle']))->assertOk()->getContent();
        $this->assertStringContainsString('id="np-lehrjahre"', $html);
        $this->assertStringContainsString('data-np-baustein="lehrjahre"', $html);
        $this->assertStringContainsString('id="np-histogramm"', $html);
    }

    #[Test]
    public function die_filter_kategorie_und_lehrjahr_stehen_in_der_leiste_und_im_export_link(): void
    {
        $this->lernende([4.0, 5.0, 3.0], 2);

        $html = $this->actingAs($this->admin)->get(route('admin.reports.grades', ['semester' => 'alle', 'kategorie' => $this->fach, 'lehrjahr' => 1]))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<select name="kategorie" id="kategorie"/', $html);
        $this->assertMatchesRegularExpression('/<option value="'.$this->fach.'" selected/', $html);
        $this->assertMatchesRegularExpression('/<option value="1" selected/', $html);
        $this->assertMatchesRegularExpression('/href="[^"]*grades\/export[^"]*kategorie='.$this->fach.'[^"]*lehrjahr=1[^"]*" data-behalte-filter/', $html);
        foreach (['data-np-kennzahlen', 'data-np-kategorien', 'data-np-schwach', 'id="lernende"'] as $anker) {
            $this->assertStringContainsString($anker, $html, 'Ersatzbereich '.$anker);
        }
    }

    #[Test]
    public function berufsbildner_bekommen_weder_html_noch_paket_des_admin_bereichs(): void
    {
        $berufsbildner = User::factory()->berufsbildner()->create();
        $this->lernende([4.0, 5.0, 6.0]);

        foreach (['admin.dashboard', 'admin.reports.grades'] as $route) {
            $this->actingAs($berufsbildner)->get(route($route))->assertForbidden();
            $this->actingAs($berufsbildner)->getJson(route($route))->assertForbidden();
        }
    }
}
