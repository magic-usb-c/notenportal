<?php

declare(strict_types=1);

namespace Tests\Feature\Verwaltung;

use App\Models\Kategorie;
use App\Models\Modul;
use App\Models\Semester;
use App\Models\User;
use App\Support\Einstellungen;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Die Lernendenliste ist eine sortierbare Tabelle: Spaltenköpfe schalten die Richtung um und sagen sie per
 * aria-sort an, eine Suche zeigt die Adresse, die Statusmarken folgen Einstellungen statt festen Werten.
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
    public function spaltenkoepfe_sortieren_und_nennen_die_richtung(): void
    {
        $this->neuerLernender();
        $this->neuerLernender();
        $admin = User::factory()->admin()->create();

        $xpath = $this->dom((string) $this->actingAs($admin)->get(route('admin.learners.index', ['sort' => 'avg', 'dir' => 'desc']))->assertOk()->getContent());
        $koepfe = collect(iterator_to_array($xpath->query('//th[@aria-sort]')))
            ->mapWithKeys(function (\DOMElement $th) {
                parse_str((string) parse_url(html_entity_decode($th->getElementsByTagName('a')->item(0)->getAttribute('href')), PHP_URL_QUERY), $q);

                return [$q['sort'] => ['aria' => $th->getAttribute('aria-sort'), 'dir' => $q['dir']]];
            });

        $this->assertSame(['name', 'lehrjahr', 'last_note', 'avg'], $koepfe->keys()->all());
        $this->assertSame(['aria' => 'descending', 'dir' => 'asc'], $koepfe['avg'], 'Aktive Spalte nennt die Richtung und kehrt sie um');
        $this->assertSame(['aria' => 'none', 'dir' => 'asc'], $koepfe['name']);
    }

    #[Test]
    public function suche_zeigt_die_adresse(): void
    {
        $lernender = $this->neuerLernender();
        $email = $lernender->benutzer->email;
        $admin = User::factory()->admin()->create();
        $zeigtAdresse = fn (array $query) => str_contains((string) $this->dom((string) $this->actingAs($admin)->get(route('admin.learners.index', $query))->assertOk()->getContent())
            ->query('//tbody//div[contains(@class,"text-muted")]')->item(0)?->textContent, $email);

        $this->assertTrue($zeigtAdresse(['suche' => $lernender->benutzer->nachname]), 'Die Suche verschweigt, warum die Person trifft');
        $this->assertFalse($zeigtAdresse([]));
    }

    #[Test]
    public function statusmarke_ohne_note_folgt_der_eingestellten_frist(): void
    {
        Semester::factory()->create();
        $lernender = $this->neuerLernender();
        $modul = Modul::factory()->create();
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $lernender->lehrberuf_id,
            'modul_id' => $modul->modul_id,
            'kategorie_id' => Kategorie::where('code', 'FACH')->value('kategorie_id'),
        ]);
        $this->actingAs($lernender->benutzer)->post(route('learner.grades.store'), [
            'typ' => 'modul', 'modul_id' => $modul->modul_id, 'pruefungsdatum' => now()->subDays(20)->toDateString(), 'note_wert' => 5.0,
        ])->assertSessionHasNoErrors();
        $admin = User::factory()->admin()->create();
        $marken = fn () => collect(iterator_to_array($this->dom((string) $this->actingAs($admin)->get(route('admin.learners.index'))->assertOk()->getContent())
            ->query("//tbody//span[contains(@class,'np-marke')]")))->map(fn (\DOMElement $s) => trim($s->textContent))->all();

        $this->assertContains(__('Ohne BB'), $marken());
        $this->assertNotContains(__('Seit :tage Tagen keine Note', ['tage' => 20]), $marken());

        Einstellungen::set(Einstellungen::FRIST_INAKTIV_TAGE, '14');

        $this->assertContains(__('Seit :tage Tagen keine Note', ['tage' => 20]), $marken());
    }
}
