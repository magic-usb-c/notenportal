// Gemeinsame Helfer: JSON-POST mit CSRF, Notenfarben nach den Grenzen aus den Einstellungen, Formatierung.

// Übersetzung: Schlüssel ist der deutsche Text, window.npI18n (Layout, nur ausserhalb von Deutsch) liefert die Übersetzung.
// Platzhalter wie in Laravel: t('Ziel :wert', { wert: '4.5' }). Neue Schlüssel in App\Support\JsTexte und lang/en.json.
export function t(schluessel, ersetzungen = {}) {
    const text = window.npI18n?.[schluessel] ?? schluessel;
    return Object.entries(ersetzungen).reduce((s, [name, wert]) => s.replaceAll(`:${name}`, String(wert)), text);
}

export async function postJson(url, body) {
    // Netzfehler werfen sonst die englische Browsermeldung («Failed to fetch»).
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify(body),
    }).catch(() => {
        throw new Error(t('Berechnung fehlgeschlagen.'));
    });
    const daten = await res.json().catch(() => null);
    if (!res.ok) {
        const meldung = daten?.errors ? Object.values(daten.errors).flat()[0] : null;
        throw new Error(meldung || statusMeldung(res.status));
    }
    return daten;
}

// Der Grund steht im Status: Drosselung (429) und abgelaufene Sitzung (419/401) sind behebbar und
// werden gesagt; alles andere bleibt die kurze Sammelmeldung (gemessen 01.10.2026: der Rundgang sah
// nur «Berechnung fehlgeschlagen.» ohne Weiterweg).
function statusMeldung(status) {
    if (status === 429) return t('Zu viele Anfragen – bitte kurz warten.');
    if (status === 419 || status === 401) return t('Sitzung abgelaufen. Seite bitte neu laden.');
    return t('Berechnung fehlgeschlagen.');
}

// Text in die Zwischenablage. navigator.clipboard gibt es nur über HTTPS (oder localhost) –
// über http im Firmennetz bleibt nur der alte execCommand-Weg über ein verstecktes Textfeld.
export async function kopieren(text) {
    if (navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch {
            // weiter mit dem Fallback
        }
    }
    const feld = document.createElement('textarea');
    feld.value = text;
    feld.setAttribute('readonly', '');
    feld.style.cssText = 'position:fixed;top:0;left:0;opacity:0';
    document.body.appendChild(feld);
    feld.select();
    let ok = false;
    try {
        ok = document.execCommand('copy');
    } catch {
        ok = false;
    }
    feld.remove();
    return ok;
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

// Wie NotenSkala::text(): gut und genügend neutral, knapp und ungenügend farbig, ungenügend zusätzlich
// unterstrichen (nie nur Farbe). Die volle Stufenfarbe (NotenSkala::farbe) nur, wo sie etwas aussagt.
const TEXT = {
    gut: 'text-text',
    genuegend: 'text-text',
    knapp: 'text-note-knapp',
    ungenuegend: 'text-note-ungenuegend underline decoration-2 underline-offset-4',
};

const FARBE = {
    gut: 'text-note-gut',
    genuegend: 'text-note-genuegend',
    knapp: 'text-note-knapp',
    ungenuegend: 'text-note-ungenuegend',
};

export function notenKlasse(wert, grenzen) {
    return TEXT[stufe(wert, grenzen)] ?? 'text-muted';
}

export function stufenFarbe(wert, grenzen = STANDARD_GRENZEN) {
    return FARBE[stufe(wert, grenzen)] ?? 'text-muted';
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

// Toast (<x-toast>): Flash-Meldung vom Server oder per Event np-toast { message, art: 'fehler' } aus JS.
// Fehler bleiben länger stehen; unter dem Zeiger läuft die Zeit nicht ab.
export function registriereToast(Alpine) {
    Alpine.data('npToast', (cfg) => ({
        show: cfg.show,
        message: cfg.message,
        fehler: cfg.fehler,
        timer: null,
        init() {
            if (this.show) this.start();
        },
        start() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => { this.show = false; }, this.fehler ? Math.max(6000, cfg.dauer) : cfg.dauer);
        },
        zeigen(detail) {
            this.message = detail.message;
            this.fehler = detail.art === 'fehler';
            this.show = true;
            this.start();
        },
    }));
}

