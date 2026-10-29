// Filter der Statistikseiten (docs/auftrag/GUI-R6.md §6): x-filterleiste mit statistik-Prop ruft npFilter auf. Jede Änderung
// (change, input, submit) setzt die Adresse mit history.replaceState, holt nach 150 ms dieselbe Route mit
// Accept: application/json und meldet das Paket {filter, diagramm, tabelle, zusammenfassung, meta} als Ereignis
// «np-statistik» (Diagramme hören mit @np-statistik.window). Serverseitig gezeichnete Bausteine ([data-np-baustein] und die
// Selektoren aus cfg.ersetze) kommen aus derselben Route als HTML und ersetzen ihren Vorgänger. Ohne JavaScript bleibt
// das Formular ein gewöhnlicher GET. Schlägt eine Abfrage fehl, lädt die Seite mit dem Filter neu.
import { format, t } from './np';

const ENTPRELLEN_MS = 150;
const BAUSTEIN = '[data-np-baustein]';
const ANSAGE = '[data-np-ansage]';

export function registriereStatistik(Alpine) {
    // cfg: { standard: { name: wert } (Werte, die nicht in die Adresse gehören), json: false (kein JSON-Paket holen), ersetze: [selektor] (HTML-Teile, die nach
    // jeder Änderung neu vom Server kommen), marken: bool (aktive Filter als Marken mit ×) }
    Alpine.data('npFilter', (cfg = {}) => ({
        marken: [],
        laedt: false,
        _timer: null,
        _abbruch: null,
        _absender: null,

        init() {
            const form = this.$el;
            form.addEventListener('submit', (ev) => {
                ev.preventDefault();
                // registriereSofortSenden sperrt das Formular beim Ändern; hier geht nichts als Seitenwechsel weg
                delete form.dataset.sendet;
                this._absender = ev.submitter ?? null;
                this.planen();
            });
            form.addEventListener('change', () => this.planen());
            form.addEventListener('input', (ev) => {
                if (ev.target.matches?.('input:not([type=checkbox]):not([type=radio]):not([type=hidden])')) this.planen();
            });
            if (cfg.marken) this.marken = this.markenBerechnen();
        },

        planen() {
            clearTimeout(this._timer);
            this._timer = setTimeout(() => this.laden(), ENTPRELLEN_MS);
        },

        standardWert(el) {
            if (Object.hasOwn(cfg.standard ?? {}, el.name)) return String(cfg.standard[el.name]);
            return el.tagName === 'SELECT' ? (el.options[0]?.value ?? '') : '';
        },

        query() {
            const form = this.$el;
            const daten = new FormData(form, this._absender?.form === form ? this._absender : undefined);
            const params = new URLSearchParams();
            for (const [name, wert] of daten) {
                if (typeof wert !== 'string' || name.startsWith('_')) continue;
                const text = wert.trim();
                if (text === '' || (cfg.standard && Object.hasOwn(cfg.standard, name) && String(cfg.standard[name]) === text)) continue;
                params.append(name, text);
            }
            const s = params.toString();
            return s ? `?${s}` : '';
        },

        adresse(query) {
            const u = new URL(this.$el.getAttribute('action') || location.href, location.href);
            u.search = query;
            u.hash = '';
            return u;
        },

        async laden() {
            const query = this.query();
            const url = this.adresse(query);
            history.replaceState(history.state, '', `${location.pathname}${query}${location.hash}`);
            document.querySelectorAll('a[data-behalte-filter]').forEach((a) => {
                const ziel = new URL(a.href, location.href);
                ziel.search = query;
                a.href = `${ziel.pathname}${ziel.search}${ziel.hash}`;
            });

            this._abbruch?.abort();
            const abbruch = (this._abbruch = new AbortController());
            const form = this.$el;
            this.laedt = true;
            form.setAttribute('aria-busy', 'true');
            try {
                const optionen = (accept) => ({ signal: abbruch.signal, credentials: 'same-origin', headers: { Accept: accept, 'X-Requested-With': 'XMLHttpRequest' } });
                const baustellen = this.ziele(document);
                // cfg.json === false: die Seite hat keine Diagramme, die das Paket hören – nur HTML tauschen,
                // sonst rechnet der Server dieselbe Statistik zweimal
                const [antwort, html] = await Promise.all([
                    cfg.json === false ? null : fetch(url, optionen('application/json')),
                    baustellen.length ? fetch(url, optionen('text/html')) : null,
                ]);
                if ((antwort && !antwort.ok) || (html && !html.ok)) throw new Error(`HTTP ${(antwort ?? html).status}`);
                const paket = antwort ? await antwort.json() : null;
                if (html) this.ersetzen(new DOMParser().parseFromString(await html.text(), 'text/html'));
                if (abbruch.signal.aborted) return;
                if (cfg.marken) this.marken = this.markenBerechnen();
                if (paket) this.$dispatch('np-statistik', paket);
            } catch (fehler) {
                if (fehler?.name === 'AbortError') return;
                location.assign(url);
            } finally {
                if (this._abbruch === abbruch) {
                    this.laedt = false;
                    form.removeAttribute('aria-busy');
                    delete form.dataset.sendet;
                }
            }
        },

        // Bausteine mit id (SVG-Komponenten) und die Selektoren aus cfg.ersetze
        ziele(wurzel) {
            return [
                ...wurzel.querySelectorAll(BAUSTEIN),
                ...(cfg.ersetze ?? []).flatMap((s) => [...wurzel.querySelectorAll(s)]),
            ];
        },

        ersetzen(doc) {
            doc.querySelectorAll(BAUSTEIN).forEach((neu) => {
                const alt = neu.id ? document.getElementById(neu.id) : null;
                if (!alt) throw new Error('Baustein fehlt');
                this.baustein(alt, neu);
            });
            (cfg.ersetze ?? []).forEach((selektor) => {
                const alt = [...document.querySelectorAll(selektor)];
                const neu = [...doc.querySelectorAll(selektor)];
                // Anzahl darf sich ändern (bedingte Blöcke): die neuen Elemente treten an die Stelle des ersten alten
                if (!alt.length) throw new Error('Ziel fehlt');
                alt[0].before(...neu.map((n) => document.adoptNode(n)));
                alt.forEach((el) => el.remove());
            });
        },

        // Wurzel und Ansage bleiben stehen (die Ansage liest der Screenreader nur, wenn sich ihr Text ändert),
        // der Rest wird ersetzt; geöffnete Tabellen bleiben offen
        baustein(alt, neu) {
            const offen = [...alt.querySelectorAll('details')].map((d) => d.open);
            const ansageAlt = alt.querySelector(ANSAGE);
            const ansageText = neu.querySelector(ANSAGE)?.textContent ?? '';
            [...alt.attributes].forEach((a) => alt.removeAttribute(a.name));
            [...neu.attributes].forEach((a) => alt.setAttribute(a.name, a.value));
            alt.replaceChildren(...[...neu.childNodes].map((n) => document.adoptNode(n)));
            alt.querySelectorAll('details').forEach((d, i) => { if (offen[i]) d.open = true; });
            const ansageNeu = alt.querySelector(ANSAGE);
            if (ansageAlt && ansageNeu) {
                ansageNeu.replaceWith(ansageAlt);
                ansageAlt.textContent = ansageText;
            }
        },

        markenBerechnen() {
            return [...this.$el.elements]
                .filter((el) => el.name && !el.name.startsWith('_') && ['SELECT', 'INPUT'].includes(el.tagName) && el.type !== 'hidden' && el.type !== 'submit')
                .map((el) => ({ el, wert: String(el.value).trim() }))
                .filter(({ el, wert }) => wert !== '' && wert !== this.standardWert(el))
                .map(({ el, wert }) => ({
                    name: el.name,
                    text: el.tagName === 'SELECT' ? (el.selectedOptions[0]?.textContent.trim() ?? wert) : wert,
                }));
        },

        markeText(marke) {
            return t('Filter entfernen: :name', { name: marke.text });
        },

        markeEntfernen(name) {
            const el = this.$el.elements.namedItem(name);
            if (!el || el instanceof RadioNodeList) return;
            el.value = this.standardWert(el);
            el.dispatchEvent(new Event('change', { bubbles: true }));
        },
    }));
    // Hülle von x-verlauf: übernimmt bei «np-statistik» Diagramm, Satz, Tabelle und die Liste ohne Datum
    Alpine.data('npVerlauf', (schluessel = 'verlauf') => ({
        aktualisiere(paket) {
            const d = paket?.diagramm?.[schluessel];
            if (!d) return;
            const huelle = this.$el;
            const chart = huelle.querySelector('[data-np-verlauf-chart]');
            if (chart) Alpine.$data(chart).setze(d);
            const satz = paket.zusammenfassung?.[schluessel] ?? '';
            const sichtbar = huelle.querySelector('[data-np-satz]');
            if (sichtbar) sichtbar.textContent = satz;
            huelle.querySelector('canvas')?.setAttribute('aria-label', satz);
            const tabelle = huelle.querySelector('[data-np-tabelle]');
            if (tabelle) tabelleRendern(tabelle, paket.tabelle?.[schluessel]);
            const liste = huelle.querySelector('[data-np-ohne-datum]');
            if (liste) listeRendern(liste, paket.meta?.[schluessel]?.ohneDatum ?? []);
        },
    }));
}

