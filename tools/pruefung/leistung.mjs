// Leistungsprobe einer angemeldeten Rolle: misst je Seite Ladezeit, LCP, Glasflächen und Bildrate.
//
//   NP_TEST_PW=… node tools/pruefung/leistung.mjs <email> <pfad[,pfad…]> [--breite=1920] [--hoehe=1080] [--hell]
//                                                  [--frames=150] [--laeufe=1] [--palette] [--ohne-glas] [--trace] [--json=datei]
//
// Je Pfad: HTTP-Status, DOMContentLoaded und Load (ms), LCP (ms), DOM-Knoten, Glasflächen (sichtbare
// Elemente mit backdrop-filter, Anteil am Fenster in %), Ruhe (60 Animationsbilder ohne Eingabe:
// Bilddauer p95, laufende Animationen, Skript-/Layoutzeit), Scrollschleife (N Animationsbilder hinunter
// und zurück: Bilddauer p50/p95/max in ms, lange Bilder > 33.4 ms = unter 30 fps, Layout-/Stil-/
// Skriptzeit aus Chrome-DevTools-Protokoll Performance.getMetrics). --palette misst zusätzlich die
// Befehlspalette (Strg+K öffnen, tippen, Escape): Öffnungszeit, Glasflächen im offenen Zustand, Bilddauer.
// --ohne-glas setzt data-transparenz="reduziert" (schaltet jeden backdrop-filter ab) – derselbe Lauf mit und
// ohne zeigt, was das Glas kostet. --trace zeichnet während Ruhe, Scrollen und Palette ein DevTools-Trace auf
// und summiert Raster-, Paint- und Composite-Zeit sowie die Anzahl gezeichneter Bilder (DrawFrame): rAF-Dauern
// sehen nur den Hauptthread, Glas wird auf Raster-Threads bezahlt.
//
// Headless-Chromium rastert in Software: absolute Werte sind nicht die eines Macs. Aussagekräftig ist der
// Vergleich vorher/nachher auf derselben Maschine mit denselben Argumenten (--json=… sichern).
// Schreibt nichts: nur GET-Aufrufe und Tastatur. Ziel über NP_URL, Passwort nur über NP_TEST_PW.
import fs from 'node:fs';
import { anmelden, basisUrl, optionen, starteBrowser, suffix } from './browser.mjs';

const { positionen, opt } = optionen(process.argv.slice(2));
const [email, pfade] = positionen;
if (!email || !pfade) {
  console.error('Aufruf: NP_TEST_PW=… node tools/pruefung/leistung.mjs <email> <pfad[,pfad…]> [--breite=1920] [--hoehe=1080] [--hell] [--frames=150] [--laeufe=1] [--palette] [--ohne-glas] [--trace] [--json=datei]');
  process.exit(1);
}
const anzahlBilder = Math.max(20, Number(opt.frames || 150));
const laeufe = Math.max(1, Number(opt.laeufe || 1));
const LANG = 33.4; // ms – länger als zwei Bilder bei 60 Hz

const r1 = (x) => Math.round(x * 10) / 10;
const quantil = (arr, q) => {
  if (!arr.length) return 0;
  const s = [...arr].sort((a, b) => a - b);
  return s[Math.min(s.length - 1, Math.max(0, Math.ceil(q * s.length) - 1))];
};
const median = (arr) => quantil(arr, 0.5);
const statistik = (dauern) => ({
  bilder: dauern.length,
  p50: r1(median(dauern)),
  p95: r1(quantil(dauern, 0.95)),
  max: r1(Math.max(0, ...dauern)),
  lang: dauern.filter((d) => d > LANG).length,
});

const { browser, page, fehler } = await starteBrowser(opt);
await page.addInitScript(() => {
  window.__npLcp = 0;
  try {
    new PerformanceObserver((liste) => {
      for (const e of liste.getEntries()) window.__npLcp = Math.max(window.__npLcp, e.startTime);
    }).observe({ type: 'largest-contentful-paint', buffered: true });
  } catch {
    /* kein LCP in diesem Browser */
  }
});
if (opt['ohne-glas']) {
  // <html> existiert beim Init-Skript noch nicht: Attribut setzen, sobald der Knoten da ist.
  await page.addInitScript(() => {
    const setzen = () => {
      if (!document.documentElement) return false;
      document.documentElement.dataset.transparenz = 'reduziert';
      return true;
    };
    if (!setzen()) {
      const o = new MutationObserver(() => setzen() && o.disconnect());
      o.observe(document, { childList: true });
    }
  });
}
const cdp = await page.context().newCDPSession(page);
await cdp.send('Performance.enable');

