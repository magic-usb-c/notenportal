<?php

namespace Tests\Feature\Verwaltung;

use App\Models\Semester;
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
        $ende = Semester::factory()->create();

        $this->actingAs($verwalter)
            ->post(route("{$bereich}.learners.tracks.store", $lernender->lernender_id), [
                'track_typ' => 'BMS',
                'start_datum' => $start->start_datum->toDateString(),
                'start_semester_id' => $start->semester_id,
            ])
            ->assertRedirect(route("{$bereich}.learners.show", $lernender->lernender_id))
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
}
