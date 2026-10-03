// Kontrastrechner über alle Theme-Blöcke in resources/css/theme.css (WCAG 2.x, relative Leuchtdichte).
//
//   node tools/pruefung/kontrast.mjs [--alle] [--minimum] [--glas=0.74] [--json=datei]
//
// Prüft je Theme (12) und Modus (hell/dunkel) sowie je Akzentvariante:
//   Text:   text, muted auf bg/card/surface-2/input ≥ 4.5:1 (Theme «kontrast» ≥ 7:1)
//           accent-text auf bg/card/surface-2 ≥ 4.5:1 · accent-contrast auf accent ≥ 4.5:1
//           note-* auf card/bg und auf der eigenen Marke (note/0.14 über card) ≥ 4.5:1
//   UI:     accent, ring, border-strong auf bg/card ≥ 3:1 · chart-* auf card ≥ 3:1
//   Glas:   text/muted auf den Materialien (np-glas, glass-bar, glass-overlay, np-glas-gruppe) als
//           Alpha-Komposition über bg, card und surface-2 (Deckungen aus app.css); saturate() wird
//           vernachlässigt – auf grauen Flächen ändert es die Leuchtdichte kaum.
// --alle zeigt jede Paarung, --minimum die kleinste Glas-Deckung je Theme/Modus, bei der text und
// muted über bg noch ihre Schwelle halten (Entwurfsgrösse für neue Materialien), --glas=a rechnet
// zusätzlich ein Material mit Deckung a aus --card. Exit 1, sobald ein Pflichtpaar reisst.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { optionen } from './browser.mjs';

