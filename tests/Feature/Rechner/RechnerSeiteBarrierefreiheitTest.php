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
        $this->assertSame(2, substr_count($html, '<th scope="row" class="truncate text-left font-normal"'));
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
}
