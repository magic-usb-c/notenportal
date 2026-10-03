// Rundgang: folgt allen internen Links einer Rolle und meldet Layoutfehler, Dunkelmodus als Standard.
//
//   NP_TEST_PW=… node tools/pruefung/rundgang.mjs <email> [--breite=1920] [--hoehe=1080] [--hell]
//                                                  [--max=150] [--start=/dashboard] [--shots=dir]
//                                                  [--bewegung=reduziert] [--transparenz=reduziert] [--kontrast=mehr]
//
// Schreibt nichts: nach der Anmeldung werden alle Nicht-GET-Requests abgebrochen.
// Befunde (je einmal pro Art+Text): UEBERLAUF, KLEIN (<10 px), ABGESCHNITTEN, ELLIPSE<90,
// RAND, VORFAHR-CLIP, UEBERLAPPUNG, STATUS, LADEN, JS. Exit-Code 1, wenn Befunde vorliegen.
// Ziel über NP_URL (Standard http://127.0.0.1:8099), Passwort nur über NP_TEST_PW.
import fs from 'node:fs';
import { ZUSTAND_USAGE, anmelden, basisUrl, optionen, starteBrowser, zustandsZeile } from './browser.mjs';

const { positionen, opt } = optionen(process.argv.slice(2));
const [email] = positionen;
if (!email) {
  console.error('Aufruf: NP_TEST_PW=… node tools/pruefung/rundgang.mjs <email> [--breite=1920] [--hoehe=1080] [--hell] [--max=150] [--start=/dashboard] [--shots=dir] ' + ZUSTAND_USAGE);
  process.exit(1);
}
const breite = Number(opt.breite || 1920);
const hoehe = Number(opt.hoehe || 1080);
const max = Number(opt.max || 150);

const { browser, page, fehler } = await starteBrowser(opt);
const verboten = /logout|export|print|download|template|\.(csv|pdf|ics|json|xlsx|zip)(\?|$)|\/calendar\/|impersonat|\/file|\/raw|\/build\/|\/storage\//i;
const muster = (p) => p.replace(/\/\d+(?=\/|$)/g, '/:id').replace(/\?.*$/, '');
const gesehen = new Set();
const besucht = new Set();
const befunde = [];

