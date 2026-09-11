<?php

namespace Tests\Feature\Lernender;

use App\Models\Fach;
use App\Models\Lernender;
use App\Models\LernenderTrack;
use App\Models\Note;
use App\Models\Semester;
use App\Models\User;
use App\Services\Import\NotenImport;
use App\Services\Noten\NoteService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Track-Fächer (BMS/ABU) gelten nach dem Track, der am Prüfungsdatum gültig war – nicht nach dem heutigen.
 * Wer die BM verlassen hat, kann alte BM-Noten weiter erfassen und importieren.
 */
class TrackNachDatumTest extends TestCase
{
    private User $user;

    private Lernender $lernender;

    private Fach $bmsFach;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-11');

        $this->user = User::factory()->lernender()->create(); // Lehrbeginn 2024-08-01
        $this->lernender = $this->user->lernender;
        $semester = Semester::factory()->create(['start_datum' => '2024-08-01', 'end_datum' => '2027-07-31']);
        $this->bmsFach = Fach::factory()->create(['track_typ' => 'BMS', 'name' => 'BM Mathematik']);
        LernenderTrack::create([
            'lernender_id' => $this->lernender->lernender_id, 'track_typ' => 'BMS',
            'start_datum' => '2024-08-01', 'end_datum' => '2025-07-31', 'start_semester_id' => $semester->semester_id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function speichere(Fach $fach, string $datum)
    {
        return $this->actingAs($this->user)->post(route('learner.grades.store'), [
            'typ' => 'fach', 'fach_id' => $fach->fach_id, 'pruefungsdatum' => $datum, 'note_wert' => 5,
        ]);
    }

    #[Test]
    public function bm_note_aus_der_track_zeit_wird_nach_track_ende_angenommen_und_bleibt_bearbeitbar(): void
    {
        $this->speichere($this->bmsFach, '2025-03-10')->assertSessionHasNoErrors();
        $note = Note::sole();
        $this->assertSame($this->bmsFach->fach_id, $note->fach_id);

        $this->actingAs($this->user)->put(route('learner.grades.update', $note->note_id), [
            'typ' => 'fach', 'fach_id' => $this->bmsFach->fach_id, 'pruefungsdatum' => '2025-07-31', 'note_wert' => 5.5,
        ])->assertSessionHasNoErrors();
        $this->assertEquals(5.5, $note->fresh()->note_wert);

        // Auswahl zeigt das Fach des beendeten Tracks; «heute erlaubt» (Rechner) bleibt unverändert ohne BM
        $service = app(NoteService::class);
        $this->assertContains('fach:'.$this->bmsFach->fach_id, collect($service->bezugOptionen((int) $this->lernender->lernender_id))->flatten(1)->pluck('wert'));
        $this->assertFalse($service->erlaubteFaecher((int) $this->lernender->lernender_id)->whereKey($this->bmsFach->fach_id)->exists());
    }

    #[Test]
    public function bm_note_nach_track_ende_wird_mit_klarer_meldung_abgewiesen(): void
    {
        $this->speichere($this->bmsFach, '2025-09-10')
            ->assertSessionHasErrors(['fach_id' => 'Der Track BMS war am 10.09.2025 nicht aktiv (Track-Zeit: 01.08.2024 – 31.07.2025). Fächer dieses Tracks brauchen ein Prüfungsdatum innerhalb der Track-Zeit.']);
        $this->assertSame(0, Note::count());
    }

    #[Test]
    public function fach_ohne_track_ist_vom_datum_unabhaengig_und_ohne_track_bleibt_die_alte_meldung(): void
    {
        $fach = Fach::factory()->create(['track_typ' => null]);
        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $this->lernender->lehrberuf_id, 'fach_id' => $fach->fach_id]);

        $this->speichere($fach, '2025-09-10')->assertSessionHasNoErrors();
        $this->speichere($fach, '2025-03-10')->assertSessionHasNoErrors();
        $this->assertSame(2, Note::count());

        // Lernender ganz ohne Track: BM-Fach weder in der Auswahl noch speicherbar (Meldung wie bisher)
        LernenderTrack::query()->delete();
        $this->assertNotContains('fach:'.$this->bmsFach->fach_id, collect(app(NoteService::class)->bezugOptionen((int) $this->lernender->lernender_id))->flatten(1)->pluck('wert'));
        $this->speichere($this->bmsFach, '2025-03-10')
            ->assertSessionHasErrors(['fach_id' => 'Dieses Fach ist für den Lehrberuf oder Track nicht freigegeben.']);
    }

    #[Test]
    public function import_markiert_und_uebernimmt_nach_track_am_pruefungsdatum(): void
    {
        $import = app(NotenImport::class);
        $id = (int) $this->lernender->lernender_id;

        $vorschau = $import->vorschau([['Datum', 'Fach/Modul', 'Note'], ['10.03.2025', 'BM Mathematik', '5'], ['10.09.2025', 'BM Mathematik', '4.5']], $id);
        $this->assertSame(['fach:'.$this->bmsFach->fach_id, 'fach:'.$this->bmsFach->fach_id], array_column($vorschau['zeilen'], 'bezug'));
        $this->assertNotSame('fehler', $vorschau['zeilen'][0]['status']);
        $this->assertSame(['fehler', 'Track am Datum nicht aktiv'], [$vorschau['zeilen'][1]['status'], $vorschau['zeilen'][1]['meldung']]);

        $zeilen = array_map(fn ($z) => ['uebernehmen' => true] + $z, $vorschau['zeilen']);
        $ergebnis = $import->importieren($zeilen, $id, (int) $this->user->getKey());
        $this->assertSame(1, $ergebnis['neu']);
        $this->assertStringContainsString('war am 10.09.2025 nicht aktiv', $ergebnis['fehler'][0]);
        $this->assertSame('2025-03-10', Note::sole()->pruefungsdatum->toDateString());
    }
}