export function registriereFormhilfen(Alpine) {
    // Bereiche einer Einstellungsseite (macOS-Einstellungsfenster): ein Bereich sichtbar, gewählt per Segment.
    // Der Bereich steht im Fragment – Formulare posten an «…#bereich», und der Browser übernimmt das Fragment
    // bei der Weiterleitung nach dem Speichern (RFC 9110, 10.2.2), so bleibt man im selben Bereich.
    // Segment (Seitenkopf) und Inhalt sind getrennte Instanzen und gleichen sich über «np-bereich» ab.
    Alpine.data('npBereiche', (standard, liste) => ({
        bereich: liste.includes(location.hash.slice(1)) ? location.hash.slice(1) : standard,
        init() {
            const setzen = (neu) => { if (liste.includes(neu)) this.bereich = neu; };
            window.addEventListener('hashchange', () => setzen(location.hash.slice(1)));
            window.addEventListener('np-bereich', (ev) => setzen(ev.detail));
        },
        wechseln(neu) {
            history.replaceState(null, '', '#' + neu);
            window.dispatchEvent(new CustomEvent('np-bereich', { detail: neu }));
            window.scrollTo({ top: 0 });
        },
    }));

    // Passwortfelder im Admin: zufälliges Passwort erzeugen (mind. eine Ziffer und ein Buchstabe, ohne
    // verwechselbare Zeichen) und in Feld plus Bestätigung setzen. Refs: «pw1», «pw2».
    Alpine.data('npPasswortFelder', () => ({
        sichtbar: false,
        generieren() {
            const zeichen = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!?#+';
            const werte = new Uint32Array(14);
            let pw;
            do {
                crypto.getRandomValues(werte);
                pw = Array.from(werte, (v) => zeichen[v % zeichen.length]).join('');
            } while (!/\d/.test(pw) || !/[a-z]/i.test(pw));
            this.$refs.pw1.value = pw;
            this.$refs.pw2.value = pw;
            this.sichtbar = true;
        },
    }));
}

// Seitenleiste: aufgeklappte Gruppen je Gerät merken (reine Bequemlichkeit, darf fehlen).
const SEITENLEISTE_ZU = 'np-seitenleiste-zu';

function geschlosseneGruppen() {
    try {
        const liste = JSON.parse(localStorage.getItem(SEITENLEISTE_ZU) || '[]');
        return Array.isArray(liste) ? liste : [];
    } catch (e) {
        return [];
    }
}