// DevTools-Trace: Raster/Paint/Composite laufen abseits des Hauptthreads und fehlen in rAF-Dauern.
const TRACE_NAMEN = {
  RasterTask: 'rasterMs',
  'RasterTask::Raster': 'rasterMs',
  Paint: 'paintMs',
  PaintImage: 'paintMs',
  CompositeLayers: 'compositeMs',
  'Commit': 'commitMs',
  UpdateLayerTree: 'layerTreeMs',
  PrePaint: 'prePaintMs',
};
let traceEreignisse = [];
cdp.on('Tracing.dataCollected', (e) => {
  traceEreignisse.push(...e.value);
});
async function traceStart() {
  if (!opt.trace) return;
  traceEreignisse = [];
  await cdp.send('Tracing.start', {
    traceConfig: {
      includedCategories: ['disabled-by-default-devtools.timeline', 'disabled-by-default-devtools.timeline.frame', 'cc', 'benchmark'],
      excludedCategories: ['*'],
    },
    transferMode: 'ReportEvents',
  });
}
async function traceEnde() {
  if (!opt.trace) return undefined;
  const fertig = new Promise((res) => cdp.once('Tracing.tracingComplete', res));
  await cdp.send('Tracing.end');
  await fertig;
  const summe = {};
  let drawFrames = 0;
  for (const e of traceEreignisse) {
    if (e.name === 'DrawFrame') drawFrames++;
    const ziel = TRACE_NAMEN[e.name];
    if (ziel && e.ph === 'X' && e.dur) summe[ziel] = (summe[ziel] || 0) + e.dur / 1000;
  }
  return { drawFrames, ...Object.fromEntries(Object.entries(summe).map(([k, v]) => [k, r1(v)])) };
}

async function metriken() {
  const { metrics } = await cdp.send('Performance.getMetrics');
  return Object.fromEntries(metrics.map((m) => [m.name, m.value]));
}
const delta = (a, b) => ({
  layoutMs: r1((b.LayoutDuration - a.LayoutDuration) * 1000),
  layoutAnzahl: b.LayoutCount - a.LayoutCount,
  stilMs: r1((b.RecalcStyleDuration - a.RecalcStyleDuration) * 1000),
  stilAnzahl: b.RecalcStyleCount - a.RecalcStyleCount,
  skriptMs: r1((b.ScriptDuration - a.ScriptDuration) * 1000),
  aufgabenMs: r1((b.TaskDuration - a.TaskDuration) * 1000),
});

// Sichtbare Elemente mit backdrop-filter (auch über ::before, wie np-glas-gruppe) und ihr Anteil am Fenster.
async function glasflaechen() {
  return page.evaluate(() => {
    const vw = innerWidth;
    const vh = innerHeight;
    let anzahl = 0;
    let flaeche = 0;
    const klassen = {};
    for (const el of document.querySelectorAll('body *')) {
      const cs = getComputedStyle(el);
      if (cs.display === 'none' || cs.visibility === 'hidden' || Number(cs.opacity) === 0) continue;
      const eigen = cs.backdropFilter || cs.webkitBackdropFilter;
      const vorher = getComputedStyle(el, '::before');
      const vorherFilter = vorher.backdropFilter || vorher.webkitBackdropFilter;
      const hat = (eigen && eigen !== 'none') || (vorherFilter && vorherFilter !== 'none' && vorher.content !== 'none');
      if (!hat) continue;
      const rect = el.getBoundingClientRect();
      const w = Math.max(0, Math.min(rect.right, vw) - Math.max(rect.left, 0));
      const h = Math.max(0, Math.min(rect.bottom, vh) - Math.max(rect.top, 0));
      if (w === 0 || h === 0) continue;
      anzahl++;
      flaeche += w * h;
      const name = [...el.classList].find((c) => /glas|glass|symbolleiste/.test(c)) || el.tagName.toLowerCase();
      klassen[name] = (klassen[name] || 0) + 1;
    }
    return { anzahl, anteilProzent: Math.round((flaeche / (vw * vh)) * 1000) / 10, klassen };
  });
}

