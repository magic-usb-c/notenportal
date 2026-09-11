<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Dokument;
use App\Models\Note;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Import\ZeugnisAbgleich;
use Database\Seeders\BasisSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ZeugnisAbgleichTest extends TestCase
{
    private User $user;

    private int $modul;

    private int $fach;

    private int $semester;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(BasisSeeder::class);
        $kategorie = DB::table('kategorien')->pluck('kategorie_id', 'code');
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $this->modul = DB::table('module')->insertGetId(['modul_nummer' => '431', 'titel' => 'Aufträge durchführen']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $lehrberuf, 'modul_id' => $this->modul, 'kategorie_id' => $kategorie['FACH']]);
        $this->fach = DB::table('faecher')->insertGetId(['kategorie_id' => $kategorie['ABU'], 'name' => 'Sprache und Kommunikation', 'kurzname' => 'SK']);
        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $lehrberuf, 'fach_id' => $this->fach]);
        $this->semester = DB::table('semester')->insertGetId(['bezeichnung' => '25/26-2', 'start_datum' => '2026-02-01', 'end_datum' => '2026-07-31', 'sortierung' => 10]);
        Konfiguration::vergessen();
        $this->user = User::factory()->lernender(['lehrberuf_id' => $lehrberuf, 'lehrbeginn' => '2024-08-01', 'lehrende' => '2028-07-31'])->create();
    }

    #[Test]
    public function zeugniszeilen_werden_erkannt_und_zugeordnet(): void
    {
        $text = "Zeugnis Frühlingssemester 2026\nKlasse INA3a\nSprache und Kommunikation ........ 4,5\n431 Aufträge durchführen   5.0   5.5\nSemester 2\nTotal 26";
        $zeilen = app(ZeugnisAbgleich::class)->zeilen($text, (int) $this->user->lernender->lernender_id);

        $this->assertSame(['fach:'.$this->fach, 'modul:'.$this->modul], array_column($zeilen, 'bezug'));
        $this->assertSame([4.5, 5.5], array_column($zeilen, 'note'));
    }

    #[Test]
    public function fehlende_zeugnisnoten_werden_am_semesterende_uebernommen(): void
    {
        $lernenderId = (int) $this->user->lernender->lernender_id;
        $ergebnis = app(ZeugnisAbgleich::class)->uebernehmen([
            ['bezug' => 'fach:'.$this->fach, 'note' => '4.5', 'uebernehmen' => '1'],
            ['bezug' => 'modul:'.$this->modul, 'note' => '5.5', 'uebernehmen' => null],
        ], $this->semester, $lernenderId, (int) $this->user->benutzer_id);

        $this->assertSame(1, $ergebnis['neu']);
        $note = Note::firstOrFail();
        $this->assertSame(['Zeugnis', '2026-07-31', 4.5, 100.0], [$note->titel, $note->pruefungsdatum->toDateString(), (float) $note->note_wert, (float) $note->gewichtung_prozent]);
    }

    #[Test]
    public function abgleich_seite_laedt_auch_ohne_textlayer_und_ist_geschuetzt(): void
    {
        $this->actingAs($this->user)->post(route('learner.documents.store'), [
            'datei' => UploadedFile::fake()->create('zeugnis.pdf', 30, 'application/pdf'), 'art' => 'zeugnis', 'semester_id' => $this->semester,
        ])->assertSessionHasNoErrors();
        $dokument = Dokument::firstOrFail();

        $this->get(route('learner.documents.reconcile', $dokument->dokument_id))->assertOk()->assertSee('Kein Text im PDF erkannt');
        $this->actingAs(User::factory()->lernender()->create())->get(route('learner.documents.reconcile', $dokument->dokument_id))->assertNotFound();
    }

    #[Test]
    public function abgleich_uebernehmen_erstellt_noten_aus_ausgewaehlten_zeilen(): void
    {
        $this->actingAs($this->user)->post(route('learner.documents.store'), [
            'datei' => UploadedFile::fake()->create('zeugnis.pdf', 30, 'application/pdf'), 'art' => 'zeugnis', 'semester_id' => $this->semester,
        ])->assertSessionHasNoErrors();
        $dokument = Dokument::firstOrFail();

        $this->actingAs($this->user)->post(route('learner.documents.reconcile.apply', $dokument->dokument_id), [
            'semester_id' => $this->semester,
            'zeilen' => [
                ['bezug' => 'fach:'.$this->fach, 'note' => '4.5', 'uebernehmen' => '1'],
                ['bezug' => 'modul:'.$this->modul, 'note' => '5.5', 'uebernehmen' => null],
            ],
        ])->assertRedirect(route('learner.documents.reconcile', ['dokument_id' => $dokument->dokument_id, 'semester_id' => $this->semester]))
            ->assertSessionHas('success');

        $note = Note::sole();
        $this->assertSame(4.5, (float) $note->note_wert);
        $this->assertSame($this->fach, $note->fach_id);
    }

    #[Test]
    public function abgleich_uebernehmen_ohne_zeilen_wird_abgewiesen(): void
    {
        $this->actingAs($this->user)->post(route('learner.documents.store'), [
            'datei' => UploadedFile::fake()->create('zeugnis.pdf', 30, 'application/pdf'), 'art' => 'zeugnis', 'semester_id' => $this->semester,
        ])->assertSessionHasNoErrors();
        $dokument = Dokument::firstOrFail();

        $this->actingAs($this->user)
            ->post(route('learner.documents.reconcile.apply', $dokument->dokument_id), ['semester_id' => $this->semester])
            ->assertSessionHasErrors('zeilen');

        $this->assertSame(0, Note::count());
    }

    #[Test]
    public function berufsbildner_ohne_aktive_betreuung_kann_zeugnisnoten_nicht_uebernehmen(): void
    {
        $lernenderId = (int) $this->user->lernender->lernender_id;
        $this->actingAs($this->user)->post(route('learner.documents.store'), [
            'datei' => UploadedFile::fake()->create('zeugnis.pdf', 30, 'application/pdf'), 'art' => 'zeugnis', 'semester_id' => $this->semester,
        ])->assertSessionHasNoErrors();
        $dokument = Dokument::firstOrFail();

        $bb = User::factory()->berufsbildner()->create();
        $this->actingAs($bb)
            ->post(route('trainer.learners.documents.reconcile.apply', [$lernenderId, $dokument->dokument_id]), [
                'semester_id' => $this->semester,
                'zeilen' => [['bezug' => 'fach:'.$this->fach, 'note' => '4.5', 'uebernehmen' => '1']],
            ])->assertNotFound();

        $this->assertSame(0, Note::count());
    }
}
