// Diagramme: Chart.js mit Farben aus den Design-Tokens. Notenachsen immer 1–6, Genügend-Grenze als beschriftete Haarlinie.
import {
    BarController, BarElement, CategoryScale, Chart, Filler, Legend, LinearScale,
    LineController, LineElement, PointElement, Tooltip,
} from 'chart.js';
import { format, notenFarbe, stufe, t, tokenFarbe } from './np';

Chart.register(BarController, BarElement, CategoryScale, Filler, Legend, LinearScale, LineController, LineElement, PointElement, Tooltip);

const serie = (i, alpha = 1) => tokenFarbe(`--chart-${(i % 6) + 1}`, alpha);
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

// Bewegung reduziert: persönliche Einstellung (data-bewegung) oder Systemeinstellung
function bewegungReduziert() {
    return document.documentElement.dataset.bewegung === 'reduziert'
        || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
}

function basis() {
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.color = tokenFarbe('--muted');
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: bewegungReduziert() ? false : { duration: 350 },
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { labels: { usePointStyle: true, boxWidth: 8, filter: (item) => !item.text.startsWith('_') } },
            tooltip: {
                backgroundColor: tokenFarbe('--card', 0.96),
                titleColor: tokenFarbe('--text'),
                bodyColor: tokenFarbe('--text'),
                borderColor: tokenFarbe('--border'),
                borderWidth: 1,
                padding: { x: 12, y: 10 },
                cornerRadius: 12,
                caretSize: 0,
                usePointStyle: true,
                boxPadding: 4,
                titleFont: { weight: '600' },
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
            const breite = ctx.measureText(ds.label).width;
            // Immer rechts neben dem Plotbereich verankert (nie auf der Linie), Höhe nach dem letzten Punkt der Reihe
            const x = Math.min(chartArea.right + 6, chart.width - breite - 2);
            labels.push({ text: ds.label, farbe: ds.borderColor, x, breite, y: letzter.y });
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
            ctx.fillStyle = l.farbe;
            ctx.fillText(l.text, l.x, l.y);
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

Chart.register(direktlabelPlugin, schwellenLabelPlugin, balkenwertPlugin, senkrechtPlugin);

const BAUER = {
    // { labels: [..], serien: [{ name, werte: [..], dick? }], grenze } – eine Serie mit dick:true wird hervorgehoben, Rest gedämpft
    verlauf(d, o = {}) {
        const hervorgehoben = d.serien.some((s) => s.dick);
        const datasets = d.serien.map((s, i) => {
            const gedaempft = hervorgehoben && !s.dick;
            const token = s.farbe ?? serienToken(i);
            const farbe = gedaempft ? tokenFarbe('--muted', 0.5) : tokenFarbe(token);
            // Fläche nur unter der hervorgehobenen bzw. einzigen Linie, sonst überlagern sich die Verläufe
            const flaeche = !gedaempft && (s.dick || d.serien.length === 1);
            return {
                label: s.name, data: s.werte, spanGaps: true, tension: 0.35, cubicInterpolationMode: 'monotone',
                borderColor: farbe, backgroundColor: flaeche ? verlaufFlaeche(token) : 'transparent',
                borderWidth: s.dick ? 3 : (gedaempft ? 1.25 : 2), borderCapStyle: 'round', borderJoinStyle: 'round',
                pointRadius: gedaempft ? 0 : 3.5, pointHoverRadius: gedaempft ? 3 : 5.5,
                pointBackgroundColor: farbe, pointBorderColor: tokenFarbe('--card'), pointBorderWidth: 1.5, pointHoverBorderWidth: 2,
                fill: flaeche ? 'start' : false, order: s.dick ? 1 : 2,
            };
        });
        if (d.grenze) datasets.push(grenzLinie(d.labels.length, d.grenze, t('genügend :wert', { wert: format(d.grenze, 1) })));
        const direkt = o.direktlabels !== false && d.serien.length > 1;
        const namen = d.serien.filter((s) => !s.name?.startsWith('_')).map((s) => s.name ?? '');
        const padRechts = direkt ? Math.min(140, Math.max(40, Math.max(0, ...namen.map((n) => n.length)) * 6 + 14)) : 8;
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

    // { punkte: [{ x, wert }], zielwert, note }
    kurve(d) {
        const labels = d.punkte.map((p) => format(p.x, 2));
        const datasets = [{
            label: t('Ergebnis'), data: d.punkte.map((p) => p.wert), stepped: true,
            borderColor: tokenFarbe('--accent'), backgroundColor: tokenFarbe('--accent', 0.1), fill: 'origin',
            borderWidth: 2.5, pointRadius: d.punkte.map((p) => (d.note !== null && Math.abs(p.x - d.note) < 0.13 ? 6 : 0)),
            pointBackgroundColor: tokenFarbe('--accent'),
        }, {
            type: 'line', label: '_ziel', data: Array(labels.length).fill(d.zielwert),
            borderColor: tokenFarbe('--text', 0.5), borderDash: [6, 4], borderWidth: 1.5, pointRadius: 0, fill: false,
            npSchwelleLabel: t('Ziel :wert', { wert: format(d.zielwert, 1) }),
        }];
        return {
            type: 'line', data: { labels, datasets },
            options: {
                ...basis(),
                plugins: { ...basis().plugins, legend: { display: false },
                    tooltip: { ...basis().plugins.tooltip, callbacks: { title: (i) => t('Note in offenen Prüfungen: :wert', { wert: i[0].label }), label: (c) => ` ${t('Ergebnis: :wert', { wert: format(c.parsed.y, 2) })}` } } },
                scales: { y: notenAchse(), x: { grid: { display: false }, border: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 11 } } },
            },
        };
    },

    // { labels, werte, grenzen } – horizontale Balken ab Note 1, schwächste zuerst; Balken neutral,
    // nur knapp/ungenügend in Notenfarbe, senkrechte Genügend-Linie, Wert am Balkenende
    balken(d) {
        const g = d.grenzen ?? {};
        const zeilen = d.labels.map((label, i) => ({ label, wert: d.werte[i] ?? null }))
            .sort((a, b) => (a.wert ?? Infinity) - (b.wert ?? Infinity));
        const farbe = (v) => (['knapp', 'ungenuegend'].includes(stufe(v, g)) ? notenFarbe(v, g, 0.9) : tokenFarbe('--chart-1', 0.9));
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
                    // lange Fach-/Modulnamen kürzen (voller Name im Tooltip), sonst schneidet die Achse mobil ab
                    ticks: { callback(v) { const l = String(this.getLabelForValue(v)); const max = this.chart.width < 520 ? 16 : 32; return l.length > max ? `${l.slice(0, max - 1)}…` : l; } } } },
            },
        };
    },

    // { labels, serien: [{ name, werte }] } – gruppierte Notensäulen ab Note 1
    gruppen(d) {
        return {
            type: 'bar',
            data: { labels: d.labels, datasets: d.serien.map((s, i) => ({
                label: s.name, data: s.werte.map((v) => (v === null ? null : [1, v])),
                backgroundColor: serie(i, 0.75), borderRadius: 6, borderSkipped: false, maxBarThickness: 28,
            })) },
            options: {
                ...basis(),
                plugins: { ...basis().plugins,
                    tooltip: { ...basis().plugins.tooltip, callbacks: { label: (c) => ` ${c.dataset.label}: ${format(c.raw?.[1], 2)}` } } },
                scales: { y: notenAchse(), x: { grid: { display: false }, border: { display: false } } },
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

    // { werte, grenzen } – Miniaturverlauf ohne Achsen, Skala fix 1–6 (für Small Multiples mit gleicher Skala)
    spark(d) {
        const werte = d.werte ?? [];
        const vorhanden = werte.map((v, i) => [i, v]).filter(([, v]) => v !== null && v !== undefined);
        const letzterIndex = vorhanden.length ? vorhanden[vorhanden.length - 1][0] : -1;
        const letzterWert = vorhanden.length ? vorhanden[vorhanden.length - 1][1] : null;
        const farbe = notenFarbe(letzterWert, d.grenzen);
        return {
            type: 'line',
            data: { labels: werte.map((_, i) => i), datasets: [{
                data: werte, spanGaps: true, tension: 0.3, borderWidth: 2,
                borderColor: farbe, backgroundColor: notenFarbe(letzterWert, d.grenzen, 0.12), fill: 'origin',
                pointRadius: (c) => (c.dataIndex === letzterIndex ? 3 : 0), pointBackgroundColor: farbe,
            }] },
            options: {
                responsive: true, maintainAspectRatio: false, animation: { duration: 0 },
                interaction: { intersect: false },
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                scales: { y: { min: 1, max: 6, display: false }, x: { display: false } },
            },
        };
    },
};

export function registriereCharts(Alpine) {
    // <div x-data="npChart('verlauf', daten)"><canvas x-ref="canvas"></canvas></div>
    // Reaktiv: x-effect="zeichne(neueDaten)"
    Alpine.data('npChart', (typ, daten = null, optionen = {}) => {
        let chart = null;
        let beobachter = null;
        let aktuell = daten;
        return {
            init() {
                if (aktuell) this.zeichne(aktuell);
                beobachter = new MutationObserver(() => aktuell && this.zeichne(aktuell));
                beobachter.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme', 'data-diagramm'] });
            },
            zeichne(neu) {
                if (!neu) return;
                aktuell = JSON.parse(JSON.stringify(neu));
                chart?.destroy();
                chart = new Chart(this.$refs.canvas, BAUER[typ](aktuell, optionen));
            },
            destroy() {
                chart?.destroy();
                beobachter?.disconnect();
            },
        };
    });
}
