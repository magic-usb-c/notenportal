<?php

declare(strict_types=1);

namespace Tests\Feature\Tastatur;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Funktionsprüfung «audit-tastatur-fokus»: Felder mit data-sofort senden bei Auswahlfeldern und Radiogruppen nicht
 * mit dem ersten Pfeil (WCAG 3.2.2), sondern erst beim Verlassen der Gruppe oder mit Enter. Zeiger, Schalter und
 * Checkboxen senden weiter sofort.
 */
class SofortSendenTastaturTest extends TestCase
{
    /** @return array<string, mixed> */
    private function szenen(): array
    {
        if ((new ExecutableFinder)->find('node') === null) {
            $this->markTestSkipped('node fehlt');
        }
        $dir = sys_get_temp_dir().'/np-sofort-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($dir);

        try {
            file_put_contents($dir.'/modul.mjs', (string) file_get_contents(resource_path('js/np.js')));
            file_put_contents($dir.'/lauf.mjs', <<<'JS'
globalThis.window = globalThis;
const aus = {};
const handler = {};
let fokusImFenster = true;
globalThis.addEventListener = () => {};
globalThis.document = {
    addEventListener: (typ, f) => { (handler[typ] ??= []).push(f); },
    hasFocus: () => fokusImFenster,
};
const feuer = (typ, ev) => { (handler[typ] ?? []).forEach((f) => f(ev)); return ev; };
const aufbau = () => {
    const form = { dataset: {}, gesendet: 0, checkValidity: () => true, reportValidity() {}, requestSubmit() { this.gesendet++; } };
    const feld = (tagName, type, name) => {
        const f = { tagName, type, name, form };
        f.closest = (sel) => (sel === '[data-sofort]' ? f : null);
        return f;
    };
    return { form, feld };
};
const zeit = { t: 1000 };
const taste = (ziel, key, extra = {}) => feuer('keydown', { target: ziel, key, timeStamp: (zeit.t += 5), preventDefault() { this.verhindert = true; }, ...extra });
const aenderung = (ziel) => feuer('change', { target: ziel, timeStamp: (zeit.t += 2) });
const verlassen = (ziel, nach = null) => feuer('focusout', { target: ziel, relatedTarget: nach });

const { registriereSofortSenden } = await import('./modul.mjs');
registriereSofortSenden();

// 1) geschlossenes select: Pfeil stellt zurück, Fokusverlust sendet einmal
{
    const { form, feld } = aufbau();
    const s = feld('SELECT', 'select-one', 'sitzung');
    taste(s, 'ArrowDown');
    aenderung(s);
    aus.selectNachPfeil = form.gesendet;
    verlassen(s);
    aus.selectNachFokusverlust = form.gesendet;
}
// 2) select: Buchstabe stellt zurück, Enter sendet und verhindert den Standard
{
    const { form, feld } = aufbau();
    const s = feld('SELECT', 'select-one', 'lernort');
    taste(s, 'b');
    aenderung(s);
    aus.selectNachBuchstabe = form.gesendet;
    const enter = taste(s, 'Enter');
    aus.selectNachEnter = form.gesendet;
    aus.enterVerhindert = enter.verhindert === true;
}
// 3) select: Auswahl mit dem Zeiger sendet sofort, auch wenn kurz vorher eine Taste fiel
{
    const { form, feld } = aufbau();
    const s = feld('SELECT', 'select-one', 'sprache');
    taste(s, 'ArrowDown');
    feuer('pointerdown', { target: s });
    aenderung(s);
    aus.selectZeiger = form.gesendet;
}
// 4) Radiogruppe: zwei Pfeile nacheinander, Fokus wandert in der Gruppe, gesendet wird erst beim Verlassen
{
    const { form, feld } = aufbau();
    const r = [feld('INPUT', 'radio', 'theme'), feld('INPUT', 'radio', 'theme'), feld('INPUT', 'radio', 'theme')];
    taste(r[0], 'ArrowRight');
    verlassen(r[0], r[1]);
    aenderung(r[1]);
    taste(r[1], 'ArrowRight');
    verlassen(r[1], r[2]);
    aenderung(r[2]);
    aus.radioNachZweiPfeilen = form.gesendet;
    verlassen(r[2], null);
    aus.radioNachVerlassen = form.gesendet;
}
// 5) Radio mit der Maus: sendet sofort
{
    const { form, feld } = aufbau();
    const r = feld('INPUT', 'radio', 'sprache');
    aenderung(r);
    aus.radioZeiger = form.gesendet;
}
// 6) Checkbox und Schalter: auch nach Taste sofort
{
    const { form, feld } = aufbau();
    const c = feld('INPUT', 'checkbox', 'aktiv');
    taste(c, 'ArrowDown');
    aenderung(c);
    aus.checkbox = form.gesendet;
}
// 7) Fenster verliert den Fokus: kein Senden, erst beim echten Verlassen
{
    const { form, feld } = aufbau();
    const s = feld('SELECT', 'select-one', 'x');
    taste(s, 'ArrowUp');
    aenderung(s);
    fokusImFenster = false;
    verlassen(s);
    aus.fensterWeg = form.gesendet;
    fokusImFenster = true;
    verlassen(s);
    aus.zurueck = form.gesendet;
}
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
    public function pfeil_auf_auswahlfeld_und_radiogruppe_sendet_erst_beim_verlassen(): void
    {
        $aus = $this->szenen();

        $this->assertSame(0, $aus['selectNachPfeil'], 'der erste Pfeil darf nicht speichern');
        $this->assertSame(1, $aus['selectNachFokusverlust']);
        $this->assertSame(0, $aus['selectNachBuchstabe']);
        $this->assertSame(1, $aus['selectNachEnter'], 'Enter sendet');
        $this->assertTrue($aus['enterVerhindert']);
        $this->assertSame(0, $aus['radioNachZweiPfeilen'], 'Pfeile innerhalb der Gruppe speichern nicht');
        $this->assertSame(1, $aus['radioNachVerlassen']);
        $this->assertSame(0, $aus['fensterWeg'], 'Fensterwechsel ist kein Verlassen des Feldes');
        $this->assertSame(1, $aus['zurueck']);
    }

    #[Test]
    public function zeiger_und_checkboxen_senden_weiter_sofort(): void
    {
        $aus = $this->szenen();

        $this->assertSame(1, $aus['selectZeiger']);
        $this->assertSame(1, $aus['radioZeiger']);
        $this->assertSame(1, $aus['checkbox']);
    }
}
