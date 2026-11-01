<?php

namespace Tests\Feature\Verwaltung;

use App\Models\Fach;
use App\Models\Note;
use App\Models\Semester;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Ein Track lässt sich nicht vor Noten beenden, die dann ausserhalb der Track-Zeit lägen. */
class TrackBeendenNotenTest extends TestCase
{
    use VerwaltungTestHilfen;

    private function aufbau(string $bereich, ?string $pruefungsdatum, ?string $trackTyp = 'BMS', bool $geloescht = false): array
    {
        $lernender = $this->neuerLernender();
        $verwalter = $this->verwalter($bereich, $lernender);
        $alt = Semester::factory()->create(['start_datum' => '2020-08-01', 'end_datum' => '2021-01-31', 'sortierung' => 900]);
        $neu = Semester::factory()->create(['start_datum' => '2021-02-01', 'end_datum' => '2021-07-31', 'sortierung' => 901]);
        $track = $this->bmsTrack($lernender, $alt->semester_id);
        $track->update(['start_datum' => '2020-08-01']);

        if ($pruefungsdatum !== null) {
            $fach = Fach::factory()->create(['track_typ' => $trackTyp]);
            $note = Note::factory()->create([
                'lernender_id' => $lernender->lernender_id, 'semester_id' => $neu->semester_id,
                'fach_id' => $fach->fach_id, 'kategorie_id' => $fach->kategorie_id, 'pruefungsdatum' => $pruefungsdatum,
            ]);
            if ($geloescht) {
                $note->delete();
            }
        }

        return [$lernender, $verwalter, $track, $alt, $neu];
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function beenden_wird_abgebrochen_wenn_noten_nach_dem_enddatum_liegen(string $bereich): void
    {
        [$lernender, $verwalter, $track, $alt] = $this->aufbau($bereich, '2021-03-15');

        $this->actingAs($verwalter)
            ->post(route("{$bereich}.tracks.end", [$lernender->lernender_id, $track->lernender_track_id]), ['end_semester_id' => $alt->semester_id])
            ->assertSessionHas('error', fn (string $m) => str_contains($m, '31.01.2021') && str_contains($m, 'späteres'));

        $this->assertNull($track->refresh()->end_datum);
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function beenden_klappt_mit_spaeterem_endsemester(string $bereich): void
    {
        [$lernender, $verwalter, $track, , $neu] = $this->aufbau($bereich, '2021-03-15');

        $this->actingAs($verwalter)
            ->post(route("{$bereich}.tracks.end", [$lernender->lernender_id, $track->lernender_track_id]), ['end_semester_id' => $neu->semester_id])
            ->assertSessionHas('success');

        $this->assertSame('2021-07-31', $track->refresh()->end_datum->toDateString());
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function geloeschte_noten_und_andere_tracks_blockieren_nicht(string $bereich): void
    {
        [$lernender, $verwalter, $track, $alt] = $this->aufbau($bereich, '2021-03-15', 'BMS', geloescht: true);
        $fach = Fach::factory()->create(['track_typ' => 'ABU']);
        Note::factory()->create([
            'lernender_id' => $lernender->lernender_id, 'semester_id' => Semester::query()->where('sortierung', 901)->value('semester_id'),
            'fach_id' => $fach->fach_id, 'kategorie_id' => $fach->kategorie_id, 'pruefungsdatum' => '2021-03-16',
        ]);

        $this->actingAs($verwalter)
            ->post(route("{$bereich}.tracks.end", [$lernender->lernender_id, $track->lernender_track_id]), ['end_semester_id' => $alt->semester_id])
            ->assertSessionHas('success');

        $this->assertSame('2021-01-31', $track->refresh()->end_datum->toDateString());
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function note_am_enddatum_blockiert_nicht(string $bereich): void
    {
        [$lernender, $verwalter, $track, $alt] = $this->aufbau($bereich, '2021-01-31');

        $this->actingAs($verwalter)
            ->post(route("{$bereich}.tracks.end", [$lernender->lernender_id, $track->lernender_track_id]), ['end_semester_id' => $alt->semester_id])
            ->assertSessionHas('success');
    }
}
