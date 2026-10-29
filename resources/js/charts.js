// Diagramme: Chart.js mit Farben aus den Design-Tokens. Notenachsen immer 1–6, Genügend-Grenze als beschriftete Haarlinie.
import {
    BarController, BarElement, CategoryScale, Chart, Filler, Legend, LinearScale,
    LineController, LineElement, PointElement, Tooltip,
} from 'chart.js';
import { bewegungRuhig, format, notenFarbe, stufe, t, tokenFarbe } from './np';

Chart.register(BarController, BarElement, CategoryScale, Filler, Legend, LinearScale, LineController, LineElement, PointElement, Tooltip);

const serie = (i, alpha = 1) => tokenFarbe(`--chart-${(i % 6) + 1}`, alpha);

// Neutrale Balken (genügend, gut): Textfarbe zu 50 % – hell und dunkel ≥ 3:1 auf der Karte, in jedem Theme grau und nie
// die Akzentfarbe von Knöpfen und Links. Die Legende (x-noten-legende neutral="bg-text/50") trägt dieselbe Farbe.
const neutralFarbe = () => tokenFarbe('--text', 0.5);
const serienToken = (i) => `--chart-${(i % 6) + 1}`;

// Fläche unter einer Linie als senkrechter Verlauf von 22 % zu 0 % (Swift Charts: AreaMark mit Gradient)
const verlaufFlaeche = (token) => ({ chart }) => {
    const { ctx, chartArea } = chart;
    if (!chartArea) return tokenFarbe(token, 0.1);
    const g = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
    g.addColorStop(0, tokenFarbe(token, 0.22));
    g.addColorStop(1, tokenFarbe(token, 0));
    return g;
};

// Tipp: <div class="np-diagramm-tipp glass-overlay"> neben dem Canvas. x-diagramm legt ihn an; fehlt er (Aufrufer ohne
// x-diagramm), entsteht er hier. Position nur über left/top/opacity, der Rest sind Klassen.
const TIPP_KLASSEN = 'np-diagramm-tipp glass-overlay absolute pointer-events-none z-20 px-3 py-2 text-xs rounded-xl text-text whitespace-nowrap transition-opacity duration-100';

function tippElement(chart) {
    const eltern = chart.canvas.parentNode;
    let el = eltern.querySelector(':scope > .np-diagramm-tipp');
    if (!el) {
        el = document.createElement('div');
        el.className = TIPP_KLASSEN;
        el.setAttribute('aria-hidden', 'true');
        el.style.opacity = '0';
        eltern.append(el);
    }
    if (getComputedStyle(eltern).position === 'static') eltern.classList.add('relative');
    return el;
}

// modell: { x, y (Pixel im Canvas), titel, zeilen: [{ text, farbe? }] } oder null zum Ausblenden
function tippZeigen(chart, modell) {
    const el = tippElement(chart);
    chart.$npTipp = modell ? [modell.titel, ...modell.zeilen.map((z) => z.text)].filter(Boolean).join(', ') : '';
    if (!modell) {
        el.style.opacity = '0';
        return;
    }
    el.replaceChildren();
    if (modell.titel) {
        const kopf = document.createElement('div');
        kopf.className = 'font-semibold';
        kopf.textContent = modell.titel;
        el.append(kopf);
    }
    modell.zeilen.forEach((z) => {
        const zeile = document.createElement('div');
        zeile.className = 'flex items-center gap-2';
        if (z.farbe) {
            const punkt = document.createElement('span');
            punkt.className = 'size-2 shrink-0 rounded-full';
            punkt.style.backgroundColor = z.farbe;
            zeile.append(punkt);
        }
        const text = document.createElement('span');
        text.textContent = z.text;
        zeile.append(text);
        el.append(zeile);
    });
    const eltern = chart.canvas.parentNode;
    const x0 = chart.canvas.offsetLeft + modell.x;
    const y0 = chart.canvas.offsetTop + modell.y;
    const rechts = x0 + 14;
    const x = rechts + el.offsetWidth > eltern.clientWidth ? x0 - 14 - el.offsetWidth : rechts;
    const y = Math.min(Math.max(0, y0 - el.offsetHeight / 2), Math.max(0, eltern.clientHeight - el.offsetHeight));
    el.style.left = `${Math.round(Math.max(0, x))}px`;
    el.style.top = `${Math.round(y)}px`;
    el.style.opacity = '1';
}

