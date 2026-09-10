// Diagramme: Chart.js mit Farben aus den Design-Tokens. Notenachsen immer 1–6, Genügend-Linie gestrichelt.
import {
    BarController, BarElement, CategoryScale, Chart, Filler, Legend, LinearScale,
    LineController, LineElement, PointElement, Tooltip,
} from 'chart.js';
import { format, notenFarbe, tokenFarbe } from './np';

Chart.register(BarController, BarElement, CategoryScale, Filler, Legend, LinearScale, LineController, LineElement, PointElement, Tooltip);

const serie = (i, alpha = 1) => tokenFarbe(`--chart-${(i % 6) + 1}`, alpha);

function basis() {
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.color = tokenFarbe('--muted');
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 350 },
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { labels: { usePointStyle: true, boxWidth: 8, filter: (item) => !item.text.startsWith('_') } },
            tooltip: {
                backgroundColor: tokenFarbe('--card', 0.96),
                titleColor: tokenFarbe('--text'),
                bodyColor: tokenFarbe('--text'),
                borderColor: tokenFarbe('--border'),
                borderWidth: 1,
                padding: 10,
                cornerRadius: 10,
                filter: (item) => !item.dataset.label?.startsWith('_'),
                callbacks: { label: (c) => ` ${c.dataset.label}: ${format(c.parsed.y ?? c.parsed.x, 2)}` },
            },
        },
    };
}

const notenAchse = (extra = {}) => ({
    min: 1, max: 6,
    ticks: { stepSize: 1 },
    grid: { color: tokenFarbe('--border', 0.7) },
    border: { display: false },
    ...extra,
});

const grenzLinie = (anzahl, wert, label = '_grenze') => ({
    type: 'line', label, data: Array(anzahl).fill(wert),
    borderColor: tokenFarbe('--note-ungenuegend', 0.55), borderDash: [5, 5], borderWidth: 1.5,
    pointRadius: 0, pointHoverRadius: 0, fill: false, order: 99,
});

const BAUER = {
    // { labels: [..], serien: [{ name, werte: [..] }], grenze }
    verlauf(d, o) {
        const datasets = d.serien.map((s, i) => ({
            label: s.name, data: s.werte, spanGaps: true, tension: 0.3,
            borderColor: s.farbe ? tokenFarbe(s.farbe) : serie(i), backgroundColor: s.farbe ? tokenFarbe(s.farbe, 0.12) : serie(i, 0.12),
            borderWidth: s.dick ? 3 : 2, pointRadius: 3.5, pointHoverRadius: 6, fill: d.serien.length === 1 ? 'origin' : false,
        }));
        if (d.grenze) datasets.push(grenzLinie(d.labels.length, d.grenze));
        return {
            type: 'line', data: { labels: d.labels, datasets },
            options: { ...basis(), scales: { y: notenAchse(), x: { grid: { display: false }, border: { display: false } } },
                plugins: { ...basis().plugins, legend: { ...basis().plugins.legend, display: d.serien.length > 1 && o.legende !== false } } },
        };
    },

    // { punkte: [{ x, wert }], zielwert, note }
    kurve(d) {
        const labels = d.punkte.map((p) => format(p.x, 2));
        const datasets = [{
            label: 'Ergebnis', data: d.punkte.map((p) => p.wert), stepped: true,
            borderColor: tokenFarbe('--accent'), backgroundColor: tokenFarbe('--accent', 0.1), fill: 'origin',
            borderWidth: 2.5, pointRadius: d.punkte.map((p) => (d.note !== null && Math.abs(p.x - d.note) < 0.13 ? 6 : 0)),
            pointBackgroundColor: tokenFarbe('--accent'),
        }, {
            type: 'line', label: '_ziel', data: Array(labels.length).fill(d.zielwert),
            borderColor: tokenFarbe('--text', 0.5), borderDash: [6, 4], borderWidth: 1.5, pointRadius: 0, fill: false,
        }];
        return {
            type: 'line', data: { labels, datasets },
            options: {
                ...basis(),
                plugins: { ...basis().plugins, legend: { display: false },
                    tooltip: { ...basis().plugins.tooltip, callbacks: { title: (i) => `Note in offenen Prüfungen: ${i[0].label}`, label: (c) => ` Ergebnis: ${format(c.parsed.y, 2)}` } } },
                scales: { y: notenAchse(), x: { grid: { display: false }, border: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 11 } } },
            },
        };
    },

    // { labels, werte, grenze, grenzen } – horizontale Balken ab Note 1
    balken(d) {
        return {
            type: 'bar',
            data: { labels: d.labels, datasets: [{ label: 'Note', data: d.werte.map((v) => (v === null ? null : [1, v])),
                backgroundColor: d.werte.map((v) => notenFarbe(v, d.grenzen, 0.75)), borderRadius: 6, borderSkipped: false, barThickness: 14 }] },
            options: {
                ...basis(), indexAxis: 'y',
                plugins: { ...basis().plugins, legend: { display: false },
                    tooltip: { ...basis().plugins.tooltip, callbacks: { label: (c) => ` ${format(c.raw?.[1], 2)}` } } },
                scales: { x: notenAchse({ position: 'top' }), y: { grid: { display: false }, border: { display: false } } },
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
            data: { labels: d.labels, datasets: [{ label: d.name ?? 'Anzahl', data: d.werte,
                backgroundColor: d.farben ? d.farben.map((v) => notenFarbe(v, d.grenzen, 0.75)) : serie(0, 0.7), borderRadius: 6 }] },
            options: {
                ...basis(),
                plugins: { ...basis().plugins, legend: { display: false },
                    tooltip: { ...basis().plugins.tooltip, callbacks: { label: (c) => ` ${c.parsed.y}` } } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: tokenFarbe('--border', 0.7) }, border: { display: false } },
                    x: { grid: { display: false }, border: { display: false } } },
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
                beobachter.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
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
