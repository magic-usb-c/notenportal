<?php

declare(strict_types=1);

namespace Tests\Feature\Statistik;

use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Notenbaum\BaumVorlage;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

/** Die Tabelle «Was fehlt zum Bestehen» im Abschluss ist für Screenreader beschriftet und nach Zeilen gelesen. */
class AbschlussTabelleTest extends TestCase
{
    use DiagrammTabellenHilfen;
    use VerwaltungTestHilfen;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'ATT', 'name' => 'Abschluss-Tabellen-Test EFZ']);
        DB::table('semester')->insert(['bezeichnung' => 'AT-1', 'start_datum' => '2024-08-01', 'end_datum' => now()->addYears(3)->toDateString(), 'sortierung' => 1]);
        Konfiguration::vergessen();
        $this->user = $this->neuerLernender([], ['lehrberuf_id' => $lehrberuf])->benutzer;
        app(BaumVorlage::class)->importieren(BaumVorlage::laden('informatiker-efz-bivo2020'), $lehrberuf);
    }

    #[Test]
    public function die_tabelle_der_offenen_positionen_hat_caption_und_zeilenkoepfe_und_zahlen_rechts(): void
    {
        $antwort = $this->actingAs($this->user)->get(route('learner.qualification.index'))->assertOk();
        $tabelle = $this->tabelleNach($antwort->getContent(), 'Was fehlt zum Bestehen');

        $this->pruefeZeilenkopf($tabelle, $antwort->viewData('statistik')['meta']['positionen']['titel']);
        $this->assertStringContainsString('text-right', $tabelle->query('//thead//th[2]')->item(0)->getAttribute('class'));
        $this->assertStringContainsString('text-right', $tabelle->query('//tbody//tr[1]/td[1]')->item(0)->getAttribute('class'));
    }
}
