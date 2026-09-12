// Schwebender Feedback-Knopf (Block G): sammelt automatisch Kontext (Seite, Zeitpunkt, letzte JS-
// Fehler, letzte fehlgeschlagene Anfragen, Bildschirm/Sprache/Zeitzone/Darstellung) und erstellt
// optional einen verkleinerten Screenshot des Seiteninhalts (modern-screenshot, über Vite gebündelt,
// kein CDN). Rolle, Browser/Betriebssystem und die App-Version ermittelt der Server selbst.

import { t } from './np';

const MAX_FEHLER = 3;
const MAX_REQUESTS = 3;
const MAX_ANHAENGE = 3;
const MAX_BREITE = 1600;
const MAX_BYTES = 1024 * 1024; // < 1 MB, Server erlaubt etwas Spielraum darüber
// Grober Vorabcheck gegen post_max_size (public/.htaccess, aktuell 16 MB): schätzt Anhänge + Screenshot
// und bricht client-seitig ab, statt den Server erst mit 413 antworten zu lassen.
const MAX_GESAMT_BYTES = 14 * 1024 * 1024;

window.__npFeedbackFehler = window.__npFeedbackFehler || [];
window.__npFeedbackRequests = window.__npFeedbackRequests || [];

function merkeFehler(text) {
    window.__npFeedbackFehler.push(String(text).slice(0, 300));
    if (window.__npFeedbackFehler.length > MAX_FEHLER) {
        window.__npFeedbackFehler.shift();
    }
}

window.addEventListener('error', (e) => merkeFehler(`${e.message ?? 'Fehler'} (${e.filename ?? '?'}:${e.lineno ?? '?'})`));
window.addEventListener('unhandledrejection', (e) => merkeFehler('Promise: ' + (e.reason?.message ?? e.reason ?? 'unbekannt')));

function pfadVon(ziel) {
    try {
        const roh = typeof ziel === 'string' ? ziel : (ziel?.url ?? '');
        return new URL(roh, window.location.href).pathname.slice(0, 200);
    } catch {
        return String(ziel ?? '').slice(0, 200);
    }
}

function merkeRequest(ziel, status) {
    window.__npFeedbackRequests.push({ pfad: pfadVon(ziel), status: Number(status) || 0 });
    if (window.__npFeedbackRequests.length > MAX_REQUESTS) {
        window.__npFeedbackRequests.shift();
    }
}

// Nur einmal patchen (Vite-HMR/mehrfaches Einbinden würde sonst mehrfach umschliessen).
if (!window.__npFeedbackFetchGepatcht) {
    window.__npFeedbackFetchGepatcht = true;
    const urspruenglichesFetch = window.fetch.bind(window);
    window.fetch = async (...args) => {
        try {
            const antwort = await urspruenglichesFetch(...args);
            if (!antwort.ok) merkeRequest(args[0], antwort.status);

            return antwort;
        } catch (fehler) {
            merkeRequest(args[0], 0);
            throw fehler;
        }
    };
}

/** Nur unverfängliche Angaben – keine Formularinhalte, Passwörter oder Token. */
function ermittleTechnik() {
    return {
        bildschirm: `${window.screen?.width ?? 0}x${window.screen?.height ?? 0}`,
        pixelverhaeltnis: String(window.devicePixelRatio || 1),
        sprache: navigator.language || '',
        zeitzone: Intl.DateTimeFormat().resolvedOptions().timeZone || '',
        darstellung: document.documentElement.classList.contains('dark') ? 'dunkel' : 'hell',
        online: navigator.onLine,
    };
}

// Alpine-Attribute (@click, :class, x-on:…) sind keine gültigen XML-Namen: Die Kopie wird als SVG
// gerendert, das sich mit ihnen nicht dekodieren lässt – der Screenshot war dann nur Hintergrundfarbe.
function entferneAlpineAttribute(wurzel) {
    if (wurzel.nodeType !== 1) return;
    for (const el of [wurzel, ...wurzel.querySelectorAll('*')]) {
        for (const a of [...el.attributes]) {
            if (!/^[A-Za-z_][\w.-]*$/.test(a.name)) el.removeAttribute(a.name);
        }
    }
}

/**
 * Screenshot des Hauptinhalts (nicht Navigation/Panel, die liegen ausserhalb von <main>).
 * Passwortfelder werden vor der Aufnahme ausgeblendet. Verkleinert auf max. 1600 px Breite,
 * Qualität sinkt schrittweise, bis das Ergebnis unter 1 MB liegt (oder das Minimum erreicht ist).
 */
