<?php

declare(strict_types=1);

namespace Tests\Feature\Berufsbildner;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

/**
 * Übersicht in schmalen Fenstern (1280 und 1440 px): die Klassentabelle behält ihre Mindestbreite und die
 * Nebenspalte rutscht darunter, statt dass «Neue Noten» und «Nächste Prüfung» in der Karte abgeschnitten werden.
 */
class UebersichtSchmalTest extends TestCase
{
    use VerwaltungTestHilfen;

    private function seite(User $bb): \DOMXPath
    {
        $html = (string) $this->actingAs($bb)->get(route('trainer.dashboard'))->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8">'.$html, LIBXML_NOERROR);

        return new \DOMXPath($dom);
    }

    #[Test]
    public function tabellenspalte_und_nebenspalte_umbrechen_statt_die_tabelle_abzuschneiden(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $this->betreue($bb, $this->neuerLernender());

        $xpath = $this->seite($bb);
        $tabelle = '//table[caption[normalize-space()="'.__('Meine Lernenden').'"]]';
        $haupt = $xpath->query($tabelle.'/ancestor::div[contains(concat(" ", normalize-space(@class), " "), " min-w-231 ")][1]');

        $this->assertSame(1, $haupt->length, 'Die Tabellenspalte trägt eine Mindestbreite, die alle acht Spalten fasst');
        $raster = $haupt->item(0)->parentNode;
        $this->assertStringContainsString('flex-wrap', $raster->getAttribute('class'), 'Die Spalten umbrechen untereinander, wenn die Mindestbreiten nicht nebeneinander passen');
        $this->assertStringNotContainsString('grid-cols-12', $raster->getAttribute('class'));
        $this->assertGreaterThan(0, $xpath->query('./div[contains(concat(" ", normalize-space(@class), " "), " min-w-76 ")]', $raster)->length, 'Die Nebenspalte hat ihre eigene Mindestbreite');
    }

    #[Test]
    public function namensspalte_laesst_der_letzten_spalte_platz_fuer_ihren_kopf(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $this->betreue($bb, $this->neuerLernender());

        $cols = $this->seite($bb)->query('//table[caption[normalize-space()="'.__('Meine Lernenden').'"]]/colgroup/col');
        $breiten = collect(iterator_to_array($cols))->map(fn (\DOMElement $c) => $c->getAttribute('class'))->all();

        // 8 Spalten, die letzte ist flexibel; die festen Breiten summieren sich auf höchstens 800 px
        $this->assertSame(['w-32', 'w-44', 'w-20', 'w-32', 'w-24', 'w-24', 'w-24', ''], $breiten);
    }
}
