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
 * «Meine Lernenden» auf der Übersicht: jede Spalte ist sichtbar, jeder sortierbare Kopf schaltet die Richtung,
 * die ganze Zeile öffnet die Lernenden, und ein leeres Filterergebnis steht als eigene Zeile in der Tabelle.
 */
class UebersichtTabelleTest extends TestCase
{
    use VerwaltungTestHilfen;

    private function seite(User $bb, array $query = []): \DOMXPath
    {
        $html = (string) $this->actingAs($bb)->get(route('trainer.dashboard', $query))->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8">'.$html, LIBXML_NOERROR);

        return new \DOMXPath($dom);
    }

    /** @return array{string, string} sort, dir */
    private static function sortierung(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        return [$q['sort'] ?? '', $q['dir'] ?? ''];
    }

    /** Lernender mit einer Note im vergangenen und einer im laufenden Semester, damit es einen Verlauf gibt */
    private function mitVerlauf(Lernender $lernender, float $vorher, float $jetzt): void
    {
        $alt = Semester::factory()->create(['start_datum' => now()->subMonths(9)->toDateString(), 'end_datum' => now()->subMonths(3)->toDateString(), 'sortierung' => 1]);
        $neu = Semester::factory()->create(['sortierung' => 2]);
        Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'semester_id' => $alt->semester_id, 'note_wert' => $vorher, 'pruefungsdatum' => now()->subMonths(5)->toDateString()]);
        Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'semester_id' => $neu->semester_id, 'note_wert' => $jetzt, 'pruefungsdatum' => now()->subWeek()->toDateString()]);
    }

    #[Test]
    public function jeder_sortierbare_kopf_schaltet_zwischen_aufsteigend_und_absteigend(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $a = $this->neuerLernender();
        $this->betreue($bb, $a);
        $this->betreue($bb, $this->neuerLernender());
        $this->mitVerlauf($a, 5.0, 4.0);

        $spalten = collect(iterator_to_array($this->seite($bb)->query('//th[@aria-sort]//a')))
            ->map(fn (\DOMElement $link) => self::sortierung($link->getAttribute('href')));
        $this->assertSame(['status', 'name', 'trend', 'semester', 'gesamt'], $spalten->pluck(0)->all());
        $this->assertSame(['asc'], $spalten->pluck(1)->unique()->values()->all(), 'Ein inaktiver Kopf sortiert zuerst aufsteigend');

        foreach ($spalten->pluck(0) as $sort) {
            $xpath = $this->seite($bb, ['sort' => $sort, 'dir' => 'asc']);
            $kopf = $xpath->query('//th[@aria-sort="ascending"]')->item(0);
            $this->assertNotNull($kopf, "«{$sort}» aufsteigend ist markiert");
            $this->assertSame([$sort, 'desc'], self::sortierung($xpath->query('.//a', $kopf)->item(0)->getAttribute('href')));
            $this->assertSame(1, $xpath->query('//th[@aria-sort!="none"]')->length, 'Nur eine Spalte ist aktiv');
        }
    }

    #[Test]
    public function alle_spalten_sind_ohne_breitenstufen_sichtbar(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $this->betreue($bb, $this->neuerLernender());

        $xpath = $this->seite($bb);
        $koepfe = collect(iterator_to_array($xpath->query('//table/thead/tr/th')));

        $this->assertCount(8, $koepfe);
        foreach ($koepfe as $th) {
            $this->assertDoesNotMatchRegularExpression('/(^|\s)(hidden|@[\w\-\[\]]+:)/', $th->getAttribute('class'), trim($th->textContent));
        }
    }

    #[Test]
    public function die_zeile_oeffnet_die_lernenden_und_traegt_einen_echten_link(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $lernender = $this->neuerLernender();
        $this->betreue($bb, $lernender);

        $zeile = $this->seite($bb)->query('//tbody/tr[@data-href]')->item(0);

        $this->assertNotNull($zeile);
        $this->assertSame(route('trainer.learners.show', $lernender->lernender_id), $zeile->getAttribute('data-href'));
        $this->assertSame($zeile->getAttribute('data-href'), $zeile->getElementsByTagName('a')->item(0)?->getAttribute('href'));
    }

    #[Test]
    public function ein_leeres_filterergebnis_steht_als_zeile_ueber_alle_spalten(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $this->betreue($bb, $this->neuerLernender());

        $xpath = $this->seite($bb);
        $leer = $xpath->query('//tbody/tr[not(@data-href)]/td')->item(0);

        $this->assertNotNull($leer);
        $this->assertStringStartsWith(__('Keine Lernenden für diesen Filter.'), trim($leer->textContent));
        $this->assertNotNull($xpath->query('//tbody/tr[not(@data-href)]/td/button')->item(0), 'Leerzeile bietet einen Weg zurück');
        $this->assertSame((string) $xpath->query('//table/thead/tr/th')->length, $leer->getAttribute('colspan'));
        $this->assertTrue($leer->parentNode->hasAttribute('x-cloak'), 'Erst sichtbar, wenn Alpine kein Treffer findet');
    }
}
