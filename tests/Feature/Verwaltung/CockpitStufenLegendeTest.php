<?php

declare(strict_types=1);

namespace Tests\Feature\Verwaltung;

use App\Models\Note;
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
