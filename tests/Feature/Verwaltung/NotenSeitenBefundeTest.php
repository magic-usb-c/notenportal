<?php

namespace Tests\Feature\Verwaltung;

use App\Models\Pruefung;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Funktionsprüfung R6, Gruppe «verwaltung-noten»: Breite der Notenliste, Leerzustand beim Erfassen,
 * Lernenden-Wechsel ohne Seitenwechsel beim Blättern, Leerzustand der Prüfungstermine.
 */
class NotenSeitenBefundeTest extends TestCase
{
    use VerwaltungTestHilfen;

    #[Test]
    public function notenliste_fuellt_die_seite_wie_die_uebersicht(): void
    {
        $lernender = $this->neuerLernender();
        $bb = $this->verwalter('trainer', $lernender);

        $inhalt = $this->actingAs($bb)
            ->get(route('trainer.learners.grades.index', $lernender->lernender_id))
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('max-w-[100rem]', $inhalt);
    }

    #[Test]
    public function erfassen_ohne_faecher_zeigt_leerzustand_mit_weg_zum_profil_statt_formular(): void
    {
        $lernender = $this->neuerLernender();
        $admin = User::factory()->admin()->create();

        $antwort = $this->actingAs($admin)
            ->get(route('admin.learners.grades.create', $lernender->lernender_id))
            ->assertOk();
        $this->assertSame([], $antwort->viewData('bezugOptionen'));

        $profil = route('admin.learners.show', [$lernender->lernender_id, 'tab' => 'profil']);
        $antwort->assertSee('Keine Fächer oder Module')
            ->assertSee('href="'.$profil.'"', false)
            ->assertDontSee('text-note-knapp', false)
            ->assertDontSee('action="'.route('admin.learners.grades.store', $lernender->lernender_id).'"', false);
    }

    #[Test]
    public function lernenden_wechsel_ist_ein_menue_mit_links_und_kein_select_mit_sofortsprung(): void
    {
        $lernender = $this->neuerLernender();
        $andere = $this->neuerLernender();
        $bb = $this->verwalter('trainer', $lernender);
        $this->betreue($bb, $andere);

        $inhalt = $this->actingAs($bb)
            ->get(route('trainer.learners.grades.index', $lernender->lernender_id))
            ->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/<select[^>]*lernenden_wechseln/', $inhalt);
        $this->assertStringNotContainsString('x-on:change="if ($el.value) window.location.href', $inhalt);
        $this->assertStringContainsString('href="'.route('trainer.learners.grades.index', $andere->lernender_id).'"', $inhalt);
        $this->assertStringContainsString('id="lernenden_wechseln"', $inhalt);
    }

    #[Test]
    public function leere_pruefungstermine_ohne_filter_bieten_alle_termine_nicht_an(): void
    {
        $lernender = $this->neuerLernender();
        $bb = $this->verwalter('trainer', $lernender);
        $this->assertSame(0, Pruefung::count());

        $this->actingAs($bb)->get(route('trainer.exams.index'))
            ->assertOk()
            ->assertSee('Keine Prüfungstermine für die aktuelle Auswahl')
            ->assertDontSee('Alle Termine');
    }

    #[Test]
    public function leere_pruefungstermine_mit_filter_bieten_alle_termine_an(): void
    {
        $lernender = $this->neuerLernender();
        $bb = $this->verwalter('trainer', $lernender);

        $this->actingAs($bb)->get(route('trainer.exams.index', ['lernender_id' => $lernender->lernender_id]))
            ->assertOk()->assertSee('Alle Termine');
        $this->actingAs($bb)->get(route('trainer.exams.index', ['zeitraum' => '7']))
            ->assertOk()->assertSee('Alle Termine');
    }
}
