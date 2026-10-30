<?php

declare(strict_types=1);

namespace Tests\Feature\Rechner;

use App\Models\Lernender;
use App\Models\User;
use App\Models\Ziel;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\NotenQuelle;
use App\Services\Auswertung\Rechner;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RechnerInaktivesModulTest extends TestCase
{
    private User $user;

    private Lernender $lernender;

    private int $modul;

    protected function setUp(): void
    {
        parent::setUp();

        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Testberuf EFZ']);
        $this->modul = DB::table('module')->insertGetId(['modul_nummer' => '908', 'titel' => 'Testmodul']);
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $lehrberuf, 'modul_id' => $this->modul,
            'kategorie_id' => DB::table('kategorien')->where('code', 'FACH')->value('kategorie_id'),
        ]);
        DB::table('semester')->insert(['bezeichnung' => 'S', 'start_datum' => '2026-02-01', 'end_datum' => '2026-12-31', 'sortierung' => 10]);
        Konfiguration::vergessen();

        $this->user = User::factory()->lernender(['lehrberuf_id' => $lehrberuf])->create();
        $this->lernender = $this->user->lernender;
        $this->actingAs($this->user)
            ->post(route('learner.grades.store'), [
                'typ' => 'modul', 'modul_id' => $this->modul, 'pruefungsdatum' => '2026-03-02',
                'note_wert' => 4, 'gewichtung_prozent' => 60,
            ])->assertSessionHasNoErrors();
        Ziel::create(['lernender_id' => $this->lernender->lernender_id, 'ebene' => 'gesamt', 'zielwert' => 5.0]);
    }

    public static function tabellen(): array
    {
        return [
            'Modul global deaktiviert' => ['module'],
            'Modul im Lehrberuf deaktiviert' => ['lehrberuf_module'],
        ];
    }

    #[Test]
    #[DataProvider('tabellen')]
    public function deaktiviertes_modul_mit_note_laesst_rechner_dashboard_und_cockpit_offen(string $tabelle): void
    {
        DB::table($tabelle)->where('modul_id', $this->modul)->update(['aktiv' => 0]);
        Konfiguration::vergessen();

        $this->actingAs($this->user)->get(route('learner.grades.calculator'))->assertOk();
        $this->actingAs($this->user)->get(route('learner.dashboard'))->assertOk();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.learners.show', $this->lernender->lernender_id))->assertOk();
    }

    #[Test]
    #[DataProvider('tabellen')]
    public function restgewicht_des_deaktivierten_moduls_zaehlt_weiter_fuer_die_benoetigte_note(string $tabelle): void
    {
        $rechner = app(Rechner::class);
        $rechne = function () use ($rechner): array {
            $seite = $rechner->seite($this->lernender, false);

            return $rechner->berechne($this->lernender, [
                'ziel' => 'modul:'.$this->modul, 'zielwert' => 4.6, 'zeilen' => $seite['vorschlaege'],
            ])['loesung'];
        };

        $vorher = $rechne();
        $this->assertSame('benoetigt', $vorher['status']);

        DB::table($tabelle)->where('modul_id', $this->modul)->update(['aktiv' => 0]);
        Konfiguration::vergessen();

        $seite = $rechner->seite($this->lernender, false);
        $eintrag = collect($seite['katalog']['module'])->firstWhere('id', $this->modul);
        $this->assertNotNull($eintrag, 'Modul mit Note bleibt im Katalog');
        $this->assertFalse($eintrag['erfassbar'], 'Inaktives Modul ist nicht zur Neuerfassung wählbar');

        $nachher = $rechne();
        $this->assertSame('benoetigt', $nachher['status']);
        $this->assertEqualsWithDelta($vorher['note'], $nachher['note'], 0.001);
        $this->assertCount(1, $rechner->offeneLeistungen($this->lernender, app(NotenQuelle::class)->auswertung((int) $this->lernender->lernender_id)));
    }

    #[Test]
    public function aktives_modul_ist_erfassbar(): void
    {
        $eintrag = collect(app(Rechner::class)->katalog($this->lernender)['module'])->firstWhere('id', $this->modul);
        $this->assertTrue($eintrag['erfassbar']);
    }
}
