<?php

namespace Tests\Feature\Verwaltung;

use App\Models\Semester;
use App\Services\Noten\NoteService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrackTest extends TestCase
{
    use VerwaltungTestHilfen;

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function track_wird_gestartet_und_beendet(string $bereich): void
    {
        $lernender = $this->neuerLernender();
        $verwalter = $this->verwalter($bereich, $lernender);
        $start = Semester::factory()->create();
        $ende = Semester::factory()->create(['sortierung' => $start->sortierung + 1]);

        $this->actingAs($verwalter)
            ->post(route("{$bereich}.learners.tracks.store", $lernender->lernender_id), [
                'track_typ' => 'BMS',
                'start_datum' => $start->start_datum->toDateString(),
                'start_semester_id' => $start->semester_id,
            ])
            ->assertRedirect(route("{$bereich}.learners.show", [$lernender->lernender_id, 'tab' => 'profil']))
            ->assertSessionHas('success');

        $track = $lernender->tracks()->sole();
        $this->assertSame('BMS', $track->track_typ);
        $this->assertNull($track->end_datum);

        $this->actingAs($verwalter)
            ->post(route("{$bereich}.tracks.end", [$lernender->lernender_id, $track->lernender_track_id]), [
                'end_semester_id' => $ende->semester_id,
            ])
            ->assertSessionHas('success');

        $track->refresh();
        $this->assertNotNull($track->end_datum);
        $this->assertSame($ende->semester_id, $track->end_semester_id);
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function doppelter_offener_track_wird_abgewiesen(string $bereich): void
    {
        $lernender = $this->neuerLernender();
        $verwalter = $this->verwalter($bereich, $lernender);
        $semester = Semester::factory()->create();
        $this->bmsTrack($lernender, $semester->semester_id);

        $this->actingAs($verwalter)
            ->post(route("{$bereich}.learners.tracks.store", $lernender->lernender_id), [
                'track_typ' => 'BMS',
                'start_datum' => now()->toDateString(),
                'start_semester_id' => $semester->semester_id,
            ])
            ->assertSessionHas('error');

        $this->assertSame(1, $lernender->tracks()->count());
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function endsemester_vor_dem_startsemester_wird_abgewiesen(string $bereich): void
    {
        $lernender = $this->neuerLernender();
        $verwalter = $this->verwalter($bereich, $lernender);
        $start = Semester::factory()->create(['sortierung' => 500]);
        $frueher = Semester::factory()->create(['sortierung' => 499]);
        $track = $this->bmsTrack($lernender, $start->semester_id);

        $this->actingAs($verwalter)
            ->post(route("{$bereich}.tracks.end", [$lernender->lernender_id, $track->lernender_track_id]), [
                'end_semester_id' => $frueher->semester_id,
            ])
            ->assertSessionHas('error');

        $this->assertNull($track->refresh()->end_datum);
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function vergangenes_endsemester_beendet_den_track_an_dessen_semesterende(string $bereich): void
    {
        $lernender = $this->neuerLernender();
        $verwalter = $this->verwalter($bereich, $lernender);
        $start = Semester::factory()->create(['start_datum' => '2020-08-01', 'end_datum' => '2021-01-31', 'sortierung' => 800]);
        $ende = Semester::factory()->create(['start_datum' => '2021-02-01', 'end_datum' => '2021-07-31', 'sortierung' => 801]);
        $track = $this->bmsTrack($lernender, $start->semester_id);
        $track->update(['start_datum' => '2020-08-01']);

        $this->actingAs($verwalter)
            ->post(route("{$bereich}.tracks.end", [$lernender->lernender_id, $track->lernender_track_id]), [
                'end_semester_id' => $ende->semester_id,
            ])
            ->assertSessionHas('success');

        $this->assertSame('2021-07-31', $track->refresh()->end_datum->toDateString());
        $this->assertFalse(app(NoteService::class)->activeTrackTypesForLernender($lernender->lernender_id)->contains('BMS'));
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function laufendes_endsemester_beendet_den_track_heute(string $bereich): void
    {
        $lernender = $this->neuerLernender();
        $verwalter = $this->verwalter($bereich, $lernender);
        $start = Semester::factory()->create();
        $ende = Semester::factory()->create(['sortierung' => $start->sortierung + 1]);
        $track = $this->bmsTrack($lernender, $start->semester_id);

        $this->actingAs($verwalter)
            ->post(route("{$bereich}.tracks.end", [$lernender->lernender_id, $track->lernender_track_id]), [
                'end_semester_id' => $ende->semester_id,
            ])
            ->assertSessionHas('success');

        $this->assertSame(today()->toDateString(), $track->refresh()->end_datum->toDateString());
    }
}