// Bilddauern aufzeichnen, bis window.__npStop gesetzt wird (für Messungen während Playwright tippt).
async function aufzeichnungStart() {
  await page.evaluate(() => {
    window.__npRec = [];
    window.__npStop = false;
    let vorher = performance.now();
    const schritt = () => {
      const jetzt = performance.now();
      window.__npRec.push(jetzt - vorher);
      vorher = jetzt;
      if (!window.__npStop) requestAnimationFrame(schritt);
    };
    requestAnimationFrame(schritt);
  });
}
async function aufzeichnungEnde() {
  return page.evaluate(() => {
    window.__npStop = true;
    return window.__npRec.slice(1);
  });
}

async function ruhe() {
  await traceStart();
  const vorher = await metriken();
  const dauern = await page.evaluate(
    async (n) =>
      new Promise((res) => {
        const d = [];
        let v = performance.now();
        const f = () => {
          const j = performance.now();
          d.push(j - v);
          v = j;
          d.length < n ? requestAnimationFrame(f) : res(d.slice(1));
        };
        requestAnimationFrame(f);
      }),
    60,
  );
  const nachher = await metriken();
  const animationen = await page.evaluate(() => document.getAnimations().filter((a) => a.playState === 'running').length);
  const trace = await traceEnde();
  return { ...statistik(dauern), animationen, ...delta(vorher, nachher), ...(trace ? { trace } : {}) };
}

async function scrollen(n) {
  await traceStart();
  const vorher = await metriken();
  const ergebnis = await page.evaluate(async (n) => {
    // Scrollt das Dokument; scrollt es nicht, den grössten inneren Scrollbereich (z. B. Tabellenblatt).
    let ziel = document.scrollingElement || document.documentElement;
    let weg = Math.max(0, ziel.scrollHeight - innerHeight);
    let bereich = 'dokument';
    if (weg === 0) {
      let best = null;
      for (const el of document.querySelectorAll('body *')) {
        const cs = getComputedStyle(el);
        if (!/(auto|scroll)/.test(cs.overflowY)) continue;
        const rest = el.scrollHeight - el.clientHeight;
        if (rest < 24) continue;
        const r = el.getBoundingClientRect();
        const gewicht = rest * Math.max(1, r.width * r.height);
        if (!best || gewicht > best.gewicht) best = { el, rest, gewicht };
      }
      if (best) {
        ziel = best.el;
        weg = best.rest;
        bereich = [...ziel.classList].slice(0, 2).join('.') || ziel.tagName.toLowerCase();
      }
    }
    const setze = (y) => (bereich === 'dokument' ? scrollTo(0, y) : (ziel.scrollTop = y));
    setze(0);
    await new Promise((res) => requestAnimationFrame(() => requestAnimationFrame(res)));
    const d = [];
    let v = performance.now();
    const halb = n / 2;
    for (let i = 1; i <= n; i++) {
      const t = i <= halb ? i / halb : (n - i) / halb;
      setze(Math.round(weg * t));
      await new Promise((res) => requestAnimationFrame(res));
      const j = performance.now();
      d.push(j - v);
      v = j;
    }
    setze(0);
    return { dauern: d, scrollweg: weg, bereich };
  }, n);
  const nachher = await metriken();
  const trace = await traceEnde();
  return { scrollweg: ergebnis.scrollweg, bereich: ergebnis.bereich, ...statistik(ergebnis.dauern), ...delta(vorher, nachher), ...(trace ? { trace } : {}) };
}

async function palette() {
  await traceStart();
  const vorher = await metriken();
  const t0 = performance.now();
  await page.keyboard.press('Control+k');
  const eingabe = page.locator('input[role=combobox]').first();
  try {
    await eingabe.waitFor({ state: 'visible', timeout: 3000 });
  } catch {
    await traceEnde();
    return { fehler: 'Palette öffnet nicht (Strg+K)' };
  }
  const oeffnenMs = r1(performance.now() - t0);
  await page.waitForTimeout(250);
  const glas = await glasflaechen();
  await aufzeichnungStart();
  await page.keyboard.type('note', { delay: 80 });
  await page.waitForTimeout(400);
  const tippen = statistik(await aufzeichnungEnde());
  await aufzeichnungStart();
  await page.keyboard.press('Escape');
  await page.waitForTimeout(400);
  const schliessen = statistik(await aufzeichnungEnde());
  const nachher = await metriken();
  const trace = await traceEnde();
  return { oeffnenMs, glas, tippen, schliessen, ...delta(vorher, nachher), ...(trace ? { trace } : {}) };
}

