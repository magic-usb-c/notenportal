<?php

namespace Tests\Feature\Verwaltung;

use App\Models\Fach;
use App\Models\Lernender;
use App\Models\Note;
use App\Models\Semester;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Berufsbildner korrigieren Noten (mit «geändert von»), legen aber keine an und
 * löschen keine. Admin darf alles.
 */
class NotenKorrekturTest extends TestCase
{
    use VerwaltungTestHilfen;

    private User $bb;

    private User $lernenderUser;

    private Lernender $lernender;

    private Note $note;

    protected function setUp(): void
    {
        parent::setUp();
        // Der Änderungshinweis wird serverseitig mit dem Datum des Requests
        // gebaut, die Erwartung im Test erst danach – über Mitternacht liefen
        // beide auseinander.
        $this->freezeTime();

        $this->lernenderUser = User::factory()->lernender()->create();
        $this->lernender = $this->lernenderUser->lernender;
        $semester = Semester::factory()->create();
        $this->bmsTrack($this->lernender, $semester->semester_id);

        $this->note = Note::factory()->create([
            'lernender_id' => $this->lernender->lernender_id,
            'semester_id' => $semester->semester_id,
            'fach_id' => Fach::factory()->create(['track_typ' => 'BMS'])->fach_id,
        ]);

        $this->bb = User::factory()->berufsbildner()->create();
        $this->betreue($this->bb, $this->lernender);
    }

    #[Test]
    public function notenliste_filter_hat_ohne_js_einen_absende_button(): void
    {
        $this->actingAs($this->bb)
            ->get(route('trainer.learners.grades.index', $this->lernender->lernender_id))
            ->assertOk()
            ->assertSeeInOrder(['<noscript>', 'Filtern', '</noscript>'], false);
    }

    #[Test]
    public function berufsbildner_korrigiert_note_und_lernender_sieht_geaendert_von(): void
    {
        $this->actingAs($this->bb)
            ->put(route('trainer.learners.grades.update', [$this->lernender->lernender_id, $this->note->note_id]), $this->formular())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('trainer.learners.grades.index', $this->lernender->lernender_id));

        $note = $this->note->fresh();
        $this->assertSame((int) $this->bb->benutzer_id, (int) $note->aktualisiert_von_benutzer_id);
        $this->assertEquals(5.25, (float) $note->note_wert);

        $hinweis = 'geändert von '.$this->bb->vorname.' '.$this->bb->nachname.', '.now()->format('d.m.Y');

        $this->actingAs($this->lernenderUser)
            ->get(route('learner.grades.index', ['semester_id' => $note->semester_id]))
            ->assertOk()
            ->assertSee($hinweis);

        $this->actingAs($this->bb)
            ->get(route('trainer.learners.grades.index', $this->lernender->lernender_id))
            ->assertSee($hinweis);
    }

    #[Test]
    public function eigene_aenderung_des_lernenden_zeigt_keinen_hinweis(): void
    {
        $this->note->update(['aktualisiert_von_benutzer_id' => $this->lernenderUser->benutzer_id]);

        $this->actingAs($this->lernenderUser)
            ->get(route('learner.grades.index', ['semester_id' => $this->note->semester_id]))
            ->assertOk()
            ->assertDontSee('geändert von');
    }

    #[Test]
    public function berufsbildner_darf_keine_noten_anlegen_oder_loeschen(): void
    {
        $id = $this->lernender->lernender_id;

        $this->actingAs($this->bb)->get(route('trainer.learners.grades.create', $id))->assertForbidden();
        $this->actingAs($this->bb)->post(route('trainer.learners.grades.store', $id), $this->formular())->assertForbidden();
        $this->actingAs($this->bb)->delete(route('trainer.learners.grades.destroy', [$id, $this->note->note_id]))->assertForbidden();

        $this->assertDatabaseCount('noten', 1);
        $this->assertNotSoftDeleted($this->note);

        $this->actingAs($this->bb)->get(route('trainer.learners.grades.index', $id))
            ->assertOk()
            ->assertSee('Korrigieren')
            ->assertDontSee(route('trainer.learners.grades.create', $id))
            ->assertDontSee('Note löschen?');
    }

    #[Test]
    public function admin_darf_noten_anlegen_korrigieren_und_loeschen(): void
    {
        $admin = User::factory()->admin()->create();
        $id = $this->lernender->lernender_id;

        $this->actingAs($admin)->post(route('admin.learners.grades.store', $id), $this->formular())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.learners.grades.index', $id));

        $neu = Note::where('lernender_id', $id)->whereKeyNot($this->note->note_id)->sole();
        $this->assertSame((int) $admin->benutzer_id, (int) $neu->erfasst_von_benutzer_id);
        $this->assertNull($neu->aktualisiert_von_benutzer_id);

        $this->actingAs($admin)->put(route('admin.learners.grades.update', [$id, $this->note->note_id]), $this->formular())
            ->assertSessionHasNoErrors();
        $this->assertSame((int) $admin->benutzer_id, (int) $this->note->fresh()->aktualisiert_von_benutzer_id);

        $this->actingAs($admin)->delete(route('admin.learners.grades.destroy', [$id, $this->note->note_id]))
            ->assertRedirect(route('admin.learners.grades.index', $id));
        $this->assertSoftDeleted($this->note);
    }

    #[Test]
    public function fremde_note_ist_ueber_den_lernenden_nicht_erreichbar(): void
    {
        $fremdeNote = Note::factory()->create(['lernender_id' => $this->neuerLernender()->lernender_id]);

        $this->actingAs($this->bb)
            ->put(route('trainer.learners.grades.update', [$this->lernender->lernender_id, $fremdeNote->note_id]), $this->formular())
            ->assertNotFound();

        $this->assertNull($fremdeNote->fresh()->aktualisiert_von_benutzer_id);
    }

    private function formular(): array
    {
        return [
            'kategorie_id' => $this->note->kategorie_id,
            'typ' => 'fach',
            'fach_id' => $this->note->fach_id,
            'titel' => 'Korrigiert',
            'pruefungsdatum' => now()->toDateString(),
            'note_wert' => 5.25,
            'gewichtung_prozent' => 100,
        ];
    }
}
