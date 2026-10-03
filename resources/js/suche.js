// Befehlspalette (Ctrl/Cmd+K): Seiten, Aktionen und – für Admin/BB – Lernende finden.
import { bewegungRuhig } from './np';

// html[data-palette] nimmt Seitenleiste und Kapseln das Glas (Glas auf Glas vermeiden, G5). Erst setzen, wenn der Scrim
// eingeblendet ist, sonst springen die Flächen sichtbar; beim Schliessen sofort entfernen.
const MAXIMUM_TREFFER = 8;

export function registriereSuche(Alpine) {
    Alpine.data('npSuche', (cfg) => ({
        offen: false,
        q: '',
        index: 0,
        treffer: [],
        timer: null,
        letzterFokus: null,
        paletteTimer: null,
        paletteAbbruch: null,

        init() {
            window.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    this.oeffnen();
                }
            });
            this.$watch('q', () => this.suchen());
            // Aus dem Back/Forward-Cache zurück: nie mit gesetztem Attribut weitermachen
            window.addEventListener('pageshow', () => this.paletteEntfernen());
        },

        // Dauer der Scrim-Einblendung (--dauer-3, sonst 200 ms) plus 50 ms Reserve, falls transitionend ausbleibt
        paletteWarten() {
            const roh = getComputedStyle(document.documentElement).getPropertyValue('--dauer-3').trim();
            const wert = parseFloat(roh);
            const ms = Number.isFinite(wert) ? (roh.endsWith('ms') ? wert : wert * 1000) : 200;
            return ms + 50;
        },

        paletteEntfernen() {
            clearTimeout(this.paletteTimer);
            this.paletteTimer = null;
            this.paletteAbbruch?.();
            this.paletteAbbruch = null;
            document.documentElement.removeAttribute('data-palette');
        },

        paletteSetzen() {
            clearTimeout(this.paletteTimer);
            this.paletteAbbruch?.();
            this.paletteAbbruch = null;
            if (!this.offen) return;
            const setze = () => {
                clearTimeout(this.paletteTimer);
                this.paletteTimer = null;
                this.paletteAbbruch?.();
                this.paletteAbbruch = null;
                if (this.offen) document.documentElement.setAttribute('data-palette', '');
            };
            const scrim = this.$refs.scrim;
            // Ruhige Bewegung: der Scrim blendet nicht über, also gibt es kein transitionend – sofort setzen
            if (bewegungRuhig() || !scrim) {
                setze();
                return;
            }
            const ende = (ereignis) => {
                if (ereignis.target === scrim && ereignis.propertyName === 'opacity') setze();
            };
            scrim.addEventListener('transitionend', ende);
            this.paletteAbbruch = () => scrim.removeEventListener('transitionend', ende);
            this.paletteTimer = setTimeout(setze, this.paletteWarten());
        },

        oeffnen() {
            if (!this.offen) this.letzterFokus = document.activeElement;
            this.offen = true;
            this.q = '';
            this.treffer = [];
            this.index = 0;
            this.$nextTick(() => {
                this.$refs.eingabe?.focus();
                this.paletteSetzen();
            });
        },

        // Fokus zurück auf das Element, von dem die Palette geöffnet wurde (Suchknopf oder Seite)
        schliessen() {
            if (!this.offen) return;
            this.offen = false;
            this.paletteEntfernen();
            const ziel = this.letzterFokus;
            this.letzterFokus = null;
            if (ziel?.isConnected) ziel.focus?.();
        },

        get lokal() {
            const q = this.q.trim().toLowerCase();
            const alle = cfg.eintraege;
            return q ? alle.filter((e) => (e.label + ' ' + (e.gruppe ?? '')).toLowerCase().includes(q)) : alle;
        },

        get liste() {
            return [...this.treffer, ...this.lokal].slice(0, MAXIMUM_TREFFER);
        },

        suchen() {
            this.index = 0;
            clearTimeout(this.timer);
            if (!cfg.url || this.q.trim().length < 2) {
                this.treffer = [];
                return;
            }
            this.timer = setTimeout(async () => {
                try {
                    const res = await fetch(`${cfg.url}?q=${encodeURIComponent(this.q.trim())}`, { headers: { Accept: 'application/json' } });
                    this.treffer = res.ok ? await res.json() : [];
                } catch {
                    this.treffer = [];
                }
            }, 180);
        },

        taste(e) {
            if (! this.liste.length) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); this.index = Math.min(this.index + 1, this.liste.length - 1); }
            if (e.key === 'ArrowUp') { e.preventDefault(); this.index = Math.max(this.index - 1, 0); }
            if (e.key === 'Enter' && this.liste[this.index]) { e.preventDefault(); this.gehe(this.liste[this.index]); }
        },

        // «Feedback melden»/«Tastenkürzel anzeigen» öffnen einen Dialog, Darstellungs-Befehle
        // setzen Attribute/speichern sofort (window.npBefehl, Layout) – beides ohne Navigation.
        gehe(treffer) {
            if (treffer.url === '#feedback-modal') {
                this.schliessen();
                window.dispatchEvent(new CustomEvent('open-modal', { detail: 'feedback' }));

                return;
            }
            if (treffer.url === '#tastenkuerzel-modal') {
                this.schliessen();
                window.dispatchEvent(new CustomEvent('open-tastenkuerzel'));

                return;
            }
            if (treffer.url.startsWith('#')) {
                this.schliessen();
                window.npBefehl?.(treffer.url);

                return;
            }
            window.location.href = treffer.url;
        },
    }));
}
