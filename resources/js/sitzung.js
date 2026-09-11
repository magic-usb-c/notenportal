// Sitzungs-Timeout-Warnung (Block AE): rein clientseitig, Texte kommen serverseitig über __()
// aus dem Layout-Partial (layouts/_sitzung.blade.php) – JsTexte muss dafür nicht erweitert werden.
// Ohne gebautes JS bleibt der Dialog einfach unsichtbar (hidden-Attribut).
//
// Letzter Kontakt liegt in localStorage (np-sitzung-kontakt), gesetzt bei jedem Seitenaufruf und
// nach jedem erfolgreichen Keep-Alive. Über das storage-Event sehen andere Tabs das sofort –
// Aktivität in Tab B verlängert so auch die Frist in Tab A. Ein Intervall alle 15 s rechnet mit
// echten Zeitstempeln, damit es auch nach Standby oder gedrosselten Timern stimmt.
//
// Keep-Alive nur per Klick, nie automatisch bei Maus-/Tastaturaktivität – sonst hält ein offen
// gelassener Tab auf einem Schulgerät die Session ewig.

const KONTAKT_KEY = 'np-sitzung-kontakt';
const INTERVALL_MS = 15000;
const WARNUNG_VOR_MS = 120000;
const ABMELDEN_VOR_MS = 5000; // Puffer für Uhrenabweichung zwischen Client und Server

function jetztMerken() {
    try {
        localStorage.setItem(KONTAKT_KEY, String(Date.now()));
    } catch (e) {
        /* privater Modus o. ä. – dann läuft die Warnung ohne Cross-Tab-Sync */
    }
}

function letzterKontakt() {
    try {
        const wert = Number(localStorage.getItem(KONTAKT_KEY));
        return Number.isFinite(wert) && wert > 0 ? wert : Date.now();
    } catch (e) {
        return Date.now();
    }
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export function registriereSitzung() {
    const dialog = document.getElementById('np-sitzung-dialog');
    if (!dialog) return;

    const laufzeitMs = Number(dialog.dataset.sekunden) * 1000;
    if (!Number.isFinite(laufzeitMs) || laufzeitMs <= 0) return;

    const keepAliveUrl = dialog.dataset.keepalive;
    const loginUrl = dialog.dataset.login;
    const logoutUrl = dialog.dataset.logout;
    const bleibenBtn = document.getElementById('np-sitzung-bleiben');
    const abmeldenBtn = document.getElementById('np-sitzung-abmelden');

    let abgemeldet = false;
    let gewarnt = false;

    jetztMerken();

    const ablauf = () => letzterKontakt() + laufzeitMs;

    function zwangsabmeldung() {
        if (abgemeldet) return;
        abgemeldet = true;

        navigator.serviceWorker?.controller?.postMessage({ type: 'ABMELDEN' });

        // Erst abmelden, dann weiterleiten: käme GET /login vor dem Logout an, leitete die
        // guest-Middleware zurück aufs Dashboard und die Noten stünden wieder auf dem Bildschirm.
        const abbruch = new AbortController();
        const frist = setTimeout(() => abbruch.abort(), 3000);
        fetch(logoutUrl, {
            method: 'POST',
            keepalive: true,
            signal: abbruch.signal,
            headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        })
            .catch(() => {})
            .finally(() => {
                clearTimeout(frist);
                location.replace(loginUrl + '?abgelaufen=1');
            });
    }

    async function keepAlive() {
        try {
            const antwort = await fetch(keepAliveUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
            });

            if (antwort.status === 204) {
                jetztMerken();
                gewarnt = false;
                dialog.hidden = true;
                return;
            }

            if (antwort.status === 401 || antwort.status === 419) {
                zwangsabmeldung();
            }
        } catch (e) {
            // Netzwerkfehler: nichts tun, der nächste Tick prüft erneut
        }
    }

    function zeigeWarnung() {
        if (gewarnt) return;
        gewarnt = true;
        dialog.hidden = false;
        bleibenBtn?.focus();
    }

    function verstecke() {
        gewarnt = false;
        dialog.hidden = true;
    }

    function pruefen() {
        const rest = ablauf() - Date.now();

        if (rest <= ABMELDEN_VOR_MS) {
            zwangsabmeldung();
            return;
        }

        if (rest <= WARNUNG_VOR_MS) {
            zeigeWarnung();
        } else if (gewarnt) {
            verstecke();
        }
    }

    bleibenBtn?.addEventListener('click', () => keepAlive());
    abmeldenBtn?.addEventListener('click', () => zwangsabmeldung());

    // Aktivität in einem anderen Tab (Seitenaufruf oder Keep-Alive dort) verlängert auch hier.
    window.addEventListener('storage', (event) => {
        if (event.key === KONTAKT_KEY) {
            verstecke();
        }
    });

    // Aus dem Back/Forward-Cache zurück: bei abgelaufener Frist sofort abmelden, keine Noten mehr sichtbar.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted && ablauf() - Date.now() <= 0) {
            zwangsabmeldung();
        }
    });

    setInterval(pruefen, INTERVALL_MS);
    pruefen();
}
