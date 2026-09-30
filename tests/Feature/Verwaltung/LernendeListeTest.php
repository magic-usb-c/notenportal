<?php

declare(strict_types=1);

namespace Tests\Feature\Verwaltung;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Die Lernendenliste zeigt je nach Breite des Inhalts Karten oder Tabelle. Die Karten dürfen dabei nichts
 * verlieren, was die Tabelle bietet: weder die Sortierung der Spaltenköpfe noch die Statusmarken.
 */
class LernendeListeTest extends TestCase
{
    use VerwaltungTestHilfen;

    private function dom(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8">'.$html, LIBXML_NOERROR);

        return new \DOMXPath($dom);
    }

    #[Test]
    public function kartenansicht_bietet_jede_sortierung_der_spaltenkoepfe(): void
    {
        $this->neuerLernender();
        $this->neuerLernender();
        $admin = User::factory()->admin()->create();

        $html = (string) $this->actingAs($admin)->get(route('admin.learners.index', ['sort' => 'avg', 'dir' => 'desc']))->assertOk()->getContent();
        $xpath = $this->dom($html);

        $parameter = function (string $url): string {
            parse_str((string) parse_url(html_entity_decode($url), PHP_URL_QUERY), $q);

            return ($q['sort'] ?? '').':'.($q['dir'] ?? '');
        };
        $optionen = collect(iterator_to_array($xpath->query('//select[@id="sortierung"]/option')))
            ->mapWithKeys(fn (\DOMElement $o) => [$parameter($o->getAttribute('value')) => $o->hasAttribute('selected')]);
        $spalten = collect(iterator_to_array($xpath->query('//*[@data-ansicht="tabelle"]//th[@aria-sort]//a')))
            ->map(fn (\DOMElement $a) => strtok($parameter($a->getAttribute('href')), ':'))->unique()->values();

        $this->assertNotEmpty($spalten);
        foreach ($spalten as $sort) {
            $this->assertArrayHasKey("{$sort}:asc", $optionen->all(), "Sortierung {$sort} aufsteigend fehlt in den Karten");
            $this->assertArrayHasKey("{$sort}:desc", $optionen->all(), "Sortierung {$sort} absteigend fehlt in den Karten");
        }
        $this->assertSame(['avg:desc'], $optionen->filter()->keys()->all(), 'Die aktuelle Sortierung ist gewählt');
    }

    #[Test]
    public function karten_zeigen_dieselben_statusmarken_wie_die_tabelle(): void
    {
        // Ohne Noten und ohne Berufsbildner: zwei Marken, die es nur in der Statusspalte gab
        $this->neuerLernender();
        $admin = User::factory()->admin()->create();

        $html = (string) $this->actingAs($admin)->get(route('admin.learners.index'))->assertOk()->getContent();
        $xpath = $this->dom($html);
        $marken = fn (string $ansicht) => collect(iterator_to_array($xpath->query("//*[@data-ansicht=\"{$ansicht}\"]//span[contains(@class,'rounded-full')]")))
            ->map(fn (\DOMElement $s) => trim($s->textContent))->sort()->values()->all();

        $this->assertContains(__('Keine Noten'), $marken('tabelle'));
        $this->assertContains(__('Ohne BB'), $marken('tabelle'));
        $this->assertSame($marken('tabelle'), $marken('karten'));
    }
}
