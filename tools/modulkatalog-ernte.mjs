#!/usr/bin/env node
// Ernte der offiziellen Modul-Stammdaten aus dem Modulbaukasten von ICT-Berufsbildung Schweiz
// (modulbaukasten.ch) in eine JSON-Datei, die «php artisan notenportal:modulkatalog» einliest.
//
// Warum ein eigenes Werkzeug und kein Abruf im Portal:
//   * modulbaukasten.ch ist eine Angular-Anwendung; die Modullisten je Abschluss stehen erst im
//     gerenderten Seitenzustand. Ein PHP-Abruf erhält nur das leere Grundgerüst.
//   * Die Seite bezieht ihre Daten über ein Zugangstoken eines fremden Systems. Dieses Werkzeug
//     verwendet und speichert dieses Token nicht und liest ausschliesslich die gerenderte Seite.
//   * Die Inhalte des Modulbaukastens gehören ICT-Berufsbildung Schweiz. Deshalb liegt im Portal
//     kein Katalog bei: wer ihn will, erntet ihn auf der eigenen Instanz. Siehe docs/modulkatalog.md.
//
// Voraussetzung: Node 20+ und Playwright mit Chromium.
//   npm i -D playwright && npx playwright install --with-deps chromium
//
// Beispiele:
//   node tools/modulkatalog-ernte.mjs --ziel=katalog.json
//   node tools/modulkatalog-ernte.mjs --ziel=katalog.json --abschluss="Entwickler/in digitales Business EFZ" --details
//   node tools/modulkatalog-ernte.mjs --ziel=katalog.json --details --nur=319,431

import fs from 'node:fs';
import { chromium } from 'playwright';

const BASIS = 'https://www.modulbaukasten.ch';
const arg = (name, standard = null) => {
    const t = process.argv.find((a) => a.startsWith(`--${name}=`));
    return t ? t.slice(name.length + 3) : (process.argv.includes(`--${name}`) ? true : standard);
};

const ZIEL = arg('ziel', 'modulkatalog.json');
const NUR_ABSCHLUSS = arg('abschluss');
const DETAILS = arg('details', false) === true;
const NUR = String(arg('nur', '') || '').split(',').map((s) => s.trim()).filter(Boolean);
const PAUSE = Number(arg('pause', 1500));
const SICHTBAR = arg('sichtbar', false) === true;

const warte = (ms) => new Promise((r) => setTimeout(r, ms));
const sauber = (s) => String(s ?? '').replace(/\s+/g, ' ').trim();

/** Eine Modulzeile der Übersicht zerlegen: Nummer, Pflichtgrad, Kürzel, Version, Titel. */
const GRADE = new Set(['pfl', 'wpfl', 'wm']);
function zeileZerlegen(zeilen) {
    const nummer = zeilen[0] ?? '';
    let pflichtgrad = null;
    let version = null;
    const titelteile = [];
    for (const z of zeilen.slice(1)) {
        if (GRADE.has(z)) { pflichtgrad = z; continue; }
        if (/^V\d+$/.test(z)) { version = z.slice(1); continue; }
        if (z === 'EOL') { continue; }                       // Status, kein Kürzel
        if (/^[A-ZÄÖÜ+]{2,5}$/.test(z)) { continue; }        // Abschlusskürzel, über die Seite bekannt
        titelteile.push(z);
    }
    const titel = sauber(titelteile.join(' '));
    return {
        nummer: sauber(nummer),
        pflichtgrad,
        version,
        titel: titel.replace(/\s*\(in progress\)$/i, ''),
        in_arbeit: /\(in progress\)/i.test(titel),
        auslaufend: zeilen.includes('EOL'),
    };
}

/** Die Modulliste des aktuell gewählten Abschlusses ernten. */
const listeErnten = (page) => page.evaluate(() => {
    const raus = [];
    for (const a of document.querySelectorAll('a[href*="/module/"]')) {
        let gruppe = '';
        let n = a.closest('mat-expansion-panel, section, div');
        while (n && !gruppe) {
            const k = n.querySelector('mat-panel-title, h2, h3, .panel-title');
            if (k && !k.contains(a)) gruppe = (k.innerText || '').trim().split('\n')[0];
            n = n.parentElement;
        }
        raus.push({
            href: a.getAttribute('href') || '',
            zeilen: (a.innerText || '').split('\n').map((z) => z.trim()).filter(Boolean),
            gruppe: gruppe.trim(),
        });
    }
    return raus;
});

async function cookiesWegklicken(page) {
    for (const t of ['Akzeptieren', 'Accept']) {
        const b = page.getByRole('button', { name: t });
        if (await b.count()) { await b.first().click().catch(() => {}); return; }
    }
}

