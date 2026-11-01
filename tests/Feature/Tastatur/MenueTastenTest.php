<?php

declare(strict_types=1);

namespace Tests\Feature\Tastatur;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Funktionsprüfung «klicktest»: Menüs (x-dropdown) lassen sich mit den Pfeiltasten bedienen (WAI-ARIA Menu Button,
 * macOS-Menü): Öffnen per Tastatur fokussiert den ersten Eintrag, Pfeil hoch den letzten, im Menü bewegen
 * Pfeil hoch/runter, Pos1 und Ende den Fokus. Das Verhalten selbst läuft in Alpine im Browser; hier steht das Markup.
 */
class MenueTastenTest extends TestCase
{
    private function menue(): string
    {
        return $this->blade(<<<'BLADE'
            <x-dropdown>
                <x-slot name="trigger"><button type="button">Mehr</button></x-slot>
                <x-slot name="content">
                    <a href="/eins" class="np-menue-eintrag">Eins</a>
                    <form method="POST" action="/zwei"><button type="submit" class="np-menue-eintrag">Zwei</button></form>
                </x-slot>
            </x-dropdown>
        BLADE)->__toString();
    }

    #[Test]
    public function menue_oeffnet_per_tastatur_mit_fokus_auf_den_ersten_eintrag(): void
    {
        $html = $this->menue();

        // Klick ohne Zeiger (Enter, Leertaste) fokussiert den ersten Eintrag, ein Mausklick nichts
        $this->assertStringContainsString('$event.detail === 0', $html);
        $this->assertStringContainsString("fokus('erster')", $html);
    }

    #[Test]
    public function menue_oeffnet_mit_pfeiltasten_auf_dem_ausloeser(): void
    {
        $html = $this->menue();

        $this->assertStringContainsString('@keydown.arrow-down.prevent', $html);
        $this->assertStringContainsString('@keydown.arrow-up.prevent', $html);
        $this->assertStringContainsString("fokus('letzter')", $html);
    }

    #[Test]
    public function menue_bewegt_den_fokus_mit_pfeiltasten_pos1_und_ende(): void
    {
        $html = $this->menue();

        $this->assertStringContainsString('menueTaste($event)', $html);
        foreach (['ArrowDown', 'ArrowUp', 'Home', 'End'] as $taste) {
            $this->assertStringContainsString($taste, $html);
        }
        // Knöpfe in Formularen zählen als Eintrag, deaktivierte und Rollen mit eigenem Tabindex nicht
        $this->assertStringContainsString('a[href], button:not([disabled])', $html);
        // Der Darstellungs-Schalter im Kontomenü bewegt sich selbst mit den Pfeiltasten (x-radiogroup) und ist kein Menüeintrag
        $this->assertStringContainsString(':not([role=radio])', $html);
    }

    #[Test]
    public function menue_behaelt_escape_und_rollen_ohne_menuitem(): void
    {
        $html = $this->menue();

        $this->assertStringContainsString('@keydown.escape.window', $html);
        $this->assertStringContainsString('aria-haspopup', $html);
        $this->assertStringNotContainsString('role="menuitem"', $html);
    }
}
