<?php

declare(strict_types=1);

namespace Tests\Feature\Statistik;

use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Lehrberuf;
use App\Models\Modul;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Die Seiten der Lernenden zeigen die Darstellungen S1–S6 (docs/auftrag/GUI-R6.md §6): jede mit einer Zusammenfassung
 * in einem Satz mit Zahl, der Tabelle in einem <details> und einem Leerzustand statt eines leeren Diagramms.
 */
class LernendeStatistikTest extends TestCase
{
    use DiagrammTabellenHilfen;

    private User $lernender;

    private User $ohneNoten;

    private int $lehrberufId;

    private int $zweitesSemester;

    protected function setUp(): void
    {
        parent::setUp();

        $kategorieId = (int) Kategorie::where('code', 'FACH')->value('kategorie_id');
        $lehrberuf = Lehrberuf::factory()->create();
        $this->lehrberufId = (int) $lehrberuf->lehrberuf_id;
        $fach = Fach::factory()->create(['track_typ' => null, 'kategorie_id' => $kategorieId]);
        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $lehrberuf->lehrberuf_id, 'fach_id' => $fach->fach_id, 'aktiv' => 1]);

        $start = Carbon::parse('2020-08-01');
        $semester = [];
        foreach ([1, 2] as $i) {
            $ende = $start->copy()->addMonths(6)->subDay();
            $semester[] = Semester::factory()->create([
                'bezeichnung' => 'S'.$i.'-'.fake()->unique()->numberBetween(1, 99999),
                'start_datum' => $start->toDateString(),
                'end_datum' => $ende->toDateString(),
                'sortierung' => $i,
            ]);
            $start = $ende->copy()->addDay();
        }
        $this->zweitesSemester = (int) $semester[1]->semester_id;
        // das laufende Semester gehört zur Agenda (Prüfungen planen)
        Semester::factory()->create();

        $lehrzeit = ['lehrberuf_id' => $lehrberuf->lehrberuf_id, 'lehrbeginn' => now()->subMonths(18)->toDateString(), 'lehrende' => now()->addMonths(30)->toDateString()];
        $this->lernender = User::factory()->lernender($lehrzeit)->create();
        $this->ohneNoten = User::factory()->lernender($lehrzeit)->create();

        foreach ([[$semester[0], 4.0], [$semester[1], 5.0]] as [$s, $wert]) {
            DB::table('noten')->insert([
                'lernender_id' => $this->lernender->lernender->lernender_id,
                'kategorie_id' => $kategorieId,
                'semester_id' => $s->semester_id,
                'fach_id' => $fach->fach_id,
                'modul_belegung_id' => null,
                'titel' => 'Note',
                'pruefungsdatum' => Carbon::parse($s->start_datum)->addDays(10)->toDateString(),
                'note_wert' => $wert,
                'gewichtung_prozent' => 100,
                'erfasst_von_benutzer_id' => $this->lernender->benutzer_id,
            ]);
        }

        // eine geplante Prüfung in zwei Wochen für die Agenda
        $modul = Modul::factory()->create();
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $lehrberuf->lehrberuf_id, 'modul_id' => $modul->modul_id,
            'kategorie_id' => Kategorie::where('code', 'UEK')->value('kategorie_id')]);
        $this->actingAs($this->lernender)->post(route('learner.exams.store'), [
            'bezug' => 'modul:'.$modul->modul_id, 'datum' => now()->addWeeks(2)->toDateString(), 'gewichtung_prozent' => 40, 'titel' => 'LB2',
        ])->assertSessionHasNoErrors();
        auth()->logout();
    }

    /**
     * Zusammenfassung enthält mindestens eine Zahl; die Tabelle steht in einem <details>. Mit $abschnitt (Attribut im
     * öffnenden Tag des Bausteins) gilt das für den Baustein selbst, nicht irgendwo auf der Seite.
     */
    private function pruefeDarstellung(string $html, string $satz, string $abschnitt = ''): void
    {
        $this->assertMatchesRegularExpression('/\d/', $satz, 'Zusammenfassung ohne Zahl: '.$satz);
        $this->assertStringContainsString(e($satz), $html);
        if ($abschnitt !== '') {
            $this->assertSame(1, preg_match('/'.preg_quote($abschnitt, '/').'.*?<\/section>/s', $html, $treffer), 'Baustein '.$abschnitt.' fehlt');
            $html = $treffer[0];
            $this->assertStringContainsString(e($satz), $html);
        }
        $this->assertStringContainsString('<details', $html);
        $this->assertStringContainsString('<table', $html);
    }

    #[Test]
    public function s1_und_s2_verlauf_und_wo_stehe_ich_haben_satz_tabelle_und_gemeinsame_filter(): void
    {
        $antwort = $this->actingAs($this->lernender)->get(route('learner.dashboard'))->assertOk();
        $html = $antwort->getContent();
        $paket = $antwort->viewData('statistik');

        $this->pruefeDarstellung($html, $paket['zusammenfassung']['verlauf'], 'x-data="npVerlauf(');
        $this->pruefeDarstellung($html, $paket['zusammenfassung']['wostehe'], 'data-np-baustein="wostehe"');
        $this->assertStringContainsString('data-np-verlauf-chart', $html);
        $this->assertStringContainsString('<canvas', $html);
        // eine Leiste für beide: Zeitraum, Kategorie, Fach, Verlaufsachse und Wo-stehe-ich-Ansicht
        $this->assertStringContainsString('x-data="npFilter(', $html);
        foreach (['zeitraum', 'kategorie', 'fach', 'achse', 'modus'] as $name) {
            $this->assertStringContainsString('name="'.$name.'"', $html);
        }
    }

    #[Test]
    public function s1_zeitraum_aus_der_adresse_steht_in_der_leiste_und_in_der_tabelle(): void
    {
        $antwort = $this->actingAs($this->lernender)->get(route('learner.dashboard', ['zeitraum' => 'semester']))->assertOk();

        $this->assertMatchesRegularExpression('/<option value="semester"\s+selected/', $antwort->getContent());
        $this->assertSame('semester', $antwort->viewData('statistik')['filter']['zeitraum']);
        // dieselbe Adresse als JSON liefert dieselben Zeilen wie die Tabelle im HTML
        $json = $this->actingAs($this->lernender)->getJson(route('learner.dashboard', ['zeitraum' => 'semester']))->assertOk();
        $this->assertSame($antwort->viewData('statistik')['tabelle']['verlauf'], $json->json('tabelle.verlauf'));
    }

    #[Test]
    public function ohne_noten_gibt_es_den_leerzustand_und_kein_diagramm(): void
    {
        $html = $this->actingAs($this->ohneNoten)->get(route('learner.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Noch keine Noten', $html);
        $this->assertStringNotContainsString('data-np-verlauf-chart', $html);
        $this->assertStringNotContainsString('data-np-baustein="wostehe"', $html);
    }

    #[Test]
    public function s3_hantel_je_fach_hat_satz_tabelle_und_sortierung(): void
    {
        $antwort = $this->actingAs($this->lernender)->get(route('learner.grades.index', ['semester_id' => $this->zweitesSemester]))->assertOk();
        $paket = $antwort->viewData('statistik');

        $this->assertNotSame([], $paket['diagramm']['hantel']['zeilen']);
        $this->pruefeDarstellung($antwort->getContent(), $paket['zusammenfassung']['hantel'], 'data-np-baustein="hantel"');
        $this->assertStringContainsString('name="sort"', $antwort->getContent());
        $this->assertStringContainsString('name="semester_id" value="'.$this->zweitesSemester.'"', $antwort->getContent());

        $this->actingAs($this->lernender)->get(route('learner.grades.index', ['semester_id' => $this->zweitesSemester, 'sort' => 'fach']))
            ->assertOk()->assertViewHas('statistik', fn (array $s) => $s['filter']['sort'] === 'fach');
    }

    #[Test]
    public function s3_ohne_vorsemester_zeigt_keine_leere_hantel(): void
    {
        $html = $this->actingAs($this->ohneNoten)->get(route('learner.grades.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-np-baustein="hantel"', $html);
    }

    #[Test]
    public function ohne_noten_zeigen_rechner_abschluss_und_agenda_keine_leeren_diagramme(): void
    {
        $rechner = $this->actingAs($this->ohneNoten)->get(route('learner.grades.calculator'))->assertOk()->getContent();
        // die Kurve rechnet im Browser und bleibt ohne Ergebnis verborgen (x-show), ein leeres Diagramm sieht niemand
        $this->assertStringContainsString('x-show="kurve" x-cloak', $rechner);

        $abschluss = $this->actingAs($this->ohneNoten)->get(route('learner.qualification.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Was fehlt zum Bestehen', $abschluss);

        $agenda = $this->actingAs($this->ohneNoten)->get(route('learner.exams.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-np-baustein="zeitleiste"', $agenda);
        $this->assertStringNotContainsString('<canvas', $agenda);
    }

    #[Test]
    public function s4_rechner_traegt_kurve_mit_spielraum_und_tabelle_in_details(): void
    {
        $html = $this->actingAs($this->lernender)->get(route('learner.grades.calculator'))->assertOk()->getContent();

        $this->assertStringContainsString("npChart('kurve')", $html);
        $this->assertStringContainsString('<details', $html);
        $this->assertStringContainsString('Ergebnis je Note in den offenen Prüfungen', $html);
    }

    #[Test]
    public function s5_abschluss_ohne_offene_positionen_zeigt_keine_leere_karte(): void
    {
        $html = $this->actingAs($this->lernender)->get(route('learner.qualification.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Was fehlt zum Bestehen', $html);
    }

    #[Test]
    public function s6_agenda_zeigt_zeitleiste_mit_noetiger_note_und_den_zeitraum_aus_der_adresse(): void
    {
        $antwort = $this->actingAs($this->lernender)->get(route('learner.exams.index'))->assertOk();
        $paket = $antwort->viewData('statistik');
        $html = $antwort->getContent();

        $this->assertSame('8w', $paket['filter']['zeitraum']);
        $this->assertNotSame([], $paket['diagramm']['zeitleiste']['zeilen'], 'Die geplante Prüfung liegt in 8 Wochen');
        $this->pruefeDarstellung($html, $paket['zusammenfassung']['zeitleiste'], 'data-np-baustein="zeitleiste"');
        $this->assertStringContainsString(e($paket['diagramm']['zeitleiste']['zeilen'][0]['text']), $html);
        $this->assertStringContainsString('data-np-agendaliste', $html);

        // 4 Wochen enthalten die Prüfung in zwei Wochen ebenfalls, ein ungültiger Wert fällt auf den Standard
        $this->actingAs($this->lernender)->get(route('learner.exams.index', ['zeitraum' => '4w']))
            ->assertOk()->assertViewHas('statistik', fn (array $s) => $s['filter']['zeitraum'] === '4w');
        $this->actingAs($this->lernender)->get(route('learner.exams.index', ['zeitraum' => 'x']))
            ->assertOk()->assertViewHas('statistik', fn (array $s) => $s['filter']['zeitraum'] === '8w');
    }

    #[Test]
    public function s6_monat_ist_ein_tauschbarer_block_mit_richtung(): void
    {
        $html = $this->actingAs($this->lernender)->get(route('learner.exams.index', ['ansicht' => 'monat']))->assertOk()->getContent();

        $this->assertStringContainsString('data-np-monat', $html);
        $this->assertStringContainsString("wechsle(\$el.href, 'vor')", $html);
        $this->assertStringContainsString("wechsle(\$el.href, 'zurueck')", $html);
    }

    #[Test]
    public function tabellen_der_diagramme_tragen_eine_beschriftung_und_zeilenkoepfe(): void
    {
        $dashboard = $this->actingAs($this->lernender)->get(route('learner.dashboard'))->assertOk();
        $this->pruefeZeilenkopf(
            $this->tabelleNach($dashboard->getContent(), 'x-data="npVerlauf('),
            $dashboard->viewData('statistik')['meta']['verlauf']['titel'],
        );

        $noten = $this->actingAs($this->lernender)->get(route('learner.grades.index', ['semester_id' => $this->zweitesSemester]))->assertOk();
        $this->pruefeZeilenkopf(
            $this->tabelleNach($noten->getContent(), 'data-np-baustein="hantel"'),
            $noten->viewData('statistik')['meta']['hantel']['titel'],
        );

        $agenda = $this->actingAs($this->lernender)->get(route('learner.exams.index'))->assertOk();
        $this->pruefeZeilenkopf(
            $this->tabelleNach($agenda->getContent(), 'data-np-baustein="zeitleiste"'),
            $agenda->viewData('statistik')['meta']['zeitleiste']['titel'],
        );

        $rechner = $this->actingAs($this->lernender)->get(route('learner.grades.calculator'))->assertOk()->getContent();
        $this->pruefeZeilenkopf($this->tabelleNach($rechner, 'x-show="kurve" x-cloak'), 'Ergebnis je Note in den offenen Prüfungen');
    }

    #[Test]
    public function rechner_kurventabelle_stellt_die_note_rechtsbuendig_wie_das_ergebnis(): void
    {
        $html = $this->actingAs($this->lernender)->get(route('learner.grades.calculator'))->assertOk()->getContent();
        $tabelle = $this->tabelleNach($html, 'x-show="kurve" x-cloak');

        $this->assertStringContainsString('text-right', $tabelle->query('//thead//th[1]')->item(0)->getAttribute('class'));
        $this->assertStringContainsString('text-right', $tabelle->query('//tbody//tr/*[1]')->item(0)->getAttribute('class'));
    }

    #[Test]
    public function rechner_diagrammhuelle_ist_fokussierbar_und_hat_von_anfang_an_einen_namen(): void
    {
        $html = $this->actingAs($this->lernender)->get(route('learner.grades.calculator'))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<div\b[^>]*npChart\(&#039;kurve&#039;\)[^>]*>|<div\b[^>]*npChart\(\'kurve\'\)[^>]*>/', $html, $huelle));
        $this->assertStringContainsString('tabindex="0"', $huelle[0]);
        $this->assertStringContainsString('aria-label="Ergebnis je Note in den offenen Prüfungen"', $huelle[0]);
    }

    #[Test]
    public function hantel_haelt_namensspalte_und_achsenplatzhalter_gleich_breit_und_laesst_fachnamen_ausschreiben(): void
    {
        $html = $this->actingAs($this->lernender)->get(route('learner.grades.index', ['semester_id' => $this->zweitesSemester]))->assertOk()->getContent();
        $this->assertSame(1, preg_match('/data-np-baustein="hantel".*?<\/section>/s', $html, $treffer));
        $this->assertGreaterThan(0, preg_match_all('/<li class="flex items-center gap-3[^"]*">\s*<span class="([^"]*)"/', $treffer[0], $spalten));

        // Zeilen und Achsenzeile (Platzhalter) teilen sich dieselbe Breite, sonst sitzen die Ziffern neben den Punkten
        $breiten = array_unique(array_map(fn (string $k) => preg_match('/\bw-\d+\b/', $k, $b) ? $b[0] : '', $spalten[1]));
        $this->assertCount(1, $breiten, 'Namensspalte und Achsenplatzhalter sind verschieden breit: '.implode(', ', $breiten));
        $this->assertNotContains('w-40', $breiten, 'Fachnamen werden bei 160 px gekürzt');
    }

    #[Test]
    public function aktionslinks_in_leerzustaenden_des_dashboards_haben_24_px_trefferflaeche(): void
    {
        // «Prüfung planen»: Noten da, aber nichts geplant; «Erste Note erfassen»: Prüfung geplant, noch keine Note
        DB::table('pruefungen')->where('lernender_id', $this->lernender->lernender->lernender_id)
            ->update(['lernender_id' => $this->ohneNoten->lernender->lernender_id]);

        $ohnePruefung = $this->actingAs($this->lernender)->get(route('learner.dashboard'))->assertOk()->getContent();
        $ohneNote = $this->actingAs($this->ohneNoten)->get(route('learner.dashboard'))->assertOk()->getContent();

        foreach ([['Prüfung planen', $ohnePruefung], ['Erste Note erfassen', $ohneNote]] as [$text, $html]) {
            preg_match_all('/<a\b[^>]*class="([^"]*)"[^>]*>\s*'.preg_quote($text, '/').'\s*<\/a>/u', $html, $links);
            // Textlink als Aktion, nicht der Knopf der Kopfzeile
            $textlinks = array_values(array_filter($links[1], fn (string $k) => ! str_contains($k, 'np-knopf')));
            $this->assertNotSame([], $textlinks, 'Textlink fehlt: '.$text);
            foreach ($textlinks as $klasse) {
                $this->assertStringContainsString('min-h-6', $klasse, $text);
                $this->assertStringContainsString('inline-flex', $klasse, $text);
            }
        }
    }
}
