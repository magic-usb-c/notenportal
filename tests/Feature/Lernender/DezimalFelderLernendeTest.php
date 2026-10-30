<?php

declare(strict_types=1);

namespace Tests\Feature\Lernender;

use App\Models\Note;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Funktionsprüfung «lernende»: Dezimalfelder sind Textfelder (der Browser zeigt vorbelegte Werte sonst nach seiner
 * Sprache mit Komma), ein getipptes Komma gilt überall als Dezimaltrennzeichen; Deep-Link-Scrollen respektiert
 * reduzierte Bewegung, Ziele halten den Fokus, der Notendrawer meldet seinen Ladezustand.
 */
class DezimalFelderLernendeTest extends TestCase
{
    private int $modul;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $this->modul = DB::table('module')->insertGetId(['modul_nummer' => '908', 'titel' => 'Testmodul Theta durchführen']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $lehrberuf, 'modul_id' => $this->modul,
            'kategorie_id' => DB::table('kategorien')->where('code', 'FACH')->value('kategorie_id')]);
        DB::table('semester')->insert(['bezeichnung' => 'T-2', 'start_datum' => now()->subMonths(2)->toDateString(), 'end_datum' => now()->addMonths(4)->toDateString(), 'sortierung' => 11]);
        Konfiguration::vergessen();
        $this->user = User::factory()->lernender(['lehrberuf_id' => $lehrberuf])->create();
        $this->actingAs($this->user);
    }

    private function daten(array $mehr = []): array
    {
        return $mehr + ['typ' => 'modul', 'modul_id' => $this->modul, 'pruefungsdatum' => now()->subDays(3)->toDateString(),
            'note_wert' => '4,5', 'gewichtung_prozent' => '33,5'];
    }

    #[Test]
    public function note_mit_dezimalkomma_erfassen_und_bearbeiten_speichert_mit_punkt(): void
    {
        $this->post(route('learner.grades.store'), $this->daten())->assertSessionHasNoErrors();
        $note = Note::query()->where('lernender_id', $this->user->lernender->lernender_id)->sole();
        $this->assertEquals(4.5, (float) $note->note_wert);
        $this->assertEquals(33.5, (float) $note->gewichtung_prozent);

        $this->put(route('learner.grades.update', ['note_id' => $note->note_id]), $this->daten(['note_wert' => '5,25', 'gewichtung_prozent' => '50']))
            ->assertSessionHasNoErrors();
        $this->assertEquals(5.25, (float) $note->refresh()->note_wert);
    }

    #[Test]
    public function dezimalfelder_im_notenformular_sind_textfelder_mit_dezimaltastatur(): void
    {
        $html = $this->get(route('learner.grades.create'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input type="text"[^>]*id="note_wert"[^>]*inputmode="decimal"/', $html);
        $this->assertMatchesRegularExpression('/<input type="text"[^>]*id="gewichtung_prozent"[^>]*inputmode="decimal"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input type="number"[^>]*id="(note_wert|gewichtung_prozent)"/', $html);
        $this->assertStringContainsString('window.np.zahl(', html_entity_decode($html), 'Gewicht-Segmente lesen das Feld über zahl()');
        $this->assertStringNotContainsString('parseFloat(gewicht)', html_entity_decode($html));
    }

    #[Test]
    public function rechnerzeilen_haben_note_und_gewicht_als_dezimalfeld_mit_fehlermeldung(): void
    {
        foreach (['learner.grades.index', 'learner.grades.calculator'] as $route) {
            $html = $this->get(route($route))->assertOk()->getContent();

            foreach (['z.wert', 'z.gewicht'] as $modell) {
                $this->assertMatchesRegularExpression('/<input type="text" inputmode="decimal" autocomplete="off" x-model="'.preg_quote($modell, '/').'"/', $html, "{$route} {$modell}");
            }
            $this->assertDoesNotMatchRegularExpression('/<input type="number"[^>]*x-model="z\.(wert|gewicht)"/', $html, $route);
            $this->assertStringContainsString(':aria-invalid="gewichtFehler(z)', $html, $route);
            $this->assertStringContainsString(':aria-invalid="wertFehler(z)', $html, $route);
        }
    }

    #[Test]
    public function deep_link_scrollt_nur_bei_normaler_bewegung_sanft(): void
    {
        $html = $this->get(route('learner.grades.index'))->assertOk()->getContent();

        $this->assertStringContainsString('scrollIntoView', $html);
        $this->assertStringNotContainsString("behavior: 'smooth'", $html);
        $this->assertStringContainsString('bewegungRuhig', $html);
    }

    #[Test]
    public function ziel_knoepfe_im_rechner_werden_nie_deaktiviert_der_fokus_bleibt(): void
    {
        $html = html_entity_decode($this->get(route('learner.grades.calculator'))->assertOk()->getContent());

        $this->assertStringNotContainsString(':disabled="speichert', $html);
        $this->assertStringContainsString('aria-disabled', $html);
        $this->assertStringContainsString('aria-busy', $html);
    }

    #[Test]
    public function notendrawer_meldet_ladezustand_und_nutzt_fuellflaechen(): void
    {
        $html = $this->get(route('learner.grades.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-np-inhalt[^>]*:aria-busy="laedt"/', $html);
        $this->assertMatchesRegularExpression('/x-show="laedt"[^>]*aria-hidden="true">\s*<div class="[^"]*bg-fill"/', $html);
        $this->assertDoesNotMatchRegularExpression('/x-show="laedt".{0,400}bg-surface-2/s', $html);
    }

    /** Führt die echte rechner.js mit dem echten np.js in Node aus (Alpine und Netz gestellt). */
    private function ausfuehren(string $skript, array $cfg = []): array
    {
        if ((new ExecutableFinder)->find('node') === null) {
            $this->markTestSkipped('node fehlt');
        }
        $dir = sys_get_temp_dir().'/np-dezimal-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($dir);

        try {
            file_put_contents($dir.'/rechner.mjs', str_replace("'./np'", "'./np.mjs'", (string) file_get_contents(resource_path('js/rechner.js'))));
            file_put_contents($dir.'/np.mjs', (string) file_get_contents(resource_path('js/np.js')));
            file_put_contents($dir.'/lauf.mjs', <<<JS
globalThis.window = globalThis;
globalThis.CustomEvent = class { constructor(n, o) { this.type = n; this.detail = o?.detail; } };
globalThis.dispatchEvent = (e) => { (globalThis.toasts ??= []).push(e.detail); };
globalThis.focusiert = [];
globalThis.document = { querySelector: () => null, getElementById: (id) => ({ focus: () => globalThis.focusiert.push(id) }) };
const fabriken = {};
const { registriereRechner } = await import('./rechner.mjs');
registriereRechner({ data: (n, f) => { fabriken[n] = f; } });
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
    public function notenformular_vorschau_und_farbe_verstehen_ein_dezimalkomma(): void
    {
        $aus = $this->ausfuehren(<<<'JS'
globalThis.angefragt = [];
globalThis.fetch = async (url, o) => { globalThis.angefragt.push(JSON.parse(o.body)); return { ok: true, json: async () => ({ vergleich: [] }) }; };
const c = fabriken.npNotenFormular({ bezug: 'modul:5', datum: '2026-01-01', gewicht: '33,5', wert: '4,5', stufe: '', stufen: [], semester: [], vorschauUrl: '/v', grenzen: { gut: 5, genuegend: 4, kritisch: 3.5 } });
c.$watch = () => {};
c.init();
await warte(); await warte(); await warte(); await warte(); await warte(); await warte(); await warte(); await warte(); await warte(); await warte();
aus.zeilen = globalThis.angefragt.map((a) => a.zeilen[0]);
aus.klasse = c.klasse('3,5') === c.klasse('3.5');
aus.klasseKomma = c.klasse('4,6') === c.klasse('4.6');
JS);
        $this->assertSame([['element' => 'modul:5', 'gewicht' => 33.5, 'wert' => 4.5, 'datum' => '2026-01-01']], $aus['zeilen']);
        $this->assertTrue($aus['klasse']);
        $this->assertTrue($aus['klasseKomma']);
    }

    #[Test]
    public function mehrfacherfassung_rechnet_mit_komma_noten(): void
    {
        $aus = $this->ausfuehren(<<<'JS'
const c = fabriken.npNotenrechnerDrawer({ heute: '2026-01-01', semesterListe: [] });
c.zeilen = [{ nr: 1, bezug: 'modul:5', datum: '2026-01-01', gewicht: '50', wert: '4,5' }, { nr: 2, bezug: 'modul:5', datum: '2026-01-01', gewicht: '100', wert: '4,5,6' }];
aus.zeilen = c.gueltigeZeilen;
JS);
        $this->assertCount(1, $aus['zeilen'], 'unlesbare Eingaben werden nicht still zu 4 verkürzt');
        $this->assertSame(4.5, $aus['zeilen'][0]['note_wert']);
        $this->assertSame(50, $aus['zeilen'][0]['gewichtung_prozent']);
    }

    #[Test]
    public function notenrechner_drawer_meldet_werte_ausserhalb_und_rechnet_sie_nicht(): void
    {
        $aus = $this->ausfuehren(<<<'JS'
const c = fabriken.npNotenrechnerDrawer({ heute: '2026-01-01', semesterListe: [], texte: { fehlerGewicht: 'G', fehlerNote: 'N' } });
const z = { nr: 1, bezug: 'modul:5', datum: '2026-01-01', gewicht: '150', wert: '7' };
c.zeilen = [z, { nr: 2, bezug: 'modul:5', datum: '2026-01-01', gewicht: '12,5', wert: '4,5' }];
aus.fehler = c.zeilenFehler(z);
aus.leer = c.zeilenFehler({ gewicht: '', wert: '' });
aus.zeilen = c.gueltigeZeilen;
JS);
        $this->assertSame('G N', $aus['fehler']);
        $this->assertSame('', $aus['leer'], 'leer ist unvollständig, kein Fehler');
        $this->assertCount(1, $aus['zeilen']);
        $this->assertSame(12.5, $aus['zeilen'][0]['gewichtung_prozent']);
    }

    #[Test]
    public function rechner_startet_mit_komma_zielwert_aus_der_adresse_mit_punkt(): void
    {
        $aus = $this->ausfuehren(<<<'JS'
const cfg = { daten: { katalog: { faecher: [], module: [], semester: [], kategorien: [] }, grenzen: {}, ziele: [], auswertung: { gesamt: { note: null } }, vorschlaege: [] }, berechnenUrl: '/x', zielUrl: '/goals', zielEntfernenUrl: '/goals/0', start: { ziel: 'gesamt', zielwert: '4,5' }, texte: {} };
globalThis.fetch = async () => ({ ok: true, json: async () => ({}) });
const c = fabriken.npRechner(cfg);
c.$watch = () => {};
c.init();
aus.zielwert = c.zielwert;
JS);
        $this->assertSame('4.5', $aus['zielwert']);
    }

    #[Test]
    public function ziel_speichern_und_entfernen_setzt_den_fokus_auf_das_zielwertfeld(): void
    {
        $aus = $this->ausfuehren(<<<'JS'
const cfg = { daten: { katalog: { faecher: [], module: [], semester: [], kategorien: [] }, grenzen: {}, ziele: [], auswertung: { gesamt: { note: null } }, vorschlaege: [] }, berechnenUrl: '/x', zielUrl: '/goals', zielEntfernenUrl: '/goals/0', start: { ziel: 'gesamt', zielwert: '4.5' }, texte: {} };
globalThis.fetch = async (url, o) => ({ ok: true, json: async () => ({ message: 'ok', ziele: o.method === 'DELETE' ? [] : [{ id: 3, ziel: 'gesamt', label: 'G', zielwert: 5 }] }) });
const c = fabriken.npRechner(cfg);
c.$watch = () => {};
c.$nextTick = (f) => f();
c.init();
c.zielwert = '5';
await c.speichereZiel();
aus.nachSpeichern = [...globalThis.focusiert];
aus.schonGespeichert = c.zielSchonGespeichert;
await c.speichereZiel();
aus.zweiterAufruf = globalThis.focusiert.length;
await c.entferneZiel(c.ziele[0]);
aus.nachEntfernen = [...globalThis.focusiert];
JS);
        $this->assertSame(['zielwert'], $aus['nachSpeichern']);
        $this->assertTrue($aus['schonGespeichert']);
        $this->assertSame(1, $aus['zweiterAufruf'], 'ein schon gespeichertes Ziel wird nicht nochmals gesendet');
        $this->assertSame(['zielwert', 'zielwert'], $aus['nachEntfernen']);
    }

    #[Test]
    public function getipptes_dezimalkomma_wird_beim_verlassen_zum_punkt(): void
    {
        $aus = $this->ausfuehren(<<<'JS'
globalThis.HTMLInputElement = class { constructor(modus, wert) { this.inputMode = modus; this.value = wert; this.gemeldet = []; } dispatchEvent(e) { this.gemeldet.push(e.type); } };
let behandler = null;
document.addEventListener = (typ, f, capture) => { if (typ === 'change') { behandler = f; aus.capture = capture; } };
const { registriereDezimalfelder } = await import('./np.mjs');
registriereDezimalfelder();
const feld = (modus, wert) => { const f = new HTMLInputElement(modus, wert); behandler({ target: f }); return [f.value, f.gemeldet]; };
aus.komma = feld('decimal', ' 4,5 ');
aus.punkt = feld('decimal', '4.5');
aus.text = feld('text', '4,5');
aus.unsinn = feld('decimal', '4,5,6');
aus.kurz = feld('decimal', ',5');
aus.zahlKurz = (await import('./np.mjs')).zahl('.5');
JS);
        $this->assertTrue($aus['capture'], 'läuft vor den change-Listenern der Seite');
        $this->assertSame(['4.5', ['input']], $aus['komma']);
        $this->assertSame(['4.5', []], $aus['punkt']);
        $this->assertSame(['4,5', []], $aus['text']);
        $this->assertSame(['4,5,6', []], $aus['unsinn']);
        $this->assertSame(['.5', ['input']], $aus['kurz']);
        $this->assertSame(0.5, $aus['zahlKurz']);
    }
}
