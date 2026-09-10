<?php

namespace Tests\Feature\Lernender;

use App\Models\Note;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ViertelnotenTest extends TestCase
{
    #[Test]
    public function viertelnoten_werden_ohne_rundung_gespeichert(): void
    {
        $note = Note::factory()->create(['note_wert' => 4.25]);

        $this->assertSame('4.25', $note->refresh()->note_wert);
        $this->assertSame('4.25', (string) DB::table('noten')->where('note_id', $note->note_id)->value('note_wert'));
    }

    #[Test]
    public function anzeige_ohne_ueberfluessige_null(): void
    {
        $this->assertSame('4.5', Note::factory()->create(['note_wert' => 4.5])->refresh()->note_wert);
        $this->assertSame('5.0', Note::factory()->create(['note_wert' => 5])->refresh()->note_wert);
    }

    #[Test]
    public function nur_schritte_von_0_05_sind_erlaubt(): void
    {
        $user = User::factory()->lernender()->create();
        $note = Note::factory()->create(['lernender_id' => $user->lernender->lernender_id]);
        $route = route('lernender.noten.update', ['note_id' => $note->note_id]);

        $this->actingAs($user)->put($route, ['note_wert' => 4.33])->assertSessionHasErrors('note_wert');
        $this->actingAs($user)->put($route, ['note_wert' => 4.25])->assertSessionDoesntHaveErrors('note_wert');
    }
}
