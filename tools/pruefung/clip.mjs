// Ausschnitt einer angemeldeten Seite als Bild – für Details, die im Vollbild zu klein sind (Scroll-Kanten,
// Tabellenköpfe, Fokusringe). Dunkelmodus als Standard.
//
//   NP_TEST_PW=… node tools/pruefung/clip.mjs <email> <pfad> <x,y,breite,hoehe> --out=<datei.png>
//                                             [--open=<selektor>] [--breite=1920] [--hoehe=1080] [--hell]
//   NP_EVAL='<js>'  wird vor dem Bild in der Seite ausgewertet und ausgegeben (z. B. getComputedStyle-Werte)
//
// --open setzt bei allen Treffern des Selektors `open = true` (details/summary aufklappen).
// Ziel über NP_URL (Standard http://127.0.0.1:8099), Passwort nur über NP_TEST_PW.
import { anmelden, basisUrl, optionen, starteBrowser } from './browser.mjs';
const { positionen, opt } = optionen(process.argv.slice(2));
const [email, pfad, clipSpec] = positionen;
const { browser, page } = await starteBrowser(opt);
try {
  await anmelden(page, email);
  await page.goto(basisUrl + pfad);
  await page.waitForTimeout(400);
  if (opt.open) { await page.evaluate((s) => document.querySelectorAll(s).forEach((d) => { d.open = true; }), opt.open); await page.waitForTimeout(400); }
  if (process.env.NP_EVAL) console.log(await page.evaluate(process.env.NP_EVAL));
  const [x, y, width, height] = clipSpec.split(',').map(Number);
  await page.screenshot({ path: opt.out, clip: { x, y, width, height } });
  console.log('ok', opt.out);
} finally { await browser.close(); }
