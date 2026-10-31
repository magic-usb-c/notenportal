<?php

namespace Tests\Feature\Lernender;

use App\Models\Note;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kommentar schreiben und löschen: Das Fach bleibt offen, ein Validierungsfehler
 * erscheint am Feld der betroffenen Prüfung.
 */
class KommentarRueckmeldungTest extends TestCase
{
    private User $user;

    private Note $erste;

    private Note $zweite;

    protected function setUp(): void
    {
        parent::setUp();

        $semester = Semester::factory()->create();
        $this->user = User::factory()->lernender()->create();
        $lernenderId = $this->user->lernender->lernender_id;
        $this->erste = Note::factory()->create(['lernender_id' => $lernenderId, 'semester_id' => $semester->semester_id, 'titel' => 'PRUEFUNG-EINS']);
        $this->zweite = Note::factory()->create(['lernender_id' => $lernenderId, 'semester_id' => $semester->semester_id, 'titel' => 'PRUEFUNG-ZWEI']);
    }

    #[Test]
    public function leerer_kommentar_leitet_mit_offenem_fach_und_fehler_zurueck(): void
    {
        $this->actingAs($this->user)
            ->from(route('learner.grades.index'))
            ->post(route('comments.store', $this->zweite->note_id), ['kommentar_text' => '   '])
            ->assertRedirect(route('learner.grades.index'))
            ->assertSessionHasErrors('kommentar_text')
            ->assertSessionHas('opened_note', $this->zweite->note_id);

        $this->assertDatabaseMissing('noten_kommentare', ['note_id' => $this->zweite->note_id]);
    }

    #[Test]
    public function fehler_erscheint_nur_beim_feld_der_betroffenen_pruefung(): void
    {
        $this->actingAs($this->user)
            ->from(route('learner.grades.index'))
            ->post(route('comments.store', $this->zweite->note_id), ['kommentar_text' => '   ']);

        $html = $this->get(route('learner.grades.index'))->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('/aria-describedby="kommentar-\d+-fehler"/', $html));
        $this->assertStringContainsString('id="kommentar-'.$this->zweite->note_id.'-fehler"', $html);
        $this->assertStringContainsString('aria-describedby="kommentar-'.$this->zweite->note_id.'-fehler"', $html);
        $this->assertStringNotContainsString('kommentar-'.$this->erste->note_id.'-fehler', $html);
    }

    #[Test]
    public function ohne_fehler_gibt_es_keine_fehlerauszeichnung(): void
    {
        $html = $this->actingAs($this->user)->get(route('learner.grades.index'))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/kommentar-\d+-fehler/', $html);
    }

    #[Test]
    public function loeschen_eines_kommentars_laesst_das_fach_offen(): void
    {
        $kommentarId = DB::table('noten_kommentare')->insertGetId([
            'note_id' => $this->zweite->note_id,
            'autor_benutzer_id' => $this->user->benutzer_id,
            'kommentar_text' => 'Eigener Kommentar',
        ]);

        $this->actingAs($this->user)
            ->delete(route('comments.destroy', $kommentarId))
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHas('opened_note', $this->zweite->note_id);

        $this->assertDatabaseMissing('noten_kommentare', ['kommentar_id' => $kommentarId]);
    }
}
