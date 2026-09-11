<?php

namespace Tests\Feature\Berufsbildner;

use App\Models\Betreuung;
use App\Models\Kategorie;
use App\Models\Note;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AlleGesehenTest extends TestCase
{
    private User $bb;

    private int $lernenderId;

    private Note $bmsNote;

    private Note $abuNote;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bb = User::factory()->berufsbildner()->create();
        $this->lernenderId = User::factory()->lernender()->create()->lernender->lernender_id;

        Betreuung::factory()->create([
            'berufsbildner_id' => $this->bb->berufsbildner->berufsbildner_id,
            'lernender_id' => $this->lernenderId,
        ]);

        $this->bmsNote = Note::factory()->create(['lernender_id' => $this->lernenderId]);
        $this->abuNote = Note::factory()->create([
            'lernender_id' => $this->lernenderId,
            'kategorie_id' => Kategorie::where('code', 'ABU')->value('kategorie_id'),
        ]);
    }

    #[Test]
    public function zaehler_beruecksichtigt_den_filter(): void
    {
        $route = route('trainer.learners.grades.index', ['lernender_id' => $this->lernenderId]);

        $this->actingAs($this->bb)->get($route)->assertSee('Alle 2 als gesehen markieren');
        $this->actingAs($this->bb)
            ->get($route.'?kategorie_id='.$this->abuNote->kategorie_id)
            ->assertSee('Alle 1 als gesehen markieren');
    }

    #[Test]
    public function markiert_nur_noten_im_aktiven_filter(): void
    {
        $this->actingAs($this->bb)
            ->post(route('trainer.learners.grades.seen_all', ['lernender_id' => $this->lernenderId]), [
                'kategorie_id' => $this->abuNote->kategorie_id,
            ])
            ->assertSessionHas('success');

        $gesehen = DB::table('noten_gesehen')->where('viewer_benutzer_id', $this->bb->benutzer_id)->pluck('note_id')->all();

        $this->assertSame([$this->abuNote->note_id], $gesehen);
    }

    #[Test]
    public function ohne_filter_werden_alle_markiert(): void
    {
        $this->actingAs($this->bb)
            ->post(route('trainer.learners.grades.seen_all', ['lernender_id' => $this->lernenderId]));

        $this->assertSame(2, DB::table('noten_gesehen')->where('viewer_benutzer_id', $this->bb->benutzer_id)->count());
    }
}
