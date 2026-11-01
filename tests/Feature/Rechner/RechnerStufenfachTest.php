<?php

declare(strict_types=1);

namespace Tests\Feature\Rechner;

use App\Models\Fach;
use App\Models\Lernender;
use App\Models\LernenderTrack;
use App\Models\Pruefung;
use App\Models\Semester;
use App\Models\User;
use App\Models\Ziel;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\NotenQuelle;
use App\Services\Auswertung\Rechner;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Stufenfächer (z. B. Sport) haben keinen Notenschnitt: nie als geplante Leistung, im Rechner oder als Ziel. */
class RechnerStufenfachTest extends TestCase
{
    private User $user;

    private Lernender $lernender;

    private Fach $sport;

    private Fach $idaf;

    private Fach $mathe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->lernender()->create();
        $this->lernender = $this->user->lernender;
        $semester = Semester::factory()->create([
            'start_datum' => now()->subMonths(3)->toDateString(),
            'end_datum' => now()->addMonths(3)->toDateString(),
            'sortierung' => 2002,
        ]);
        LernenderTrack::create([
            'lernender_id' => $this->lernender->lernender_id, 'track_typ' => 'BMS',
            'start_datum' => now()->subYear()->toDateString(), 'start_semester_id' => $semester->semester_id,
        ]);
        $this->sport = Fach::factory()->create(['skala' => 'stufe', 'zaehlt' => false, 'name' => 'Sport']);
        $this->idaf = Fach::factory()->create(['skala' => 'note', 'zaehlt' => false, 'name' => 'Interdisziplinäres Arbeiten']);
        $this->mathe = Fach::factory()->create(['name' => 'Mathematik']);
        foreach ([$this->sport, $this->idaf, $this->mathe] as $i => $fach) {
            Pruefung::create([
                'lernender_id' => $this->lernender->lernender_id, 'fach_id' => $fach->fach_id,
                'titel' => 'Prüfung '.$i, 'datum' => now()->addDays(10 + $i)->toDateString(), 'gewichtung_prozent' => 100,
            ]);
        }
        Konfiguration::vergessen();
    }

    #[Test]
    public function geplante_pruefung_im_stufenfach_ist_keine_leistung(): void
    {
        $geplante = app(NotenQuelle::class)->geplante([(int) $this->lernender->lernender_id])[(int) $this->lernender->lernender_id] ?? [];
        $faecher = array_map(fn ($l) => $l->fachId, $geplante);

        $this->assertNotContains((int) $this->sport->fach_id, $faecher);
        $this->assertContains((int) $this->mathe->fach_id, $faecher);
        $this->assertContains((int) $this->idaf->fach_id, $faecher, 'Nicht zählende Fächer mit Note bleiben unverändert');
    }

    #[Test]
    public function stufenfach_ist_nicht_im_rechnerkatalog_und_nicht_in_den_vorschlaegen(): void
    {
        $rechner = app(Rechner::class);
        $ids = collect($rechner->katalog($this->lernender)['faecher'])->pluck('id')->all();

        $this->assertNotContains((int) $this->sport->fach_id, $ids);
        $this->assertContains((int) $this->mathe->fach_id, $ids);

        $a = app(NotenQuelle::class)->auswertung((int) $this->lernender->lernender_id, true);
        $elemente = collect($rechner->vorschlaege($this->lernender, $a))->pluck('element')->implode(' ');
        $this->assertStringNotContainsString('fach:'.$this->sport->fach_id.'@', $elemente);
    }

    #[Test]
    public function stufenfach_laesst_sich_nicht_als_ziel_speichern(): void
    {
        $this->actingAs($this->user)
            ->post(route('learner.goals.store'), ['ziel' => 'fach:'.$this->sport->fach_id, 'zielwert' => 4.5])
            ->assertSessionHasErrors('ziel');

        $this->assertSame(0, Ziel::where('lernender_id', $this->lernender->lernender_id)->count());

        $this->actingAs($this->user)
            ->post(route('learner.goals.store'), ['ziel' => 'fach:'.$this->mathe->fach_id, 'zielwert' => 4.5])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Ziel::where('lernender_id', $this->lernender->lernender_id)->count());
    }

    #[Test]
    public function rechner_lehnt_simulierte_note_im_stufenfach_ab(): void
    {
        $sid = (int) Semester::query()->value('semester_id');
        $zeile = fn (Fach $f) => ['ziel' => 'gesamt', 'zielwert' => 4.5,
            'zeilen' => [['element' => "fach:{$f->fach_id}@semester:{$sid}", 'gewicht' => 100, 'wert' => 5, 'datum' => null]]];

        $this->actingAs($this->user)->postJson(route('learner.grades.calculator.calculate'), $zeile($this->sport))
            ->assertStatus(422)->assertJsonValidationErrors('zeilen.0.element');
        $this->actingAs($this->user)->postJson(route('learner.grades.calculator.calculate'), $zeile($this->mathe))->assertOk();
    }

    #[Test]
    public function agenda_nennt_fuer_das_stufenfach_keine_benoetigte_note(): void
    {
        $html = $this->actingAs($this->user)->get(route('learner.exams.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Prüfung 0', $html, 'Die geplante Sportprüfung bleibt in der Agenda sichtbar');
        $this->assertStringNotContainsString('ziel=fach%3A'.$this->sport->fach_id.'%40', $html, 'Stufenfach: kein «Was brauche ich?»');
        $this->assertStringContainsString('ziel=fach%3A'.$this->mathe->fach_id.'%40', $html);

        $json = $this->actingAs($this->user)->getJson(route('learner.exams.index'))->assertOk()->json();
        $titel = collect($json['diagramm']['zeitleiste']['zeilen'] ?? [])->pluck('titel')->all();

        $this->assertNotContains('Prüfung 0', $titel, 'Stufenfach: keine nötige Note');
        $this->assertContains('Prüfung 2', $titel);
    }
}
