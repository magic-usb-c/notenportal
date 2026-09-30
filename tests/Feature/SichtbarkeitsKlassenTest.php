<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Einstellungen;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tailwind ordnet .hidden im gebauten CSS vor .inline-flex, .flex usw. ein. Stehen beide ohne
 * Breitenpräfix am selben Element, gewinnt die Display-Klasse: das Element ist auf jeder Breite
 * sichtbar. So lagen Seitenleisten-Schalter und Feedback-Symbol auch mobil in der Leiste.
 * Ausblenden unterhalb einer Breite deshalb mit max-lg:hidden usw.
 */
class SichtbarkeitsKlassenTest extends TestCase
{
    private const DISPLAY = ['block', 'inline-block', 'inline', 'flex', 'inline-flex', 'grid', 'inline-grid', 'table', 'table-cell', 'table-row', 'contents', 'flow-root', 'list-item'];

    public static function seiten(): array
    {
        return [
            'Admin' => ['admin', 'admin.dashboard'],
            'Berufsbildner' => ['berufsbildner', 'trainer.dashboard'],
            'Lernende' => ['lernender', 'learner.dashboard'],
        ];
    }

    #[Test]
    #[DataProvider('seiten')]
    public function kein_element_traegt_hidden_zusammen_mit_einer_display_klasse(string $rolle, string $route): void
    {
        $user = User::factory()->{$rolle}()->create();
        $html = (string) $this->actingAs($user)->get(route($route))->assertOk()->getContent();

        preg_match_all('/\sclass="([^"]*)"/', $html, $treffer);
        $konflikte = collect($treffer[1])
            ->map(fn (string $k) => preg_split('/\s+/', trim($k)))
            ->filter(fn (array $k) => in_array('hidden', $k, true) && array_intersect($k, self::DISPLAY) !== [])
            ->map(fn (array $k) => implode(' ', $k))
            ->values()->all();

        $this->assertSame([], $konflikte);
    }

    /**
     * «order-*» ordnet nur die Anzeige um: Tabulator und Screenreader folgen weiter dem DOM (WCAG 2.4.3).
     * Die Reihenfolge der Karten steht deshalb im DOM, nicht im CSS.
     */
    #[Test]
    #[DataProvider('seiten')]
    public function dashboard_ordnet_karten_nicht_per_css_um(string $rolle, string $route): void
    {
        $user = User::factory()->{$rolle}()->create();
        $html = (string) $this->actingAs($user)->get(route($route))->assertOk()->getContent();

        preg_match_all('/\sclass="([^"]*)"/', $html, $treffer);
        $umordnend = collect($treffer[1])
            ->flatMap(fn (string $k) => preg_split('/\s+/', trim($k)))
            ->filter(fn (string $k) => preg_match('/^([a-z0-9-]+:)*-?order-/', $k) === 1)
            ->unique()->values()->all();

        $this->assertSame([], $umordnend);
    }

    /**
     * Priority+: die Leiste legt, was nicht passt, in «Mehr». Das geht nur, wenn «Mehr» jedes Ziel der Leiste
     * kennt – sonst wäre ein ausgelagerter Eintrag bei grosser Schrift oder schmalem Fenster unerreichbar.
     */
    #[Test]
    #[DataProvider('seiten')]
    public function mehr_menue_kennt_jedes_ziel_der_leiste(string $rolle, string $route): void
    {
        $user = User::factory()->{$rolle}()->create();
        $html = (string) $this->actingAs($user)->get(route($route))->assertOk()->getContent();

        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8">'.$html, LIBXML_NOERROR);
        $xpath = new \DOMXPath($dom);
        $ziele = fn (string $ausdruck) => collect(iterator_to_array($xpath->query($ausdruck)))
            ->map(fn (\DOMElement $a) => $a->getAttribute('href'))->unique()->sort()->values()->all();

        $leiste = $ziele('//*[@x-data="npLeistenUeberlauf"]/*[@data-ueberlauf]/descendant-or-self::a');
        $mehr = $ziele('//*[@data-mehr-menue]//*[@data-mehr]/descendant-or-self::a');

        $this->assertNotSame([], $leiste);
        $this->assertSame($leiste, $mehr);
    }

    #[Test]
    public function feedback_symbol_in_der_leiste_nur_ohne_schwebenden_knopf(): void
    {
        $user = User::factory()->lernender()->create();

        Einstellungen::set(Einstellungen::FEEDBACK_KNOPF, '1');
        $this->actingAs($user)->get(route('learner.dashboard'))->assertOk()->assertDontSee('title="'.__('Feedback melden').'"', false);

        Einstellungen::set(Einstellungen::FEEDBACK_KNOPF, '0');
        $this->actingAs($user)->get(route('learner.dashboard'))->assertOk()->assertSee('title="'.__('Feedback melden').'"', false);
    }
}