const zelle = (art, text, rechts) => {
    const el = document.createElement(art);
    el.textContent = text;
    if (art === 'th') el.scope = 'col';
    if (rechts) el.className = 'text-right';
    return el;
};

// Tabelle aus {spalten, zeilen}; letzte Spalte rechtsbündig wie im Server-HTML von x-verlauf
function tabelleRendern(tabelle, daten) {
    if (!daten) return;
    const letzte = daten.spalten.length - 1;
    const kopf = document.createElement('tr');
    daten.spalten.forEach((s, i) => kopf.append(zelle('th', s, i === letzte)));
    tabelle.tHead?.replaceChildren(kopf);
    tabelle.tBodies[0]?.replaceChildren(...daten.zeilen.map((z) => {
        const tr = document.createElement('tr');
        z.forEach((c, i) => tr.append(zelle('td', c, i === letzte)));
        return tr;
    }));
}

// Positionen ohne Datum (IPA, Schlussprüfung) als Liste «Titel Note»; ohne Einträge bleibt die Liste ausgeblendet
function listeRendern(liste, eintraege) {
    liste.classList.toggle('hidden', eintraege.length === 0);
    ['mt-2', 'flex', 'flex-wrap', 'gap-x-4', 'gap-y-1'].forEach((k) => liste.classList.toggle(k, eintraege.length > 0));
    liste.replaceChildren(...eintraege.map((e) => {
        const li = document.createElement('li');
        const titel = document.createElement('span');
        titel.className = 'text-text';
        titel.textContent = e.titel;
        const wert = document.createElement('span');
        wert.className = 'tabular-nums';
        wert.textContent = e.wert === null ? '–' : format(e.wert, 1);
        li.append(titel, ' ', wert);
        return li;
    }));
}
