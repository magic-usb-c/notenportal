<?php

namespace Tests\Feature\Lernender;

use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Lernender;
use App\Models\Modul;
use App\Models\Note;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kein Lernender sieht oder ändert Daten eines anderen – weder über die
 * Oberfläche noch über manipulierte URLs, IDs oder Formularfelder.
 */
class DatenisolationTest extends TestCase
{
    private User $a;

    private Lernender $lernenderA;

    private Lernender $lernenderB;

    private Note $noteA;

    private Note $noteB;

    private Fach $bmsFach;

    protected function setUp(): void
    {
        parent::setUp();

        $semester = Semester::factory()->create();
        $this->a = User::factory()->lernender()->create();
        $this->lernenderA = $this->a->lernender;
        $this->lernenderB = User::factory()->lernender()->create()->lernender;

        $this->bmsFach = Fach::factory()->create(['track_typ' => 'BMS']);
        DB::table('lernender_tracks')->insert([
            'lernender_id' => $this->lernenderA->lernender_id,
            'track_typ' => 'BMS',
            'start_datum' => '2024-08-01',
            'start_semester_id' => $semester->semester_id,
        ]);

        $this->noteA = Note::factory()->create([
            'lernender_id' => $this->lernenderA->lernender_id,
            'semester_id' => $semester->semester_id,
            'titel' => 'EIGENE-A',
        ]);
        $this->noteB = Note::factory()->create([
            'lernender_id' => $this->lernenderB->lernender_id,
            'semester_id' => $semester->semester_id,
            'titel' => 'GEHEIM-B',
            'note_wert' => 3.5,
        ]);
    }

    private function gueltigeNote(array $ueberschreiben = []): array
    {
        return [
            'kategorie_id' => Kategorie::where('code', 'BMS')->value('kategorie_id'),
            'typ' => 'fach',
            'fach_id' => $this->bmsFach->fach_id,
            'titel' => 'Neue Note',
            'pruefungsdatum' => now()->toDateString(),
            'note_wert' => 5,
            'gewichtung_prozent' => 100,
            ...$ueberschreiben,
        ];
    }

    #[Test]
    public function fremde_note_kann_weder_geoeffnet_noch_geaendert_noch_geloescht_werden(): void
    {
        $id = ['note_id' => $this->noteB->note_id];
        $this->actingAs($this->a);

        $this->get(route('learner.grades.edit', $id))->assertNotFound();
        $this->put(route('learner.grades.update', $id), $this->gueltigeNote(['note_wert' => 6]))->assertNotFound();
        $this->delete(route('learner.grades.destroy', $id))->assertNotFound();
        $this->patchJson(route('learner.grades.title.update', $id), ['titel' => 'übernommen'])->assertNotFound();

        $this->noteB->refresh();
        $this->assertSame('3.5', $this->noteB->note_wert);
        $this->assertSame('GEHEIM-B', $this->noteB->titel);
        $this->assertNull($this->noteB->geloescht_am);
    }

    #[Test]
    public function fremde_note_kann_nicht_als_gesehen_markiert_oder_kommentiert_werden(): void
    {
        $id = ['note_id' => $this->noteB->note_id];
        $this->actingAs($this->a);

        $this->postJson(route('learner.grades.seen', $id))->assertStatus(404);
        $this->post(route('comments.store', $id), ['kommentar_text' => 'Hallo'])->assertForbidden();

        $this->assertDatabaseMissing('noten_gesehen', ['note_id' => $this->noteB->note_id]);
        $this->assertDatabaseMissing('noten_kommentare', ['note_id' => $this->noteB->note_id]);
    }

    #[Test]
    public function fremder_kommentar_kann_nicht_geloescht_werden(): void
    {
        $kommentarId = DB::table('noten_kommentare')->insertGetId([
            'note_id' => $this->noteB->note_id,
            'autor_benutzer_id' => $this->lernenderB->benutzer_id,
            'kommentar_text' => 'Kommentar von B',
        ]);

        $this->actingAs($this->a)
            ->delete(route('comments.destroy', ['kommentar_id' => $kommentarId]))
            ->assertForbidden();

        $this->assertDatabaseHas('noten_kommentare', ['kommentar_id' => $kommentarId]);
    }

    #[Test]
    public function ansichten_und_exporte_enthalten_nur_eigene_noten(): void
    {
        $geloescht = Note::factory()->create([
            'lernender_id' => $this->lernenderA->lernender_id,
            'semester_id' => $this->noteA->semester_id,
            'titel' => 'GELOESCHT-A',
        ]);
        $geloescht->delete();
        $this->actingAs($this->a);

        foreach ([
            route('learner.grades.index'),
            route('learner.grades.index', ['_open' => $this->noteB->note_id]),
            route('learner.grades.print'),
            route('learner.grades.calculator'),
            route('learner.dashboard'),
        ] as $url) {
            $this->get($url)->assertOk()->assertDontSee('GEHEIM-B')->assertDontSee('GELOESCHT-A');
        }

        $this->get(route('learner.grades.index'))->assertSee('EIGENE-A');

        $csv = $this->get(route('learner.grades.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('EIGENE-A', $csv);
        $this->assertStringNotContainsString('GEHEIM-B', $csv);
        $this->assertStringNotContainsString('GELOESCHT-A', $csv);
    }

    #[Test]
    public function eingeschleuste_lernender_id_wird_ignoriert(): void
    {
        $this->actingAs($this->a)
            ->post(route('learner.grades.store'), $this->gueltigeNote(['lernender_id' => $this->lernenderB->lernender_id]))
            ->assertSessionHasNoErrors();

        $neu = Note::where('titel', 'Neue Note')->sole();
        $this->assertSame($this->lernenderA->lernender_id, $neu->lernender_id);
        $this->assertSame($this->a->benutzer_id, $neu->erfasst_von_benutzer_id);
    }

    #[Test]
    public function modul_eines_fremden_lehrberufs_wird_abgewiesen(): void
    {
        $fremdesModul = Modul::factory()->create();
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $this->lernenderB->lehrberuf_id,
            'modul_id' => $fremdesModul->modul_id,
        ]);

        $this->actingAs($this->a)
            ->post(route('learner.grades.store'), $this->gueltigeNote([
                'kategorie_id' => Kategorie::where('code', 'FACH')->value('kategorie_id'),
                'typ' => 'modul',
                'fach_id' => null,
                'modul_id' => $fremdesModul->modul_id,
            ]))
            ->assertSessionHasErrors('modul_id');

        $this->assertDatabaseMissing('modul_belegungen', ['lernender_id' => $this->lernenderA->lernender_id]);
    }

    #[Test]
    public function fach_ausserhalb_des_eigenen_tracks_wird_abgewiesen(): void
    {
        $abuFach = Fach::factory()->create(['track_typ' => 'ABU']);

        $this->actingAs($this->a)
            ->post(route('learner.grades.store'), $this->gueltigeNote(['fach_id' => $abuFach->fach_id]))
            ->assertSessionHasErrors('fach_id');
    }
}
