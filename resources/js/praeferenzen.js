import { t } from './np';

// Persönliche Darstellung in den Einstellungen (profile/partials/update-profile-information-form): wie in den
// Systemeinstellungen gilt jede Änderung sofort und bleibt ohne «Speichern» erhalten. Die Vorschau setzt die
// data-*-Attribute am <html> direkt; gespeichert wird kurz gesammelt und streng nacheinander, sonst könnte eine
// spätere Anfrage eine frühere überholen. Was beim Verlassen der Seite noch offen ist, geht mit keepalive raus.
// Lehnt der Server ab, springt die Vorschau auf den zuletzt gespeicherten Stand zurück.
// Alpine-Zustand besteht aus Proxys, die structuredClone ablehnt; die Werte sind reines JSON
const kopie = (wert) => (wert === undefined ? undefined : JSON.parse(JSON.stringify(wert)));

export function registrierePraeferenzen(Alpine) {
    Alpine.data('npPraeferenzen', (cfg) => ({
        ...cfg.werte,
        dunkel: document.documentElement.classList.contains('dark'),
        stand: kopie(cfg.werte),
        offen: {},
        unterwegs: null,
        timer: null,
        kette: Promise.resolve(),

        init() {
            // Ohne gespeicherte eigene Farbe startet der Farbwähler beim aktuellen Akzent statt bei Schwarz
            if (!this.akzentEigen) this.akzentEigen = this.akzentAlsHex() ?? '';
            new MutationObserver(() => { this.dunkel = document.documentElement.classList.contains('dark'); })
                .observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
            window.addEventListener('pagehide', () => this.senden(true));
        },

        effektivTheme() {
            return this.theme || cfg.betriebTheme;
        },

        akzentAlsHex() {
            const teile = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim().split(/\s+/).map(Number);
            if (teile.length !== 3 || teile.some((z) => !Number.isInteger(z) || z < 0 || z > 255)) return null;
            return '#' + teile.map((z) => z.toString(16).padStart(2, '0')).join('');
        },

        // Eigene Farbe beim Ziehen sofort als Farbton zeigen; die auf Kontrast geprüften Tokens liefert danach der Server
        hexZuRgb(hex) {
            const m = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex || '');
            return m ? [parseInt(m[1], 16), parseInt(m[2], 16), parseInt(m[3], 16)].join(' ') : null;
        },

        anwenden() {
            const wurzel = document.documentElement;
            const d = wurzel.dataset;
            d.theme = this.effektivTheme();
            if (this.effektivTheme() === 'kontrast' || !this.akzent) {
                delete d.akzent;
                wurzel.style.removeProperty('--accent');
            } else if (this.akzent === 'eigen') {
                d.akzent = 'eigen';
                const rgb = this.hexZuRgb(this.akzentEigen);
                if (rgb) wurzel.style.setProperty('--accent', rgb);
            } else {
                d.akzent = this.akzent;
                wurzel.style.removeProperty('--accent');
            }
            const setze = (schluessel, wert, standard) => { if (wert === standard) delete d[schluessel]; else d[schluessel] = wert; };
            setze('schrift', this.schrift, 'normal');
            setze('schriftart', this.schriftart, 'standard');
            setze('dichte', this.dichte, 'normal');
            setze('diagramm', this.diagramm, 'standard');
            setze('ecken', this.ecken, 'rund');
            setze('transparenz', this.transparenz, 'normal');
            setze('bewegung', this.bewegungReduziert ? 'reduziert' : 'normal', 'normal');
            if (d.navigation !== this.navigation) {
                d.navigation = this.navigation;
                window.dispatchEvent(new CustomEvent('np-navigation'));
            }
        },

        // Hell/Dunkel wie der Schalter in der Leiste: sofort umschalten und im Browser merken (layouts/_darstellung)
        darstellungAnwenden() {
            window.npDarstellung = this.darstellung;
            window.npDarstellungAnwenden?.();
            try {
                if (this.darstellung === 'system') localStorage.removeItem('theme');
                else localStorage.setItem('theme', this.darstellung === 'dunkel' ? 'dark' : 'light');
            } catch {
                // ohne Speicher gilt die Wahl trotzdem, nur Anmeldeseite und Fehlerseiten kennen sie nicht
            }
        },

        darstellungSetzen() {
            this.darstellungAnwenden();
            this.setzen({ darstellung: this.darstellung });
        },

        eigeneFarbeWaehlen() {
            if (!this.akzentEigen) this.akzentEigen = this.akzentAlsHex() ?? '';
            this.setzen(this.akzentEigen ? { akzent: this.akzent, akzent_eigen: this.akzentEigen } : { akzent: this.akzent });
        },

        setzen(felder) {
            this.anwenden();
            Object.assign(this.offen, felder);
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.senden(), 400);
        },

        senden(beimVerlassen = false) {
            clearTimeout(this.timer);
            if (!Object.keys(this.offen).length) return;
            if (beimVerlassen) {
                // Eine noch laufende Anfrage könnte nach dieser ankommen: ihre Felder gehen mit, das Neueste zuletzt
                this.speichern({ ...this.unterwegs, ...this.offen }, true);
                this.offen = {};
                return;
            }
            const body = this.offen;
            this.offen = {};
            this.kette = this.kette.then(() => this.speichern(body, false));
        },

        // Anfrage-Schlüssel → Zustand dieser Komponente
        zustand(body) {
            const z = { ...body };
            if ('akzent_eigen' in z) { z.akzentEigen = z.akzent_eigen; delete z.akzent_eigen; }
            if ('bewegung' in z) { z.bewegungReduziert = z.bewegung === 'reduziert'; delete z.bewegung; }
            return z;
        },

        async speichern(body, keepalive) {
            if (!keepalive) this.unterwegs = body;
            try {
                const res = await fetch(cfg.url, {
                    method: 'PATCH',
                    keepalive,
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify(body),
                });
                if (res.status === 419) throw new Error(t('Sitzung abgelaufen. Seite bitte neu laden.'));
                if (!res.ok) throw new Error(t('Änderung konnte nicht gespeichert werden.'));
                const daten = await res.json();
                Object.assign(this.stand, kopie(this.zustand(body)));
                if (typeof daten.akzent_stil === 'string') this.akzentStil(daten.akzent_stil);
            } catch (e) {
                if (keepalive) return;
                this.zuruecksetzen(body);
                window.dispatchEvent(new CustomEvent('np-toast', {
                    detail: { message: e.message || t('Änderung konnte nicht gespeichert werden.'), art: 'fehler' },
                }));
            } finally {
                if (!keepalive) this.unterwegs = null;
            }
        },

        // Abgelehnte Felder auf den gespeicherten Stand – ausser solche, die inzwischen erneut geändert wurden
        zuruecksetzen(body) {
            const neuer = this.zustand(this.offen);
            for (const schluessel of Object.keys(this.zustand(body))) {
                if (!(schluessel in neuer)) this[schluessel] = kopie(this.stand[schluessel]);
            }
            if ('darstellung' in body && !('darstellung' in neuer)) this.darstellungAnwenden();
            this.anwenden();
        },

        // Server-Tokens der eigenen Farbe (wie <x-akzent-eigen-stil> beim Laden) statt der rohen Vorschau
        akzentStil(css) {
            let stil = document.getElementById('np-akzent-eigen');
            if (!stil) {
                stil = document.createElement('style');
                stil.id = 'np-akzent-eigen';
                document.head.append(stil);
            }
            stil.textContent = css;
            if (css) document.documentElement.style.removeProperty('--accent');
        },
    }));
}
