// Feedback-Dialog: sammelt automatisch Kontext (Seite, Zeitpunkt, letzte JS-Fehler) und erstellt
// optional einen verkleinerten Screenshot des Seiteninhalts (modern-screenshot, über Vite gebündelt,
// kein CDN). Rolle und Browser/Betriebssystem ermittelt der Server selbst aus Session und User-Agent.

import { t } from './np';

const MAX_FEHLER = 3;
const MAX_BREITE = 1600;
const MAX_BYTES = 1024 * 1024; // < 1 MB, Server erlaubt etwas Spielraum darüber

window.__npFeedbackFehler = window.__npFeedbackFehler || [];

function merkeFehler(text) {
    window.__npFeedbackFehler.push(String(text).slice(0, 300));
    if (window.__npFeedbackFehler.length > MAX_FEHLER) {
        window.__npFeedbackFehler.shift();
    }
}

window.addEventListener('error', (e) => merkeFehler(`${e.message ?? 'Fehler'} (${e.filename ?? '?'}:${e.lineno ?? '?'})`));
window.addEventListener('unhandledrejection', (e) => merkeFehler('Promise: ' + (e.reason?.message ?? e.reason ?? 'unbekannt')));

/**
 * Screenshot des Hauptinhalts (nicht Navigation/Modal, die liegen ausserhalb von <main>).
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
    };

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
    }
}

export function registriereFeedback(Alpine) {
    Alpine.data('feedbackDialog', (cfg) => ({
        kategorie: 'idee',
        text: '',
        mitScreenshot: true,
        loading: false,
        ladeText: t('Senden'),
        error: '',

        reset() {
            this.kategorie = 'idee';
            this.text = '';
            this.mitScreenshot = true;
            this.loading = false;
            this.ladeText = t('Senden');
            this.error = '';
        },

        async senden() {
            if (this.loading || this.text.trim().length < 3) return;
            this.error = '';
            this.loading = true;
            this.ladeText = t('Senden…');

            try {
                const formular = new FormData();
                formular.append('kategorie', this.kategorie);
                formular.append('text', this.text);
                formular.append('route_name', cfg.routeName ?? '');
                formular.append('url', cfg.pfad ?? '');
                formular.append('viewport', window.innerWidth + 'x' + window.innerHeight);
                formular.append('js_fehler', JSON.stringify(window.__npFeedbackFehler ?? []));

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
                    window.dispatchEvent(new CustomEvent('close-modal', { detail: 'feedback' }));
                    window.dispatchEvent(new CustomEvent('np-toast', { detail: { message: t('Danke, deine Meldung ist eingegangen.') } }));
                    this.reset();
                } else if (res.status === 422) {
                    const daten = await res.json();
                    this.error = Object.values(daten.errors ?? {}).flat().join(' ') || t('Bitte Eingaben prüfen.');
                } else if (res.status === 429) {
                    this.error = t('Gerade viele Meldungen unterwegs – bitte kurz warten.');
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
