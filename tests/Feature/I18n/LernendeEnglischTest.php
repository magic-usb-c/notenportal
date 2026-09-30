<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Support\Einstellungen;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * i18n-Nachweis für den Lernenden-Bereich (Paket C): die Seiten lassen sich vollständig auf
 * Englisch rendern, ohne fehlende Übersetzungsschlüssel, und zeigen erkennbar englischen Text.
 */
class LernendeEnglischTest extends TestCase
{
    private function bereite(): User
    {
        $this->seed(BasisSeeder::class);
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $modul = DB::table('module')->insertGetId(['modul_nummer' => '908', 'titel' => 'Testmodul Theta durchfuehren']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $lehrberuf, 'modul_id' => $modul,
            'kategorie_id' => DB::table('kategorien')->where('code', 'FACH')->value('kategorie_id')]);
        DB::table('semester')->insert(['bezeichnung' => 'T-1', 'start_datum' => now()->subMonths(8)->toDateString(), 'end_datum' => now()->subMonths(2)->toDateString(), 'sortierung' => 10]);
        DB::table('semester')->insert(['bezeichnung' => 'T-2', 'start_datum' => now()->subMonths(2)->addDay()->toDateString(), 'end_datum' => now()->addMonths(4)->toDateString(), 'sortierung' => 11]);
        Konfiguration::vergessen();

        Einstellungen::set(SetLocale::WAHL_AKTIV, '1');
        $user = User::factory(['vorname' => 'Nando', 'nachname' => 'Lolli', 'locale' => 'en'])
            ->lernender(['lehrberuf_id' => $lehrberuf])->create();
        $this->actingAs($user);

        $this->post(route('learner.grades.store'), [
            'typ' => 'modul', 'modul_id' => $modul, 'pruefungsdatum' => now()->subDays(3)->toDateString(),
            'note_wert' => '5.0', 'gewichtung_prozent' => 100,
        ])->assertSessionHasNoErrors();

        return $user;
    }

    #[Test]
    public function lernenden_seiten_rendern_vollstaendig_auf_englisch_ohne_fehlende_schluessel(): void
    {
        $this->bereite();

        $fehlend = [];
        Lang::handleMissingKeysUsing(function (string $key, array $replace, ?string $locale) use (&$fehlend) {
            if ($locale === 'en') {
                $fehlend[] = $key;
            }

            return null;
        });

        $seiten = [
            'learner' => route('learner.dashboard'),
            'grades' => route('learner.grades.index'),
            'agenda' => route('learner.exams.index'),
            'calculator' => route('learner.grades.calculator'),
            'documents' => route('learner.documents.index'),
        ];

        $inhalte = [];
        foreach ($seiten as $name => $url) {
            $inhalte[$name] = $this->get($url)->assertOk()->getContent();
        }

        Lang::handleMissingKeysUsing(null);

        $this->assertSame([], array_values(array_unique($fehlend)), 'Fehlende Übersetzungsschlüssel beim Rendern auf Englisch: '.implode(', ', array_unique($fehlend)));

        $this->assertStringContainsString('Grades', $inhalte['learner']);
        $this->assertStringContainsString('Agenda', $inhalte['learner']);

        $this->assertStringContainsString('Grade', $inhalte['grades']);

        $this->assertStringContainsString('Exam', $inhalte['agenda']);

        $this->assertStringContainsString('Calculator', $inhalte['calculator']);
        $this->assertStringContainsString('Subject', $inhalte['calculator']);

        $this->assertStringContainsString('Documents', $inhalte['documents']);
        $this->assertStringContainsString('Upload', $inhalte['documents']);
    }
}
