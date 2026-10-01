<?php

declare(strict_types=1);

namespace Tests\Feature\Berufsbildner;

use App\Models\Betreuung;
use App\Models\Kategorie;
use App\Models\Note;
use App\Models\User;
use App\Services\Uebersicht;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Dashboard, Lernendenliste, Cockpit, Admin-Übersicht und Notenseite zählen dieselben Noten als neu. */
class UngelesenTest extends TestCase
{
    private User $bb;

    private User $lernender;

    private int $lernenderId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bb = User::factory()->berufsbildner()->create(['vorname' => 'Bea', 'nachname' => 'Betreuerin']);
        $this->lernender = User::factory()->lernender()->create();
        $this->lernenderId = (int) $this->lernender->lernender->lernender_id;
        Betreuung::factory()->create(['berufsbildner_id' => $this->bb->berufsbildner->berufsbildner_id, 'lernender_id' => $this->lernenderId]);
    }

    /** @return array<string, int> */
    private function zaehler(): array
    {
        $this->actingAs($this->bb);
        $uebersicht = app(Uebersicht::class);
        $seite = $this->get(route('trainer.learners.grades.index', $this->lernenderId))->assertOk();

        return [
            'dashboard' => $uebersicht->berufsbildner($this->bb)['zeilen']->firstWhere('lernender.lernender_id', $this->lernenderId)->neu,
            'cockpit' => $uebersicht->lernendenDetail($this->lernender->lernender, 'trainer', $this->bb)['neu'],
            'liste' => $this->get(route('trainer.learners.index'))->viewData('zeilen')->firstWhere('lernender.lernender_id', $this->lernenderId)->ungelesen,
            'admin' => collect($uebersicht->admin()['proBb'])->firstWhere('name', 'Bea Betreuerin')->neu,
            'notenseite' => $seite->viewData('neuCount'),
            'markiert' => substr_count($seite->getContent(), 'role="img" aria-label="'.__('Neu').'"'),
        ];
    }

    private function alleGleich(int $erwartet): void
    {
        $this->assertSame(array_fill_keys(['dashboard', 'cockpit', 'liste', 'admin', 'notenseite', 'markiert'], $erwartet), $this->zaehler());
    }

    private function sehen(Note $note): void
    {
        DB::table('noten_gesehen')->upsert([['note_id' => $note->note_id, 'viewer_benutzer_id' => $this->bb->benutzer_id, 'gesehen_am' => now()]],
            ['note_id', 'viewer_benutzer_id'], ['gesehen_am']);
    }

    #[Test]
    public function aenderung_der_lernenden_macht_eine_gesehene_note_wieder_neu(): void
    {
        $note = Note::factory()->create(['lernender_id' => $this->lernenderId, 'erfasst_von_benutzer_id' => $this->lernender->benutzer_id]);
        $this->alleGleich(1);

        $this->sehen($note);
        $this->alleGleich(0);

        $this->travel(5)->minutes();
        $note->update(['note_wert' => 4.5, 'aktualisiert_von_benutzer_id' => $this->lernender->benutzer_id]);
        $this->alleGleich(1);
    }

    #[Test]
    public function eigenes_zaehlt_nie_als_neu(): void
    {
        // selbst erfasst, selbst korrigiert, selbst kommentiert
        $eigene = Note::factory()->create(['lernender_id' => $this->lernenderId, 'erfasst_von_benutzer_id' => $this->bb->benutzer_id]);
        $gesehen = Note::factory()->create(['lernender_id' => $this->lernenderId, 'erfasst_von_benutzer_id' => $this->lernender->benutzer_id]);
        $this->sehen($gesehen);
        $this->travel(5)->minutes();
        $gesehen->update(['note_wert' => 5.0, 'aktualisiert_von_benutzer_id' => $this->bb->benutzer_id]);
        DB::table('noten_kommentare')->insert(['note_id' => $gesehen->note_id, 'autor_benutzer_id' => $this->bb->benutzer_id, 'kommentar_text' => 'Gut.', 'erstellt_am' => now()]);
        $this->alleGleich(0);

        // Antwort der Lernenden macht sie neu
        $this->travel(5)->minutes();
        DB::table('noten_kommentare')->insert(['note_id' => $eigene->note_id, 'autor_benutzer_id' => $this->lernender->benutzer_id, 'kommentar_text' => 'Danke', 'erstellt_am' => now()]);
        $this->alleGleich(1);
    }

    #[Test]
    public function stammdatenpflege_am_fach_macht_noten_nicht_neu(): void
    {
        $note = Note::factory()->create(['lernender_id' => $this->lernenderId, 'erfasst_von_benutzer_id' => $this->lernender->benutzer_id]);
        $this->sehen($note);
        $this->travel(5)->minutes();

        $neueKategorie = Kategorie::where('code', 'ABU')->value('kategorie_id');
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.master-data.subjects.update', $note->fach_id), [
                'name' => $note->fach->name, 'kurzname' => $note->fach->kurzname, 'kategorie_id' => $neueKategorie, 'track_typ' => $note->fach->track_typ, 'aktiv' => 1,
            ])->assertSessionHasNoErrors();

        $this->assertSame((int) $neueKategorie, (int) $note->fresh()->kategorie_id);
        $this->alleGleich(0);
    }
}
