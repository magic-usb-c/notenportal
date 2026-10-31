<?php

declare(strict_types=1);

namespace Tests\Feature\Rechner;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Rechner-Seite (Block R6): Tabellen mit Beschriftung und Zeilenkopf, Zusammenfassung der Kurve auch ohne Spielraum,
 * Flächen nur über Füll-Tokens.
 */
class RechnerSeiteBarrierefreiheitTest extends TestCase
{
    private function seite(): string
    {
        $user = User::factory()->lernender()->create();

        $antwort = $this->actingAs($user)->get(route('learner.grades.calculator'));
        $antwort->assertOk();

        return $antwort->getContent();
    }

    #[Test]
    public function tabellen_auswirkung_und_promotion_haben_beschriftung_und_zeilenkopf(): void
    {
        $html = $this->seite();

        $this->assertStringContainsString('<caption class="sr-only">Auswirkung</caption>', $html);
        $this->assertStringContainsString('<caption class="sr-only">Promotion</caption>', $html);

        // Erste Zelle jeder Zeile ist ein Zeilenkopf (th scope=row), nicht td
        // Auswirkung kürzt lange Namen, Promotion umbricht sie (schmale Spalte, nichts darf abgeschnitten werden)
        $this->assertSame(1, substr_count($html, '<th scope="row" class="truncate text-left font-normal"'));
        $this->assertSame(1, substr_count($html, '<th scope="row" class="text-left font-normal"'));
        $this->assertDoesNotMatchRegularExpression('/<tr>\s*<td class="truncate"/', $html);
    }

    #[Test]
    public function kurve_nennt_den_wert_auch_wenn_die_offenen_pruefungen_nichts_bewegen(): void
    {
        $html = $this->seite();

        $this->assertStringContainsString('Endnote bleibt bei :wert, egal welche Note', $html);
        // Der Satz hängt am Canvas-Label und am Absatz unter der Kurve (beide über denselben Ausdruck) und rechnet mit dem echten Wert
        $this->assertStringContainsString("replace(':wert', fmt(ergebnis.loesung.minimum))) : '')", html_entity_decode($html, ENT_QUOTES));
    }

    #[Test]
    public function zeilenstreifen_und_entfernen_hover_nutzen_fuell_tokens(): void
    {
        $html = $this->seite();

        $this->assertStringNotContainsString('bg-text/', $html);
        $this->assertStringContainsString('even:bg-fill-2', $html);
        $this->assertStringContainsString('hover:bg-fill ', $html);
    }

    #[Test]
    public function gekuerzter_zeilenkopf_der_auswirkung_traegt_den_vollen_namen_als_title(): void
    {
        $html = $this->seite();

        $this->assertMatchesRegularExpression(
            '/<th scope="row" class="truncate text-left font-normal"[^>]*\s:title="v\.label"/',
            $html,
        );
    }

    #[Test]
    public function promotion_tabelle_fluchtet_in_den_randspalten_mit_auswirkung_und_titel(): void
    {
        $html = $this->seite();

        $start = strpos($html, '<caption class="sr-only">Promotion</caption>');
        $this->assertNotFalse($start);
        $tabelle = substr($html, $start, strpos($html, '</table>', $start) - $start);

        // Randspalten behalten das Standardpolster der Tabelle (12 px) wie die Auswirkung, nur die inneren Zahlenspalten sind enger
        $this->assertStringContainsString('<th scope="col">Kategorie</th>', $tabelle);
        $this->assertStringContainsString('<th scope="col" class="text-right">Promotion</th>', $tabelle);
        $this->assertStringContainsString('<th scope="row" class="text-left font-normal">', $tabelle);
        $this->assertStringContainsString('<td class="text-right">', $tabelle);
        $this->assertSame(3, substr_count($tabelle, '<th scope="col" class="px-2 text-right">'));
        $this->assertStringNotContainsString('<th scope="col" class="px-2">', $tabelle);
    }

    #[Test]
    public function promotion_kopf_hat_keinen_erklaerenden_tooltip(): void
    {
        $this->assertStringNotContainsString('Heute → Szenario', $this->seite());
    }
}