async function messen(pfad) {
  const antwort = await page.goto(basisUrl + pfad, { waitUntil: 'load' });
  await page.waitForTimeout(600);
  const lade = await page.evaluate(() => {
    const nav = performance.getEntriesByType('navigation')[0];
    return {
      dclMs: nav ? Math.round(nav.domContentLoadedEventEnd) : null,
      loadMs: nav ? Math.round(nav.loadEventEnd) : null,
      lcpMs: Math.round(window.__npLcp || 0),
      hoehe: document.documentElement.scrollHeight,
    };
  });
  const m = await metriken();
  const ergebnis = {
    pfad,
    status: antwort?.status() ?? null,
    ...lade,
    knoten: m.Nodes,
    glas: await glasflaechen(),
    ruhe: await ruhe(),
    scroll: await scrollen(anzahlBilder),
  };
  if (opt.palette) ergebnis.palette = await palette();
  return ergebnis;
}

const traceZeile = (t) =>
  t ? `${t.drawFrames} Bilder · Raster ${t.rasterMs ?? 0} · Paint ${t.paintMs ?? 0} · Composite ${t.compositeMs ?? 0} · Commit ${t.commitMs ?? 0} ms` : '–';
const zeile = (e) =>
  [
    `${e.status} ${e.pfad}`,
    `DCL ${e.dclMs} · Load ${e.loadMs} · LCP ${e.lcpMs} ms`,
    `Knoten ${e.knoten}`,
    `Glas ${e.glas.anzahl} (${e.glas.anteilProzent} %)`,
    `Ruhe p95 ${e.ruhe.p95} ms · Anim ${e.ruhe.animationen} · Skript ${e.ruhe.skriptMs} ms`,
    `Scroll ${e.scroll.scrollweg}px (${e.scroll.bereich}) p50 ${e.scroll.p50} / p95 ${e.scroll.p95} / max ${e.scroll.max} ms · lang ${e.scroll.lang}/${e.scroll.bilder}`,
    `Layout ${e.scroll.layoutMs} ms (${e.scroll.layoutAnzahl}×) · Stil ${e.scroll.stilMs} ms · Skript ${e.scroll.skriptMs} ms`,
    e.scroll.trace ? `Trace Scroll: ${traceZeile(e.scroll.trace)} · Ruhe: ${traceZeile(e.ruhe.trace)}` : null,
    e.palette?.trace ? `Trace Palette: ${traceZeile(e.palette.trace)}` : null,
    e.palette
      ? e.palette.fehler ||
        `Palette öffnet ${e.palette.oeffnenMs} ms · Glas ${e.palette.glas.anzahl} (${e.palette.glas.anteilProzent} %) · Tippen p95 ${e.palette.tippen.p95} / max ${e.palette.tippen.max} ms · Schliessen p95 ${e.palette.schliessen.p95} ms`
      : null,
  ]
    .filter(Boolean)
    .join('\n    ');

// Median über mehrere Läufe je Pfad: jede Zahl im Ergebnisbaum wird einzeln gemittelt.
function mediane(laeufe) {
  const erster = laeufe[0];
  const misch = (schluessel, werte) => {
    const w = werte.filter((x) => x !== undefined);
    if (w.every((x) => typeof x === 'number')) return median(w);
    if (w.every((x) => x && typeof x === 'object' && !Array.isArray(x))) {
      return Object.fromEntries(Object.keys(w[0]).map((k) => [k, misch(k, w.map((x) => x[k]))]));
    }
    return w[0];
  };
  return misch('', laeufe);
}

const ergebnisse = [];
try {
  await anmelden(page, email);
  for (const pfad of pfade.split(',')) {
    const runden = [];
    for (let i = 0; i < laeufe; i++) runden.push(await messen(pfad));
    const e = laeufe > 1 ? { ...mediane(runden), laeufe: runden } : runden[0];
    ergebnisse.push(e);
    console.log(zeile(e));
  }
  if (fehler.length) console.log('Konsole:', fehler.join('\n'));
  if (opt.json) {
    const datei = typeof opt.json === 'string' ? opt.json : `leistung-${suffix(opt)}.json`;
    fs.writeFileSync(datei, JSON.stringify({ email, breite: Number(opt.breite || 1920), hoehe: Number(opt.hoehe || 1080), hell: Boolean(opt.hell), ohneGlas: Boolean(opt['ohne-glas']), trace: Boolean(opt.trace), frames: anzahlBilder, laeufe, ergebnisse }, null, 2));
    console.log('JSON:', datei);
  }
} finally {
  await browser.close();
}