export function registriereSeitenleiste(Alpine) {
    Alpine.data('npSeitenleisteGruppe', (name, aktiv) => ({
        // Die Gruppe mit der aktuellen Seite ist immer offen
        auf: aktiv || !geschlosseneGruppen().includes(name),
        umschalten() {
            this.auf = !this.auf;
            try {
                const liste = geschlosseneGruppen().filter((g) => g !== name);
                if (!this.auf) liste.push(name);
                localStorage.setItem(SEITENLEISTE_ZU, JSON.stringify(liste));
            } catch (e) {}
        },
    }));

    // Aufklappmenü der Leiste («Mehr», Gruppen). Überfahren öffnet nur mit echter Maus: auf Touch kommen
    // mouseenter und click direkt nacheinander, das Menü ging auf und sofort wieder zu. Ein Klick oder Tipp
    // öffnet und hält offen, der zweite schliesst. Escape gibt den Fokus an den Knopf zurück, Tab hinaus schliesst.
    Alpine.data('npLeistenMenue', () => ({
        auf: false,
        gehalten: false,
        rein(e) {
            if (e.pointerType === 'mouse') this.auf = true;
        },
        raus(e) {
            if (e.pointerType === 'mouse' && !this.gehalten) this.auf = false;
        },
        klick() {
            if (this.auf && this.gehalten) {
                this.zu();
            } else {
                this.auf = true;
                this.gehalten = true;
            }
        },
        zu() {
            this.auf = false;
            this.gehalten = false;
        },
        escape() {
            if (!this.auf) return;
            this.zu();
            this.$refs.knopf.focus();
        },
        fokusRaus(e) {
            // Nur wenn der Fokus sichtbar woandershin geht (Tab); einen Tipp ins Leere fängt click.outside
            if (e.relatedTarget && !this.$root.contains(e.relatedTarget)) this.zu();
        },
    }));

    // Priority+ der Tableiste (Navigation «oben»): was nicht passt, wandert von hinten in «Mehr». Die Tableiste steht
    // in der Mitte der Symbolleiste; vorne und hinten sind gleich breit (flex-1 basis-0), damit sie mittig bleibt. Ihr
    // Platz ist deshalb die Zeile minus zweimal die breitere Seite – der kleine Titel vorne kürzt sich und zählt nicht.
    Alpine.data('npLeistenUeberlauf', () => ({
        init() {
            this.eintraege = [...this.$root.querySelectorAll(':scope > [data-ueberlauf]')];
            this.mehr = this.$root.querySelector(':scope > [data-mehr-menue]');
            this.zeile = this.$root.closest('[data-symbolleiste-zeile]');
            if (!this.mehr || !this.zeile) return;
            let geplant = false;
            const neu = () => {
                if (geplant) return;
                geplant = true;
                requestAnimationFrame(() => {
                    geplant = false;
                    this.einpassen();
                });
            };
            const beobachter = new ResizeObserver(neu);
            beobachter.observe(this.zeile);
            this.zeile.querySelectorAll('[data-symbolleiste-anfang], [data-symbolleiste-ende]').forEach((el) => beobachter.observe(el));
            // Schrift aus dem Profil (auch die Vorschau) ändert die Breite der Einträge, nicht die der Zeile
            new MutationObserver(neu).observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['data-schrift', 'data-schriftart', 'data-navigation', 'lang'],
            });
            document.fonts?.ready.then(neu);
            this.einpassen();
        },
        einpassen() {
            if (this.$root.offsetParent === null) return; // Seitenleiste gewählt oder unter 1024 px
            const px = (wert) => parseFloat(wert) || 0;
            const inhalt = (el, ohne = null) => {
                const kinder = [...el.children].filter((k) => !k.hidden && (!ohne || !k.matches(ohne)) && k.getClientRects().length > 0);
                const luecke = px(getComputedStyle(el).columnGap);
                return kinder.reduce((summe, k) => summe + k.getBoundingClientRect().width, 0) + luecke * Math.max(0, kinder.length - 1);
            };
            const stil = getComputedStyle(this.zeile);
            const anfang = this.zeile.querySelector('[data-symbolleiste-anfang]');
            const ende = this.zeile.querySelector('[data-symbolleiste-ende]');
            const seite = Math.max(anfang ? inhalt(anfang, '[data-kuerzbar]') : 0, ende ? inhalt(ende) : 0);
            const platz = this.zeile.clientWidth - px(stil.paddingLeft) - px(stil.paddingRight) - 2 * seite - 2 * px(stil.columnGap);
            const kapsel = getComputedStyle(this.$root);
            const belegt = () => inhalt(this.$root) + px(kapsel.paddingLeft) + px(kapsel.paddingRight);

            this.eintraege.forEach((el) => { el.hidden = false; });
            this.mehr.hidden = true;
            if (belegt() > platz) {
                this.mehr.hidden = false;
                for (let i = this.eintraege.length - 1; i >= 0 && belegt() > platz; i--) this.eintraege[i].hidden = true;
            }

            let badge = 0;
            let aktiv = false;
            for (const el of this.eintraege) {
                const ziel = this.mehr.querySelector(`[data-mehr="${el.dataset.ueberlauf}"]`);
                if (ziel) ziel.hidden = !el.hidden;
                if (el.hidden) {
                    badge += Number(el.dataset.badge || 0);
                    aktiv ||= 'aktiv' in el.dataset;
                }
            }
            this.mehr.querySelector('button').toggleAttribute('data-aktiv', aktiv);
            const zahl = this.mehr.querySelector('[data-mehr-badge]');
            zahl.textContent = String(badge);
            zahl.hidden = badge === 0;
        },
    }));

    // Seitenleiste. Ab 1024 px blendet «ausblenden» sie ganz aus und zeigt die Tableiste (Präferenz «navigation»,
    // wie das «sidebarAdaptable»-Muster); darunter ist sie eine Schublade über dem Inhalt, die den Fokus hält,
    // bis Escape, ein Tipp daneben oder der Knopf sie schliesst.
    Alpine.data('npSeitenleiste', () => ({
        ausloeser: null,
        init() {
            this.breit = window.matchMedia('(min-width: 64rem)');
            window.addEventListener('np-seitenleiste-zeigen', () => this.zeigen());
            window.addEventListener('np-schublade-zu', () => this.zu());
            this.breit.addEventListener('change', () => this.zu(false));
            // Aus dem Back/Forward-Cache zurück: nicht mit offener Schublade weitermachen
            window.addEventListener('pageshow', () => this.zu(false));
            this.$root.addEventListener('keydown', (e) => this.fokusHalten(e));
        },
        offen() {
            return document.documentElement.hasAttribute('data-schublade');
        },
        zeigen() {
            if (this.breit.matches) {
                window.npBefehl('#navigation:seite');
                return;
            }
            this.ausloeser = document.activeElement;
            document.documentElement.setAttribute('data-schublade', '');
            this.$nextTick(() => {
                const ziel = this.$root.querySelector('nav [aria-current="page"]') ?? this.$root.querySelector('nav a');
                ziel?.focus({ preventScroll: true });
            });
        },
        ausblenden() {
            if (this.offen()) {
                this.zu();
                return;
            }
            window.npBefehl('#navigation:oben');
            // Der Knopf verschwindet mit der Leiste: der Fokus geht an den Knopf, der sie wieder einblendet
            requestAnimationFrame(() => document.querySelector('[data-seitenleiste-zeigen]')?.focus({ preventScroll: true }));
        },
        zu(fokus = true) {
            if (!this.offen()) return;
            document.documentElement.removeAttribute('data-schublade');
            if (fokus) this.ausloeser?.focus?.({ preventScroll: true });
            this.ausloeser = null;
        },
        fokusHalten(e) {
            if (e.key !== 'Tab' || !this.offen()) return;
            const ziele = [...this.$root.querySelectorAll('a[href], button:not([disabled])')].filter((el) => el.getClientRects().length > 0);
            if (!ziele.length) return;
            const erstes = ziele[0];
            const letztes = ziele[ziele.length - 1];
            if (e.shiftKey && document.activeElement === erstes) {
                e.preventDefault();
                letztes.focus();
            } else if (!e.shiftKey && document.activeElement === letztes) {
                e.preventDefault();
                erstes.focus();
            }
        },
    }));

    // Symbolleiste: der weiche Rand (Scroll Edge) erscheint, sobald Inhalt unter der Leiste liegt, und der kleine
    // Titel, sobald der grosse Titel der Seite unter ihr verschwunden ist (HIG «Toolbars»: large title).
    Alpine.data('npSymbolleiste', () => ({
        titelKlein: false,
        init() {
            let geplant = false;
            const kante = () => {
                geplant = false;
                this.$root.style.setProperty('--np-kante', Math.min(1, Math.max(0, window.scrollY / 16)).toFixed(3));
            };
            window.addEventListener('scroll', () => {
                if (geplant) return;
                geplant = true;
                requestAnimationFrame(kante);
            }, { passive: true });
            kante();

            const titel = document.querySelector('[data-np-titel]');
            if (!titel || !('IntersectionObserver' in window)) return;
            const hoehe = Math.round(this.$root.getBoundingClientRect().height);
            new IntersectionObserver(([eintrag]) => {
                this.titelKlein = !eintrag.isIntersecting && eintrag.boundingClientRect.top < hoehe;
            }, { rootMargin: `-${hoehe}px 0px 0px 0px` }).observe(titel);
        },
    }));
}

