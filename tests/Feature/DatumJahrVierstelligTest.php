<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Modul;
use App\Models\Note;
use App\Models\Pruefung;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

/**
 * Befund «Datum»: Das Datumsfeld des Browsers nimmt Jahre mit mehr als vier Ziffern an.
 * Ohne feste Formatregel deutet strtotime «20266-12-01» still als anderes Datum
 * (gespeichert wurde 2006-12-01, ohne Fehlermeldung).
 */
class DatumJahrVierstelligTest extends TestCase
{
    use VerwaltungTestHilfen;

    private const MELDUNG = 'Bitte ein gültiges Datum eingeben.';

    /** @return array<string, array{string}> */
    public static function ungueltigeDaten(): array
    {
        return [
            'fuenf Ziffern' => ['20266-12-01'],
            'fuenf Ziffern vorne' => ['12026-12-01'],
            'extrem' => ['99999-01-01'],
        ];
    }

    #[Test]
    #[DataProvider('ungueltigeDaten')]
    public function lernender_kann_pruefung_nicht_mit_falschem_jahr_planen_oder_verschieben(string $datum): void
    {
        Semester::factory()->create();
        $user = User::factory()->lernender()->create();
        $modul = Modul::factory()->create();
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $user->lernender->lehrberuf_id, 'modul_id' => $modul->modul_id,
            'kategorie_id' => Kategorie::where('code', 'UEK')->value('kategorie_id'),
        ]);
        $daten = ['bezug' => 'modul:'.$modul->modul_id, 'gewichtung_prozent' => 40, 'titel' => 'LB'];

        $this->actingAs($user)->post(route('learner.exams.store'), $daten + ['datum' => $datum])
            ->assertSessionHasErrors(['datum' => self::MELDUNG]);
        $this->assertSame(0, Pruefung::count());

        $gueltig = now()->addWeek()->toDateString();
        $this->actingAs($user)->post(route('learner.exams.store'), $daten + ['datum' => $gueltig])
            ->assertSessionHasNoErrors();
        $p = Pruefung::sole();

        $this->actingAs($user)->put(route('learner.exams.update', $p->pruefung_id), $daten + ['datum' => $datum])
            ->assertSessionHasErrors(['datum' => self::MELDUNG]);
        $this->assertSame($gueltig, $p->fresh()->datum->toDateString());
    }

    #[Test]
    #[DataProvider('ungueltigeDaten')]
    public function lernender_kann_note_nicht_mit_falschem_jahr_speichern_oder_aendern(string $datum): void
    {
        $semester = Semester::factory()->create();
        $user = User::factory()->lernender()->create();
        $fach = Fach::factory()->create();
        $daten = ['typ' => 'fach', 'fach_id' => $fach->fach_id, 'note_wert' => 5.0, 'gewichtung_prozent' => 100];

        $this->actingAs($user)->post(route('learner.grades.store'), $daten + ['pruefungsdatum' => $datum])
            ->assertSessionHasErrors(['pruefungsdatum' => self::MELDUNG]);
        $this->assertSame(0, Note::count());

        $note = Note::factory()->create([
            'lernender_id' => $user->lernender->lernender_id, 'semester_id' => $semester->semester_id, 'fach_id' => $fach->fach_id,
            'pruefungsdatum' => now()->toDateString(),
        ]);
        $this->actingAs($user)->put(route('learner.grades.update', $note->note_id), $daten + ['pruefungsdatum' => $datum])
            ->assertSessionHasErrors(['pruefungsdatum' => self::MELDUNG]);
        $this->assertSame(now()->toDateString(), $note->fresh()->pruefungsdatum->toDateString());
    }

    #[Test]
    #[DataProvider('ungueltigeDaten')]
    public function berufsbildner_kann_note_nicht_mit_falschem_jahr_korrigieren(string $datum): void
    {
        $semester = Semester::factory()->create();
        $lernender = User::factory()->lernender()->create()->lernender;
        $this->bmsTrack($lernender, $semester->semester_id);
        $fach = Fach::factory()->create(['track_typ' => 'BMS']);
        $bb = $this->verwalter('trainer', $lernender);
        $note = Note::factory()->create([
            'lernender_id' => $lernender->lernender_id, 'semester_id' => $semester->semester_id, 'fach_id' => $fach->fach_id,
            'pruefungsdatum' => now()->toDateString(),
        ]);

        $this->actingAs($bb)->put(route('trainer.learners.grades.update', [$lernender->lernender_id, $note->note_id]), [
            'typ' => 'fach', 'fach_id' => $fach->fach_id, 'pruefungsdatum' => $datum, 'note_wert' => 5.0, 'gewichtung_prozent' => 100,
        ])->assertSessionHasErrors(['pruefungsdatum' => self::MELDUNG]);
        $this->assertSame(now()->toDateString(), $note->fresh()->pruefungsdatum->toDateString());
    }

    #[Test]
    #[DataProvider('ungueltigeDaten')]
    public function abgabetermin_der_verwaltung_verlangt_vierstelliges_jahr(string $datum): void
    {
        Semester::factory()->create();
        $lernender = User::factory()->lernender()->create()->lernender;
        $modul = Modul::factory()->create();
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $lernender->lehrberuf_id, 'modul_id' => $modul->modul_id,
            'kategorie_id' => Kategorie::where('code', 'UEK')->value('kategorie_id'),
        ]);

        $this->actingAs($this->verwalter('trainer', $lernender))->post(route('trainer.exams.store'), [
            'lernender_id' => $lernender->lernender_id, 'bezug' => 'modul:'.$modul->modul_id,
            'titel' => 'Bericht', 'datum' => $datum, 'gewichtung_prozent' => 30,
        ])->assertSessionHasErrors(['datum' => self::MELDUNG]);
        $this->assertSame(0, Pruefung::count());
    }
}