/** Abschnitt aus dem Seitentext lesen: Überschrift auf eigener Zeile, Inhalt bis zur Leerzeile. */
function abschnitt(text, titel) {
    const zeilen = text.split('\n').map((z) => z.trim());
    const i = zeilen.findIndex((z) => z === titel);
    if (i < 0) return null;
    const raus = [];
    for (let j = i + 1; j < zeilen.length; j++) {
        if (zeilen[j] === '') { if (raus.length) break; continue; }
        raus.push(zeilen[j]);
        if (raus.length > 40) break;
    }
    return raus.length ? sauber(raus.join(' ')) : null;
}

function handlungszieleLesen(text) {
    const zeilen = text.split('\n').map((z) => z.trim());
    const start = zeilen.findIndex((z) => z === 'Handlungsziele');
    if (start < 0) return [];
    const raus = [];
    for (let i = start + 1; i < zeilen.length; i++) {
        const z = zeilen[i];
        if (z === 'Zugewiesene Handlungskompetenzen im Bildungsplan oder in der Wegleitung') break;
        const m = /^(\d{1,2})\.$/.exec(z);
        if (!m) continue;
        const teile = [];
        for (let j = i + 1; j < zeilen.length && teile.length < 6; j++) {
            const t = zeilen[j];
            if (t === '' || /^\d{1,2}\.$/.test(t)) break;
            if (t.startsWith('Handlungsnotwendige Kenntnisse')) break;
            if (t.startsWith('Hinweis:')) break;
            if (t === 'Alle öffnen' || t === 'Alle schliessen') continue;
            teile.push(t);
        }
        if (teile.length) raus.push({ nummer: m[1], text: sauber(teile.join(' ')) });
    }
    return raus;
}

/** LBV-Elemente: «LBV 319-2 - Element 1 - Gewichtung: 33% - Richtzeit: 0.75» plus Folgefelder. */
function lbvLesen(text) {
    const zeilen = text.split('\n').map((z) => z.trim());
    const elemente = [];
    for (let i = 0; i < zeilen.length; i++) {
        const m = /^(LBV\s+[\w.\-]+\s*-\s*Element\s+\d+)\s*-\s*Gewichtung:\s*([\d.]+)%(?:\s*-\s*Richtzeit:\s*([\d.]+))?/i.exec(zeilen[i]);
        if (!m) continue;
        const feld = (name) => {
            for (let j = i + 1; j < Math.min(i + 60, zeilen.length); j++) {
                if (/^LBV\s+[\w.\-]+\s*-\s*Element\s+\d+\s*-\s*Gewichtung/i.test(zeilen[j])) break;
                if (zeilen[j] === name) {
                    const teile = [];
                    for (let k = j + 1; k < zeilen.length && teile.length < 8; k++) {
                        if (zeilen[k] === '') { if (teile.length) break; continue; }
                        if (/^(Hilfsmittel|Praxisbezug|Prüfungsform|Sozialform|Bewertungskriterien|Beschreibung)$/.test(zeilen[k])) break;
                        teile.push(zeilen[k]);
                    }
                    return teile.length ? sauber(teile.join(' ')) : null;
                }
            }
            return null;
        };
        elemente.push({
            bezeichnung: sauber(m[1]),
            gewichtung_prozent: Number(m[2]),
            richtzeit: m[3] ? Number(m[3]) : null,
            beschreibung: feld('Beschreibung'),
            pruefungsform: feld('Prüfungsform'),
            sozialform: feld('Sozialform'),
        });
    }
    return {
        anzahl_elemente: Number(abschnitt(text, 'Anzahl Elemente')) || elemente.length || null,
        richtzeit_total: Number(abschnitt(text, 'Richtzeit total')) || null,
        beschreibung: abschnitt(text, 'Beschreibung'),
        elemente,
    };
}

