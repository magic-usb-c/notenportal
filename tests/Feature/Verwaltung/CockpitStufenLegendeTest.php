<?php

declare(strict_types=1);

namespace Tests\Feature\Verwaltung;

use App\Models\Note;
use App\Models\User;
use App\Support\NotenSkala;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Zeugnisnoten-Heatmap im Cockpit (Admin und Berufsbildner) codiert Notenstufen per Farbe
 * (NotenSkala::badge) – dieselbe Legende wie im Lernenden-Dashboard muss zeigen, was die Farben
 * bedeuten (WCAG 1.4.1, docs/audit-backlog.md).
 */
class CockpitStufenLegendeTest extends TestCase
{
    use VerwaltungTestHilfen;

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function cockpit_zeigt_stufenlegende_bei_zeugnisnoten(string $rolle): void
    {
        $lernender = $this->neuerLernender();
        Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'note_wert' => 4.5]);
        $user = $this->verwalter($rolle, $lernender);

        $this->actingAs($user)
            ->get(route("{$rolle}.learners.show", $lernender->lernender_id))
            ->assertOk()
            ->assertSee('Zeugnisnoten')
            ->assertSeeInOrder(['ungenügend', 'knapp', 'genügend', 'gut']);
    }

    /**
     * Die Legende muss aussehen wie die Zellen, die sie erklärt: neutrale Heatmap-Zellen sind helle Chips
     * (NotenSkala::badge), nicht der Grauton der Diagrammbalken, den die Legende vorher zeigte.
     */
    #[Test]
    #[DataProvider('verwalterRollen')]
    public function legende_der_heatmap_zeigt_die_farben_der_zellen(string $rolle): void
    {
        $lernender = $this->neuerLernender();
        Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'note_wert' => 4.5]);
        $html = (string) $this->actingAs($this->verwalter($rolle, $lernender))
            ->get(route("{$rolle}.learners.show", $lernender->lernender_id))->assertOk()->getContent();

        $this->assertLegendeWieZellen($html);
    }

    #[Test]
    public function heatmap_der_lernenden_hat_dieselbe_legende(): void
    {
        $user = User::factory()->lernender()->create();
        Note::factory()->create(['lernender_id' => $user->lernender->lernender_id, 'note_wert' => 4.5]);
        $html = (string) $this->actingAs($user)->get(route('learner.grades.index'))->assertOk()->getContent();

        $this->assertLegendeWieZellen($html);
    }

    private function assertLegendeWieZellen(string $html): void
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8">'.$html, LIBXML_NOERROR);
        $muster = collect(iterator_to_array((new \DOMXPath($dom))->query('//ul[@data-legende]//span[@aria-hidden="true"]')))
            ->map(fn (\DOMElement $s) => preg_split('/\s+/', trim($s->getAttribute('class'))))->all();

        $this->assertCount(3, $muster, 'ungenügend, knapp, genügend / gut');
        foreach ([3.0, 3.7, 4.5] as $i => $note) {
            $hintergrund = collect(explode(' ', NotenSkala::badge($note)))->first(fn (string $k) => str_starts_with($k, 'bg-'));
            $this->assertContains($hintergrund, $muster[$i], "Legende für Note {$note} zeigt nicht die Zellfarbe {$hintergrund}");
        }
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function cockpit_ohne_noten_zeigt_keine_legende(string $rolle): void
    {
        $lernender = $this->neuerLernender();
        $user = $this->verwalter($rolle, $lernender);

        $this->actingAs($user)
            ->get(route("{$rolle}.learners.show", $lernender->lernender_id))
            ->assertOk()
            ->assertSee('Noch keine Noten')
            // Legende nur bei vorhandenen Zeugnisnoten – die anderen Vorkommen von "knapp"
            // (z. B. Status-Badge bg-note-knapp) sind unabhängig davon zu erwarten.
            ->assertDontSee('flex flex-wrap items-center gap-x-4 gap-y-1 text-2xs text-muted', false);
    }
}
