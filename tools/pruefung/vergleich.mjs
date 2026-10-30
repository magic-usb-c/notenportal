// Vergleicht zwei JSON-Ausgaben von leistung.mjs (gleiche Argumente, gleiche Maschine) und schreibt eine
// Markdown-Tabelle vorher/nachher/Differenz je Seite.
//
//   node tools/pruefung/vergleich.mjs --vorher=a.json[,a2.json] --nachher=b.json[,b2.json] [--titel=…]
//
// Mehrere Dateien je Seite werden zusammengelegt (z. B. je Rolle eine Datei). Spalten: LCP, Knoten, Glasanteil
// Ruhe, Ruhe p95, Animationen in Ruhe, Scroll p95 und lange Bilder, Palette Glasanteil und Tippen p95. Fehlt ein
// Wert (Messung nicht gelaufen), steht «–». Schreibt nichts, nur stdout.
import fs from 'node:fs';

const arg = (name) => process.argv.find((a) => a.startsWith(`--${name}=`))?.split('=').slice(1).join('=');
const dateien = (liste) => (liste || '').split(',').filter(Boolean);
if (!arg('vorher') || !arg('nachher')) {
  console.error('Aufruf: node tools/pruefung/vergleich.mjs --vorher=a.json --nachher=b.json [--titel=…]');
  process.exit(2);
}

function lade(liste) {
  const seiten = new Map();
  for (const datei of dateien(liste)) {
    const j = JSON.parse(fs.readFileSync(datei, 'utf8'));
    for (const e of j.ergebnisse || []) seiten.set(`${j.email.split('@')[0]} ${e.pfad}`, e);
  }
  return seiten;
}

const vorher = lade(arg('vorher'));
const nachher = lade(arg('nachher'));
const MASSE = [
  ['LCP ms', (e) => e.lcpMs || null],
  ['Knoten', (e) => e.knoten],
  ['Glas Ruhe %', (e) => e.glas?.anteilProzent],
  ['Ruhe p95', (e) => e.ruhe?.p95],
  ['Anim', (e) => e.ruhe?.animationen],
  ['Scroll p95', (e) => (e.scroll?.scrollweg ? e.scroll.p95 : null)],
  ['lange Bilder', (e) => (e.scroll ? e.scroll.lang : null)],
  ['Palette Glas %', (e) => e.palette?.glas?.anteilProzent],
  ['Tippen p95', (e) => e.palette?.tippen?.p95],
];
const zahl = (w) => (w === null || w === undefined || Number.isNaN(w) ? '–' : String(Math.round(w * 10) / 10));
const diff = (a, b) => (a == null || b == null ? '–' : `${b - a >= 0 ? '+' : ''}${Math.round((b - a) * 10) / 10}`);

const zeilen = [];
if (arg('titel')) zeilen.push(`### ${arg('titel')}`, '');
zeilen.push(`| Seite | ${MASSE.map(([n]) => n).join(' | ')} |`, `|---|${MASSE.map(() => '---:').join('|')}|`);
for (const schluessel of new Set([...vorher.keys(), ...nachher.keys()])) {
  const a = vorher.get(schluessel);
  const b = nachher.get(schluessel);
  const zellen = MASSE.map(([, f]) => {
    const wa = a ? f(a) : null;
    const wb = b ? f(b) : null;
    return `${zahl(wa)} → ${zahl(wb)} (${diff(wa, wb)})`;
  });
  zeilen.push(`| \`${schluessel}\` | ${zellen.join(' | ')} |`);
}
console.log(zeilen.join('\n'));
