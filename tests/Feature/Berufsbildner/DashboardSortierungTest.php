<?php

declare(strict_types=1);

namespace Tests\Feature\Berufsbildner;

use App\Models\Betreuung;
use App\Models\Note;
use App\Models\Semester;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardSortierungTest extends TestCase
{
    #[Test]
    public function sortierung_nach_gesamtnote_ordnet_die_zeilen(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $hoch = User::factory()->lernender()->create(['nachname' => 'Hochnote']);
        $tief = User::factory()->lernender()->create(['nachname' => 'Tiefnote']);

        foreach ([$hoch, $tief] as $u) {
            Betreuung::factory()->create([
                'berufsbildner_id' => $bb->berufsbildner->berufsbildner_id,
                'lernender_id' => $u->lernender->lernender_id,
            ]);
        }

        Note::factory()->create(['lernender_id' => $hoch->lernender->lernender_id, 'note_wert' => 6.0]);
        Note::factory()->create(['lernender_id' => $tief->lernender->lernender_id, 'note_wert' => 2.0]);

        $html = $this->actingAs($bb)
            ->get(route('trainer.dashboard', ['order' => 'gesamt', 'dir' => 'desc']))
            ->assertOk()
            ->getContent();

        $html = $this->tabelle($html);
        $this->assertLessThan(strpos($html, 'Tiefnote'), strpos($html, 'Hochnote'));

        $htmlAsc = $this->actingAs($bb)
            ->get(route('trainer.dashboard', ['order' => 'gesamt', 'dir' => 'asc']))
            ->assertOk()
            ->getContent();

        $htmlAsc = $this->tabelle($htmlAsc);
        $this->assertLessThan(strpos($htmlAsc, 'Hochnote'), strpos($htmlAsc, 'Tiefnote'));
    }

    #[Test]
    public function ungueltiger_sortierschluessel_faellt_auf_default_zurueck(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $a = User::factory()->lernender()->create(['nachname' => 'Aaron']);
        $z = User::factory()->lernender()->create(['nachname' => 'Zumstein']);

        foreach ([$a, $z] as $u) {
            Betreuung::factory()->create([
                'berufsbildner_id' => $bb->berufsbildner->berufsbildner_id,
                'lernender_id' => $u->lernender->lernender_id,
            ]);
        }

        $standard = $this->actingAs($bb)->get(route('trainer.dashboard'))->assertOk()->getContent();
        $ungueltig = $this->actingAs($bb)->get(route('trainer.dashboard', ['order' => 'unbekannt']))->assertOk()->getContent();

        // Beide Antworten sortieren nach dem Default (Status, dann Nachname): Aaron vor Zumstein,
        // keine Spalte als aktiv markiert.
        $this->assertLessThan(strpos($this->tabelle($standard), 'Zumstein'), strpos($this->tabelle($standard), 'Aaron'));
        $this->assertLessThan(strpos($this->tabelle($ungueltig), 'Zumstein'), strpos($this->tabelle($ungueltig), 'Aaron'));
        $this->assertStringNotContainsString('aria-sort="ascending"', $ungueltig);
        $this->assertStringNotContainsString('aria-sort="descending"', $ungueltig);
    }

    #[Test]
    public function aria_sort_ist_auf_der_aktiven_spalte_gesetzt(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $lernender = User::factory()->lernender()->create();

        Betreuung::factory()->create([
            'berufsbildner_id' => $bb->berufsbildner->berufsbildner_id,
            'lernender_id' => $lernender->lernender->lernender_id,
        ]);
        Note::factory()->create(['lernender_id' => $lernender->lernender->lernender_id]);

        $html = $this->actingAs($bb)
            ->get(route('trainer.dashboard', ['order' => 'gesamt', 'dir' => 'desc']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('aria-sort="descending"', $html);
    }

    #[Test]
    public function vier_filterkacheln_stehen_ueber_der_tabelle(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $lernender = User::factory()->lernender()->create();

        Betreuung::factory()->create([
            'berufsbildner_id' => $bb->berufsbildner->berufsbildner_id,
            'lernender_id' => $lernender->lernender->lernender_id,
        ]);

        $html = $this->actingAs($bb)->get(route('trainer.dashboard'))->assertOk()->getContent();

        $kacheln = '<div role="radiogroup" x-radiogroup aria-label="'.__('Filter').'"';
        $start = strpos($html, $kacheln);
        $this->assertNotFalse($start);
        $this->assertSame(4, substr_count(substr($html, $start, strpos($html, '<table') - $start), 'role="radio"'));
    }

    #[Test]
    public function die_hantel_aendert_die_tabellensortierung_nicht(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $hoch = User::factory()->lernender()->create(['nachname' => 'Hochnote']);
        $tief = User::factory()->lernender()->create(['nachname' => 'Tiefnote']);
        foreach ([$hoch, $tief] as $u) {
            Betreuung::factory()->create(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $u->lernender->lernender_id]);
        }
        Note::factory()->create(['lernender_id' => $hoch->lernender->lernender_id, 'note_wert' => 6.0]);
        Note::factory()->create(['lernender_id' => $tief->lernender->lernender_id, 'note_wert' => 2.0]);

        foreach (['delta', 'name'] as $hantel) {
            $html = $this->actingAs($bb)
                ->get(route('trainer.dashboard', ['order' => 'gesamt', 'dir' => 'desc', 'sort' => $hantel]))
                ->assertOk()->getContent();

            $this->assertStringContainsString('aria-sort="descending"', $html, "Hantelsortierung «{$hantel}» setzt die Tabelle zurück");
            $tabelle = $this->tabelle($html);
            $this->assertLessThan(strpos($tabelle, 'Tiefnote'), strpos($tabelle, 'Hochnote'));
        }
    }

    #[Test]
    public function das_hantelformular_reicht_die_tabellensortierung_weiter(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $lernender = User::factory()->lernender()->create();
        Betreuung::factory()->create(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $lernender->lernender->lernender_id]);

        $html = $this->actingAs($bb)->get(route('trainer.dashboard', ['order' => 'gesamt', 'dir' => 'desc']))->assertOk()->getContent();
        $ende = (int) strpos($html, 'id="hantel-status"');
        $start = (int) strrpos(substr($html, 0, $ende), '<form');
        $form = substr($html, $start, (int) strpos($html, '</form>', $ende) - $start);

        $this->assertMatchesRegularExpression('/<input[^>]*type="hidden"[^>]*name="order"[^>]*value="gesamt"/', $form);
        $this->assertMatchesRegularExpression('/<input[^>]*type="hidden"[^>]*name="dir"[^>]*value="desc"/', $form);

        $ohne = $this->actingAs($bb)->get(route('trainer.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('name="order"', $ohne);
    }

    #[Test]
    public function die_kopf_links_der_tabelle_nutzen_order_und_behalten_die_hantel(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $lernender = User::factory()->lernender()->create();
        Betreuung::factory()->create(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $lernender->lernender->lernender_id]);

        $html = $this->actingAs($bb)->get(route('trainer.dashboard', ['status' => 'kritisch', 'sort' => 'name']))->assertOk()->getContent();

        preg_match('/<th[^>]*aria-sort="none"[^>]*><a href="([^"]+)"/', $html, $treffer);
        parse_str((string) parse_url(html_entity_decode($treffer[1]), PHP_URL_QUERY), $q);
        $this->assertArrayHasKey('order', $q);
        $this->assertSame('asc', $q['dir']);
        $this->assertSame('name', $q['sort'], 'Die Hantelsortierung bleibt im Kopf-Link erhalten');
        $this->assertSame('kritisch', $q['status']);
    }

    #[Test]
    public function alte_lesezeichen_mit_sort_gesamt_sortieren_weiter_die_tabelle(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $hoch = User::factory()->lernender()->create(['nachname' => 'Hochnote']);
        $tief = User::factory()->lernender()->create(['nachname' => 'Tiefnote']);
        foreach ([$hoch, $tief] as $u) {
            Betreuung::factory()->create(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $u->lernender->lernender_id]);
        }
        Note::factory()->create(['lernender_id' => $hoch->lernender->lernender_id, 'note_wert' => 6.0]);
        Note::factory()->create(['lernender_id' => $tief->lernender->lernender_id, 'note_wert' => 2.0]);

        $html = $this->actingAs($bb)->get(route('trainer.dashboard', ['sort' => 'gesamt', 'dir' => 'desc']))->assertOk()->getContent();

        $this->assertStringContainsString('aria-sort="descending"', $html);
        $tabelle = $this->tabelle($html);
        $this->assertLessThan(strpos($tabelle, 'Tiefnote'), strpos($tabelle, 'Hochnote'));
    }

    #[Test]
    public function die_hantelfilter_laden_ohne_seitenwechsel_und_ersetzen_die_liste_darunter(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $lernender = User::factory()->lernender()->create();
        Betreuung::factory()->create(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $lernender->lernender->lernender_id]);

        $html = $this->actingAs($bb)->get(route('trainer.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('x-data="npFilter(', $html, 'Filterleiste übernimmt das Absenden');
        $this->assertStringContainsString('data-np-untergenuegend', $html, 'Ziel für die Liste «unter genügend» ist immer vorhanden');
        $this->assertStringContainsString('data-np-filterstatus', $html);
        $this->assertStringNotContainsString('x-on:change="$el.form.requestSubmit()"', $html);
    }

    #[Test]
    public function das_delta_hat_einen_text_fuer_screenreader_und_keine_notenfarbe(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $lernender = User::factory()->lernender()->create();
        Betreuung::factory()->create(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $lernender->lernender->lernender_id]);
        $alt = Semester::factory()->create(['start_datum' => now()->subMonths(9)->toDateString(), 'end_datum' => now()->subMonths(3)->toDateString(), 'sortierung' => 1]);
        $neu = Semester::factory()->create(['sortierung' => 2]);
        Note::factory()->create(['lernender_id' => $lernender->lernender->lernender_id, 'semester_id' => $alt->semester_id, 'note_wert' => 5.5, 'pruefungsdatum' => now()->subMonths(5)->toDateString()]);
        Note::factory()->create(['lernender_id' => $lernender->lernender->lernender_id, 'semester_id' => $neu->semester_id, 'note_wert' => 5.0, 'pruefungsdatum' => now()->subWeek()->toDateString()]);

        $tabelle = $this->tabelle($this->actingAs($bb)->get(route('trainer.dashboard'))->assertOk()->getContent());

        $this->assertStringContainsString('▼ −0.5', $tabelle);
        $this->assertStringContainsString(__(':delta zum Vorsemester', ['delta' => '−0.5']), $tabelle);
        $this->assertMatchesRegularExpression('/<span class="sr-only">[^<]*−0\.5/u', $tabelle);
        $this->assertDoesNotMatchRegularExpression('/<span class="ml-1 text-2xs[^"]*text-note-knapp/', $tabelle, 'Ein Rückgang ist keine Notenstufe');
    }

    #[Test]
    public function die_kopf_links_werden_nach_dem_hantelfilter_neu_vom_server_geholt(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $lernender = User::factory()->lernender()->create();
        Betreuung::factory()->create(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $lernender->lernender->lernender_id]);

        $html = $this->actingAs($bb)->get(route('trainer.dashboard'))->assertOk()->getContent();

        // Die Kopf-Links tragen die Hantelwerte der Adresse beim Rendern; ohne Neuholen bleiben sie nach einem Filterwechsel veraltet
        $this->assertStringContainsString('[data-np-tabellenkopf]\u0022]', $html, 'Selektor in der Filterkonfiguration');
        $this->assertSame(1, substr_count($html, '<thead data-np-tabellenkopf>'), 'Kopfzeile der Tabelle «Meine Lernenden» ist markiert');
        $kopf = strpos($html, '<thead data-np-tabellenkopf>');
        $this->assertGreaterThan(strpos($html, '<caption class="sr-only">'.__('Meine Lernenden').'</caption>'), $kopf);
    }

    #[Test]
    public function das_ausblenden_per_filter_wird_angesagt(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $lernender = User::factory()->lernender()->create();
        Betreuung::factory()->create(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $lernender->lernender->lernender_id]);

        $html = $this->actingAs($bb)->get(route('trainer.dashboard'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<p[^>]*class="sr-only"[^>]*aria-live="polite"[^>]*x-text=/', $html);
        $this->assertStringContainsString('Lernenden angezeigt', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*x-model\\.debounce[^>]*aria-label|<input[^>]*aria-label[^>]*x-model\\.debounce/', $html, 'Die Suche wird entprellt, die Ansage läuft nicht bei jedem Tastendruck');
    }

    /** Nur der Tabellenkörper «Meine Lernenden»: Namen stehen weiter oben schon in «Handlungsbedarf» (andere Reihenfolge). */
    private function tabelle(string $html): string
    {
        // Die Hantel darüber hat eine eigene Tabelle («Als Tabelle»); gemeint ist die mit der Beschriftung «Meine Lernenden»
        $kopf = strpos($html, '<caption class="sr-only">'.__('Meine Lernenden').'</caption>');
        $this->assertNotFalse($kopf, 'Tabelle «Meine Lernenden» fehlt');
        $start = strpos($html, '<tbody>', $kopf);

        return substr($html, $start, (int) strpos($html, '</tbody>', $start) - $start);
    }
}