// Chart.js ruft das bei jeder Änderung der aktiven Punkte auf (tooltip.enabled ist aus, damit kein Canvas-Tooltip entsteht)
function externerTipp({ chart, tooltip }) {
    const punkte = tooltip.getActiveElements().length ? (tooltip.dataPoints ?? []) : [];
    if (!punkte.length) {
        tippZeigen(chart, null);
        return;
    }
    const pos = punkte.map((p) => p.element.getProps(['x', 'y'], true));
    const zeilen = tooltip.body.flatMap((b, i) => b.lines.map((text) => {
        const farbe = tooltip.labelColors[i]?.backgroundColor;
        return { text: String(text).trim(), farbe: typeof farbe === 'string' ? farbe : null };
    }));
    tippZeigen(chart, {
        x: pos.reduce((summe, p) => summe + p.x, 0) / pos.length,
        y: pos.reduce((summe, p) => summe + p.y, 0) / pos.length,
        titel: tooltip.title.join(' '),
        zeilen,
    });
}

// Erstes Zeichnen gestaffelt (B13): je Punkt 30 ms, der letzte beginnt so, dass alles nach 500 ms steht. Spätere
// Aktualisierungen (Filterwechsel, Rechner) rücken alle Punkte gleichzeitig (B14).
const ANIMATION_DAUER = 300;
const ANIMATION_GESAMT = 500;

function staffel() {
    const wert = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--np-staffel'));
    return Number.isFinite(wert) ? wert : 30;
}

function animation() {
    if (bewegungRuhig()) return false;
    const je = staffel();
    return {
        duration: ANIMATION_DAUER,
        easing: 'easeOutCubic',
        delay: (c) => (c.type === 'data' && c.mode === 'default' && !c.chart.$npFertig
            ? Math.min((c.dataIndex ?? 0) * je, ANIMATION_GESAMT - ANIMATION_DAUER)
            : 0),
        onComplete: ({ chart }) => { chart.$npFertig = true; },
    };
}

function basis() {
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.color = tokenFarbe('--muted');
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: animation(),
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { labels: { usePointStyle: true, boxWidth: 8, filter: (item) => !item.text.startsWith('_') } },
            tooltip: {
                enabled: false,
                external: externerTipp,
                filter: (item) => !item.dataset.label?.startsWith('_'),
                callbacks: { label: (c) => ` ${c.dataset.label}: ${format(c.parsed.y ?? c.parsed.x, 2)}` },
            },
        },
    };
}

const notenAchse = (extra = {}) => ({
    min: 1, max: 6,
    ticks: { stepSize: 1, padding: 6 },
    grid: { color: tokenFarbe('--text', 0.07), drawTicks: false },
    border: { display: false },
    ...extra,
});

// Haarlinie für eine Schwelle (z. B. genügend) mit Text-Label statt roter Strichlinie
const grenzLinie = (anzahl, wert, beschriftung = null, label = '_grenze') => ({
    type: 'line', label, data: Array(anzahl).fill(wert),
    borderColor: tokenFarbe('--muted', 0.55), borderWidth: 1,
    pointRadius: 0, pointHoverRadius: 0, fill: false, order: 99,
    npSchwelleLabel: beschriftung,
});

// Direktlabels am Linienende (Datawrapper-Prinzip) statt Legende; Labels, die sich waagrecht
// überdecken, werden senkrecht auseinandergeschoben und bleiben im Diagrammbereich.
const direktlabelPlugin = {
    id: 'npDirektlabel',
    afterDatasetsDraw(chart) {
        if (!chart.options.plugins?.npDirektlabel?.aktiv) return;
        const { ctx, chartArea } = chart;
        const zeile = 13;
        // Text in Textfarbe (Linienfarben erreichen im Dunkeln keine 4.5:1), Zuordnung über einen
        // Punkt in Linienfarbe davor (Grafik, 3:1 genügt)
        const punkt = 9;
        ctx.save();
        ctx.font = `600 11px ${Chart.defaults.font.family}`;
        ctx.textBaseline = 'middle';
        ctx.textAlign = 'left';

        const labels = [];
        chart.data.datasets.forEach((ds, i) => {
            if (!ds.label || ds.label.startsWith('_') || !chart.isDatasetVisible(i)) return;
            const meta = chart.getDatasetMeta(i);
            // Lücken (null) liegen bei Chart.js auf der x-Achse: nur echte Werte berücksichtigen
            const punkte = meta.data.filter((p, j) => p && !p.skip && ds.data[j] !== null && ds.data[j] !== undefined && Number.isFinite(p.y));
            const letzter = punkte[punkte.length - 1];
            if (!letzter) return;
            const breite = ctx.measureText(ds.label).width + punkt;
            // Immer rechts neben dem Plotbereich verankert (nie auf der Linie), Höhe nach dem letzten Punkt der Reihe
            const x = Math.min(chartArea.right + 6, chart.width - breite - 2);
            labels.push({ text: ds.label, farbe: ds.npLabelFarbe ?? tokenFarbe('--text'), linie: ds.borderColor, x, breite, y: letzter.y });
        });

        const ueberdeckt = (a, b) => a.x < b.x + b.breite && b.x < a.x + a.breite;
        labels.sort((a, b) => a.y - b.y);
        // Nach unten schieben, bis kein Label ein vorheriges überdeckt …
        labels.forEach((l, i) => {
            labels.slice(0, i).forEach((v) => {
                if (ueberdeckt(l, v) && l.y < v.y + zeile) l.y = v.y + zeile;
            });
        });
        // … und von unten her zurück in den Diagrammbereich
        for (let i = labels.length - 1; i >= 0; i--) {
            const l = labels[i];
            l.y = Math.min(l.y, chartArea.bottom - zeile / 2);
            labels.slice(i + 1).forEach((n) => {
                if (ueberdeckt(l, n) && l.y > n.y - zeile) l.y = n.y - zeile;
            });
            l.y = Math.max(l.y, chartArea.top + zeile / 2);
        }

        labels.forEach((l) => {
            ctx.fillStyle = l.linie;
            ctx.beginPath();
            ctx.arc(l.x + 3, l.y, 3, 0, Math.PI * 2);
            ctx.fill();
            ctx.fillStyle = l.farbe;
            ctx.fillText(l.text, l.x + punkt, l.y);
        });
        ctx.restore();
    },
};