const { opt } = optionen(process.argv.slice(2));
const wurzel = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const css = fs.readFileSync(path.join(wurzel, 'resources/css/theme.css'), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');

// Deckungen der Materialien direkt aus resources/css/app.css (@utility-Blöcke), damit nichts von Hand nachgeführt wird.
const appCss = fs.readFileSync(path.join(wurzel, 'resources/css/app.css'), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');
function utilityBlock(name) {
  const start = appCss.indexOf(`@utility ${name} {`);
  if (start < 0) return null;
  const anfang = appCss.indexOf('{', start);
  let tiefe = 0;
  for (let i = anfang; i < appCss.length; i++) {
    if (appCss[i] === '{') tiefe++;
    else if (appCss[i] === '}' && --tiefe === 0) return appCss.slice(anfang + 1, i);
  }
  return null;
}
// Liefert je Modus [Quelltoken, Deckung] der ersten Flächenangabe rgb(var(--x-rgb) / a) ausserhalb von
// @supports/@media-Fallbacks; verschachtelte Blöcke mit «dark» zählen als dunkel.
function materialDeckung(name) {
  const inner = utilityBlock(name);
  if (!inner) return null;
  const stapel = [];
  let puffer = '';
  const ergebnis = {};
  for (const ch of inner) {
    if (ch === '{') {
      stapel.push(puffer.trim());
      puffer = '';
    } else if (ch === '}') {
      stapel.pop();
      puffer = '';
    } else if (ch === ';') {
      const m = puffer.match(/background(?:-color)?\s*:\s*rgb\(var\(--([a-z0-9-]+?)(?:-rgb)?\)\s*\/\s*(0?\.\d+|1)\)/);
      if (m && !stapel.some((x) => /@supports|@media/.test(x))) {
        const modus = stapel.some((x) => /dark/.test(x)) ? 'dunkel' : 'hell';
        ergebnis[modus] ??= [m[1], Number(m[2])];
      }
      puffer = '';
    } else puffer += ch;
  }
  if (!ergebnis.hell && !ergebnis.dunkel) return null;
  ergebnis.hell ??= ergebnis.dunkel;
  ergebnis.dunkel ??= ergebnis.hell;
  return ergebnis;
}
const MATERIAL = {};
for (const name of ['np-glas', 'glass-bar', 'glass-seitenleiste', 'glass-overlay', 'np-glas-gruppe']) {
  const d = materialDeckung(name);
  if (d) MATERIAL[name] = d;
  else console.error(`Hinweis: @utility ${name} ohne auswertbare Flächenangabe in app.css`);
}
if (opt.glas) MATERIAL[`glas ${opt.glas}`] = { hell: ['card', Number(opt.glas)], dunkel: ['card', Number(opt.glas)] };
// Scrollkante (np-symbolleiste::before): Verlauf aus --bg, der den Inhalt unter der Leiste abblendet, bevor
// er die Schrift erreicht. Für das Modell zählt der schwächste Stopp über der Textzeile (zweiter Stopp).
const kanteStopps = [...(utilityBlock('np-symbolleiste') || '').matchAll(/rgb\(var\(--bg-rgb\)\s*\/\s*(0?\.\d+|1)\)/g)].map((m) => Number(m[1])).filter((a) => a > 0);
const KANTE = kanteStopps.length ? Math.min(...kanteStopps) : 0;

const bloecke = [];
for (const m of css.matchAll(/([^{}]+)\{([^{}]*)\}/g)) {
  const selektor = m[1].trim().replace(/\s+/g, ' ');
  const werte = {};
  for (const z of m[2].matchAll(/--([a-z0-9-]+)\s*:\s*(\d+)\s+(\d+)\s+(\d+)\s*;/g)) werte[z[1]] = [+z[2], +z[3], +z[4]];
  if (Object.keys(werte).length) bloecke.push({ selektor, werte });
}
const themeName = (s) => (/:root/.test(s) ? 'gletscher' : s.match(/data-theme='([a-z]+)'/)?.[1]);
const akzentName = (s) => s.match(/data-akzent='([a-z]+)'/)?.[1];
const modus = (s) => (/\.dark/.test(s) ? 'dunkel' : 'hell');

const themes = {};
const akzente = {};
for (const b of bloecke) {
  const a = akzentName(b.selektor);
  const t = themeName(b.selektor);
  if (a) akzente[`${a}/${modus(b.selektor)}`] = b.werte;
  else if (t && !/\s/.test(b.selektor.replace(/,\s*/g, ''))) themes[`${t}/${modus(b.selektor)}`] = { ...(themes[`${t}/${modus(b.selektor)}`] || {}), ...b.werte };
}

const lin = (c) => {
  const s = c / 255;
  return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
};
const leucht = ([r, g, b]) => 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
const kontrast = (a, b) => {
  const [h, d] = [leucht(a), leucht(b)].sort((x, y) => y - x);
  return (h + 0.05) / (d + 0.05);
};
const misch = (vorne, alpha, hinten) => vorne.map((v, i) => Math.round(v * alpha + hinten[i] * (1 - alpha)));
const r2 = (x) => Math.round(x * 100) / 100;

const befunde = [];
const alle = [];
function pruefe(kontext, vorne, hinten, name, schwelle, pflicht = true) {
  if (!vorne || !hinten) return;
  const k = r2(kontrast(vorne, hinten));
  const eintrag = { kontext, paar: name, kontrast: k, schwelle, ok: k >= schwelle, pflicht };
  alle.push(eintrag);
  if (!eintrag.ok && pflicht) befunde.push(eintrag);
}

for (const [schluessel, t] of Object.entries(themes)) {
  const [name, m] = schluessel.split('/');
  const textSchwelle = name === 'kontrast' ? 7 : 4.5;
  for (const flaeche of ['bg', 'card', 'surface-2', 'input']) {
    pruefe(schluessel, t.text, t[flaeche], `text auf ${flaeche}`, textSchwelle);
    pruefe(schluessel, t.muted, t[flaeche], `muted auf ${flaeche}`, textSchwelle);
  }
  for (const flaeche of ['bg', 'card', 'surface-2']) pruefe(schluessel, t['accent-text'], t[flaeche], `accent-text auf ${flaeche}`, 4.5);
  pruefe(schluessel, t['accent-contrast'], t.accent, 'accent-contrast auf accent', 4.5);
  for (const flaeche of ['bg', 'card']) {
    pruefe(schluessel, t.accent, t[flaeche], `accent auf ${flaeche} (UI)`, 3);
    pruefe(schluessel, t.ring, t[flaeche], `ring auf ${flaeche} (UI)`, 3);
    pruefe(schluessel, t['border-strong'], t[flaeche], `border-strong auf ${flaeche} (UI)`, 3);
  }
  for (const n of ['gut', 'genuegend', 'knapp', 'ungenuegend']) {
    pruefe(schluessel, t[`note-${n}`], t.card, `note-${n} auf card`, 4.5);
    pruefe(schluessel, t[`note-${n}`], t.bg, `note-${n} auf bg`, 4.5);
    pruefe(schluessel, t[`note-${n}`], misch(t[`note-${n}`], 0.14, t.card), `note-${n} auf eigener Marke`, 4.5);
  }
  for (let i = 1; i <= 6; i++) pruefe(schluessel, t[`chart-${i}`], t.card, `chart-${i} auf card (Grafik)`, 3);
  pruefe(schluessel, misch(t.text, 0.45, t.card), t.card, 'faint auf card (Info, inaktiv)', 3, false);
  for (const [material, def] of Object.entries(MATERIAL)) {
    const [quelle, alpha] = def[m];
    for (const unterlage of ['bg', 'card', 'surface-2']) {
      const flaeche = misch(t[quelle], alpha, t[unterlage]);
      pruefe(schluessel, t.text, flaeche, `text auf ${material} über ${unterlage}`, textSchwelle);
      pruefe(schluessel, t.muted, flaeche, `muted auf ${material} über ${unterlage}`, textSchwelle);
      pruefe(schluessel, t['accent-text'], flaeche, `accent-text auf ${material} über ${unterlage}`, 4.5);
    }
    // Akzentfläche als Unterlage (Primärknopf hinter einem Menü): Hinweis, keine Pflicht
    const ueberAkzent = misch(t[quelle], alpha, t.accent);
    pruefe(schluessel, t.text, ueberAkzent, `text auf ${material} über accent (Info)`, textSchwelle, false);
  }
}

// Akzentvarianten gelten für jedes Nicht-Kontrast-Theme desselben Modus
for (const [schluessel, a] of Object.entries(akzente)) {
  const [akzent, m] = schluessel.split('/');
  for (const [tSchluessel, t] of Object.entries(themes)) {
    const [name, tm] = tSchluessel.split('/');
    if (tm !== m || name === 'kontrast') continue;
    const k = `${name}/${m} + akzent ${akzent}`;
    for (const flaeche of ['bg', 'card', 'surface-2', 'input']) pruefe(k, a['accent-text'], t[flaeche], `accent-text auf ${flaeche}`, 4.5);
    pruefe(k, a['accent-contrast'], a.accent, 'accent-contrast auf accent', 4.5);
    for (const flaeche of ['bg', 'card']) {
      pruefe(k, a.accent, t[flaeche], `accent auf ${flaeche} (UI)`, 3);
      pruefe(k, a.ring, t[flaeche], `ring auf ${flaeche} (UI)`, 3);
    }
    if (a['chart-1']) pruefe(k, a['chart-1'], t.card, 'chart-1 auf card (Grafik)', 3);
  }
}

if (opt.minimum) {
  // Unterlagen, die im Portal unter einem Material liegen können: Grund, Karte, Akzentknopf,
  // Diagramm- und Notenfarben. Die Deckung muss über der schlechtesten davon halten.
  console.log('Kleinste Glas-Deckung (card über jeder Token-Unterlage), bei der text UND muted ihre Schwelle halten:');
  for (const [schluessel, t] of Object.entries(themes)) {
    const schwelle = schluessel.startsWith('kontrast') ? 7 : 4.5;
    const unterlagen = ['bg', 'card', 'surface-2', 'accent', 'chart-1', 'chart-2', 'chart-3', 'chart-4', 'chart-5', 'chart-6', 'note-gut', 'note-genuegend', 'note-knapp', 'note-ungenuegend']
      .map((n) => t[n])
      .filter(Boolean);
    let lo = 0;
    let hi = 1;
    const haelt = (a) => unterlagen.every((u) => {
      const f = misch(t.card, a, u);
      return kontrast(t.text, f) >= schwelle && kontrast(t.muted, f) >= schwelle;
    });
    if (!haelt(1)) {
      console.log(`  ${schluessel.padEnd(20)} – reisst schon bei voller Deckung`);
      continue;
    }
    if (haelt(0)) {
      console.log(`  ${schluessel.padEnd(20)} 0.00 (jede Deckung hält)`);
      continue;
    }
    for (let i = 0; i < 24; i++) (haelt((lo + hi) / 2) ? (hi = (lo + hi) / 2) : (lo = (lo + hi) / 2));
    const nurGrund = (() => {
      let l = 0;
      let h = 1;
      const ok = (a) => ['bg', 'surface-2'].every((n) => {
        const f = misch(t.card, a, t[n]);
        return kontrast(t.text, f) >= schwelle && kontrast(t.muted, f) >= schwelle;
      });
      if (ok(0)) return 0;
      for (let i = 0; i < 24; i++) (ok((l + h) / 2) ? (h = (l + h) / 2) : (l = (l + h) / 2));
      return h;
    })();
    const mitKante = (() => {
      if (!KANTE) return null;
      let l = 0;
      let h = 1;
      const ok = (a) => unterlagen.every((u) => {
        const f = misch(t.card, a, misch(t.bg, KANTE, u));
        return kontrast(t.text, f) >= schwelle && kontrast(t.muted, f) >= schwelle;
      });
      if (ok(0)) return 0;
      if (!ok(1)) return 1;
      for (let i = 0; i < 24; i++) (ok((l + h) / 2) ? (h = (l + h) / 2) : (l = (l + h) / 2));
      return h;
    })();
    console.log(`  ${schluessel.padEnd(20)} ${r2(hi).toFixed(2)}   nur über Grund/surface-2: ${r2(nurGrund).toFixed(2)}   mit Scrollkante (bg ${KANTE}): ${mitKante === null ? '–' : r2(mitKante).toFixed(2)}`);
  }
  console.log('');
}

const zeile = (e) => `${e.ok ? 'ok  ' : 'FEHL'} ${e.kontext.padEnd(34)} ${e.paar.padEnd(44)} ${e.kontrast.toFixed(2).padStart(6)} (≥ ${e.schwelle})${e.pflicht ? '' : ' Info'}`;
if (opt.alle) for (const e of alle) console.log(zeile(e));
else for (const e of befunde) console.log(zeile(e));
const info = alle.filter((e) => !e.ok && !e.pflicht);
console.log(`Materialien aus app.css: ${Object.entries(MATERIAL).map(([n, d]) => `${n} hell ${d.hell[0]}/${d.hell[1]} dunkel ${d.dunkel[0]}/${d.dunkel[1]}`).join(' · ')}`);
console.log(`${Object.keys(themes).length} Theme-Blöcke, ${Object.keys(akzente).length} Akzentblöcke, ${alle.length} Paare geprüft, ${befunde.length} Pflichtverstösse, ${info.length} Hinweise unter Schwelle.`);
if (opt.json) fs.writeFileSync(typeof opt.json === 'string' ? opt.json : 'kontrast.json', JSON.stringify({ befunde, alle }, null, 2));
process.exit(befunde.length ? 1 : 0);
