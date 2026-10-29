// Screenshots einer angemeldeten Rolle, Dunkelmodus als Standard.
//
//   NP_TEST_PW=… node tools/pruefung/shot.mjs <email> <pfad[,pfad…]> [--breite=1920] [--hoehe=1080]
//                                              [--hell] [--fenster] [--name=prefix] [--dir=.]
//                                              [--bewegung=reduziert] [--transparenz=reduziert] [--kontrast=mehr]
//   node tools/pruefung/shot.mjs - /login,/forgot-password --gast        (ohne Anmeldung, kein Passwort nötig)
//
// Pro Pfad eine Datei <dir>/<name>-<n>-<breite>[-hell].png (ganze Seite; --fenster: nur Viewport).
// Meldet HTTP-Status, UEBERLAUF (horizontaler Scroll) und JavaScript-Fehler der Konsole; je Seite eine
// Zustandszeile (matchMedia reduced-motion / contrast more / reduced-transparency, dataset.fenster).
// Ziel über NP_URL (Standard http://127.0.0.1:8099), Passwort nur über NP_TEST_PW.
import fs from 'node:fs';
import { ZUSTAND_USAGE, anmelden, basisUrl, optionen, starteBrowser, suffix, zustandsZeile } from './browser.mjs';

const { positionen, opt } = optionen(process.argv.slice(2));
const [email, pfade] = positionen;
if (!email || !pfade) {
  console.error('Aufruf: NP_TEST_PW=… node tools/pruefung/shot.mjs <email> <pfad[,pfad…]> [--breite=1920] [--hoehe=1080] [--hell] [--fenster] [--name=x] [--dir=.] ' + ZUSTAND_USAGE + ' | shot.mjs - <pfade> --gast');
  process.exit(1);
}
const dir = opt.dir || '.';
fs.mkdirSync(dir, { recursive: true });

const { browser, page, fehler } = await starteBrowser(opt);
try {
  if (!opt.gast) await anmelden(page, email);
  let i = 0;
  for (const p of pfade.split(',')) {
    const r = await page.goto(basisUrl + p);
    await page.waitForTimeout(300);
    const datei = `${dir}/${opt.name || 'shot'}-${i++}-${suffix(opt)}.png`;
    await page.screenshot({ path: datei, fullPage: !opt.fenster });
    const ueberlauf = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    console.log(r?.status() ?? 'hash', p, datei, ueberlauf ? 'UEBERLAUF' : '');
    console.log(await zustandsZeile(page));
  }
  if (fehler.length) console.log('Konsole:', fehler.join('\n'));
} finally {
  await browser.close();
}
