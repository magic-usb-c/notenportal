<?php

declare(strict_types=1);

namespace Tests\Feature\Berufsbildner;

use App\Models\DigestItem;
use App\Models\Note;
use App\Models\User;
use App\Services\Notifications\Messages\GradeAdded;
use App\Services\Notifications\Messages\GradeSeen;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

/** «Alle gesehen» meldet und benachrichtigt nur die Noten, die für den Betrachter neu waren. */
class AlleGesehenMeldungTest extends TestCase
{
    use VerwaltungTestHilfen;

    /** @return array{0: User, 1: User, 2: Collection} */
    private function aufbau(int $gesehen): array
    {
        $lernenderUser = User::factory()->lernender()->create(['email' => 'lernender@firma.ch']);
        $bb = User::factory()->berufsbildner()->create(['email' => 'bb@firma.ch']);
        $this->betreue($bb, $lernenderUser->lernender);
        $noten = Note::factory()->count(3)->create(['lernender_id' => $lernenderUser->lernender->lernender_id]);
        foreach ($noten->take($gesehen) as $n) {
            DB::table('noten_gesehen')->insert(['note_id' => $n->note_id, 'viewer_benutzer_id' => $bb->benutzer_id, 'gesehen_am' => now()->addMinute()]);
        }

        return [$lernenderUser, $bb, $noten];
    }

    #[Test]
    public function meldung_und_benachrichtigung_zaehlen_nur_die_neuen_noten(): void
    {
        [$lernenderUser, $bb] = $this->aufbau(2);

        $this->actingAs($bb)
            ->post(route('trainer.learners.grades.seen_all', $lernenderUser->lernender->lernender_id))
            ->assertSessionHas('success', '1 Note als gesehen markiert.');

        $titel = DigestItem::query()->where('user_id', $lernenderUser->benutzer_id)->pluck('title')->all();
        $this->assertSame(['1 Note angesehen'], $titel);
    }

    #[Test]
    public function zweiter_klick_ohne_neue_note_meldet_nichts_mehr(): void
    {
        [$lernenderUser, $bb] = $this->aufbau(0);
        $route = route('trainer.learners.grades.seen_all', $lernenderUser->lernender->lernender_id);

        $this->actingAs($bb)->post($route)->assertSessionHas('success', '3 Noten als gesehen markiert.');
        $this->assertSame(1, DigestItem::query()->count());

        $this->actingAs($bb)->post($route)->assertSessionHas('success', 'Keine neuen Noten.');
        $this->assertSame(1, DigestItem::query()->count(), 'Ohne neue Note darf nichts erneut gemeldet werden');
    }

    #[Test]
    public function sammelmeldungen_kennen_die_einzahl(): void
    {
        $this->assertSame('1 Note angesehen', GradeSeen::sammel(1, '/x')->subject);
        $this->assertStringContainsString('1 Note als gesehen markiert', GradeSeen::sammel(1, '/x')->lines[0]);
        $this->assertSame('3 Noten angesehen', GradeSeen::sammel(3, '/x')->subject);

        $lernender = User::factory()->lernender()->create(['vorname' => 'Test', 'nachname' => 'Person'])->lernender;
        $this->assertStringContainsString('1 Note importiert', GradeAdded::sammel($lernender, 1, '/x')->subject);
        $this->assertStringNotContainsString('1 Noten', GradeAdded::sammel($lernender, 1, '/x')->lines[0]);
        $this->assertStringContainsString('3 Noten importiert', GradeAdded::sammel($lernender, 3, '/x')->subject);
    }
}
