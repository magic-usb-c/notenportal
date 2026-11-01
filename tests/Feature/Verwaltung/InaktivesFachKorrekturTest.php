<?php

namespace Tests\Feature\Verwaltung;

use App\Models\Fach;
use App\Models\Lernender;
use App\Models\Note;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Wird ein Fach deaktiviert, das schon Noten hat (der vom Portal empfohlene Weg), bleiben diese Noten
 * korrigierbar. Neue Noten und das Umhängen auf ein anderes inaktives Fach bleiben gesperrt.
 */
class InaktivesFachKorrekturTest extends TestCase
{
    use VerwaltungTestHilfen;

    private Lernender $lernender;

    private Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        $this->lernender = $this->neuerLernender();
        $this->semester = Semester::factory()->create();
    }

    /** @return array<string, array{0: string}> */
    public static function varianten(): array
    {
        return ['Track-Fach' => ['track'], 'Lehrberuf-Fach' => ['beruf']];
    }

    /** @return array{0: Fach, 1: Note} Fach (noch aktiv) mit einer Note von heute */
    private function fachMitNote(string $variante): array
    {
        if ($variante === 'track') {
            $fach = Fach::factory()->create(['track_typ' => 'BMS', 'name' => 'Inaktiv Track']);
            DB::table('lernender_tracks')->insert([
                'lernender_id' => $this->lernender->lernender_id, 'track_typ' => 'BMS',
                'start_datum' => '2020-01-01', 'start_semester_id' => $this->semester->semester_id,
            ]);
        } else {
            $fach = Fach::factory()->create(['track_typ' => null, 'name' => 'Inaktiv Beruf']);
            DB::table('lehrberuf_faecher')->insert([
                'lehrberuf_id' => $this->lernender->lehrberuf_id, 'fach_id' => $fach->fach_id, 'aktiv' => 1,
            ]);
        }

        $note = Note::factory()->create([
            'lernender_id' => $this->lernender->lernender_id,
            'semester_id' => $this->semester->semester_id,
            'fach_id' => $fach->fach_id,
            'kategorie_id' => $fach->kategorie_id,
            'pruefungsdatum' => today()->toDateString(),
            'note_wert' => 3.0,
        ]);

        return [$fach, $note];
    }

    private function deaktiviere(Fach $fach): void
    {
        DB::table('faecher')->where('fach_id', $fach->fach_id)->update(['aktiv' => 0]);
    }

    /** @return array<string, mixed> */
    private function eingabe(Fach $fach, string $wert = '4.0', ?string $datum = null): array
    {
        return [
            'typ' => 'fach', 'fach_id' => $fach->fach_id, 'pruefungsdatum' => $datum ?? today()->toDateString(),
            'note_wert' => $wert, 'gewichtung_prozent' => 100,
        ];
    }

    private function wert(Note $note): float
    {
        return (float) DB::table('noten')->where('note_id', $note->note_id)->value('note_wert');
    }

    #[Test]
    #[DataProvider('varianten')]
    public function lernende_korrigieren_note_eines_inaktiven_fachs(string $variante): void
    {
        [$fach, $note] = $this->fachMitNote($variante);
        $this->deaktiviere($fach);
        $user = User::find($this->lernender->benutzer_id);

        $this->actingAs($user)->put(route('learner.grades.update', $note->note_id), $this->eingabe($fach))
            ->assertSessionHasNoErrors();

        $this->assertSame(4.0, $this->wert($note));
        $this->assertSame($fach->fach_id, $note->fresh()->fach_id);
    }

    #[Test]
    #[DataProvider('varianten')]
    public function lernende_sehen_das_inaktive_fach_im_bearbeiten_formular(string $variante): void
    {
        [$fach, $note] = $this->fachMitNote($variante);
        $this->deaktiviere($fach);
        $user = User::find($this->lernender->benutzer_id);

        $this->actingAs($user)->get(route('learner.grades.edit', $note->note_id))
            ->assertOk()->assertSee('value="fach:'.$fach->fach_id.'"', false);
        $this->actingAs($user)->get(route('learner.grades.edit', ['note_id' => $note->note_id, 'drawer' => 1]))
            ->assertOk()->assertSee('value="fach:'.$fach->fach_id.'"', false);
    }

    #[Test]
    #[DataProvider('varianten')]
    public function neue_note_im_inaktiven_fach_bleibt_abgelehnt(string $variante): void
    {
        [$fach] = $this->fachMitNote($variante);
        $this->deaktiviere($fach);
        $user = User::find($this->lernender->benutzer_id);

        $this->actingAs($user)->post(route('learner.grades.store'), $this->eingabe($fach))
            ->assertSessionHasErrors('fach_id');

        $this->assertSame(1, Note::query()->where('lernender_id', $this->lernender->lernender_id)->count());
    }

    #[Test]
    #[DataProvider('varianten')]
    public function note_laesst_sich_nicht_auf_ein_anderes_inaktives_fach_umhaengen(string $variante): void
    {
        [$fach, $note] = $this->fachMitNote($variante);
        $anderes = Fach::factory()->create(['track_typ' => $fach->track_typ, 'name' => 'Anderes inaktiv', 'aktiv' => 0]);
        if ($variante === 'beruf') {
            DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $this->lernender->lehrberuf_id, 'fach_id' => $anderes->fach_id, 'aktiv' => 1]);
        }
        $this->deaktiviere($fach);
        $user = User::find($this->lernender->benutzer_id);

        $this->actingAs($user)->put(route('learner.grades.update', $note->note_id), $this->eingabe($anderes))
            ->assertSessionHasErrors('fach_id');

        $this->assertSame($fach->fach_id, $note->fresh()->fach_id);
        $this->assertSame(3.0, $this->wert($note));
    }

    #[Test]
    public function track_fach_ausserhalb_der_track_zeit_bleibt_abgelehnt(): void
    {
        [$fach, $note] = $this->fachMitNote('track');
        DB::table('lernender_tracks')->where('lernender_id', $this->lernender->lernender_id)->update(['end_datum' => '2020-06-30']);
        $this->deaktiviere($fach);
        $user = User::find($this->lernender->benutzer_id);

        $this->actingAs($user)->put(route('learner.grades.update', $note->note_id), $this->eingabe($fach))
            ->assertSessionHasErrors('fach_id');

        $this->assertSame(3.0, $this->wert($note));
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function verwalter_korrigieren_note_eines_inaktiven_fachs(string $bereich): void
    {
        [$fach, $note] = $this->fachMitNote('track');
        $this->deaktiviere($fach);
        $verwalter = $this->verwalter($bereich, $this->lernender);
        $lid = $this->lernender->lernender_id;

        $this->actingAs($verwalter)->get(route("{$bereich}.learners.grades.edit", [$lid, $note->note_id]))
            ->assertOk()->assertSee('value="fach:'.$fach->fach_id.'"', false);

        $this->actingAs($verwalter)->put(route("{$bereich}.learners.grades.update", [$lid, $note->note_id]), $this->eingabe($fach))
            ->assertSessionHasNoErrors();

        $this->assertSame(4.0, $this->wert($note));
    }

    #[Test]
    public function admin_kann_im_inaktiven_fach_keine_neue_note_erfassen(): void
    {
        [$fach] = $this->fachMitNote('track');
        $this->deaktiviere($fach);
        $admin = User::factory()->admin()->create();
        $lid = $this->lernender->lernender_id;

        $this->actingAs($admin)->post(route('admin.learners.grades.store', $lid), $this->eingabe($fach))
            ->assertSessionHasErrors('fach_id');
        $this->actingAs($admin)->get(route('admin.learners.grades.create', $lid))
            ->assertOk()->assertDontSee('value="fach:'.$fach->fach_id.'"', false);
    }
}
