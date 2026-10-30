<?php

declare(strict_types=1);

namespace Tests\Feature\Tastatur;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Tastatur und Trefferflächen: Kürzel bei offenem Dialog, Roving-Tabindex in versteckten Menüs,
 * Mindestgrösse der Bedienelemente. Die JS-Teile laufen in Node mit einem kleinen DOM-Ersatz.
 */
class TastaturBefundeTest extends TestCase
{
    /** @return array<string, mixed> */
    private function inNode(string $modul, string $skript): array
    {
        if ((new ExecutableFinder)->find('node') === null) {
            $this->markTestSkipped('node fehlt');
        }
        $dir = sys_get_temp_dir().'/np-tastatur-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($dir);

        try {
            file_put_contents($dir.'/modul.mjs', (string) file_get_contents(resource_path('js/'.$modul)));
            file_put_contents($dir.'/lauf.mjs', "globalThis.window = globalThis;\nconst aus = {};\n".$skript."\nconsole.log(JSON.stringify(aus));\n");
            $p = new Process(['node', $dir.'/lauf.mjs']);
            $p->run();
            $this->assertSame(0, $p->getExitCode(), $p->getErrorOutput().$p->getOutput());

            return json_decode(trim($p->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        } finally {
            File::deleteDirectory($dir);
        }
    }

    /** Gemeinsamer Aufbau für die Kürzel: Dokument mit frei bestückbaren Dialogen, Komponente mit Zielen. */
    private function kuerzelSkript(string $szenen): string
    {
        return <<<JS
let dialoge = [];
const dialog = (offen, inert = false) => ({
    closest: (sel) => (sel === '[inert]' && inert ? {} : null),
    checkVisibility: () => offen,
});
globalThis.document = {
    querySelectorAll: () => dialoge,
    querySelector: () => null,
    activeElement: null,
};
globalThis.addEventListener = () => {};
globalThis.location = { href: '/start' };
let fabrik;
const { registriereTastenkuerzel } = await import('./modul.mjs');
registriereTastenkuerzel({ data: (n, f) => { fabrik = f; } });
const eigener = dialog(false);
const komp = fabrik({ ziele: { d: '/dashboard' }, neueNote: '/grades/create' });
komp.\$refs = { dialog: eigener };
komp.init();
const taste = (key) => {
    let verhindert = false;
    komp.taste({ key, target: { tagName: 'BUTTON' }, defaultPrevented: false, ctrlKey: false, metaKey: false, altKey: false, preventDefault: () => { verhindert = true; } });
    return verhindert;
};
$szenen
JS;
    }

    #[Test]
    public function kuerzel_wirken_nicht_bei_offenem_dialog_oder_drawer(): void
    {
        $aus = $this->inNode('tastenkuerzel.js', $this->kuerzelSkript(<<<'JS'
dialoge = [dialog(true)];          // offener Drawer oder Dialog
taste('n');
aus.nOffen = location.href;
taste('g'); taste('d');
aus.gOffen = location.href;
aus.slashOffen = taste('/');

dialoge = [dialog(true), dialog(true)];
aus.zweiOffen = (taste('n'), location.href);
JS));

        $this->assertSame('/start', $aus['nOffen'], 'n darf bei offenem Dialog nicht navigieren');
        $this->assertSame('/start', $aus['gOffen'], 'g, d darf bei offenem Dialog nicht navigieren');
        $this->assertFalse($aus['slashOffen'], '/ darf bei offenem Dialog die Palette nicht öffnen');
        $this->assertSame('/start', $aus['zweiOffen']);
    }

    #[Test]
    public function kuerzel_wirken_bei_geschlossenem_oder_inertem_drawer(): void
    {
        $aus = $this->inNode('tastenkuerzel.js', $this->kuerzelSkript(<<<'JS'
dialoge = [dialog(false)];         // geschlossen: unsichtbar
taste('n');
aus.unsichtbar = location.href;

location.href = '/start';
dialoge = [dialog(true, true)];    // sichtbar markiert, aber inert (schliesst gerade)
taste('g'); taste('d');
aus.inert = location.href;
JS));

        $this->assertSame('/grades/create', $aus['unsichtbar']);
        $this->assertSame('/dashboard', $aus['inert']);
    }

    #[Test]
    public function uebersicht_oeffnet_trotz_geschlossenem_drawer_und_nicht_bei_offenem(): void
    {
        $aus = $this->inNode('tastenkuerzel.js', $this->kuerzelSkript(<<<'JS'
komp.$nextTick = () => {};
dialoge = [dialog(false)];
taste('?');
aus.geschlossen = komp.offen;
komp.offen = false;
dialoge = [dialog(true)];
taste('?');
aus.offen = komp.offen;
JS));

        $this->assertTrue($aus['geschlossen'], 'Übersicht muss mit geschlossenem Drawer öffnen (offsetParent-Fehler)');
        $this->assertFalse($aus['offen']);
    }

    #[Test]
    public function radiogruppe_setzt_roving_tabindex_auch_im_unsichtbaren_menue(): void
    {
        $aus = $this->inNode('np.js', <<<'JS'
const radio = (checked) => {
    const attr = { 'aria-checked': String(checked), role: 'radio' };
    return {
        offsetParent: null, // Konto-Menü beim Init noch display:none
        disabled: false,
        getAttribute: (k) => attr[k] ?? null,
        setAttribute: (k, v) => { attr[k] = String(v); },
        attr,
    };
};
const radios = [radio(false), radio(false), radio(true)];
globalThis.MutationObserver = class { observe() {} disconnect() {} };
let direktive;
const { registriereRadiogroup } = await import('./modul.mjs');
registriereRadiogroup({ directive: (n, f) => { direktive = f; } });
let tasten;
const el = { querySelectorAll: () => radios, addEventListener: (t, f) => { tasten = f; } };
direktive(el, {}, { cleanup: () => {} });
aus.tabindex = radios.map((r) => r.attr.tabindex ?? null);

// Pfeil runter wählt wie rechts den nächsten
let geklickt = null;
radios.forEach((r, i) => { r.focus = () => { globalThis.document = { activeElement: r }; }; r.click = () => { geklickt = i; }; });
globalThis.document = { activeElement: radios[0] };
tasten({ key: 'ArrowDown', preventDefault() {} });
aus.runter = geklickt;
globalThis.document = { activeElement: radios[1] };
tasten({ key: 'ArrowUp', preventDefault() {} });
aus.hoch = geklickt;
JS);

        $this->assertSame(['-1', '-1', '0'], $aus['tabindex'], 'ein Tab-Stopp auf der gewählten Option');
        $this->assertSame(1, $aus['runter']);
        $this->assertSame(0, $aus['hoch']);
    }

    #[Test]
    public function filterleiste_entfernen_knopf_hat_mindestens_24_px(): void
    {
        $html = (string) file_get_contents(resource_path('views/components/filterleiste.blade.php'));
        $this->assertMatchesRegularExpression('/<button[^>]*class="[^"]*\bsize-6\b[^"]*"[^>]*markeEntfernen/s', $html);
        $this->assertDoesNotMatchRegularExpression('/<button[^>]*\bsize-4\b[^>]*markeEntfernen/s', $html);
    }

    #[Test]
    public function tabellen_checkboxen_liegen_in_einer_24_px_grossen_trefferflaeche(): void
    {
        foreach ([
            'admin/stammdaten/lehrberufe/show.blade.php' => 3,
            'admin/stammdaten/notenbaeume/show.blade.php' => 1,
        ] as $datei => $erwartet) {
            $html = (string) file_get_contents(resource_path('views/'.$datei));
            preg_match_all('/<label class="[^"]*\bsize-6\b[^"]*">\s*<input (?:id="[^"]*" )?type="checkbox".{0,400}?np-haken.{0,120}?<\/label>/s', $html, $mitLabel);
            $this->assertCount($erwartet, $mitLabel[0], "$datei: Tabellen-Checkboxen ohne label size-6 (24 px)");
        }
    }
}
