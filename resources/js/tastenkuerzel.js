// Globale Tastenkürzel (nur ausserhalb von Eingabefeldern, ohne Ctrl/Cmd/Alt):
// «?» öffnet die Übersicht, «g» dann ein zweiter Buchstabe (innerhalb 1 s) navigiert,
// «/» fokussiert die Suche (Befehlspalette), «n» erfasst eine neue Note (nur Lernende).
// Ziel-URLs kommen serverseitig aus App\Support\Navigation und werden per Alpine.data-Konfiguration
// aus resources/views/components/tastenkuerzel.blade.php übergeben – nie hier hartcodiert.

const CHORD_FENSTER_MS = 1000;

function istEingabefeld(el) {
    if (!el) return false;
    const tag = el.tagName;

    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable;
}

// Ein anderer Dialog (z. B. Feedback-Modal) ist offen – nicht gleichzeitig die Übersicht öffnen.
function andererDialogOffen(ausser) {
    return [...document.querySelectorAll('[role="dialog"][aria-modal="true"]')]
        .some((el) => el !== ausser && el.offsetParent !== null);
}

// Befehlspalette (Ctrl/Cmd+K, resources/js/suche.js) öffnen, ohne suche.js zu ändern: die Funktion
// hängt an der Alpine-Komponente npSuche, die wir über Alpine.$data direkt aufrufen.
function fokussiereSuche() {
    const el = document.querySelector('[x-data^="npSuche("]');
    const daten = el && window.Alpine ? window.Alpine.$data(el) : null;
    daten?.oeffnen?.();
}

export function registriereTastenkuerzel(Alpine) {
    Alpine.data('npTastenkuerzel', (cfg) => ({
        offen: false,
        letzterFokus: null,
        gGedruecktUm: 0,

        init() {
            window.addEventListener('keydown', (e) => this.taste(e));
            // Vom Benutzermenü aus (kein Tastendruck) – gleiches Muster wie open-modal.
            window.addEventListener('open-tastenkuerzel', () => this.oeffnen());
        },

        oeffnen() {
            if (andererDialogOffen(this.$refs.dialog)) return;
            this.gGedruecktUm = 0; // angefangenes «g» verfällt, sonst navigiert die nächste Taste nach dem Schliessen
            this.letzterFokus = document.activeElement;
            this.offen = true;
            this.$nextTick(() => this.$refs.schliessenKnopf?.focus());
        },

        schliessen() {
            this.offen = false;
            this.gGedruecktUm = 0;
            this.letzterFokus?.focus?.();
            this.letzterFokus = null;
        },

        // Tab-Falle innerhalb des offenen Dialogs (nur Anfang/Ende umbiegen).
        haltFokusImDialog(e) {
            const feld = [...this.$refs.dialog.querySelectorAll('a, button, [tabindex]:not([tabindex="-1"])')]
                .filter((el) => el.offsetParent !== null);
            if (!feld.length) return;

            const erstes = feld[0];
            const letztes = feld[feld.length - 1];

            if (e.shiftKey && document.activeElement === erstes) {
                e.preventDefault();
                letztes.focus();
            } else if (!e.shiftKey && document.activeElement === letztes) {
                e.preventDefault();
                erstes.focus();
            }
        },

        taste(e) {
            if (e.defaultPrevented || e.ctrlKey || e.metaKey || e.altKey) return;
            if (istEingabefeld(e.target)) return;
            if (this.offen) return; // Esc/Tab regelt das Dialog-Markup selbst

            const jetzt = Date.now();
            if (this.gGedruecktUm && jetzt - this.gGedruecktUm <= CHORD_FENSTER_MS) {
                this.gGedruecktUm = 0;
                const ziel = cfg.ziele?.[e.key];
                if (ziel) {
                    e.preventDefault();
                    window.location.href = ziel;
                }
                return;
            }

            if (e.key === 'g') {
                this.gGedruecktUm = jetzt;
                return;
            }
            this.gGedruecktUm = 0;

            if (e.key === '?') {
                e.preventDefault();
                this.oeffnen();
            } else if (e.key === '/') {
                e.preventDefault();
                fokussiereSuche();
            } else if (e.key === 'n' && cfg.neueNote) {
                e.preventDefault();
                window.location.href = cfg.neueNote;
            }
        },
    }));
}
