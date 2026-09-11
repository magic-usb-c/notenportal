<?php

declare(strict_types=1);

namespace Tests\Feature\Lernender;

use App\Models\Kategorie;
use App\Models\Modul;
use App\Models\Note;
use App\Models\Pruefung;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PruefungenTest extends TestCase
{
    private User $user;

    private Modul $modul;

    protected function setUp(): void
    {
        parent::setUp();
        Semester::factory()->create();
        $this->user = User::factory()->lernender()->create();
        $this->modul = Modul::factory()->create();
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $this->user->lernender->lehrberuf_id,
            'modul_id' => $this->modul->modul_id,
            'kategorie_id' => Kategorie::where('code', 'UEK')->value('kategorie_id'),
        ]);
    }

    private function planen(array $daten = []): void
    {
        $this->actingAs($this->user)->post(route('learner.exams.store'), [
            'bezug' => 'modul:'.$this->modul->modul_id, 'datum' => now()->addWeek()->toDateString(),
            'gewichtung_prozent' => 40, 'titel' => 'LB2', ...$daten,
        ])->assertSessionHasNoErrors();
    }

    #[Test]
    public function planen_anzeigen_und_als_note_eintragen(): void
    {
        $this->planen();
        $p = Pruefung::sole();

        $this->actingAs($this->user)->get(route('learner.exams.index'))->assertOk()->assertSee('LB2');
        $this->actingAs($this->user)->get(route('learner.grades.create', ['pruefung' => $p->pruefung_id]))
            ->assertOk()->assertSee('value="'.$p->pruefung_id.'"', false);

        $this->actingAs($this->user)->post(route('learner.grades.store'), [
            'typ' => 'modul', 'modul_id' => $this->modul->modul_id, 'pruefungsdatum' => now()->toDateString(),
            'note_wert' => 5, 'gewichtung_prozent' => 40, 'pruefung_id' => $p->pruefung_id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(Note::sole()->note_id, $p->fresh()->note_id, 'Note hängt an der Prüfung');
        $this->assertSame(0, Pruefung::query()->offen()->count());
        $this->assertSame(Kategorie::where('code', 'UEK')->value('kategorie_id'), Note::sole()->kategorie_id);

        // Note gelöscht → Prüfung wieder offen
        Note::sole()->delete();
        $this->assertNull($p->fresh()->note_id);
    }

    #[Test]
    public function vergangene_planung_erscheint_als_note_fehlt(): void
    {
        $this->planen(['datum' => now()->subDays(3)->toDateString()]);

        $this->actingAs($this->user)->get(route('learner.exams.index'))->assertSee('Note fehlt')->assertSee('vor 3 Tagen');
    }

    #[Test]
    public function fremde_module_und_fremde_planungen_sind_gesperrt(): void
    {
        $fremd = Modul::factory()->create();
        $this->actingAs($this->user)->post(route('learner.exams.store'), [
            'bezug' => 'modul:'.$fremd->modul_id, 'datum' => now()->toDateString(), 'gewichtung_prozent' => 100,
        ])->assertSessionHasErrors('bezug');

        $this->planen();
        $p = Pruefung::sole();
        $andere = User::factory()->lernender()->create();

        $this->actingAs($andere)->delete(route('learner.exams.destroy', $p->pruefung_id))->assertNotFound();
        $this->actingAs($andere)->put(route('learner.exams.update', $p->pruefung_id), [
            'bezug' => 'modul:'.$this->modul->modul_id, 'datum' => now()->toDateString(), 'gewichtung_prozent' => 10,
        ])->assertNotFound();
        $this->actingAs($andere)->get(route('learner.grades.create', ['pruefung' => $p->pruefung_id]))->assertDontSee('LB2');

        // Note mit fremder pruefung_id löscht die fremde Planung nicht
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $andere->lernender->lehrberuf_id, 'modul_id' => $this->modul->modul_id,
            'kategorie_id' => Kategorie::where('code', 'UEK')->value('kategorie_id')]);
        $this->actingAs($andere)->post(route('learner.grades.store'), [
            'typ' => 'modul', 'modul_id' => $this->modul->modul_id, 'pruefungsdatum' => now()->toDateString(),
            'note_wert' => 4, 'pruefung_id' => $p->pruefung_id,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pruefungen', ['pruefung_id' => $p->pruefung_id]);
    }

    #[Test]
    public function geplante_pruefung_erscheint_im_rechner(): void
    {
        $this->planen();

        $this->actingAs($this->user)->get(route('learner.grades.calculator'))->assertOk()->assertSee('LB2');

        $vorschlaege = app(\App\Services\Auswertung\Rechner::class)->seite($this->user->lernender, false)['vorschlaege'];
        $this->assertSame(['geplant'], array_column($vorschlaege, 'quelle'));
    }
}
