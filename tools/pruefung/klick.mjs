// Klickt Selektoren nacheinander (Menü öffnen, Tab wechseln, Drawer aufziehen) und macht
// danach einen Fenster-Screenshot – für Zustände, die ein Seitenaufruf allein nicht zeigt.
//
//   NP_TEST_PW=… node tools/pruefung/klick.mjs <email> <pfad> <selektor[,selektor…]>
//                                               [--breite=1920] [--hoehe=1080] [--hell] [--name=x] [--dir=.]
//                                               [--bewegung=reduziert] [--transparenz=reduziert] [--kontrast=mehr]
//
// Ziel über NP_URL (Standard http://127.0.0.1:8099), Passwort nur über NP_TEST_PW.
import fs from 'node:fs';
import { ZUSTAND_USAGE, anmelden, basisUrl, optionen, starteBrowser, suffix, zustandsZeile } from './browser.mjs';

const { positionen, opt } = optionen(process.argv.slice(2));
const [email, pfad, sel] = positionen;
if (!email || !pfad || !sel) {
  console.error('Aufruf: NP_TEST_PW=… node tools/pruefung/klick.mjs <email> <pfad> <selektor[,…]> [--breite=1920] [--hoehe=1080] [--hell] [--name=x] [--dir=.] ' + ZUSTAND_USAGE);
  process.exit(1);
}
const dir = opt.dir || '.';
fs.mkdirSync(dir, { recursive: true });

const { browser, page, fehler } = await starteBrowser(opt);
try {
  await anmelden(page, email);
  await page.goto(basisUrl + pfad);
  await page.waitForTimeout(300);
  console.log(await zustandsZeile(page));
  for (const s of sel.split(',').filter(Boolean)) {
    // erstes sichtbares Element – versteckte Varianten (z. B. Mobilleiste) überspringen
    await page.locator(s).locator('visible=true').first().click({ timeout: 10000 });
    await page.waitForTimeout(350);
  }
  const datei = `${dir}/${opt.name || 'klick'}-${suffix(opt)}.png`;
  await page.screenshot({ path: datei });
  console.log(datei);
  if (fehler.length) console.log('Konsole:', fehler.join('\n'));
} finally {
  await browser.close();
}
