<?php

declare(strict_types=1);

namespace Tests\Feature\Auswertung;

use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Notenbaum\BaumVorlage;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

/**
 * Abschlussseite: Leerwert der Prognose über <x-note> (dünner Strich statt Balken) und Baum-Tabelle mit Beschriftung und Zeilenkopf.
 */
class AbschlussSeiteTest extends TestCase
{
    use VerwaltungTestHilfen;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'AST', 'name' => 'Abschlussseiten-Test EFZ']);
        DB::table('semester')->insert(['bezeichnung' => 'AS-1', 'start_datum' => '2024-08-01', 'end_datum' => now()->addYears(3)->toDateString(), 'sortierung' => 1]);
        Konfiguration::vergessen();
        $this->user = $this->neuerLernender([], ['lehrberuf_id' => $lehrberuf])->benutzer;
        app(BaumVorlage::class)->importieren(BaumVorlage::laden('informatiker-efz-bivo2020'), $lehrberuf);
    }

    private function seite(): string
    {
        return $this->actingAs($this->user)->get(route('learner.qualification.index'))->assertOk()->getContent();
    }

    #[Test]
    public function leere_prognose_zeigt_den_duennen_platzhalter_der_notenkomponente_und_keinen_balken(): void
    {
        $html = $this->seite();

        // Komponente: innerer Strich dünn (font-normal) in text-xl, nicht der fette Heldenstrich
        $this->assertStringContainsString('font-normal text-muted text-xl', $html);
        $this->assertDoesNotMatchRegularExpression('/<span[^>]*font-semibold leading-none text-muted[^>]*>–<\/span>/u', $html);
    }

    #[Test]
    public function baumtabelle_hat_caption_und_zeilenkopf_je_teil(): void
    {
        $html = $this->seite();

        $tabellen = preg_match_all('/<table class="np-tabelle table-fixed text-sm">\s*<caption class="sr-only">[^<]+<\/caption>(.*?)<\/table>/s', $html, $treffer);
        $this->assertGreaterThan(0, $tabellen, 'Baum-Tabelle ohne caption');
        foreach ($treffer[1] as $tabelle) {
            $this->assertMatchesRegularExpression('/<th scope="row"[^>]*>/', $tabelle);
            $this->assertDoesNotMatchRegularExpression('/<tbody>\s*<tr>\s*<td class="pl-/', $tabelle, 'Namenszelle ist noch td');
        }
    }
}