// Wert am Balkenende (horizontale Balken)
const balkenwertPlugin = {
    id: 'npBalkenwert',
    afterDatasetsDraw(chart) {
        if (!chart.options.plugins?.npBalkenwert?.aktiv) return;
        const { ctx } = chart;
        ctx.save();
        ctx.font = `600 11px ${Chart.defaults.font.family}`;
        ctx.fillStyle = tokenFarbe('--text');
        ctx.textBaseline = 'middle';
        ctx.textAlign = 'left';
        chart.data.datasets.forEach((ds, i) => {
            chart.getDatasetMeta(i).data.forEach((balken, j) => {
                const wert = ds.data[j]?.[1];
                if (wert === null || wert === undefined) return;
                ctx.fillText(format(wert), balken.x + 6, balken.y);
            });
        });
        ctx.restore();
    },
};

// Senkrechte Schwelle (z. B. genügend) als Haarlinie über die ganze Höhe, Beschriftung unter dem Diagrammbereich
const senkrechtPlugin = {
    id: 'npSenkrecht',
    afterDatasetsDraw(chart) {
        const o = chart.options.plugins?.npSenkrecht;
        if (!o || o.wert === null || o.wert === undefined) return;
        const { ctx, chartArea, scales } = chart;
        const x = Math.round(scales.x.getPixelForValue(o.wert)) + 0.5;
        ctx.save();
        ctx.strokeStyle = tokenFarbe('--text', 0.6);
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(x, chartArea.top);
        ctx.lineTo(x, chartArea.bottom);
        ctx.stroke();
        if (o.text) {
            ctx.font = `11px ${Chart.defaults.font.family}`;
            ctx.fillStyle = tokenFarbe('--muted');
            ctx.textBaseline = 'top';
            ctx.textAlign = 'center';
            ctx.fillText(o.text, x, chartArea.bottom + 4);
        }
        ctx.restore();
    },
};

// Wert einer Kurve an der Stelle x, linear zwischen den beiden benachbarten Punkten (kein Serveraufruf)
function interpoliere(punkte, x) {
    const v = punkte.filter((p) => p && Number.isFinite(p.y)).sort((a, b) => a.x - b.x);
    if (!v.length || x < v[0].x - 1e-9 || x > v[v.length - 1].x + 1e-9) return null;
    for (let i = 1; i < v.length; i++) {
        if (x <= v[i].x + 1e-9) {
            const [a, b] = [v[i - 1], v[i]];
            return b.x === a.x ? b.y : a.y + ((b.y - a.y) * (x - a.x)) / (b.x - a.x);
        }
    }
    return v[0].y;
}

// Scrub (B17): senkrechte Linie samt Punkt folgt Zeiger und Pfeiltasten über die ganze Zeichenfläche; Wert und Tipp
// kommen aus den vorhandenen Kurvenpunkten. note = null blendet alles aus.
function scrubSetze(chart, note) {
    const o = (chart.$npScrub ??= {});
    if (note === null || note === undefined) {
        o.note = null;
        o.wert = null;
        o.text = '';
        tippZeigen(chart, null);
        return;
    }
    o.note = Math.min(6, Math.max(1, note));
    o.wert = interpoliere(chart.data.datasets[0]?.data ?? [], o.note);
    if (o.wert === null) {
        o.text = '';
        tippZeigen(chart, null);
        return;
    }
    const titel = t('Note in offenen Prüfungen: :wert', { wert: format(o.note, 2) });
    const zeile = t('Ergebnis: :wert', { wert: format(o.wert, 2) });
    o.text = `${titel}, ${zeile}`;
    tippZeigen(chart, {
        x: chart.scales.x.getPixelForValue(o.note),
        y: chart.scales.y.getPixelForValue(o.wert),
        titel,
        zeilen: [{ text: zeile, farbe: tokenFarbe('--accent') }],
    });
}