let abgebrochen = 0;
let zustand = '';
try {
  await anmelden(page, email);
  // Lesende POSTs des Rechners (berechnen/simulieren schreiben nichts) laufen durch – sonst zeigt die
  // Rechnerseite im Rundgang «Berechnung fehlgeschlagen», ein Werkzeug-, kein Portalbefund (01.10.2026).
  const lesendePosts = /\/calculator(\/simulate)?$/;
  await page.route('**/*', (r) => {
    const req = r.request();
    if (req.method() === 'GET' || (req.method() === 'POST' && lesendePosts.test(new URL(req.url()).pathname))) return r.continue();
    abgebrochen++;
    return r.abort();
  });
  const warte = [opt.start || new URL(page.url()).pathname];
  if (opt.shots) fs.mkdirSync(opt.shots, { recursive: true });

  while (warte.length && besucht.size < max) {
    const pfad = warte.shift();
    if (besucht.has(pfad)) continue;
    const m = muster(pfad);
    if (gesehen.has(m)) continue;
    gesehen.add(m);
    besucht.add(pfad);
    fehler.length = 0;
    abgebrochen = 0;
    let r;
    try {
      r = await page.goto(basisUrl + pfad, { waitUntil: 'networkidle', timeout: 20000 });
    } catch (e) {
      befunde.push([pfad, 'LADEN', String(e).slice(0, 120)]);
      continue;
    }
    await page.waitForTimeout(250);
    await page.bringToFront();
    zustand ||= await zustandsZeile(page);
    const status = r?.status() ?? 0;
    if (status >= 400) {
      befunde.push([pfad, 'STATUS', String(status)]);
      continue;
    }
    const links = await page.$$eval('a[href]', (as) => as.map((a) => a.getAttribute('href')));
    for (const h of links) {
      if (!h) continue;
      let voll;
      try {
        voll = new URL(h, basisUrl + pfad);
      } catch {
        continue;
      }
      if (voll.origin !== new URL(basisUrl).origin) continue;
      const p = voll.pathname;
      if (verboten.test(voll.href) || besucht.has(p)) continue;
      warte.push(p);
    }
    const b = await page.evaluate(() => {
      const out = [];
      const sichtbar = (el) => {
        const s = getComputedStyle(el);
        if (s.visibility === 'hidden' || s.display === 'none' || Number(s.opacity) === 0) return false;
        const r = el.getBoundingClientRect();
        return r.width > 0 && r.height > 0;
      };
      const name = (el) =>
        (el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + '.' + [...el.classList].slice(0, 3).join('.')).slice(0, 70) +
        ' «' + (el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 40) + '»';
      if (document.documentElement.scrollWidth > innerWidth + 1) out.push(['UEBERLAUF', document.documentElement.scrollWidth + ' > ' + innerWidth]);
      const blaetter = [];
      for (const el of document.querySelectorAll('body *')) {
        if (['SCRIPT', 'STYLE', 'svg', 'SVG', 'OPTION', 'TEMPLATE', 'TITLE'].includes(el.tagName)) continue;
        if (el.closest('[aria-hidden="true"], .sr-only, template, [x-cloak], dialog:not([open]), canvas, select')) continue;
        const zu = el.closest('details:not([open])');
        if (zu && !el.closest('summary')) continue;
        const eigenerText = [...el.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim());
        if (!eigenerText || !sichtbar(el)) continue;
        const s = getComputedStyle(el);
        const fs = parseFloat(s.fontSize);
        if (fs < 10) out.push(['KLEIN', fs + 'px ' + name(el)]);
        if (el.scrollWidth > el.clientWidth + 1 && /hidden|clip/.test(s.overflowX) && s.textOverflow !== 'ellipsis' && s.display !== 'inline') out.push(['ABGESCHNITTEN', name(el)]);
        if (s.textOverflow === 'ellipsis' && el.scrollWidth > el.clientWidth + 1 && el.clientWidth < 90) out.push(['ELLIPSE<90', el.clientWidth + 'px ' + name(el)]);
        const r = el.getBoundingClientRect();
        if (r.right > innerWidth + 1 || r.left < -1) out.push(['RAND', name(el)]);
        let v = el.parentElement;
        while (v && v !== document.body) {
          const vs = getComputedStyle(v);
          if (/hidden|clip/.test(vs.overflowX) || /hidden|clip/.test(vs.overflowY)) {
            const vr = v.getBoundingClientRect();
            if (vr.width > 0 && (r.right > vr.right + 2 || r.left < vr.left - 2) && vs.position !== 'fixed') out.push(['VORFAHR-CLIP', name(el) + ' in ' + name(v).slice(0, 50)]);
            break;
          }
          v = v.parentElement;
        }
        const range = document.createRange();
        for (const n of el.childNodes) {
          if (n.nodeType !== 3 || !n.textContent.trim()) continue;
          range.selectNodeContents(n);
          for (const rr of range.getClientRects()) {
            if (rr.width <= 1 || rr.height <= 1) continue;
            let l = rr.left, o = rr.top, re = rr.right, u = rr.bottom;
            for (let w = el.parentElement; w && w !== document.documentElement; w = w.parentElement) {
              const ws = getComputedStyle(w);
              if (ws.overflowX !== 'visible' || ws.overflowY !== 'visible' || ws.contentVisibility === 'hidden') {
                const wr = w.getBoundingClientRect();
                l = Math.max(l, wr.left); o = Math.max(o, wr.top); re = Math.min(re, wr.right); u = Math.min(u, wr.bottom);
              }
              if (ws.position === 'fixed') break;
            }
            if (re - l > 1 && u - o > 1) blaetter.push({ el, r: { left: l, top: o, right: re, bottom: u } });
          }
        }
      }
      const zeige = new Set();
      for (let i = 0; i < blaetter.length; i++) {
        for (let j = i + 1; j < blaetter.length; j++) {
          const a = blaetter[i], c = blaetter[j];
          if (a.el === c.el || a.el.contains(c.el) || c.el.contains(a.el)) continue;
          const x = Math.min(a.r.right, c.r.right) - Math.max(a.r.left, c.r.left);
          const y = Math.min(a.r.bottom, c.r.bottom) - Math.max(a.r.top, c.r.top);
          if (x > 2 && y > 3) {
            const mx = (Math.max(a.r.left, c.r.left) + Math.min(a.r.right, c.r.right)) / 2;
            const my = (Math.max(a.r.top, c.r.top) + Math.min(a.r.bottom, c.r.bottom)) / 2;
            const oben = document.elementFromPoint(mx, my);
            if (!oben || !(a.el.contains(oben) || c.el.contains(oben) || oben.contains(a.el) || oben.contains(c.el))) continue;
            const k = name(a.el) + ' ⟷ ' + name(c.el);
            if (!zeige.has(k)) {
              zeige.add(k);
              out.push(['UEBERLAPPUNG', k]);
            }
          }
        }
      }
      return out;
    });
    for (const [art, text] of b) befunde.push([pfad, art, text]);
    // net::ERR_FAILED stammt von den absichtlich abgebrochenen Nicht-GET-Requests, nicht von der Seite
    for (const f of fehler) {
      if (abgebrochen && /net::ERR_FAILED/.test(f)) continue;
      befunde.push([pfad, 'JS', f]);
    }
    if (opt.shots) await page.screenshot({ path: `${opt.shots}/${pfad.replace(/[^a-z0-9]+/gi, '_')}.png`, fullPage: true });
  }
} finally {
  await browser.close();
}

console.log(`Besucht ${besucht.size} Seiten (${email}, ${breite}x${hoehe}, ${opt.hell ? 'hell' : 'dunkel'})`);
if (zustand) console.log(zustand);
const eindeutig = new Set();
for (const [p, a, t] of befunde) {
  const k = a + t;
  if (eindeutig.has(k)) continue;
  eindeutig.add(k);
  console.log(`${a}\t${p}\t${t}`);
}
process.exit(eindeutig.size ? 1 : 0);
