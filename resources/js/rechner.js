// Noten-Rechner und Live-Vorschau im Notenformular. Gerechnet wird serverseitig (eine Engine),
// die Oberfläche schickt nur das Szenario.
import { bedarfKlasse, format, notenKlasse, postJson } from './np';

const SPEICHERBAR = ['gesamt', 'kategorie', 'fach', 'modul'];
let zeilenNummer = 0;

function parseElement(text) {
    const m = /^(fach|modul):(\d+)(?:@semester:(\d+))?$/.exec(text ?? '');
    return m ? { typ: m[1], id: m[2], semester: m[3] ?? '' } : { typ: 'modul', id: '', semester: '' };
}

export function registriereRechner(Alpine) {
    Alpine.data('npRechner', (cfg) => ({
        katalog: cfg.daten.katalog,
        grenzen: cfg.daten.grenzen,
        ziele: cfg.daten.ziele,
        gesamt: cfg.daten.auswertung.gesamt,
        ebene: 'gesamt',
        zielId: '',
        zeitraum: '',
        zielwert: '4.5',
        zeilen: [],
        ergebnis: null,
        laedt: false,
        fehler: null,
        timer: null,

        init() {
            this.zeilen = cfg.daten.vorschlaege.map((z) => this.zeileAus(z));
            const start = cfg.start?.ziel ?? this.ziele[0]?.ziel ?? 'gesamt';
            const wert = cfg.start?.zielwert ?? this.ziele.find((z) => z.ziel === start)?.zielwert
                ?? (this.gesamt.note !== null && this.gesamt.note >= 4.5 ? 5 : 4.5);
            this.setzeZiel(start, wert);
            this.$watch(() => JSON.stringify([this.zielText, this.zielwert, this.zeilen]), () => this.planen());
            this.berechnen();
        },

        zeileAus(z) {
            const e = parseElement(z.element);
            return { nr: ++zeilenNummer, ...e, gewicht: String(z.gewicht ?? 100), wert: z.wert ?? '', quelle: z.quelle ?? null, titel: z.titel ?? null, datum: z.datum ?? null };
        },

        neueZeile(typ = null) {
            const t = typ ?? (this.katalog.faecher.length ? 'fach' : 'modul');
            this.zeilen.push({ nr: ++zeilenNummer, typ: t, id: '', semester: String(this.katalog.aktuelles_semester ?? ''), gewicht: '100', wert: '', quelle: null, titel: null, datum: null });
        },

        entferne(nr) {
            this.zeilen = this.zeilen.filter((z) => z.nr !== nr);
        },

        setzeZiel(text, wert = null) {
            const m = /^(gesamt|kategorie|semester|fach|modul)(?::(\d+))?(?:@semester:(\d+))?$/.exec(text) ?? [null, 'gesamt'];
            this.ebene = m[1];
            this.zielId = m[2] ?? '';
            this.zeitraum = m[3] ?? '';
            if (wert !== null) this.zielwert = format(wert);
        },

        waehleEbene(e) {
            this.ebene = e;
            this.zeitraum = '';
            const liste = { kategorie: this.katalog.kategorien, semester: this.katalog.semester, fach: this.katalog.faecher, modul: this.katalog.module }[e];
            this.zielId = liste?.length ? String(e === 'semester' ? (this.katalog.aktuelles_semester ?? liste[0].id) : liste[0].id) : '';
        },

        get zielText() {
            if (this.ebene === 'gesamt') return 'gesamt';
            if (!this.zielId) return null;
            const sem = this.zeitraum && ['kategorie', 'fach'].includes(this.ebene) ? `@semester:${this.zeitraum}` : '';
            return `${this.ebene}:${this.zielId}${sem}`;
        },

        get speicherbar() {
            return cfg.zielUrl && SPEICHERBAR.includes(this.ebene) && !this.zeitraum && this.zielText;
        },

        get gespeichertesZiel() {
            return this.ziele.find((z) => z.ziel === this.zielText) ?? null;
        },

        elementText(z) {
            if (!z.id) return null;
            if (z.typ === 'fach') return z.semester ? `fach:${z.id}@semester:${z.semester}` : null;
            return `modul:${z.id}`;
        },

        planen() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.berechnen(), 220);
        },

        async berechnen() {
            const wert = parseFloat(this.zielwert);
            if (!this.zielText || !Number.isFinite(wert) || wert < 1 || wert > 6) return;
            const zeilen = this.zeilen
                .map((z) => ({ element: this.elementText(z), gewicht: parseFloat(z.gewicht), wert: z.wert === '' ? null : parseFloat(z.wert), datum: z.datum }))
                .filter((z) => z.element && Number.isFinite(z.gewicht) && z.gewicht >= 0 && z.gewicht <= 100 && (z.wert === null || (z.wert >= 1 && z.wert <= 6)));
            this.laedt = true;
            try {
                this.ergebnis = await postJson(cfg.berechnenUrl, { ziel: this.zielText, zielwert: wert, zeilen });
                this.fehler = null;
            } catch (e) {
                this.fehler = e.message;
            } finally {
                this.laedt = false;
            }
        },

        get offene() {
            return this.zeilen.filter((z) => z.wert === '' && this.elementText(z)).length;
        },

        get kurve() {
            if (!this.ergebnis?.kurve?.length) return null;
            return { punkte: this.ergebnis.kurve, zielwert: this.ergebnis.ziel.zielwert, note: this.ergebnis.loesung.note };
        },

        name(z) {
            const liste = z.typ === 'fach' ? this.katalog.faecher : this.katalog.module;
            return liste.find((x) => String(x.id) === String(z.id))?.name ?? '';
        },

        klasse(v) {
            return notenKlasse(v, this.grenzen);
        },

        get heroKlasse() {
            const l = this.ergebnis?.loesung;
            if (!l) return '';
            if (l.status === 'benoetigt') return bedarfKlasse(l.note, this.grenzen);
            if (l.status === 'erreicht') return notenKlasse(6, this.grenzen);
            if (l.status === 'unerreichbar') return notenKlasse(1, this.grenzen);
            return notenKlasse(l.resultat ?? l.aktuell, this.grenzen);
        },

        fmt(v, stellen = null) {
            return format(v, stellen);
        },

        delta(a, b) {
            if (a === null || b === null) return null;
            return Math.round((b - a) * 100) / 100;
        },
    }));

    // Notenformular: zeigt vor dem Speichern, wie sich die Note auf Fach/Modul, Kategorie und Gesamtschnitt auswirkt.
    Alpine.data('npNotenFormular', (cfg) => ({
        loading: false,
        bezug: cfg.bezug ?? '',
        datum: cfg.datum,
        gewicht: String(cfg.gewicht ?? 100),
        wert: cfg.wert ?? '',
        vorschau: [],
        timer: null,

        init() {
            this.$watch(() => [this.bezug, this.datum, this.gewicht, this.wert].join('|'), () => this.planen());
            this.planen();
        },

        get typ() {
            return this.bezug.split(':')[0] ?? '';
        },

        get id() {
            return this.bezug.split(':')[1] ?? '';
        },

        get semester() {
            return cfg.semester.find((s) => s.start <= this.datum && s.ende >= this.datum) ?? null;
        },

        planen() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.laden(), 250);
        },

        async laden() {
            const w = parseFloat(this.wert);
            const g = parseFloat(this.gewicht);
            const element = this.typ === 'fach' ? (this.semester ? `fach:${this.id}@semester:${this.semester.id}` : null) : (this.id ? `modul:${this.id}` : null);
            if (!cfg.vorschauUrl || !element || !Number.isFinite(w) || w < 1 || w > 6) {
                this.vorschau = [];
                return;
            }
            try {
                const r = await postJson(cfg.vorschauUrl, {
                    ziel: element, zielwert: 4, ersetzt: cfg.ersetzt ?? null,
                    zeilen: [{ element, gewicht: Number.isFinite(g) ? g : 100, wert: w, datum: this.datum }],
                });
                this.vorschau = r.vergleich.filter((z) => z.vorher !== z.nachher || z.ist_ziel);
            } catch {
                this.vorschau = [];
            }
        },

        klasse(v) {
            return notenKlasse(v, cfg.grenzen);
        },

        fmt(v) {
            return format(v);
        },
    }));

    // Notenrechner-Drawer der Notenseite: mehrere hypothetische Noten (immer mit Wert, kein Ziel) simulieren –
    // rechnet über denselben Endpunkt/dieselbe Engine, speichert nichts (siehe RechnerController::simulieren()).
    Alpine.data('npNotenrechnerDrawer', (cfg) => ({
        zeilen: [],
        vergleich: [],
        promotion: [],
        laedt: false,
        fehler: null,
        timer: null,

        init() {
            this.neueZeile();
            this.$watch(() => JSON.stringify(this.zeilen), () => this.planen());
        },

        neueZeile() {
            if (this.zeilen.length >= 10) return;
            this.zeilen.push({ nr: ++zeilenNummer, bezug: '', datum: cfg.heute, gewicht: '100', wert: '' });
        },

        entferne(nr) {
            this.zeilen = this.zeilen.filter((z) => z.nr !== nr);
        },

        semesterVon(datum) {
            return cfg.semesterListe.find((s) => s.start <= datum && s.ende >= datum) ?? null;
        },

        planen() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.berechnen(), 250);
        },

        get gueltigeZeilen() {
            return this.zeilen
                .filter((z) => z.bezug && z.datum && z.wert !== '')
                .map((z) => {
                    const [typ, id] = z.bezug.split(':');
                    const gewicht = parseFloat(z.gewicht);

                    return {
                        typ,
                        fach_id: typ === 'fach' ? id : null,
                        modul_id: typ === 'modul' ? id : null,
                        pruefungsdatum: z.datum,
                        note_wert: parseFloat(z.wert),
                        gewichtung_prozent: Number.isFinite(gewicht) ? gewicht : null,
                    };
                })
                .filter((z) => Number.isFinite(z.note_wert) && z.note_wert >= 1 && z.note_wert <= 6);
        },

        async berechnen() {
            const zeilen = this.gueltigeZeilen;
            if (!zeilen.length) {
                this.vergleich = [];
                this.promotion = [];
                this.fehler = null;
                return;
            }

            this.laedt = true;
            try {
                const r = await postJson(cfg.berechnenUrl, { zeilen });
                this.vergleich = r.vergleich.filter((z) => z.vorher !== z.nachher);
                this.promotion = r.promotion;
                this.fehler = null;
            } catch (e) {
                this.fehler = e.message;
                this.vergleich = [];
                this.promotion = [];
            } finally {
                this.laedt = false;
            }
        },

        klasse(v) {
            return notenKlasse(v, cfg.grenzen);
        },

        fmt(v, stellen = null) {
            return format(v, stellen);
        },

        delta(a, b) {
            if (a === null || b === null) return null;
            return Math.round((b - a) * 100) / 100;
        },
    }));
}