// Tabellenzeilen mit data-href öffnen ihr Ziel bei einem Klick irgendwo in der Zeile, wie eine Liste in macOS.
// Bedienelemente der Zeile behalten ihren eigenen Klick, markierter Text bleibt markierbar, Ctrl-/Cmd- und
// Mittelklick öffnen wie bei einem Link einen neuen Tab. Tastatur und Screenreader erreichen das Ziel über
// den Link in der Zeile – data-href ist nur die grössere Trefferfläche für die Maus.
// Felder mit data-sofort senden ihr Formular beim Ändern ab (Einstellungen, die sofort gelten). Ein Formular
// geht nur einmal weg, auch wenn zwei seiner Felder kurz nacheinander ändern; ungültige Eingaben meldet der
// Browser am Feld. Kommt die Seite aus dem Verlaufscache zurück, ist die Sperre wieder offen.
export function registriereSofortSenden() {
    document.addEventListener('change', (ev) => {
        const form = ev.target.closest?.('[data-sofort]')?.form;
        if (!form || form.dataset.sendet) return;
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        form.dataset.sendet = '1';
        form.requestSubmit();
    });
    window.addEventListener('pageshow', (ev) => {
        if (ev.persisted) document.querySelectorAll('form[data-sendet]').forEach((form) => delete form.dataset.sendet);
    });
}

