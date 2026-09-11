// Gemeinsame Helfer: JSON-POST mit CSRF, Notenfarben nach den Grenzen aus den Einstellungen, Formatierung.

// Übersetzung: Schlüssel ist der deutsche Text, window.npI18n (Layout, nur ausserhalb von Deutsch) liefert die Übersetzung.
// Platzhalter wie in Laravel: t('Ziel :wert', { wert: '4.5' }). Neue Schlüssel in App\Support\JsTexte und lang/en.json.
export function t(schluessel, ersetzungen = {}) {
    const text = window.npI18n?.[schluessel] ?? schluessel;
    return Object.entries(ersetzungen).reduce((s, [name, wert]) => s.replaceAll(`:${name}`, String(wert)), text);
}

export async function postJson(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify(body),
    });
    const daten = await res.json().catch(() => null);
    if (!res.ok) {
        const meldung = daten?.errors ? Object.values(daten.errors).flat()[0] : null;
        throw new Error(meldung || t('Berechnung fehlgeschlagen.'));
    }
    return daten;
}

const STANDARD_GRENZEN = { gut: 5.0, genuegend: 4.0, kritisch: 3.5 };

export function stufe(wert, grenzen = STANDARD_GRENZEN) {
    const v = parseFloat(wert);
    if (!Number.isFinite(v)) return null;
    if (v >= grenzen.gut - 1e-9) return 'gut';
    if (v >= grenzen.genuegend - 1e-9) return 'genuegend';
    if (v >= grenzen.kritisch - 1e-9) return 'knapp';
    return 'ungenuegend';
}

const TEXT = {
    gut: 'text-note-gut',
    genuegend: 'text-note-genuegend',
    knapp: 'text-note-knapp',
    ungenuegend: 'text-note-ungenuegend',
};

export function notenKlasse(wert, grenzen) {
    return TEXT[stufe(wert, grenzen)] ?? 'text-muted';
}

// Benötigte Note nach Schwierigkeit einfärben (hoch = schwer)
export function bedarfKlasse(wert, grenzen = STANDARD_GRENZEN) {
    const v = parseFloat(wert);
    if (!Number.isFinite(v)) return 'text-muted';
    if (v <= grenzen.genuegend + 0.5) return TEXT.gut;
    if (v <= grenzen.gut + 0.25) return TEXT.knapp;
    return TEXT.ungenuegend;
}

export function format(wert, stellen = null) {
    const v = parseFloat(wert);
    if (!Number.isFinite(v)) return '–';
    if (stellen !== null) return v.toFixed(stellen);
    const s = v.toFixed(2);
    return s.endsWith('0') ? s.slice(0, -1) : s;
}

export function tokenFarbe(name, alpha = 1) {
    const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v ? `rgb(${v} / ${alpha})` : `rgb(128 128 128 / ${alpha})`;
}

export function notenFarbe(wert, grenzen, alpha = 1) {
    const s = stufe(wert, grenzen);
    return s ? tokenFarbe(`--note-${s}`, alpha) : tokenFarbe('--muted', alpha);
}

// Scrollbare Tabellen (overflow-x-auto) in den Tab-Weg holen und benennen (axe scrollable-region-focusable).
// Name: <caption>, sonst nächstliegende Überschrift (h1–h3) in Karte oder Seitenkopf, sonst Fallback.
export function registriereScrollbereiche() {
    document.querySelectorAll('.overflow-x-auto').forEach((el) => {
        if (el.hasAttribute('role') || !el.querySelector('table')) return;

        const caption = el.querySelector('table > caption')?.textContent.trim();
        let label = caption || null;
        for (let knoten = el; !label && knoten; knoten = knoten.parentElement) {
            for (let geschwister = knoten.previousElementSibling; !label && geschwister; geschwister = geschwister.previousElementSibling) {
                const h = geschwister.matches('h1,h2,h3') ? geschwister : geschwister.querySelector('h1,h2,h3');
                if (h?.textContent.trim()) label = h.textContent.trim();
            }
        }

        el.setAttribute('tabindex', '0');
        el.setAttribute('role', 'region');
        el.setAttribute('aria-label', label || t('Tabelle'));
    });
}

// Segmented Control (role="radiogroup"): Pfeiltasten links/rechts bewegen den Fokus und wählen
// (roving tabindex – nur ein [role="radio"] ist im Tab-Weg). Einsatz: <div role="radiogroup" x-radiogroup>.
export function registriereRadiogroup(Alpine) {
    Alpine.directive('radiogroup', (el, directive, { cleanup }) => {
        const radios = () => Array.from(el.querySelectorAll('[role="radio"]'))
            .filter((r) => !r.disabled && r.getAttribute('aria-disabled') !== 'true' && r.offsetParent !== null);

        const rovingTabindex = () => {
            const liste = radios();
            const aktiv = liste.find((r) => r.getAttribute('aria-checked') === 'true') ?? liste[0];
            liste.forEach((r) => r.setAttribute('tabindex', r === aktiv ? '0' : '-1'));
        };

        rovingTabindex();
        // aria-checked (Auswahl) und style/class (x-show-Sichtbarkeit) ändern sich reaktiv über Alpine
        const beobachter = new MutationObserver(rovingTabindex);
        beobachter.observe(el, { attributes: true, attributeFilter: ['aria-checked', 'style', 'class'], subtree: true });
        cleanup(() => beobachter.disconnect());

        el.addEventListener('keydown', (ev) => {
            if (ev.key !== 'ArrowLeft' && ev.key !== 'ArrowRight') return;
            const liste = radios();
            const i = liste.indexOf(document.activeElement);
            if (i === -1) return;
            ev.preventDefault();
            const naechster = liste[(i + (ev.key === 'ArrowRight' ? 1 : -1) + liste.length) % liste.length];
            naechster.focus();
            naechster.click();
        });
    });
}
