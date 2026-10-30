<?php

declare(strict_types=1);

namespace Tests\Feature\Rechner;

use App\Models\User;
use App\Models\Ziel;
use App\Services\Auswertung\Konfiguration;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Funktionsprüfung «rechner»: ungültige Eingaben nie still verkürzt rechnen (Feldfehler, Ergebnis weg) und
 * Ziel speichern/entfernen ohne Seitenwechsel, damit das Szenario erhalten bleibt.
 */
class RechnerEingabeUndZieleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Konfiguration::vergessen();
    }

    #[Test]
    public function ziel_speichern_per_json_liefert_meldung_und_neue_zielliste_ohne_weiterleitung(): void
    {
        $user = User::factory()->lernender()->create();

        $antwort = $this->actingAs($user)->postJson(route('learner.goals.store'), ['ziel' => 'gesamt', 'zielwert' => 5])
            ->assertOk()
            ->assertJsonPath('message', __('Ziel gespeichert.'))
            ->assertJsonCount(1, 'ziele')
            ->assertJsonPath('ziele.0.ziel', 'gesamt')
            ->assertJsonPath('ziele.0.zielwert', 5);

        $this->assertSame(Ziel::sole()->ziel_id, $antwort->json('ziele.0.id'));
    }

    #[Test]
    public function ziel_speichern_per_json_meldet_ungueltigen_zielwert_als_422(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->postJson(route('learner.goals.store'), ['ziel' => 'gesamt', 'zielwert' => 7])
            ->assertStatus(422)->assertJsonValidationErrors('zielwert');
        $this->assertDatabaseCount('ziele', 0);
    }

    #[Test]
    public function ziel_entfernen_per_json_liefert_meldung_und_restliche_ziele(): void
    {
        $user = User::factory()->lernender()->create();
        $this->actingAs($user)->postJson(route('learner.goals.store'), ['ziel' => 'gesamt', 'zielwert' => 4.5])->assertOk();

        $this->actingAs($user)->deleteJson(route('learner.goals.destroy', Ziel::sole()->ziel_id))
            ->assertOk()
            ->assertJsonPath('message', __('Ziel entfernt.'))
            ->assertJsonPath('ziele', []);
        $this->assertDatabaseCount('ziele', 0);
    }

    #[Test]
    public function ziel_speichern_ohne_javascript_leitet_zum_rechner_mit_dem_gespeicherten_wert(): void
    {
        $user = User::factory()->lernender()->create();

        // Referer mit älterem zielwert (Link aus der Übersicht): der Redirect darf ihn nicht zurückbringen
        $this->actingAs($user)->from(route('learner.grades.calculator', ['ziel' => 'gesamt', 'zielwert' => 4.5]))
            ->post(route('learner.goals.store'), ['ziel' => 'gesamt', 'zielwert' => 5])
            ->assertRedirect(route('learner.grades.calculator', ['ziel' => 'gesamt', 'zielwert' => 5]))
            ->assertSessionHas('success', __('Ziel gespeichert.'));
    }

    #[Test]
    public function rechner_seite_markiert_felder_und_fuehrt_ziele_ohne_formularsprung(): void
    {
        $this->assertTrue(Route::has('learner.goals.destroy'));
        $user = User::factory()->lernender()->create();

        $html = $this->actingAs($user)->get(route('learner.grades.calculator'))->assertOk()->getContent();

        $this->assertStringContainsString('aria-invalid', $html);
        $this->assertStringContainsString('zielwert-fehler', $html);
        $this->assertStringContainsString('-fehler', $html);
        $this->assertStringContainsString('@submit.prevent="speichereZiel()"', html_entity_decode($html));
        $this->assertStringContainsString('Zielwert zwischen 1 und 6.', $html);
        $this->assertStringContainsString('Gewicht zwischen 0 und 100.', $html);
        $this->assertStringContainsString('Note zwischen 1 und 6.', $html);
        // Zielwert als Textfeld: ein Zahlenfeld zeigte «4,5» je nach Browsersprache
        $this->assertSame(1, preg_match('/<input id="zielwert"[^>]*>/s', $html, $z));
        $this->assertStringContainsString('type="text"', $z[0]);
        $this->assertStringContainsString('inputmode="decimal"', $z[0]);
    }

    /** Führt die echte Komponente npRechner in Node aus (Alpine und Netz gestellt) und liefert die Ergebnisse. */
    private function ausfuehren(string $skript): array
    {
        if ((new ExecutableFinder)->find('node') === null) {
            $this->markTestSkipped('node fehlt');
        }
        $dir = sys_get_temp_dir().'/np-rechner-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($dir);

        try {
            $js = str_replace("'./np'", "'./np-stub.mjs'", (string) file_get_contents(resource_path('js/rechner.js')));
            file_put_contents($dir.'/rechner.mjs', $js);
            file_put_contents($dir.'/np-stub.mjs', <<<'JS'
export const format = (v, s = null) => (v === null || v === undefined || v === '' ? '–' : s ? Number(v).toFixed(s) : String(Number(v)));
export const notenKlasse = () => '';
export const stufenFarbe = () => '';
export const zahl = (w) => { const t = String(w ?? '').trim().replace(',', '.'); return /^-?(\d+(\.\d*)?|\.\d+)$/.test(t) ? Number(t) : NaN; };
export const postJson = async (url, body) => { globalThis.anfragen.push(body); return globalThis.antwort(body); };
JS);
            file_put_contents($dir.'/lauf.mjs', <<<JS
globalThis.anfragen = [];
globalThis.toasts = [];
globalThis.window = globalThis;
globalThis.CustomEvent = class { constructor(n, o) { this.type = n; this.detail = o?.detail; } };
globalThis.dispatchEvent = (e) => { globalThis.toasts.push(e.detail); };
globalThis.document = { querySelector: () => null, getElementById: () => ({ focus: () => {} }) };
let fabrik;
const { registriereRechner } = await import('./rechner.mjs');
registriereRechner({ data: (n, f) => { if (n === 'npRechner') fabrik = f; } });
const texte = { fehlerZielwert: 'ZW', fehlerGewicht: 'GW', fehlerNote: 'NT', fehlerZielSpeichern: 'SP', fehlerZielEntfernen: 'EN', ebene: {} };
const erg = { ziel: { zielwert: 4.5 }, loesung: { status: 'benoetigt', note: 4, unbekannte: 1 }, vergleich: [], promotion: [], kurve: [] };
globalThis.antwort = () => erg;
const cfg = { daten: { katalog: { faecher: [{ id: 7, name: 'F', erfassbar: true }], module: [], semester: [{ id: 1, name: 'S' }], kategorien: [] }, grenzen: {}, ziele: [], auswertung: { gesamt: { note: null } }, vorschlaege: [] }, berechnenUrl: '/x', zielUrl: '/goals', zielEntfernenUrl: '/goals/0', start: { ziel: 'gesamt', zielwert: '4.5' }, texte };
const c = fabrik(cfg);
c.\$watch = () => {};
c.\$nextTick = (f) => f();
c.init();
const warte = () => new Promise((r) => setTimeout(r, 30));
const aus = {};
$skript
console.log(JSON.stringify(aus));
JS);
            $p = new Process(['node', $dir.'/lauf.mjs']);
            $p->run();
            $this->assertSame(0, $p->getExitCode(), $p->getErrorOutput().$p->getOutput());

            return json_decode(trim($p->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        } finally {
            File::deleteDirectory($dir);
        }
    }

    #[Test]
    public function ungueltiger_zielwert_blendet_das_ergebnis_aus_und_nennt_den_fehler(): void
    {
        $aus = $this->ausfuehren(<<<'JS'
await warte();
aus.vorher = c.ergebnis !== null;
c.zielwert = '7';
await c.berechnen();
aus.fehler = c.zielwertFehler;
aus.ergebnis = c.ergebnis;
aus.speicherbar = !!c.speicherbar;
aus.anfragen = globalThis.anfragen.length;
c.zielwert = '';
aus.leerFehler = c.zielwertFehler;
JS);
        $this->assertTrue($aus['vorher']);
        $this->assertSame('ZW', $aus['fehler']);
        $this->assertNull($aus['ergebnis'], 'altes Ergebnis darf nicht stehen bleiben');
        $this->assertFalse($aus['speicherbar']);
        $this->assertSame(1, $aus['anfragen'], 'mit ungültigem Zielwert wird nichts gerechnet');
        $this->assertNull($aus['leerFehler'], 'ein leeres Feld ist unvollständig, kein Fehler');
    }

    #[Test]
    public function ungueltige_zeile_wird_nicht_still_gefiltert_sondern_markiert_und_blockiert(): void
    {
        $aus = $this->ausfuehren(<<<'JS'
c.neueZeile('fach'); c.neueZeile('fach');
c.zeilen[0].id = '7'; c.zeilen[0].semester = '1';
c.zeilen[1].id = '7'; c.zeilen[1].semester = '1';
await c.berechnen();
aus.davor = globalThis.anfragen.at(-1).zeilen.length;
const n = globalThis.anfragen.length;
c.zeilen[1].wert = '45';
c.zeilen[0].gewicht = '150';
await c.berechnen();
aus.wertFehler = c.wertFehler(c.zeilen[1]);
aus.gewichtFehler = c.gewichtFehler(c.zeilen[0]);
aus.zeilenFehler = c.zeilenFehler(c.zeilen[0]);
aus.ergebnis = c.ergebnis;
aus.kurve = c.kurve;
aus.neueAnfragen = globalThis.anfragen.length - n;
c.zeilen[1].wert = '4.5'; c.zeilen[0].gewicht = '50';
await c.berechnen();
aus.danach = globalThis.anfragen.at(-1).zeilen.length;
aus.ergebnisDanach = c.ergebnis !== null;
aus.keinFehler = c.zeilenFehler(c.zeilen[1]);
JS);
        $this->assertSame(2, $aus['davor']);
        $this->assertSame('NT', $aus['wertFehler']);
        $this->assertSame('GW', $aus['gewichtFehler']);
        $this->assertSame('GW', $aus['zeilenFehler']);
        $this->assertNull($aus['ergebnis']);
        $this->assertNull($aus['kurve']);
        $this->assertSame(0, $aus['neueAnfragen'], 'es wird nicht mit verkürztem Stand gerechnet');
        $this->assertSame(2, $aus['danach'], 'beide Zeilen gehen in die Rechnung');
        $this->assertTrue($aus['ergebnisDanach']);
        $this->assertSame('', $aus['keinFehler']);
    }

    #[Test]
    public function speichern_und_entfernen_fuehren_die_zielliste_nach_und_behalten_das_szenario(): void
    {
        $aus = $this->ausfuehren(<<<'JS'
c.neueZeile('fach');
c.zeilen[0].id = '7'; c.zeilen[0].semester = '1'; c.zeilen[0].wert = '5';
c.zielwert = '5';
globalThis.fetch = async (url, o) => {
  globalThis.fetches = (globalThis.fetches ?? []).concat([[o.method, url, o.body ?? null]]);
  const ziele = o.method === 'DELETE' ? [] : [{ id: 3, ziel: 'gesamt', label: 'Gesamt', zielwert: 5 }];
  return { ok: true, json: async () => ({ message: o.method === 'DELETE' ? 'weg' : 'ok', ziele }) };
};
await c.speichereZiel();
aus.ziele1 = c.ziele.length;
aus.knopfWert = c.gespeichertesZiel?.zielwert;
await c.entferneZiel(c.ziele[0]);
aus.ziele2 = c.ziele.length;
aus.zeilen = c.zeilen.length;
aus.wert = c.zeilen[0].wert;
aus.zielwert = c.zielwert;
aus.fetches = globalThis.fetches;
aus.toasts = globalThis.toasts;
aus.speichert = c.speichert;
globalThis.fetch = async () => ({ ok: false, json: async () => ({ errors: { zielwert: ['kaputt'] } }) });
await c.speichereZiel();
aus.toastFehler = globalThis.toasts.at(-1);
JS);
        $this->assertSame(1, $aus['ziele1']);
        $this->assertEquals(5, $aus['knopfWert']);
        $this->assertSame(0, $aus['ziele2']);
        $this->assertSame(1, $aus['zeilen'], 'Szenario-Zeile bleibt');
        $this->assertSame('5', $aus['wert']);
        $this->assertSame('5', $aus['zielwert']);
        $this->assertSame([['POST', '/goals', '{"ziel":"gesamt","zielwert":5}'], ['DELETE', '/goals/3', null]], $aus['fetches']);
        $this->assertSame([['message' => 'ok'], ['message' => 'weg']], array_slice($aus['toasts'], 0, 2));
        $this->assertFalse($aus['speichert']);
        $this->assertSame(['message' => 'kaputt', 'art' => 'fehler'], $aus['toastFehler']);
    }
}
