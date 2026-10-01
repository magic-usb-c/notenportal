<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Kategorie;
use App\Models\Modul;
use App\Models\Semester;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Support\Einstellungen;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

class BerufsbildnerUebersichtTest extends TestCase
{
    use VerwaltungTestHilfen;

    #[Test]
    public function tiefer_schnitt_folgt_der_auswertung_und_der_eingestellten_grenze(): void
    {
        Einstellungen::set(Einstellungen::NOTE_GENUEGEND, '4.5');
        Konfiguration::vergessen();
        Semester::factory()->create();
        $bb = User::factory()->berufsbildner()->create();
        $lernender = User::factory()->lernender()->create();
        $this->betreue($bb, $lernender->lernender);
        $modul = Modul::factory()->create();
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $lernender->lernender->lehrberuf_id,
            'modul_id' => $modul->modul_id,
            'kategorie_id' => Kategorie::where('code', 'FACH')->value('kategorie_id'),
        ]);
        $this->actingAs($lernender)->post(route('learner.grades.store'), [
            'typ' => 'modul', 'modul_id' => $modul->modul_id, 'pruefungsdatum' => now()->toDateString(), 'note_wert' => 4.0,
        ])->assertSessionHasNoErrors();

        $antwort = $this->actingAs(User::factory()->admin()->create())->get(route('admin.trainers.index'))->assertOk();

        $antwort->assertSee('Ø unter 4.5');
        $this->assertSame(1, $antwort->viewData('stats')[$bb->berufsbildner->berufsbildner_id]->tief_avg);
    }

    #[Test]
    public function ohne_note_folgt_der_eingestellten_frist_in_uebersicht_und_lernendenliste(): void
    {
        Semester::factory()->create();
        $bb = User::factory()->berufsbildner()->create();
        $modul = Modul::factory()->create();
        $lernende = [];
        foreach ([20, 5] as $tage) {
            $lernender = User::factory()->lernender()->create();
            $this->betreue($bb, $lernender->lernender);
            DB::table('lehrberuf_module')->insertOrIgnore([
                'lehrberuf_id' => $lernender->lernender->lehrberuf_id,
                'modul_id' => $modul->modul_id,
                'kategorie_id' => Kategorie::where('code', 'FACH')->value('kategorie_id'),
            ]);
            $this->actingAs($lernender)->post(route('learner.grades.store'), [
                'typ' => 'modul', 'modul_id' => $modul->modul_id, 'pruefungsdatum' => now()->subDays($tage)->toDateString(), 'note_wert' => 5.0,
            ])->assertSessionHasNoErrors();
            $lernende[$tage] = $lernender->lernender->lernender_id;
        }
        $admin = User::factory()->admin()->create();
        $bbId = $bb->berufsbildner->berufsbildner_id;
        $ohneNote = fn () => $this->actingAs($admin)->get(route('admin.learners.index', ['warnung' => 'keine_noten']))->assertOk()
            ->viewData('zeilen')->map(fn ($z) => $z->lernender->lernender_id)->all();

        // Standard 30 Tage: beide haben eine Note innerhalb der Frist
        $antwort = $this->actingAs($admin)->get(route('admin.trainers.index'))->assertOk()->assertSee('Ohne Note seit 30 Tagen');
        $this->assertSame(0, $antwort->viewData('stats')[$bbId]->ohne_noten);
        $this->assertSame([], $ohneNote());

        Einstellungen::set(Einstellungen::FRIST_INAKTIV_TAGE, '14');

        $antwort = $this->actingAs($admin)->get(route('admin.trainers.index'))->assertOk()->assertSee('Ohne Note seit 14 Tagen');
        $this->assertSame(1, $antwort->viewData('stats')[$bbId]->ohne_noten);
        $this->assertSame([$lernende[20]], $ohneNote());
        $this->actingAs($admin)->get(route('admin.learners.index'))->assertSee(__('Kein Eintrag seit :tage Tagen', ['tage' => 14]));
    }

    #[Test]
    public function abgeschlossene_lehre_zaehlt_in_keiner_warnung(): void
    {
        Semester::factory()->create();
        $bb = User::factory()->berufsbildner()->create();
        $modul = Modul::factory()->create();
        $lernende = [];
        foreach (['laufend' => now()->addYear(), 'abgeschlossen' => now()->subDays(2)] as $art => $ende) {
            $lernender = User::factory()->lernender()->create();
            $this->betreue($bb, $lernender->lernender);
            DB::table('lehrberuf_module')->insertOrIgnore([
                'lehrberuf_id' => $lernender->lernender->lehrberuf_id,
                'modul_id' => $modul->modul_id,
                'kategorie_id' => Kategorie::where('code', 'FACH')->value('kategorie_id'),
            ]);
            $this->actingAs($lernender)->post(route('learner.grades.store'), [
                'typ' => 'modul', 'modul_id' => $modul->modul_id, 'pruefungsdatum' => now()->subDays(60)->toDateString(), 'note_wert' => 3.0,
            ])->assertSessionHasNoErrors();
            $lernender->lernender->update(['lehrende' => $ende->toDateString()]);
            $lernende[$art] = $lernender->lernender->lernender_id;
        }
        $admin = User::factory()->admin()->create();
        $stats = $this->actingAs($admin)->get(route('admin.trainers.index'))->assertOk()->viewData('stats')[$bb->berufsbildner->berufsbildner_id];
        $liste = fn (string $warnung) => $this->actingAs($admin)->get(route('admin.learners.index', ['warnung' => $warnung]))->assertOk()
            ->viewData('zeilen')->map(fn ($z) => $z->lernender->lernender_id)->values()->all();

        // Kennzahl und Ziel des Links stimmen überein: nur die laufende Lehre.
        $this->assertSame(1, $stats->ohne_noten);
        $this->assertSame(1, $stats->tief_avg);
        $this->assertSame([$lernende['laufend']], $liste('keine_noten'));
        $this->assertSame([$lernende['laufend']], $liste('tief_avg'));
    }
}
