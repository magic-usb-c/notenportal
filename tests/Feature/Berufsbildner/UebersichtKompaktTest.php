<?php

declare(strict_types=1);

namespace Tests\Feature\Berufsbildner;

use App\Models\Lernender;
use App\Models\Note;
use App\Models\Semester;
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

    /** Container-Stufen von Tailwind in aufsteigender Breite */
    private const array STUFEN = ['3xs', '2xs', 'xs', 'sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl', '6xl', '7xl'];

    /** Lernender mit einer Note im vergangenen und einer im laufenden Semester, damit es einen Verlauf gibt */
    private function mitVerlauf(Lernender $lernender, float $vorher, float $jetzt): void
    {
        $alt = Semester::factory()->create(['start_datum' => now()->subMonths(9)->toDateString(), 'end_datum' => now()->subMonths(3)->toDateString(), 'sortierung' => 1]);
        $neu = Semester::factory()->create(['sortierung' => 2]);
        Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'semester_id' => $alt->semester_id, 'note_wert' => $vorher, 'pruefungsdatum' => now()->subMonths(5)->toDateString()]);
        Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'semester_id' => $neu->semester_id, 'note_wert' => $jetzt, 'pruefungsdatum' => now()->subWeek()->toDateString()]);
    }

    #[Test]
    public function jede_sortierung_der_spaltenkoepfe_ist_in_beiden_richtungen_waehlbar_und_wird_angezeigt(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $a = $this->neuerLernender();
        $b = $this->neuerLernender();
        $this->betreue($bb, $a);
        $this->betreue($bb, $b);
        $this->mitVerlauf($a, 5.0, 4.0);

        $xpath = $this->seite($bb);
        $optionen = collect(iterator_to_array($xpath->query('//select[@id="sortierung"]/option')))
            ->mapWithKeys(fn (\DOMElement $o) => [self::sortierung($o->getAttribute('value')) => $o->hasAttribute('selected')]);
        $spalten = collect(iterator_to_array($xpath->query('//th[@aria-sort]//a')))
            ->map(fn (\DOMElement $a) => strtok(self::sortierung($a->getAttribute('href')), ':'))->unique()->values();

        $this->assertContains('trend', $spalten->all(), 'Mit zwei Semestern gibt es einen Verlauf');
        foreach ($spalten as $sort) {
            foreach (['asc', 'desc'] as $dir) {
                $this->assertTrue($optionen->has("{$sort}:{$dir}"), "Sortierung «{$sort}:{$dir}» fehlt in der Auswahl");
            }
        }
        $this->assertSame(['status:asc'], $optionen->filter()->keys()->all(), 'Ohne Wahl gilt die Standardreihenfolge: kritische zuerst');

        // Jede Sortierung, die ein Spaltenkopf einstellen kann, erscheint danach als gewählt
        foreach ($optionen->keys() as $schluessel) {
            [$sort, $dir] = explode(':', $schluessel);
            $gewaehlt = collect(iterator_to_array($this->seite($bb, ['sort' => $sort, 'dir' => $dir])->query('//select[@id="sortierung"]/option[@selected]')))
                ->map(fn (\DOMElement $o) => self::sortierung($o->getAttribute('value')))->all();
            $this->assertSame([$schluessel], $gewaehlt);
        }
    }

    #[Test]
    public function die_auswahl_bleibt_bis_alle_sortierbaren_spaltenkoepfe_sichtbar_sind(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $a = $this->neuerLernender();
        $this->betreue($bb, $a);
        $this->betreue($bb, $this->neuerLernender());
        $this->mitVerlauf($a, 5.0, 4.0);

        $xpath = $this->seite($bb);
        $stufe = fn (string $klassen, string $art) => preg_match('/(?:^|\s)@([a-z0-9]+):'.$art.'(?:\s|$)/', $klassen, $m) ? array_search($m[1], self::STUFEN, true) : null;
        $auswahl = $stufe($xpath->query('//select[@id="sortierung"]')->item(0)->getAttribute('class'), 'hidden');
        $koepfe = collect(iterator_to_array($xpath->query('//th[@aria-sort]')))
            ->map(fn (\DOMElement $th) => $stufe($th->getAttribute('class'), 'table-cell'))->filter(fn ($s) => $s !== null);

        $this->assertNotNull($auswahl, 'Die Auswahl verschwindet ab einer Kartenbreite');
        $this->assertNotEmpty($koepfe);
        $this->assertGreaterThanOrEqual($koepfe->max(), $auswahl, 'Die Auswahl verschwindet, bevor jeder sortierbare Spaltenkopf sichtbar ist');
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
        // Der Status der ausgeblendeten Statusspalte steht als eigenes Etikett in der Namenszelle, schmal sichtbar
        $spalte = trim($xpath->query('//tbody/tr/td[1]')->item(0)->textContent);
        $etikett = $xpath->query('.//span[contains(@class,"@xl:hidden") and span[@aria-hidden="true" and contains(@class,"rounded-full")]]', $namenszelle)->item(0);
        $this->assertNotSame('', $spalte);
        $this->assertNotNull($etikett, 'Statusetikett in der Namenszelle');
        $this->assertSame($spalte, trim($etikett->textContent));
    }
}
