<?php

declare(strict_types=1);

namespace Tests\Feature\Berufsbildner;

use App\Models\Betreuung;
use App\Models\Note;
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
            ->get(route('trainer.dashboard', ['sort' => 'gesamt', 'dir' => 'desc']))
            ->assertOk()
            ->getContent();

        $html = $this->tabelle($html);
        $this->assertLessThan(strpos($html, 'Tiefnote'), strpos($html, 'Hochnote'));

        $htmlAsc = $this->actingAs($bb)
            ->get(route('trainer.dashboard', ['sort' => 'gesamt', 'dir' => 'asc']))
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
        $ungueltig = $this->actingAs($bb)->get(route('trainer.dashboard', ['sort' => 'unbekannt']))->assertOk()->getContent();

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
            ->get(route('trainer.dashboard', ['sort' => 'gesamt', 'dir' => 'desc']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('aria-sort="descending"', $html);
    }

    #[Test]
    public function mobiler_segmentfilter_ist_nicht_ab_sm_versteckt(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $lernender = User::factory()->lernender()->create();

        Betreuung::factory()->create([
            'berufsbildner_id' => $bb->berufsbildner->berufsbildner_id,
            'lernender_id' => $lernender->lernender->lernender_id,
        ]);

        $html = $this->actingAs($bb)->get(route('trainer.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('role="radiogroup"', $html);
        $this->assertStringNotContainsString('hidden items-center gap-1 rounded-lg bg-surface-2 p-0.5 text-xs sm:inline-flex', $html);
        $this->assertStringContainsString('overflow-x-auto', $html);
    }

    /** Nur der Tabellenkörper «Meine Lernenden»: Namen stehen weiter oben schon in «Handlungsbedarf» (andere Reihenfolge). */
    private function tabelle(string $html): string
    {
        $start = strpos($html, '<tbody>');
        $this->assertNotFalse($start, 'Tabelle «Meine Lernenden» fehlt');

        return substr($html, $start, (int) strpos($html, '</tbody>', $start) - $start);
    }
}
