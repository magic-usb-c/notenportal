// Gemeinsamer Unterbau der Prüfwerkzeuge: Playwright finden, Browser starten, anmelden.
//
// Playwright wird zuerst als Projektpaket gesucht, dann an der global installierten Stelle
// der Cloud-Umgebung (/opt/node22/lib/node_modules/playwright). Chromium findet Playwright
// über PLAYWRIGHT_BROWSERS_PATH; ist die Revision dort nicht die erwartete, wird der erste
// vorhandene Chromium-Ordner genommen. NP_PLAYWRIGHT überschreibt den Paketpfad,
// NP_CHROMIUM den Browserpfad.
//
// Passwort ausschliesslich aus NP_TEST_PW – nie als Argument (Argumente stehen in der Prozessliste).
//
// Gemeinsame Zustandsflags (shot, klick, rundgang, leistung):
//   --bewegung=reduziert     prefers-reduced-motion: reduce (emulateMedia) + html[data-bewegung='reduziert']
//   --transparenz=reduziert  prefers-reduced-transparency: reduce (CDP Emulation.setEmulatedMedia); wirkt matchMedia
//                            danach nicht, setzt das Werkzeug html[data-transparenz='reduziert'] («Ersatz: data-transparenz»)
//   --kontrast=mehr          prefers-contrast: more (emulateMedia)
// Nach jedem Laden meldet zustandsZeile() die matchMedia-Zustände und document.documentElement.dataset.fenster.
import fs from 'node:fs';
import path from 'node:path';

export const basisUrl = (process.env.NP_URL || 'http://127.0.0.1:8099').replace(/\/$/, '');

export function optionen(argv) {
  const positionen = argv.filter((a) => !a.startsWith('--'));
  const opt = Object.fromEntries(
    // am ersten «=» trennen: Selektoren wie --menue=button[aria-label^="Konto"] enthalten selbst ein «=»
    argv.filter((a) => a.startsWith('--')).map((a) => a.replace(/^--/, '')).map((a) => {
      const i = a.indexOf('=');
      return i < 0 ? [a, true] : [a.slice(0, i), a.slice(i + 1)];
    }),
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

function startOptionen() {
  return { env: { ...process.env, LANG: 'de_CH.UTF-8', LC_ALL: 'de_CH.UTF-8' } };
}

export const ZUSTAND_USAGE = '[--bewegung=reduziert] [--transparenz=reduziert] [--kontrast=mehr]';

function pruefeZustandsflags(opt) {
  for (const [flag, erlaubt] of [['bewegung', 'reduziert'], ['transparenz', 'reduziert'], ['kontrast', 'mehr']]) {
    if (opt[flag] !== undefined && opt[flag] !== erlaubt) {
      throw new Error(`--${flag} kennt nur den Wert «${erlaubt}» (--${flag}=${erlaubt}).`);
    }
  }
}

export async function starteBrowser(opt) {
  pruefeZustandsflags(opt);
  const { chromium } = await ladePlaywright();
  let browser;
  try {
    // Chromium formatiert <input type=date> nach der Systemsprache (LANG), nicht nach context.locale –
    // ohne de_CH stünde in Screenshots 08/01/2026 statt 01.08.2026 (gemessen 01.10.2026).
    browser = await chromium.launch(startOptionen());
  } catch (e) {
    const pfad = chromiumPfad();
    if (!pfad) throw e;
    browser = await chromium.launch({ ...startOptionen(), executablePath: pfad });
  }
  const ctx = await browser.newContext({
    viewport: { width: Number(opt.breite || 1920), height: Number(opt.hoehe || 1080) },
    colorScheme: opt.hell ? 'light' : 'dark',
    locale: 'de-CH',
  });
  const page = await ctx.newPage();
  await zustandEmulieren(ctx, page, opt);
  const fehler = [];
  page.on('console', (m) => {
    if (m.type() === 'error' && !/status of 40[34]/.test(m.text())) fehler.push(m.text().slice(0, 200));
  });
  page.on('pageerror', (e) => fehler.push(String(e).slice(0, 200)));
  return { browser, page, fehler };
}

// <html> existiert beim Init-Skript noch nicht: Attribut setzen, sobald der Knoten da ist (vor Alpine).
function attributVorAlpine(page, name, wert) {
  return page.addInitScript(
    ([n, w]) => {
      const setzen = () => {
        if (!document.documentElement) return false;
        document.documentElement.dataset[n] = w;
        return true;
      };
      if (!setzen()) {
        const o = new MutationObserver(() => setzen() && o.disconnect());
        o.observe(document, { childList: true });
      }
    },
    [name, wert],
  );
}

async function zustandEmulieren(ctx, page, opt) {
  const bewegung = opt.bewegung === 'reduziert';
  const transparenz = opt.transparenz === 'reduziert';
  const kontrast = opt.kontrast === 'mehr';
  const modus = opt.hell ? 'light' : 'dark';
  if (bewegung || kontrast) {
    await page.emulateMedia({
      colorScheme: modus,
      ...(bewegung ? { reducedMotion: 'reduce' } : {}),
      ...(kontrast ? { contrast: 'more' } : {}),
    });
  }
  if (bewegung) await attributVorAlpine(page, 'bewegung', 'reduziert');
  if (!transparenz) return;
  // CDP ersetzt die gesamte Feature-Liste: prefers-color-scheme (sonst verliert die Seite den Dunkelmodus) und
  // die oben gesetzten Playwright-Zustände müssen mitgegeben werden.
  const cdp = await ctx.newCDPSession(page);
  const features = [
    { name: 'prefers-color-scheme', value: modus },
    { name: 'prefers-reduced-transparency', value: 'reduce' },
    ...(bewegung ? [{ name: 'prefers-reduced-motion', value: 'reduce' }] : []),
    ...(kontrast ? [{ name: 'prefers-contrast', value: 'more' }] : []),
  ];
  await cdp.send('Emulation.setEmulatedMedia', { features });
  const wirkt = await page.evaluate(() => matchMedia('(prefers-reduced-transparency: reduce)').matches);
  if (!wirkt) {
    await attributVorAlpine(page, 'transparenz', 'reduziert');
    console.log('Ersatz: data-transparenz');
  }
}

// Zustandszeile nach dem Laden: matchMedia-Zustände der Emulation und dataset.fenster (vor R6-03 leer).
export async function zustandsZeile(page) {
  await page.bringToFront();
  const z = await page.evaluate(() => ({
    bewegung: matchMedia('(prefers-reduced-motion: reduce)').matches,
    kontrast: matchMedia('(prefers-contrast: more)').matches,
    transparenz: matchMedia('(prefers-reduced-transparency: reduce)').matches,
    ersatz: document.documentElement.dataset.transparenz === 'reduziert',
    fenster: document.documentElement.dataset.fenster ?? '',
  }));
  return `Zustand: reduced-motion=${z.bewegung} contrast-more=${z.kontrast} reduced-transparency=${z.transparenz}${z.ersatz ? ' (Ersatz: data-transparenz)' : ''} fenster=${z.fenster}`;
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
