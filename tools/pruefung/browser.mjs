// Gemeinsamer Unterbau der Prüfwerkzeuge: Playwright finden, Browser starten, anmelden.
//
// Playwright wird zuerst als Projektpaket gesucht, dann an der global installierten Stelle
// der Cloud-Umgebung (/opt/node22/lib/node_modules/playwright). Chromium findet Playwright
// über PLAYWRIGHT_BROWSERS_PATH; ist die Revision dort nicht die erwartete, wird der erste
// vorhandene Chromium-Ordner genommen. NP_PLAYWRIGHT überschreibt den Paketpfad,
// NP_CHROMIUM den Browserpfad.
//
// Passwort ausschliesslich aus NP_TEST_PW – nie als Argument (Argumente stehen in der Prozessliste).
import fs from 'node:fs';
import path from 'node:path';

export const basisUrl = (process.env.NP_URL || 'http://127.0.0.1:8099').replace(/\/$/, '');

export function optionen(argv) {
  const positionen = argv.filter((a) => !a.startsWith('--'));
  const opt = Object.fromEntries(
    argv.filter((a) => a.startsWith('--')).map((a) => a.replace(/^--/, '').split('=')).map(([k, v]) => [k, v ?? true]),
  );
  return { positionen, opt };
}

async function ladePlaywright() {
  const kandidaten = [
    process.env.NP_PLAYWRIGHT,
    'playwright',
    '/opt/node22/lib/node_modules/playwright/index.mjs',
    '/usr/lib/node_modules/playwright/index.mjs',
  ].filter(Boolean);
  for (const k of kandidaten) {
    try {
      return await import(k);
    } catch {
      /* nächster Kandidat */
    }
  }
  throw new Error('Playwright nicht gefunden – npm i -D playwright oder NP_PLAYWRIGHT=<pfad zu index.mjs> setzen.');
}

function chromiumPfad() {
  if (process.env.NP_CHROMIUM) return process.env.NP_CHROMIUM;
  const wurzel = process.env.PLAYWRIGHT_BROWSERS_PATH;
  if (!wurzel || !fs.existsSync(wurzel)) return undefined;
  const ordner = fs.readdirSync(wurzel).filter((d) => /^chromium-\d+$/.test(d)).sort().reverse();
  for (const d of ordner) {
    const p = path.join(wurzel, d, 'chrome-linux', 'chrome');
    if (fs.existsSync(p)) return p;
  }
  return undefined;
}

export async function starteBrowser(opt) {
  const { chromium } = await ladePlaywright();
  let browser;
  try {
    browser = await chromium.launch();
  } catch (e) {
    const pfad = chromiumPfad();
    if (!pfad) throw e;
    browser = await chromium.launch({ executablePath: pfad });
  }
  const ctx = await browser.newContext({
    viewport: { width: Number(opt.breite || 1920), height: Number(opt.hoehe || 1080) },
    colorScheme: opt.hell ? 'light' : 'dark',
    locale: 'de-CH',
  });
  const page = await ctx.newPage();
  const fehler = [];
  page.on('console', (m) => {
    if (m.type() === 'error' && !/status of 40[34]/.test(m.text())) fehler.push(m.text().slice(0, 200));
  });
  page.on('pageerror', (e) => fehler.push(String(e).slice(0, 200)));
  return { browser, page, fehler };
}

export async function anmelden(page, email) {
  if (!email) throw new Error('E-Mail des Testkontos fehlt.');
  if (!process.env.NP_TEST_PW) throw new Error('NP_TEST_PW ist nicht gesetzt.');
  await page.goto(basisUrl + '/login');
  await page.fill('input[name=email]', email);
  await page.fill('input[name=password]', process.env.NP_TEST_PW);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  if (/\/login$/.test(page.url())) throw new Error('Anmeldung fehlgeschlagen für ' + email);
  // Einmaliger Feedback-Hinweis verdeckt sonst unten rechts Inhalt (wird serverseitig als gesehen gemerkt).
  const zu = page.locator('button[aria-label="Hinweis schliessen"]');
  if (!process.env.NP_HINWEIS_LASSEN && (await zu.count()) && (await zu.first().isVisible())) {
    await zu.first().click();
    await page.waitForTimeout(300);
  }
}

export function suffix(opt) {
  return `${Number(opt.breite || 1920)}${opt.hell ? '-hell' : ''}`;
}
