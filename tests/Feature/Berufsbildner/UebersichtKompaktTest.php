<?php

declare(strict_types=1);

namespace Tests\Feature\Berufsbildner;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

/**
 * «Meine Lernenden» blendet schmal Spalten aus (Status, Lehrjahr, Verlauf, Gesamt, nächste Prüfung). Was
 * dabei verschwindet, muss in der Zeile stehen bleiben, und jede Sortierung der Spaltenköpfe bleibt wählbar.
 */
class UebersichtKompaktTest extends TestCase
{
    use VerwaltungTestHilfen;

    private function seite(User $bb, array $query = []): \DOMXPath
    {
        $html = (string) $this->actingAs($bb)->get(route('trainer.dashboard', $query))->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8">'.$html, LIBXML_NOERROR);

        return new \DOMXPath($dom);
    }

    private static function sortierung(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        return ($q['sort'] ?? '').':'.($q['dir'] ?? '');
    }

    #[Test]
    public function jede_sortierung_der_spaltenkoepfe_ist_auch_ohne_spaltenkoepfe_waehlbar(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $this->betreue($bb, $this->neuerLernender());
        $this->betreue($bb, $this->neuerLernender());

        $xpath = $this->seite($bb);
        $optionen = collect(iterator_to_array($xpath->query('//select[@id="sortierung"]/option')))
            ->mapWithKeys(fn (\DOMElement $o) => [self::sortierung($o->getAttribute('value')) => $o->hasAttribute('selected')]);
        $spalten = collect(iterator_to_array($xpath->query('//th[@aria-sort]//a')))
            ->map(fn (\DOMElement $a) => strtok(self::sortierung($a->getAttribute('href')), ':'))->unique()->values();

        $this->assertContains('status', $spalten->all());
        foreach ($spalten as $sort) {
            $this->assertTrue($optionen->keys()->contains(fn (string $k) => str_starts_with($k, "{$sort}:")), "Sortierung «{$sort}» fehlt in der Auswahl");
        }
        $this->assertSame(['status:asc'], $optionen->filter()->keys()->all(), 'Ohne Wahl gilt die Standardreihenfolge: kritische zuerst');

        $xpath = $this->seite($bb, ['sort' => 'gesamt', 'dir' => 'desc']);
        $gewaehlt = collect(iterator_to_array($xpath->query('//select[@id="sortierung"]/option[@selected]')))
            ->map(fn (\DOMElement $o) => self::sortierung($o->getAttribute('value')))->all();
        $this->assertSame(['gesamt:desc'], $gewaehlt);
    }

    #[Test]
    public function die_zeile_nennt_gesamtnote_und_status_auch_ohne_ihre_spalten(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $this->betreue($bb, $this->neuerLernender());

        $xpath = $this->seite($bb);
        $namenszelle = $xpath->query('//tbody/tr/td[a[contains(@href,"/trainer/learners/")]]')->item(0);

        $this->assertNotNull($namenszelle);
        $this->assertStringContainsString(__('Gesamt'), $namenszelle->textContent);
        $this->assertGreaterThan(0, $xpath->query('.//*[contains(@class,"@xl:hidden")]', $namenszelle)->length);
    }
}