export function registriereZeilenLinks() {
    const ziel = (ev) => {
        const zeile = ev.target.closest?.('tr[data-href]');
        if (!zeile || ev.target.closest('a, button, input, select, textarea, label, summary, [data-zeile-ignorieren]')) return null;
        if (window.getSelection()?.toString()) return null;
        return zeile.dataset.href;
    };
    document.addEventListener('click', (ev) => {
        if (ev.button !== 0 || ev.defaultPrevented) return;
        const href = ziel(ev);
        if (!href) return;
        if (ev.ctrlKey || ev.metaKey || ev.shiftKey) window.open(href, '_blank', 'noopener');
        else window.location.assign(href);
    });
    document.addEventListener('auxclick', (ev) => {
        if (ev.button !== 1) return;
        const href = ziel(ev);
        if (href) window.open(href, '_blank', 'noopener');
    });
}

// Liste mit Detailbereich wie in Mail (x-data="npAuswahlliste({ reihenfolge, start, praefix })"): Auswahl per
// Klick oder Pfeiltasten (ARIA-Listbox, Fokus wandert mit). Die Adresse merkt sie als #<praefix>-ID, damit
// Zurück, Neuladen und Links direkt auf dieselbe Zeile führen.
export function registriereAuswahlliste(Alpine) {
    Alpine.data('npAuswahlliste', (cfg) => ({
        gewaehlt: null,

        init() {
            this.gewaehlt = this.ausAdresse() ?? (cfg.reihenfolge.includes(cfg.start) ? cfg.start : cfg.reihenfolge[0] ?? null);
            window.addEventListener('hashchange', () => {
                const id = this.ausAdresse();
                if (id !== null) this.waehlen(id, false);
            });
            this.$nextTick(() => this.zeile(this.gewaehlt)?.scrollIntoView({ block: 'nearest' }));
        },

        ausAdresse() {
            const treffer = window.location.hash.match(/^#([a-z]+)-(\d+)$/);
            const id = treffer && treffer[1] === cfg.praefix ? Number(treffer[2]) : null;
            return cfg.reihenfolge.includes(id) ? id : null;
        },

        zeile(id) {
            return this.$refs.liste?.querySelector(`[data-auswahl="${id}"]`) ?? null;
        },

        waehlen(id, adresseSetzen = true) {
            if (this.gewaehlt === id) return;
            this.gewaehlt = id;
            if (adresseSetzen) history.replaceState(null, '', `#${cfg.praefix}-${id}`);
            this.$nextTick(() => this.$refs.detail?.scrollTo({ top: 0 }));
        },

        // Pfeil auf/ab, Pos1/Ende: nächste Zeile wählen und den Fokus mitnehmen
        bewegen(schritt) {
            const liste = cfg.reihenfolge;
            const i = liste.indexOf(this.gewaehlt);
            const id = liste[Math.min(Math.max(i + schritt, 0), liste.length - 1)];
            if (id === undefined) return;
            this.waehlen(id);
            this.$nextTick(() => {
                const z = this.zeile(id);
                z?.focus({ preventScroll: true });
                z?.scrollIntoView({ block: 'nearest' });
            });
        },
    }));

    // Quellliste, deren Einträge eigene Seiten öffnen (x-data="npQuellliste({ name, suche })", Module): Tippen filtert
    // ohne Neuladen, Pfeiltasten wandern durch die sichtbaren Einträge, Eingabe öffnet den ersten Treffer. Der Filter
    // reist im Link mit, und die Liste steht auf der nächsten Seite wieder an derselben Stelle – wie eine Seitenleiste,
    // die beim Wechsel stehen bleibt. Der Fokus kehrt zum gewählten Eintrag zurück, wenn er aus der Liste kam.
    Alpine.data('npQuellliste', (cfg) => ({
        q: cfg.suche ?? '',
        treffer: 0,

        init() {
            this.filtern();
            const liste = this.$refs.liste;
            const stand = this.merken('lesen');
            if (stand?.scroll !== undefined) liste.scrollTop = stand.scroll;
            const aktiv = liste.querySelector('[aria-current="page"]');
            if (!aktiv) return;
            if (aktiv.offsetTop < liste.scrollTop || aktiv.offsetTop + aktiv.offsetHeight > liste.scrollTop + liste.clientHeight) {
                liste.scrollTop = aktiv.offsetTop - (liste.clientHeight - aktiv.offsetHeight) / 2;
            }
            if (stand?.fokus) aktiv.focus({ preventScroll: true });
        },

        // Stand pro Fenster (sessionStorage); ohne Speicher (privates Fenster, gesperrt) bleibt die Liste einfach oben
        merken(art, wert = null) {
            const schluessel = `np-quellliste:${cfg.name}`;
            try {
                if (art === 'lesen') {
                    const s = JSON.parse(sessionStorage.getItem(schluessel) ?? 'null');
                    sessionStorage.removeItem(schluessel);
                    return s;
                }
                sessionStorage.setItem(schluessel, JSON.stringify(wert));
            } catch {
                return null;
            }
            return null;
        },

        sichtbar() {
            return [...this.$refs.liste.querySelectorAll('a[data-suchtext]:not([hidden])')];
        },

        filtern() {
            const begriffe = this.q.trim().toLowerCase().split(/\s+/).filter(Boolean);
            let n = 0;
            this.$refs.liste.querySelectorAll('a[data-suchtext]').forEach((el) => {
                const passt = begriffe.every((b) => el.dataset.suchtext.includes(b));
                el.hidden = !passt;
                if (passt) n++;
            });
            this.$refs.liste.querySelectorAll('[data-gruppe]').forEach((g) => {
                g.hidden = !g.querySelector('a[data-suchtext]:not([hidden])');
            });
            this.treffer = n;
        },

        leeren(ev) {
            if (!this.q) return;
            ev.preventDefault();
            this.q = '';
            this.filtern();
        },

        ersten() {
            this.sichtbar()[0]?.click();
        },

        // Klick auf einen Eintrag: Filter an den Link hängen, Scrollstand und Fokusherkunft merken
        oeffnen(ev) {
            const a = ev.target.closest('a[href]');
            if (!a || ev.defaultPrevented) return;
            const url = new URL(a.href);
            const q = this.q.trim();
            if (q) url.searchParams.set('suche', q);
            else url.searchParams.delete('suche');
            a.href = url.toString();
            this.merken('schreiben', { scroll: this.$refs.liste.scrollTop, fokus: ev.detail === 0 });
        },

        bewegen(schritt) {
            const liste = this.sichtbar();
            if (!liste.length) return;
            const i = liste.indexOf(document.activeElement);
            if (i === 0 && schritt < 0) {
                this.$root.querySelector('input[type="search"]')?.focus();
                return;
            }
            const ziel = i === -1 ? liste[0] : liste[Math.min(i + schritt, liste.length - 1)];
            ziel.focus({ preventScroll: true });
            ziel.scrollIntoView({ block: 'nearest' });
        },
    }));
}