const scrubPlugin = {
    id: 'npScrub',
    afterEvent(chart, args) {
        if (!chart.options.plugins?.npScrub?.aktiv) return;
        const e = args.event;
        if (e.type === 'mouseout') {
            scrubSetze(chart, null);
            args.changed = true;
        } else if (['mousemove', 'touchstart', 'touchmove', 'click'].includes(e.type)) {
            scrubSetze(chart, args.inChartArea ? chart.scales.x.getValueForPixel(e.x) : null);
            args.changed = true;
        }
    },
    afterDatasetsDraw(chart) {
        const o = chart.$npScrub;
        if (!chart.options.plugins?.npScrub?.aktiv || o?.note === null || o?.note === undefined || o.wert === null) return;
        const { ctx, chartArea, scales } = chart;
        const x = Math.round(scales.x.getPixelForValue(o.note)) + 0.5;
        const y = scales.y.getPixelForValue(o.wert);
        ctx.save();
        ctx.strokeStyle = tokenFarbe('--text', 0.35);
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(x, chartArea.top);
        ctx.lineTo(x, chartArea.bottom);
        ctx.stroke();
        ctx.fillStyle = tokenFarbe('--accent');
        ctx.strokeStyle = tokenFarbe('--card');
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(x, y, 4.5, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
        ctx.restore();
    },
};

// Punkte entlang der sichtbaren Datenlinien (Stützpunkte plus Zwischenpunkte), für die Kollisionsprüfung von Labels
const linienPunkte = (chart) => {
    const punkte = [];
    chart.data.datasets.forEach((ds, i) => {
        if (!ds.label || ds.label.startsWith('_') || !chart.isDatasetVisible(i)) return;
        const echt = chart.getDatasetMeta(i).data.filter((p, j) => p && !p.skip && ds.data[j] !== null && ds.data[j] !== undefined && Number.isFinite(p.y));
        echt.forEach((p, j) => {
            punkte.push([p.x, p.y]);
            const n = echt[j + 1];
            if (n) for (let s = 1; s < 8; s++) punkte.push([p.x + (n.x - p.x) * s / 8, p.y + (n.y - p.y) * s / 8]);
        });
    });
    return punkte;
};

// Text-Label neben einer Schwellenlinie (z. B. «genügend 4.0»): links oberhalb, sonst die erste Stelle (Ecken, dann
// entlang der Linie), an der keine Datenlinie durch den Text läuft; ist keine frei, die mit den wenigsten Punkten darunter
const schwellenLabelPlugin = {
    id: 'npSchwellenLabel',
    afterDatasetsDraw(chart) {
        const { ctx, chartArea } = chart;
        chart.data.datasets.forEach((ds, i) => {
            if (!ds.npSchwelleLabel) return;
            const punkt = chart.getDatasetMeta(i).data?.[0];
            if (!punkt) return;
            ctx.save();
            ctx.font = `11px ${Chart.defaults.font.family}`;
            const breite = ctx.measureText(ds.npSchwelleLabel).width;
            const hoehe = 12;
            const daten = linienPunkte(chart);
            // Ecken zuerst, dann Zwischenstellen entlang der Linie; je Stelle oberhalb und unterhalb
            const links = chartArea.left + 2;
            const rechts = chartArea.right - 2 - breite;
            const stellen = [links, rechts, ...[0.25, 0.5, 0.75].map((f) => links + (rechts - links) * f)];
            const kandidaten = stellen
                .flatMap((x) => [{ x, y: punkt.y - 3 - hoehe }, { x, y: punkt.y + 3 }])
                .filter((k) => k.y >= chartArea.top && k.y + hoehe <= chartArea.bottom);
            // Passt das Label in keiner Stelle in die Zeichenfläche (winziges Diagramm), bleibt es weg statt über Achsen zu ragen
            if (rechts < links || kandidaten.length === 0) {
                ctx.restore();
                return;
            }
            const ueberdeckt = (k) => daten.filter(([x, y]) => x > k.x - 5 && x < k.x + breite + 5 && y > k.y - 5 && y < k.y + hoehe + 5).length;
            // Kein freier Platz: die Stelle mit den wenigsten verdeckten Punkten statt blind der ersten
            const ort = kandidaten.find((k) => ueberdeckt(k) === 0)
                ?? kandidaten.reduce((best, k) => (ueberdeckt(k) < ueberdeckt(best) ? k : best));
            ctx.fillStyle = tokenFarbe('--muted');
            ctx.textBaseline = 'top';
            ctx.textAlign = 'left';
            ctx.fillText(ds.npSchwelleLabel, ort.x, ort.y);
            ctx.restore();
        });
    },
};

Chart.register(direktlabelPlugin, schwellenLabelPlugin, balkenwertPlugin, senkrechtPlugin, scrubPlugin);

// Anteil der Diagrammbreite, den die Namensachse horizontaler Balken höchstens belegt
const ACHSENANTEIL = 0.36;

// Text auf eine Pixelbreite kürzen (Auslassungszeichen am Ende), gemessen in der Schrift der Achsenbeschriftung
function kuerzeNachBreite(chart, text, breite) {
    const ctx = chart.ctx;
    ctx.save();
    ctx.font = `${Chart.defaults.font.weight ?? 'normal'} ${Chart.defaults.font.size}px ${Chart.defaults.font.family}`;
    let ergebnis = text;
    if (ctx.measureText(text).width > breite) {
        let n = text.length;
        while (n > 1 && ctx.measureText(`${text.slice(0, n).trimEnd()}…`).width > breite) n--;
        ergebnis = `${text.slice(0, n).trimEnd()}…`;
    }
    ctx.restore();
    return ergebnis;
}

const BAUER = {
    // { labels: [..], serien: [{ name, werte: [..], dick? }], grenze } – eine Serie mit dick:true wird hervorgehoben, Rest gedämpft
    verlauf(d, o = {}) {
        const hervorgehoben = d.serien.some((s) => s.dick);
        // Gedämpfte Reihen behalten ihren Ton (jeder --chart-Token ≥ 3:1 auf der Karte) und tragen zusätzlich
        // ein Strichmuster – HIG «nie nur Farbe». Ein gemeinsames Grau machte sie ununterscheidbar (2.7:1).
        const STRICH = [[6, 4], [2, 3], [10, 4, 2, 4], []];
        let gedaempfte = 0;
        const datasets = d.serien.map((s, i) => {
            const gedaempft = hervorgehoben && !s.dick;
            const token = s.farbe ?? serienToken(i);
            const farbe = tokenFarbe(token);
            // Fläche nur unter der hervorgehobenen bzw. einzigen Linie, sonst überlagern sich die Verläufe
            const flaeche = !gedaempft && (s.dick || d.serien.length === 1);
            return {
                label: s.name, data: s.werte, spanGaps: true, tension: 0.35, cubicInterpolationMode: 'monotone',
                borderColor: farbe, backgroundColor: flaeche ? verlaufFlaeche(token) : 'transparent',
                borderWidth: s.dick ? 3 : (gedaempft ? 1.5 : 2), borderCapStyle: 'round', borderJoinStyle: 'round',
                borderDash: gedaempft ? STRICH[gedaempfte++ % STRICH.length] : [],
                pointRadius: gedaempft ? 0 : 3.5, pointHoverRadius: gedaempft ? 3 : 5.5,
                pointBackgroundColor: farbe, pointBorderColor: tokenFarbe('--card'), pointBorderWidth: 1.5, pointHoverBorderWidth: 2,
                fill: flaeche ? 'start' : false, order: s.dick ? 1 : 2,
                npLabelFarbe: tokenFarbe(gedaempft ? '--muted' : '--text'),
            };
        });
        if (d.grenze) datasets.push(grenzLinie(d.labels.length, d.grenze, t('genügend :wert', { wert: format(d.grenze, 1) })));
        const direkt = o.direktlabels !== false && d.serien.length > 1;
        const namen = d.serien.filter((s) => !s.name?.startsWith('_')).map((s) => s.name ?? '');
        const padRechts = direkt ? Math.min(140, Math.max(40, Math.max(0, ...namen.map((n) => n.length)) * 6 + 23)) : 8;
        return {
            type: 'line', data: { labels: d.labels, datasets },
            options: {
                ...basis(), layout: { padding: { right: padRechts } },
                // Achse nie schräg: wird es eng, kürzt «1. Semester» zu «1. Sem.»
                scales: { y: notenAchse(), x: { grid: { display: false }, border: { display: false },
                    ticks: { maxRotation: 0, autoSkip: true, callback(v) {
                        const l = String(this.getLabelForValue(v));
                        const platz = (this.chart.chartArea?.width ?? this.chart.width) / Math.max(1, this.chart.data.labels.length);
                        return platz < 84 ? l.replace(/\p{L}{6,}/u, (w) => `${w.slice(0, 3)}.`) : l;
                    } } } },
                plugins: { ...basis().plugins, npDirektlabel: { aktiv: direkt },
                    legend: { ...basis().plugins.legend, display: !direkt && d.serien.length > 1 && o.legende !== false } },
            },
        };
    },

    // { punkte: [{ x, wert }], zielwert, note } – Endnote, wenn alle offenen Prüfungen dieselbe Note x bekommen; das Band
    // zeigt den Spielraum zwischen schlechtester (Note 1) und bester Endnote (Note 6). Scrub über die ganze Fläche.
    kurve(d) {
        const werte = d.punkte.map((p) => p.wert).filter((w) => w !== null && w !== undefined && Number.isFinite(w));
        const schlecht = werte.length ? Math.min(...werte) : null;
        const beste = werte.length ? Math.max(...werte) : null;
        const waagrecht = (wert) => [{ x: 1, y: wert }, { x: 6, y: wert }];
        const hilfslinie = { type: 'line', borderWidth: 0, pointRadius: 0, pointHoverRadius: 0, fill: false, order: 3 };
        const datasets = [{
            label: t('Ergebnis'), data: d.punkte.map((p) => ({ x: p.x, y: p.wert })),
            borderColor: tokenFarbe('--accent'), backgroundColor: 'transparent', fill: false, clip: false, order: 1,
            borderWidth: 2.5, pointRadius: d.punkte.map((p) => (d.note !== null && d.note !== undefined && Math.abs(p.x - d.note) < 0.13 ? 6 : 0)),
            pointBackgroundColor: tokenFarbe('--accent'),
        }, {
            type: 'line', label: '_ziel', data: waagrecht(d.zielwert),
            borderColor: tokenFarbe('--text', 0.5), borderDash: [6, 4], borderWidth: 1.5, pointRadius: 0, fill: false, order: 2,
            npSchwelleLabel: t('Ziel :wert', { wert: format(d.zielwert, 1) }),
        }];
        if (werte.length && beste - schlecht > 0.005) {
            datasets.push(
                { ...hilfslinie, label: '_schlechteste', data: waagrecht(schlecht), npSchwelleLabel: t('schlechteste Endnote :wert', { wert: format(schlecht, 1) }) },
                { ...hilfslinie, label: '_beste', data: waagrecht(beste), fill: '-1', backgroundColor: tokenFarbe('--accent', 0.12), npSchwelleLabel: t('beste Endnote :wert', { wert: format(beste, 1) }) },
            );
        }
        return {
            type: 'line', data: { datasets },
            options: {
                ...basis(), layout: { padding: { left: 2, right: 10, top: 6 } },
                plugins: { ...basis().plugins, legend: { display: false }, npScrub: { aktiv: true },
                    tooltip: { ...basis().plugins.tooltip, external: null } },
                scales: { y: notenAchse(), x: { type: 'linear', min: 1, max: 6, grid: { display: false }, border: { display: false }, ticks: { stepSize: 1, padding: 6 } } },
            },
        };
    },

    // { labels, werte, grenzen } – horizontale Balken ab Note 1, schwächste zuerst; Balken neutral,
    // nur knapp/ungenügend in Notenfarbe, senkrechte Genügend-Linie, Wert am Balkenende
    balken(d) {
        const g = d.grenzen ?? {};
        const zeilen = d.labels.map((label, i) => ({ label, wert: d.werte[i] ?? null }))
            .sort((a, b) => (a.wert ?? Infinity) - (b.wert ?? Infinity));
        const farbe = (v) => (['knapp', 'ungenuegend'].includes(stufe(v, g)) ? notenFarbe(v, g, 0.9) : neutralFarbe());
        return {
            type: 'bar',
            data: { labels: zeilen.map((z) => z.label), datasets: [{ label: t('Note'), data: zeilen.map((z) => (z.wert === null ? null : [1, z.wert])),
                backgroundColor: zeilen.map((z) => farbe(z.wert)), borderRadius: 8, borderSkipped: false, barThickness: 16 }] },
            options: {
                ...basis(), indexAxis: 'y', layout: { padding: { right: 36, bottom: g.genuegend ? 18 : 0 } },
                plugins: { ...basis().plugins, legend: { display: false },
                    tooltip: { ...basis().plugins.tooltip, callbacks: { label: (c) => ` ${format(c.raw?.[1], 2)}` } },
                    npBalkenwert: { aktiv: true },
                    npSenkrecht: { wert: g.genuegend ?? null, text: g.genuegend ? t('genügend :wert', { wert: format(g.genuegend, 1) }) : null } },
                scales: { x: notenAchse({ position: 'top' }), y: { grid: { display: false }, border: { display: false },
                    // Namensspalte höchstens 36 % der Diagrammbreite (die Balken behalten über die Hälfte); was darin nicht
                    // ganz Platz hat, wird nach gemessener Textbreite gekürzt (voller Name im Tooltip)
                    afterFit(achse) { achse.width = Math.min(achse.width, achse.chart.width * ACHSENANTEIL + 8); },
                    ticks: { callback(v) { return kuerzeNachBreite(this.chart, String(this.getLabelForValue(v)), this.chart.width * ACHSENANTEIL); } } } },
            },
        };
    },

    // { labels, werte, farben?: [notenwert je Balken] } – vertikale Säulen (Anzahlen)
    saeulen(d) {
        return {
            type: 'bar',
            data: { labels: d.labels, datasets: [{ label: d.name ?? t('Anzahl'), data: d.werte,
                backgroundColor: d.farben ? d.farben.map((v) => notenFarbe(v, d.grenzen, 0.75)) : serie(0, 0.7), borderRadius: 6 }] },
            options: {
                ...basis(),
                plugins: { ...basis().plugins, legend: { display: false },
                    tooltip: { ...basis().plugins.tooltip, callbacks: { label: (c) => ` ${c.parsed.y}` } } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: tokenFarbe('--text', 0.07), drawTicks: false }, border: { display: false } },
                    // nur jede 4. Beschriftung (z. B. KW), nie schräg
                    x: { grid: { display: false }, border: { display: false },
                        // von rechts (aktuellste Woche) her jede 4. beschriften, nie schräg
                        ticks: { maxRotation: 0, minRotation: 0, autoSkip: false,
                            callback(v, i) { return (this.chart.data.labels.length - 1 - i) % 4 === 0 ? this.getLabelForValue(v) : ''; } } } },
            },
        };
    },

    // { labels, werte, grenzen, name } – Histogramm in 0.5-Klassen (Label = Untergrenze): knapp und ungenügend
    // in Notenfarbe, Rest neutral – dieselbe Regel wie die Balken und die Legende (x-noten-legende)
    histogramm(d) {
        const g = d.grenzen ?? undefined;
        return {
            type: 'bar',
            data: { labels: d.labels, datasets: [{ label: d.name ?? t('Anzahl'), data: d.werte,
                backgroundColor: d.labels.map((l) => (['knapp', 'ungenuegend'].includes(stufe(parseFloat(l), g)) ? notenFarbe(parseFloat(l), g, 0.85) : tokenFarbe('--chart-1', 0.85))),
                borderRadius: 6 }] },
            options: {
                ...basis(),
                plugins: { ...basis().plugins, legend: { display: false },
                    tooltip: { ...basis().plugins.tooltip, callbacks: { label: (c) => ` ${c.parsed.y}` } } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: tokenFarbe('--text', 0.07), drawTicks: false }, border: { display: false } },
                    x: { grid: { display: false }, border: { display: false } } },
            },
        };
    },
};