async function modulDetails(page, nummer, version) {
    const url = `${BASIS}/module/${nummer}/${version}/de-DE`;
    await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
    await warte(2500);
    await cookiesWegklicken(page);

    const reiter = await page.evaluate(() =>
        [...document.querySelectorAll('[role="tab"]')].map((t) => (t.innerText || '').trim()).filter(Boolean));

    const daten = { nummer, version, quelle_url: url, reiter };
    for (const name of reiter) {
        await page.getByRole('tab', { name, exact: false }).first().click().catch(() => {});
        await warte(2000);
        for (const k of ['Alle öffnen']) {
            const b = page.getByRole('button', { name: k });
            if (await b.count()) { await b.first().click().catch(() => {}); await warte(1200); }
        }
        const text = await page.evaluate(() => document.body.innerText);
        if (name === 'Modulidentifikation') {
            const kopf = /Modul publiziert:\s*([\d.]+)\s*\|\s*Version:\s*(\d+)/.exec(text);
            daten.publiziert_am = kopf ? kopf[1].split('.').reverse().join('-') : null;
            daten.kompetenz = abschnitt(text, 'Kompetenz');
            daten.objekt = abschnitt(text, 'Objekt');
            daten.handlungsziele = handlungszieleLesen(text);
            daten.titel = sauber((new RegExp(`^${nummer}\\s+(.+)$`, 'm').exec(text) || [])[1] || '');
        } else if (name === 'LBV') {
            daten.lbv = lbvLesen(text);
        } else if (name === 'Abschlüsse') {
            daten.abschluesse = text.split('\n').map((z) => z.trim())
                .filter((z) => /(EFZ|EBA|EFA|ED)\b/.test(z) && z.length < 90);
        }
    }
    return daten;
}

const browser = await chromium.launch({ headless: !SICHTBAR });
const page = await browser.newPage();
console.log('Übersicht laden …');
await page.goto(`${BASIS}/`, { waitUntil: 'networkidle', timeout: 60000 });
await warte(3000);
await cookiesWegklicken(page);
await warte(500);

// Abschlussliste lesen
await page.locator('mat-select').first().click();
await warte(1000);
const namen = (await page.evaluate(() =>
    [...document.querySelectorAll('mat-option')].map((o) => (o.innerText || '').trim()))).filter(Boolean);
await page.keyboard.press('Escape');
await warte(400);

const abschluesse = [];
const gesehen = new Map();
for (let i = 1; i < namen.length; i++) {          // 0 = «-- Keiner --»
    if (NUR_ABSCHLUSS && namen[i] !== NUR_ABSCHLUSS) continue;
    await page.locator('mat-select').first().click();
    await warte(800);
    await page.locator('mat-option').nth(i).click();
    await warte(PAUSE + 2500);

    const roh = await listeErnten(page);
    const kennung = (page.url().match(/[?&]d=([^&]+)/) || [])[1] || null;
    const module = roh.map((r) => {
        const m = zeileZerlegen(r.zeilen);
        const lehrjahr = /^(\d)\.\s*Lehrjahr/.exec(r.gruppe);
        return {
            ...m,
            lehrjahr: lehrjahr ? Number(lehrjahr[1]) : null,
            kompetenzfeld: lehrjahr ? null : (r.gruppe || null),
        };
    }).filter((m) => m.nummer);
    for (const m of module) {
        const s = `${m.nummer}/${m.version ?? ''}`;
        if (!gesehen.has(s)) gesehen.set(s, { nummer: m.nummer, version: m.version, titel: m.titel, auslaufend: m.auslaufend, in_arbeit: m.in_arbeit });
    }
    abschluesse.push({ name: namen[i], kennung, module });
    console.log(`  ${namen[i]} → ${module.length} Module`);
}

// Einzelmodule mit allen Angaben
const module = [...gesehen.values()];
if (DETAILS) {
    const liste = module.filter((m) => m.version && (NUR.length === 0 || NUR.includes(m.nummer)));
    console.log(`Einzelmodule laden: ${liste.length} (Pause ${PAUSE} ms)`);
    for (const [n, m] of liste.entries()) {
        try {
            const d = await modulDetails(page, m.nummer, m.version);
            Object.assign(m, d);
            console.log(`  ${n + 1}/${liste.length} ${m.nummer} V${m.version} – ${d.handlungsziele?.length ?? 0} Handlungsziele, ${d.lbv?.elemente?.length ?? 0} LBV-Elemente`);
        } catch (e) {
            console.log(`  ${n + 1}/${liste.length} ${m.nummer} V${m.version} – FEHLER: ${e.message}`);
            m.fehler = e.message;
        }
        await warte(PAUSE);
    }
}

const ausgabe = {
    format: 1,
    quelle: 'modulbaukasten.ch',
    quelle_hinweis: 'Inhalte gehören ICT-Berufsbildung Schweiz, Nutzungsbedingungen Modulbaukasten beachten.',
    geerntet_am: new Date().toISOString(),
    abschluesse,
    module,
};
fs.writeFileSync(ZIEL, JSON.stringify(ausgabe, null, 1));
console.log(`\nGeschrieben: ${ZIEL} (${abschluesse.length} Abschlüsse, ${module.length} Module)`);
console.log('Weiter mit: php artisan notenportal:modulkatalog '+ZIEL);
await browser.close();
