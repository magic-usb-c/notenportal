<?php

declare(strict_types=1);

namespace Tests\Feature\Verwaltung;

use App\Models\Fach;
use App\Models\Lernender;
use App\Models\Pruefung;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Support\Format;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Funktionsprüfung «klicktest»: gekürzte Zeilen im Cockpit tragen den vollen Text als title. */
class CockpitTooltipGekuerzteZeilenTest extends TestCase
{
    use VerwaltungTestHilfen;

    #[Test]
    public function naechste_pruefung_traegt_den_vollen_text_als_title(): void
    {
        $lernender = $this->neuerLernender();
        $bb = $this->verwalter('trainer', $lernender);
        $fach = Fach::factory()->create(['name' => 'Wirtschaft und Recht mit sehr langem Namen']);
        $pruefung = Pruefung::create([
            'lernender_id' => $lernender->lernender_id, 'fach_id' => $fach->fach_id, 'datum' => now()->addDays(5)->toDateString(),
            'gewichtung_prozent' => 100, 'quelle' => Pruefung::MANUELL,
        ]);
        $voll = e($pruefung->bezeichnung().' · '.Format::wann($pruefung->datum));

        $this->actingAs($bb)->get(route('trainer.learners.show', $lernender->lernender_id))
            ->assertOk()
            ->assertSee('title="'.$voll.'"', false);
    }

    #[Test]
    public function modulstatus_zeile_traegt_titel_und_zeitangabe_als_title(): void
    {
        $this->seed(BasisSeeder::class);
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TTP', 'name' => 'Tooltip-Test EFZ']);
        $modul = DB::table('module')->insertGetId(['modul_nummer' => '433', 'titel' => 'Kundendaten verwalten']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $lehrberuf, 'modul_id' => $modul,
            'kategorie_id' => DB::table('kategorien')->where('code', 'FACH')->value('kategorie_id')]);
        DB::table('semester')->insert([
            'bezeichnung' => 'TTP-1', 'start_datum' => now()->subMonths(6)->toDateString(), 'end_datum' => now()->addMonths(6)->toDateString(), 'sortierung' => 20,
        ]);
        Konfiguration::vergessen();

        $lernender = $this->neuerLernender([], ['lehrberuf_id' => $lehrberuf]);
        $benutzer = User::find(Lernender::findOrFail($lernender->lernender_id)->benutzer_id);
        $this->actingAs($benutzer)->post(route('learner.grades.store'), [
            'typ' => 'modul', 'modul_id' => $modul, 'pruefungsdatum' => now()->subDays(30)->toDateString(),
            'note_wert' => '5.0', 'gewichtung_prozent' => 40,
        ])->assertSessionHasNoErrors();
        Pruefung::create([
            'lernender_id' => $lernender->lernender_id, 'modul_id' => $modul, 'art' => Pruefung::ART_ABGABE,
            'titel' => 'Schlussdokumentation', 'datum' => now()->addDays(10)->toDateString(),
            'gewichtung_prozent' => 20, 'quelle' => Pruefung::MANUELL,
        ]);

        $html = $this->actingAs($this->verwalter('admin', $lernender))
            ->get(route('admin.learners.show', $lernender->lernender_id))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/title="[^"]*Kundendaten verwalten[^"]*"/', $html);
        $this->assertMatchesRegularExpression('/title="Schlussdokumentation · [^"]+"/', $html);
    }
}