const TASTEN = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 };
const kopie = (x) => JSON.parse(JSON.stringify(x));

export function registriereCharts(Alpine) {
    // <div x-data="npChart('verlauf', daten)"><canvas x-ref="canvas"></canvas></div> (x-diagramm mit typ legt das an)
    // Reaktiv: x-effect="zeichne(neueDaten)" oder setze(neueDaten) – gleicher Typ und gleiche Zahl Datensätze werden
    // an Ort aktualisiert (Punkte wandern), nur sonst und beim Themewechsel wird neu gebaut.
    // Tastatur: Pfeile, Home und End auf dem fokussierten Element führen durch die Punkte, aria-live sagt den Wert an.
    Alpine.data('npChart', (typ, daten = null, optionen = {}) => {
        let chart = null;
        let beobachter = null;
        let aktuell = daten;
        let index = null;
        let host = null;
        let ansage = null;
        let tasteHoerer = null;
        let blurHoerer = null;
        return {
            init() {
                host = this.$el;
                this.huelleEinrichten();
                if (aktuell) this.zeichne(aktuell);
                // Themewechsel: Farben sind im Chart festgeschrieben, deshalb neu bauen
                beobachter = new MutationObserver(() => aktuell && this.baue(aktuell));
                beobachter.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme', 'data-diagramm'] });
                // Beschriftungen werden in der Schrift gemessen, die beim ersten Zeichnen gilt: erst nach dem Laden der Schrift
                // neu messen (ohne Animation), sonst kürzt eine breitere Ersatzschrift Namen, die in Inter ganz hinpassen
                document.fonts?.ready.then(() => chart?.update('none'));
            },

            // Fokussierbare Hülle mit Namen und Ansage; fehlt etwas (Aufrufer ohne x-diagramm), wird es ergänzt
            huelleEinrichten() {
                if (!host.hasAttribute('tabindex')) host.setAttribute('tabindex', '0');
                if (!host.hasAttribute('role')) host.setAttribute('role', 'group');
                const name = this.$refs.canvas?.getAttribute('aria-label');
                if (name && !host.hasAttribute('aria-label')) host.setAttribute('aria-label', name);
                ansage = host.querySelector('.np-diagramm-ansage');
                if (!ansage) {
                    ansage = document.createElement('p');
                    ansage.className = 'np-diagramm-ansage sr-only';
                    ansage.setAttribute('aria-live', 'polite');
                    host.append(ansage);
                }
                tasteHoerer = (e) => this.taste(e);
                blurHoerer = () => this.loeschen();
                host.addEventListener('keydown', tasteHoerer);
                host.addEventListener('blur', blurHoerer);
            },

            zeichne(neu) {
                this.setze(neu);
            },

            baue(neu) {
                aktuell = kopie(neu);
                chart?.destroy();
                index = null;
                chart = new Chart(this.$refs.canvas, BAUER[typ](aktuell, optionen));
            },

            setze(neu) {
                if (!neu) return;
                const kopierte = kopie(neu);
                const frisch = BAUER[typ](kopierte, optionen);
                if (!chart || chart.config.type !== frisch.type || chart.data.datasets.length !== frisch.data.datasets.length) {
                    this.baue(neu);
                    return;
                }
                aktuell = kopierte;
                const note = chart.$npScrub?.note ?? null;
                if (!chart.options.plugins?.npScrub?.aktiv) this.loeschen(false);
                chart.options = frisch.options;
                chart.data.labels = frisch.data.labels;
                frisch.data.datasets.forEach((ds, i) => Object.assign(chart.data.datasets[i], ds));
                chart.update(bewegungRuhig() ? 'none' : undefined);
                if (note !== null) {
                    scrubSetze(chart, note);
                    chart.draw();
                }
            },

            // Punkte der Tastaturposition i: je sichtbarer Reihe mit Wert ein aktives Element
            aktive(i) {
                return chart.data.datasets
                    .map((ds, datasetIndex) => ({ ds, datasetIndex }))
                    .filter(({ ds, datasetIndex }) => !ds.label?.startsWith('_') && chart.isDatasetVisible(datasetIndex)
                        && ds.data[i] !== null && ds.data[i] !== undefined && chart.getDatasetMeta(datasetIndex).data[i])
                    .map(({ datasetIndex }) => ({ datasetIndex, index: i }));
            },

            suche(start, richtung) {
                const n = chart.data.labels?.length ?? 0;
                for (let i = start; i >= 0 && i < n; i += richtung) if (this.aktive(i).length) return i;
                return null;
            },

            taste(e) {
                if (!chart || e.target !== host || e.altKey || e.ctrlKey || e.metaKey) return;
                const schritt = TASTEN[e.key];
                if (schritt === undefined && e.key !== 'Home' && e.key !== 'End') return;
                e.preventDefault();
                if (chart.options.plugins?.npScrub?.aktiv) {
                    this.tasteKurve(e.key, schritt);
                    return;
                }
                let ziel;
                if (e.key === 'Home') ziel = this.suche(0, 1);
                else if (e.key === 'End') ziel = this.suche((chart.data.labels?.length ?? 0) - 1, -1);
                else if (index === null) ziel = schritt > 0 ? this.suche(0, 1) : this.suche((chart.data.labels?.length ?? 0) - 1, -1);
                else ziel = this.suche(index + schritt, schritt) ?? index;
                if (ziel !== null) this.markiere(ziel);
            },

            markiere(i) {
                index = i;
                const aktiv = this.aktive(i);
                const pos = aktiv.map(({ datasetIndex }) => chart.getDatasetMeta(datasetIndex).data[i].getProps(['x', 'y'], true));
                const mitte = { x: pos.reduce((a, p) => a + p.x, 0) / pos.length, y: pos.reduce((a, p) => a + p.y, 0) / pos.length };
                chart.setActiveElements(aktiv);
                chart.tooltip.setActiveElements(aktiv, mitte);
                chart.update('none');
                ansage.textContent = chart.$npTipp ?? '';
            },

            // Kurve (Rechner): ←/→ in Viertelnoten, Home/End an die Enden der Achse
            tasteKurve(key, schritt) {
                const o = (chart.$npScrub ??= {});
                let note = o.note;
                if (key === 'Home') note = 1;
                else if (key === 'End') note = 6;
                else if (note === null || note === undefined) note = schritt > 0 ? 1 : 6;
                else if (schritt > 0) note = Math.min(6, (Math.floor(note / 0.25 + 1e-9) + 1) * 0.25);
                else note = Math.max(1, (Math.ceil(note / 0.25 - 1e-9) - 1) * 0.25);
                scrubSetze(chart, note);
                chart.draw();
                ansage.textContent = o.text ?? '';
            },

            loeschen(neuzeichnen = true) {
                if (!chart) return;
                index = null;
                if (chart.options.plugins?.npScrub?.aktiv) {
                    if (chart.$npScrub?.note != null) {
                        scrubSetze(chart, null);
                        if (neuzeichnen) chart.draw();
                    }
                    return;
                }
                if (chart.getActiveElements().length || chart.tooltip?.getActiveElements().length) {
                    chart.setActiveElements([]);
                    chart.tooltip.setActiveElements([], { x: 0, y: 0 });
                    if (neuzeichnen) chart.update('none');
                }
            },

            destroy() {
                host?.removeEventListener('keydown', tasteHoerer);
                host?.removeEventListener('blur', blurHoerer);
                chart?.destroy();
                beobachter?.disconnect();
            },
        };
    });
}
