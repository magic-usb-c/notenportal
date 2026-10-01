<?php

namespace Tests\Feature\Verwaltung;

use App\Models\Note;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Notenliste der Berufsbildner: Liste mit Detailbereich. Ein Link mit ?_open= (Mitteilung zu Kommentar oder neuer
 * Note) öffnet die Seite, auf der die Note steht, und wählt sie.
 */
class NotenListeTest extends TestCase
{
    use VerwaltungTestHilfen;

    #[Test]
    public function link_auf_eine_note_oeffnet_ihre_seite_und_waehlt_sie(): void
    {
        $lernender = User::factory()->lernender()->create()->lernender;
        $bb = User::factory()->berufsbildner()->create();
        $this->betreue($bb, $lernender);

        // 30 Noten an 30 Tagen, dazu zwei am selben Tag (Reihenfolge dort nach ID absteigend)
        $noten = collect(range(1, 30))->map(fn (int $tag) => Note::factory()->create([
            'lernender_id' => $lernender->lernender_id,
            'pruefungsdatum' => now()->subDays($tag)->toDateString(),
        ]));
        $gleicherTag = Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'pruefungsdatum' => now()->subDays(25)->toDateString()]);

        $this->actingAs($bb);
        $url = fn (array $p = []) => route('trainer.learners.grades.index', [$lernender->lernender_id, ...$p]);

        // Ohne Link: erste Seite, neueste Note gewählt
        $seite = $this->get($url())->assertOk();
        $this->assertSame(1, $seite->viewData('notes')->currentPage());
        $seite->assertSee('start: '.$noten[0]->note_id.',', false);

        // Platz 26 (die zweite Note vom 25. Tag steht davor) → Seite 2, dort gewählt; Seitenlinks ohne _open
        $ziel = $noten[24];
        $seite = $this->get($url(['_open' => $ziel->note_id]))->assertOk();
        $this->assertSame(2, $seite->viewData('notes')->currentPage());
        $seite->assertSee('start: '.$ziel->note_id.',', false);
        $this->assertStringNotContainsString('_open', $seite->viewData('notes')->url(1));

        $seite = $this->get($url(['_open' => $gleicherTag->note_id]))->assertOk();
        $this->assertSame(1, $seite->viewData('notes')->currentPage());

        // Ausdrückliche Seite gewinnt; fremde oder gelöschte Note: erste Seite
        $this->assertSame(1, $this->get($url(['_open' => $ziel->note_id, 'page' => 1]))->viewData('notes')->currentPage());
        $fremd = Note::factory()->create();
        $this->assertSame(1, $this->get($url(['_open' => $fremd->note_id]))->viewData('notes')->currentPage());
        $noten[29]->delete();
        $this->assertSame(1, $this->get($url(['_open' => $noten[29]->note_id]))->viewData('notes')->currentPage());
    }

    #[Test]
    public function liste_zeigt_jede_note_mit_detail_und_kommentarformular(): void
    {
        $lernender = User::factory()->lernender()->create()->lernender;
        $bb = User::factory()->berufsbildner()->create();
        $this->betreue($bb, $lernender);
        $noten = Note::factory()->count(3)->create(['lernender_id' => $lernender->lernender_id]);

        $inhalt = $this->actingAs($bb)->get(route('trainer.learners.grades.index', $lernender->lernender_id))->assertOk()->getContent();

        foreach ($noten as $n) {
            $this->assertStringContainsString('role="option" id="note-'.$n->note_id.'"', $inhalt);
            $this->assertStringContainsString('aria-labelledby="note-titel-'.$n->note_id.'"', $inhalt);
            $this->assertStringContainsString(route('comments.store', $n->note_id), $inhalt);
        }
    }
}