async function erstelleScreenshot() {
    let domToBlob;
    try {
        ({ domToBlob } = await import('modern-screenshot'));
    } catch {
        return null;
    }

    const wurzel = document.querySelector('main') ?? document.body;
    const skala = Math.min(1, MAX_BREITE / (wurzel.scrollWidth || MAX_BREITE));
    const optionen = {
        scale: skala > 0 ? skala : 1,
        quality: 0.85,
        type: 'image/jpeg',
        backgroundColor: getComputedStyle(document.body).backgroundColor || '#ffffff',
        filter: (node) => !(node instanceof HTMLInputElement && node.type === 'password'),
        onCloneNode: entferneAlpineAttribute,
    };

    // Einblend-Animationen (np-fade, opacity 0 → 1) starten in der Kopie neu bei Bild 0.
    const stopp = document.createElement('style');
    stopp.textContent = '*,*::before,*::after{animation:none!important;transition:none!important}';
    document.head.appendChild(stopp);

    try {
        let blob = await domToBlob(wurzel, optionen);
        let qualitaet = optionen.quality;
        while (blob && blob.size > MAX_BYTES && qualitaet > 0.35) {
            qualitaet -= 0.15;
            blob = await domToBlob(wurzel, { ...optionen, quality: qualitaet });
        }

        return blob;
    } catch {
        return null;
    } finally {
        stopp.remove();
    }
}

export function registriereFeedback(Alpine) {
    Alpine.data('feedbackDialog', (cfg) => ({
        open: false,
        kategorie: 'idee',
        text: '',
        mitScreenshot: true,
        mitTechnik: true,
        anhaenge: [],
        technik: ermittleTechnik(),
        loading: false,
        ladeText: t('Senden'),
        error: '',

        init() {
            window.addEventListener('open-modal', (e) => {
                if (e.detail === 'feedback') this.open = true;
            });
            window.addEventListener('close-modal', (e) => {
                if (e.detail === 'feedback') this.open = false;
            });
        },

        reset() {
            this.kategorie = 'idee';
            this.text = '';
            this.mitScreenshot = true;
            this.mitTechnik = true;
            this.anhaenge = [];
            this.loading = false;
            this.ladeText = t('Senden');
            this.error = '';
        },

        schliessen() {
            this.open = false;
            this.reset();
        },

        dateienWaehlen(e) {
            this.anhaenge = [...this.anhaenge, ...Array.from(e.target.files ?? [])].slice(0, MAX_ANHAENGE);
            e.target.value = '';
        },

        anhangEntfernen(index) {
            this.anhaenge = this.anhaenge.filter((_, i) => i !== index);
        },

        async senden() {
            if (this.loading || this.text.trim().length < 3) return;
            this.error = '';

            const geschaetzteGroesse = this.anhaenge.reduce((summe, d) => summe + d.size, 0) + (this.mitScreenshot ? MAX_BYTES : 0);
            if (geschaetzteGroesse > MAX_GESAMT_BYTES) {
                this.error = t('Die Anhänge sind zusammen zu gross. Bitte weniger oder kleinere Dateien wählen.');
                return;
            }

            this.loading = true;
            this.ladeText = t('Senden…');

            try {
                const formular = new FormData();
                formular.append('kategorie', this.kategorie);
                formular.append('text', this.text);
                formular.append('route_name', cfg.routeName ?? '');
                formular.append('url', cfg.pfad ?? '');

                if (this.mitTechnik) {
                    formular.append('viewport', window.innerWidth + 'x' + window.innerHeight);
                    formular.append('js_fehler', JSON.stringify(window.__npFeedbackFehler ?? []));
                    formular.append('technik', JSON.stringify({
                        ...this.technik,
                        online: navigator.onLine,
                        fehlgeschlagene_requests: window.__npFeedbackRequests ?? [],
                    }));
                }

                for (const datei of this.anhaenge) {
                    formular.append('anhaenge[]', datei);
                }

                if (this.mitScreenshot) {
                    this.ladeText = t('Screenshot wird erstellt…');
                    const blob = await erstelleScreenshot();
                    if (blob) formular.append('screenshot', blob, 'screenshot.jpg');
                    this.ladeText = t('Senden…');
                }

                const res = await fetch(cfg.url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        Accept: 'application/json',
                    },
                    body: formular,
                });

                if (res.status === 201) {
                    window.dispatchEvent(new CustomEvent('np-toast', { detail: { message: t('Danke, deine Meldung ist eingegangen.') } }));
                    this.schliessen();
                } else if (res.status === 422) {
                    const daten = await res.json();
                    this.error = Object.values(daten.errors ?? {}).flat().join(' ') || t('Bitte Eingaben prüfen.');
                } else if (res.status === 429) {
                    this.error = t('Gerade viele Meldungen unterwegs – bitte kurz warten.');
                } else if (res.status === 413) {
                    this.error = t('Die Anhänge sind zusammen zu gross. Bitte weniger oder kleinere Dateien wählen.');
                } else if (res.status === 419) {
                    this.error = t('Sitzung abgelaufen. Seite bitte neu laden.');
                } else {
                    this.error = t('Meldung konnte nicht gesendet werden.');
                }
            } catch {
                this.error = t('Meldung konnte nicht gesendet werden.');
            } finally {
                this.loading = false;
                this.ladeText = t('Senden');
            }
        },
    }));
}
