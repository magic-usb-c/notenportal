export const meta = {
  name: 'notenportal-funktionspruefung',
  description: 'Funktionsprüfung des Notenportals: Fehler je Rolle, Notenlogik, Sicherheit, Mails, Jobs und Klicktests im Browser finden, jeden Befund mit Sonde nachweisen und gegnerisch prüfen',
  whenToUse: 'Vor einer Fertigmeldung oder nach grösseren Änderungen an Controllern, Services, Mails oder Jobs. args: { dimensionen?: string[], dbs: string[] (eigene *_test-DBs, je Agent eine), env: "<env.sh>", scratch: "<ordner>", max?: number, hinweise?: { <dimension>: "Schwerpunkt dieser Runde" } }. Mehrere Läufe mit getrennten dimensionen und dbs dürfen gleichzeitig laufen.',
  phases: [
    { title: 'Suche', detail: 'ein Finder je Dimension (sonnet high), Sonden in eigener Test-DB' },
    { title: 'Nachweis', detail: 'je Befund ein Nachweis per Sonde oder Messung (sonnet high)' },
    { title: 'Gegenprüfung', detail: 'je nachgewiesenem Befund mit Schwere hoch/mittel ein Widerleger (opus high)' },
  ],
}

const A = typeof args === 'string' ? JSON.parse(args) : (args || {})
const ENV = A.env
const SCRATCH = A.scratch
const DBS = Array.isArray(A.dbs) && A.dbs.length ? A.dbs : null
const MAX = A.max || 12
if (!ENV || !SCRATCH || !DBS) throw new Error('args.env, args.scratch und args.dbs sind Pflicht')

let zaehler = 0
const naechsteDb = () => DBS[(zaehler++) % DBS.length]

const KONTEXT = (db) => `
Projekt: Notenportal (Laravel 13, MariaDB, Blade + Tailwind 4 + Alpine). Repo-Wurzel /var/www/notenportal, Branch main.
Rollen: Lernende (Routen learner.*, Pfade ohne Präfix wie /learner, /grades, /qualification, /modules, /documents, /exams, /settings),
Berufsbildner (trainer.*, /trainer), Admin (admin.*, /admin). Controller app/Http/Controllers/{Lernender,Verwaltung,Admin,Auth,…},
Services app/Services (Auswertung = einzige Quelle für Durchschnitte, Regeln docs/notenlogik.md, Notenbaum docs/auftrag/NOTENBAUM.md),
Mails app/Notifications/PortalMail.php + app/Services/Notifications + resources/views/mail, Befehle app/Console, Zeitplan routes/console.php.
Funktionsumfang: docs/funktionsumfang.md. Bewusst Gelassenes (KEIN Befund): docs/audit-backlog.md und «Offen für David» in docs/auftrag/UEBERGABE.md.

UMGEBUNG (VM, wird von Apache live ausgeliefert):
- Vor JEDEM Bash-Befehl mit php/artisan/node: \`source ${ENV}\`. Es lenkt den Config-/Routen-Cache um; ohne das trifft
  jedes \`php artisan …\` die Hauptdatenbank notenportal. NIE gegen die Datenbank notenportal arbeiten, nie Daten dort ändern.
- Deine eigene Test-DB: ${db}. Sonden als PHPUnit-Test AUSSERHALB des Repos schreiben, in ${SCRATCH}/sonden/<name>Test.php,
  namespace Tests\\Sonde, extends Tests\\TestCase (RefreshDatabase ist dort schon eingebunden, NICHT nochmals use RefreshDatabase).
  Ausführen: \`source ${ENV} && DB_DATABASE=${db} php vendor/bin/phpunit ${SCRATCH}/sonden/<name>Test.php\`.
  Factories: User::factory()->admin()|berufsbildner()|lernender(), Passwort UserFactory::PASSWORT. Vorbilder in tests/Feature.
- Demo-Instanz: \${NP_URL} (http://127.0.0.1:8099, DB notenportal_demo, Wegwerfdaten). Konten laura.frei (Admin), michael.baumann
  (Berufsbildner), nina.huber/elena.fischer (Lernende), livia.gerber (Lernende ohne Noten), alle @demo.example. Passwort NUR über
  \$NP_TEST_PW (env.sh setzt es) – nie ausgeben, nie als Argument. Browser-Werkzeuge: tools/pruefung/{shot,klick,rundgang}.mjs
  (Skill-Datei .claude/skills/notenportal-pruefwerkzeuge/SKILL.md), Playwright über NP_PLAYWRIGHT. Eigene Playwright-Skripte in
  ${SCRATCH}/ ablegen; tools/pruefung/browser.mjs exportiert starteBrowser/anmelden (lies es).
- Du bist Prüfer: KEINE Änderung an Dateien im Repo, keine Git-Schreibbefehle, kein composer, kein npm. Nur ${SCRATCH} beschreiben.
- Nicht raten: jeder Befund braucht Datei:Zeile UND einen Beleg (Sonde, die rot ist, oder Befehl mit Ausgabe). Lies die Stelle.
- Keine Stilfragen, keine Geschmacksfragen, keine reinen Refactorings. Gesucht sind Fehler, die eine Person trifft: falsches
  Ergebnis, 500er, Datenverlust, Daten anderer sichtbar, Aktion ohne Wirkung, falsche/fehlende Meldung, kaputter Link, Mail
  falsch oder gar nicht versendet, Sicherheitslücke.`

const BEFUND_SCHEMA = {
  type: 'object',
  properties: {
    befunde: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          titel: { type: 'string' },
          datei: { type: 'string', description: 'repo-relativ' },
          zeile: { type: 'number' },
          schwere: { type: 'string', enum: ['hoch', 'mittel', 'niedrig'] },
          wirkung: { type: 'string', description: 'Was die Person erlebt (Rolle, Schritt, Ergebnis)' },
          beleg: { type: 'string', description: 'Sonde mit Pfad und Ausgabe, oder Befehl mit Ausgabe' },
          ursache: { type: 'string' },
          fix: { type: 'string', description: 'minimaler Fix mit Dateien' },
        },
        required: ['titel', 'datei', 'zeile', 'schwere', 'wirkung', 'beleg', 'ursache', 'fix'],
      },
    },
    abgedeckt: { type: 'array', items: { type: 'string' }, description: 'geprüfte Abläufe ohne Befund (kurz)' },
    nicht_geprueft: { type: 'array', items: { type: 'string' } },
  },
  required: ['befunde', 'abgedeckt', 'nicht_geprueft'],
}

const NACHWEIS_SCHEMA = {
  type: 'object',
  properties: {
    real: { type: 'boolean', description: 'true nur, wenn du den Fehler selbst reproduziert hast' },
    beleg: { type: 'string', description: 'eigene Sonde/Befehl und Ausgabe' },
    sonde: { type: 'string', description: 'Pfad der Sonde, die ohne Fix rot ist (leer, wenn keine)' },
    fix: { type: 'string', description: 'bestätigter oder besserer minimaler Fix' },
    schwere: { type: 'string', enum: ['hoch', 'mittel', 'niedrig'] },
  },
  required: ['real', 'beleg', 'sonde', 'fix', 'schwere'],
}

const WIDERLEGUNG_SCHEMA = {
  type: 'object',
  properties: {
    widerlegt: { type: 'boolean', description: 'true, wenn der Befund kein Fehler ist (gewollt, dokumentiert, Sonde falsch, nicht erreichbar)' },
    begruendung: { type: 'string' },
    fix_risiko: { type: 'string', description: 'Was der vorgeschlagene Fix brechen könnte' },
  },
  required: ['widerlegt', 'begruendung', 'fix_risiko'],
}

const DIMENSIONEN = {
  lernende: `Dimension LERNENDE – alle Abläufe der Rolle Lernende End-to-End: Note erfassen/bearbeiten/löschen/wiederherstellen
(app/Http/Controllers/Lernender, app/Services/NoteService* o. ä.), Semesterzuordnung am Rand (erster/letzter Tag, ausserhalb der Lehrzeit),
Prüfungen/Agenda, Ziele, Zielrechner, Abschluss/Qualifikation, Module, Dokumente (Upload, Download, Löschen), Notenimport (Excel/CSV/PDF,
Vorschau, Übernahme), Zeugnis-Abgleich, Einstellungen, Profil, Passwortwechsel, Kalender-Abo. Je Formular: Validierung, Grenzwerte
(1.0/6.0/Viertelnoten, Stufen A/B/C), leere Felder, Doppelklick, fremde IDs im Request (ID darf nur aus der Session kommen).
Höchstens ${MAX} Befunde.`,
  berufsbildner: `Dimension BERUFSBILDNER – alle Abläufe der Rolle: Dashboard, Lernendenliste, Cockpit mit Reitern, Notenliste mit Inspektor,
Kommentare (anlegen, löschen nach Betreuungsende), «gesehen» markieren, Prüfungstermine, Betreuung (Beginn, Ende, Wechsel), Rechner,
Abschluss, Export. Prüfe besonders: nur AKTIV betreute Lernende sichtbar (vergangene/künftige Betreuung = 404/403), Zählwerte und Ampeln
stimmen mit den Daten, Benachrichtigungen werden ausgelöst. Höchstens ${MAX} Befunde.`,
  admin: `Dimension ADMIN – Benutzerkonten (anlegen, bearbeiten, Rolle wechseln, deaktivieren, Passwort zurücksetzen, Einladung),
Stammdaten (Lehrberufe, Module mit Katalogimport, Fächer, Kategorien, Notenbäume Import/Export, Semester), Einrichtungsassistent
Schritte 1–8, Betrieb (Einstellungen, Darstellung, E-Mail inkl. Testmail, Sitzung, Sicherung/Wiederherstellung, Kopie ausser Haus),
Berichte und Export, Feedback, Versand- und Aktivitätsprotokoll, Benachrichtigungsvorgaben. Prüfe Lösch-/Sperrregeln, Folgen von
Änderungen auf bestehende Noten, Mehrfachabsenden, Fehlermeldungen. Höchstens ${MAX} Befunde.`,
  notenlogik: `Dimension NOTENLOGIK – app/Services/Auswertung (Rechenkern, Notenbaum, Rundung, Promotion, Zielrechner, Statistik,
Verteilung, Lernstand) gegen docs/notenlogik.md und docs/auftrag/NOTENBAUM.md und die gesicherten Fakten in docs/auftrag/LAGE.md §2/§4.
Rechne Fälle selbst nach (Sonden): Gewichte, Rundung auf halbe/Zehntel, Fallnoten, max. ungenügend, Minuspunkte, «zählt nicht»,
Stufen (Sport), gelöschte Noten (geloescht_am), Teilnoten/Prüfungsgewichte, leere Kategorien, Lernende ohne Noten, Semestergrenzen,
BM-Promotion. Auch: zeigen Views/Exporte/Notenblatt/Mails dieselben Werte wie der Rechenkern? Höchstens ${MAX} Befunde.`,
  sicherheit: `Dimension SICHERHEIT – Autorisierung je Route (Policies, role:-Middleware, Scopes), IDOR in allen Routen mit IDs, Mass
Assignment, Datei-Uploads (Typ, Grösse, Pfad, Auslieferung privater Dateien, Logo, Feedback-Anhänge, Import), signierte URLs
(Abmelden, Kalender-Abo), Passwort-Zurücksetzen/Einladung (Token-Ablauf, Wiederverwendung), Sitzung (Fixierung, Abmelden, Timeout),
XSS ({!! !!}, x-html, JSON in Attributen), CSRF-Ausnahmen, Rate-Limits, Raw-SQL ohne Binding, Header (CSP, HSTS), Fehlerseiten ohne
Interna, Logs ohne Passwörter. Belege jede Lücke mit einer Sonde. Höchstens ${MAX} Befunde.`,
  mails: `Dimension MAILS – jede Mailart aus app/Services/Notifications/NotificationCatalog.php plus Konto-Mails (AccountMails, Passwort
zurücksetzen, Einladung) und Tageszusammenfassung. (1) Auslösung: wird jede Mail beim richtigen Ereignis an die richtigen Personen
erzeugt (Rolle, Einstellungen je Person, Häufigkeit sofort/täglich/nie, Pflichtmails), landet sie im MailLog, wird bei Fehler
korrekt markiert? (2) Inhalt: rendere JEDE Mailart mit realistischen Demo-Daten als HTML und Text (Sonde oder Skript in ${SCRATCH}/mails/),
prüfe Betreff, Preheader, Anrede, Texte (Schweizer Hochdeutsch, ss), Zahlenformat, Links (absolut, richtige Route, signiert wo nötig,
Abmelden funktioniert inkl. One-Click-POST), Textversion vollständig. (3) Darstellung: Screenshots jeder HTML-Mail mit Playwright hell
und dunkel (colorScheme) bei 600 px und 1200 px Breite nach ${SCRATCH}/mails/, mit Read ansehen; Mailprogramm-Tauglichkeit
(Tabellenlayout, Inline-Stile, keine CSS-Variablen, Outlook). Melde Fehler und klar unschöne Darstellung (abgeschnitten, Kontrast,
Ausrichtung, leere Abschnitte, doppelte Abstände). Höchstens ${MAX} Befunde.`,
  jobs: `Dimension JOBS UND BEFEHLE – routes/console.php (Zeitplan), app/Console/Commands (notenportal:*), Jobs/Listener, Warteschlange:
Sicherung und Wiederherstellung, Aufräumen, Erinnerungen (Prüfungen, Semesterende, Inaktivität, unter Grenze), Tageszusammenfassung,
Kalenderabgleich, Bereitschaft, Pilot-Vorbereitung, Katalogimport. Prüfe Zeitzone (Europe/Zurich), Idempotenz (doppelter Lauf),
Grenzfälle (keine Daten, gelöschte Noten, beendete Betreuung), Fehlerbehandlung und Meldungen. Höchstens ${MAX} Befunde.`,
  klicktest: `Dimension KLICKTEST IM BROWSER – spiele auf der Demo-Instanz die wichtigsten Abläufe je Rolle mit Playwright wirklich durch
(eigene Skripte in ${SCRATCH}/klick/, Vorbild tools/pruefung/klick.mjs und browser.mjs): Lernende nina.huber: Note erfassen, bearbeiten,
löschen, Prüfung anlegen, Ziel setzen, Rechner bedienen, Dokument hochladen, Einstellungen speichern. Berufsbildner michael.baumann:
Note kommentieren, als gesehen markieren, Prüfungstermin, Cockpit-Reiter. Admin laura.frei: Konto anlegen, Fach anlegen/umbenennen/
löschen, Einstellung speichern, Bericht filtern und exportieren, Testmail (Mailer der Demo schreibt ins Log). Erfasse je Schritt:
HTTP-Status, Konsolenfehler, Erfolgsmeldung sichtbar, Ergebnis wirklich gespeichert (Seite neu laden), Fokus/Tastatur bei Dialogen.
Demo-Daten darfst du verändern (Wegwerf). Höchstens ${MAX} Befunde.`,
}

const gewaehlt = (Array.isArray(A.dimensionen) && A.dimensionen.length ? A.dimensionen : Object.keys(DIMENSIONEN))
  .filter((d) => DIMENSIONEN[d])
log(`Dimensionen: ${gewaehlt.join(', ')} · DBs: ${DBS.join(', ')}`)

const ergebnisse = await pipeline(
  gewaehlt,
  (dim) => {
    const db = naechsteDb()
    return agent(`${KONTEXT(db)}

${DIMENSIONEN[dim]}${A.hinweise && A.hinweise[dim] ? `

SCHWERPUNKT DIESER RUNDE (eine frühere Runde hat das Übrige schon abgedeckt und ihre Befunde sind behoben): ${A.hinweise[dim]}` : ''}

Vorgehen: Routen der Dimension mit \`source ${ENV} && php artisan route:list --except-vendor\` (gefiltert) erfassen, Controller/Services lesen,
Verdachtsstellen mit Sonden oder Browser-Skripten nachweisen. Erst melden, wenn der Beleg steht. Liste unter «abgedeckt», was du geprüft hast
und ohne Befund ist, unter «nicht_geprueft», was du nicht geschafft hast.`, { label: `suche:${dim}`, phase: 'Suche', model: 'sonnet', effort: 'high', schema: BEFUND_SCHEMA })
  },
  (fund, dim) => {
    if (!fund) return { dim, fund: null, befunde: [] }
    const gesehen = new Set()
    const eindeutig = fund.befunde.filter((b) => {
      const k = `${b.datei}:${Math.round((b.zeile || 0) / 5)}:${b.titel.slice(0, 30)}`
      if (gesehen.has(k)) return false
      gesehen.add(k)
      return true
    })
    log(`${dim}: ${eindeutig.length} Befunde, ${fund.abgedeckt.length} Abläufe ohne Befund`)
    return parallel(eindeutig.map((b) => async () => {
      const db = naechsteDb()
      const nachweis = await agent(`${KONTEXT(db)}

Ein Finder meldet diesen Befund (Dimension ${dim}). Reproduziere ihn UNABHÄNGIG – vertraue seiner Sonde nicht, schreibe eine eigene
(${SCRATCH}/sonden/Nachweis_<kurz>Test.php) oder führe einen eigenen Befehl aus. real=true nur, wenn du den Fehler selbst beobachtet hast.
Prüfe zuerst, ob der Punkt in docs/audit-backlog.md oder unter «Offen für David» (docs/auftrag/UEBERGABE.md) bewusst gelassen ist – dann real=false.

Befund: ${b.titel}
Datei: ${b.datei}:${b.zeile} · Schwere laut Finder: ${b.schwere}
Wirkung: ${b.wirkung}
Ursache laut Finder: ${b.ursache}
Beleg des Finders: ${b.beleg}
Fix-Vorschlag: ${b.fix}`, { label: `nachweis:${dim}:${b.titel.slice(0, 40)}`, phase: 'Nachweis', model: 'sonnet', effort: 'high', schema: NACHWEIS_SCHEMA })
      if (!nachweis || !nachweis.real) return { ...b, dim, nachweis, widerlegung: null, status: nachweis ? 'nicht reproduziert' : 'ungeprüft' }
      if (nachweis.schwere === 'niedrig') return { ...b, dim, nachweis, widerlegung: null, status: 'bestätigt' }
      const widerlegung = await agent(`Du bist Widerleger. Ein Befund im Notenportal (/var/www/notenportal, Laravel) wurde von zwei Agents als real gemeldet.
Versuche ihn zu WIDERLEGEN: Ist das Verhalten gewollt (Kommentar, Test, docs/funktionsumfang.md, docs/notenlogik.md, docs/audit-backlog.md,
«Offen für David» in docs/auftrag/UEBERGABE.md)? Ist die Sonde fehlerhaft (falsche Annahme, falscher Aufbau)? Ist der Weg für eine echte
Person gar nicht erreichbar? Bewerte zusätzlich das Risiko des Fix-Vorschlags. Lies Code und Sonde selbst; nur lesende Befehle
(vor php/artisan: source ${ENV}; eigene DB für Sonden: ${naechsteDb()}). Keine Änderung im Repo. Im Zweifel widerlegt=false.

Befund: ${b.titel} (${b.datei}:${b.zeile})
Wirkung: ${b.wirkung}
Ursache: ${b.ursache}
Nachweis: ${nachweis.beleg}
Sonde: ${nachweis.sonde || '–'}
Fix: ${nachweis.fix}`, { label: `widerlegen:${dim}:${b.titel.slice(0, 40)}`, phase: 'Gegenprüfung', model: 'opus', effort: 'high', schema: WIDERLEGUNG_SCHEMA })
      const status = !widerlegung ? 'bestätigt (Widerleger ausgefallen)' : (widerlegung.widerlegt ? 'widerlegt' : 'bestätigt')
      return { ...b, dim, nachweis, widerlegung, status }
    })).then((liste) => ({ dim, fund, befunde: liste.filter(Boolean) }))
  },
)

const alle = ergebnisse.filter(Boolean)
const bestaetigt = alle.flatMap((e) => e.befunde.filter((b) => b.status.startsWith('bestätigt')))
const verworfen = alle.flatMap((e) => e.befunde.filter((b) => !b.status.startsWith('bestätigt')))
const ausgefallen = gewaehlt.filter((d) => !alle.find((e) => e.dim === d && e.fund))
if (ausgefallen.length) log(`Ausgefallen (ungeprüft): ${ausgefallen.join(', ')}`)
log(`Bestätigt ${bestaetigt.length} · verworfen/ungeprüft ${verworfen.length}`)
return {
  bestaetigt: bestaetigt.map((b) => ({ dim: b.dim, titel: b.titel, datei: b.datei, zeile: b.zeile, schwere: b.nachweis?.schwere || b.schwere, wirkung: b.wirkung, ursache: b.ursache, fix: b.nachweis?.fix || b.fix, sonde: b.nachweis?.sonde || '', fix_risiko: b.widerlegung?.fix_risiko || '', status: b.status })),
  verworfen: verworfen.map((b) => ({ dim: b.dim, titel: b.titel, datei: b.datei, status: b.status, grund: b.widerlegung?.begruendung || b.nachweis?.beleg || '' })),
  abgedeckt: Object.fromEntries(alle.map((e) => [e.dim, e.fund ? { ok: e.fund.abgedeckt, offen: e.fund.nicht_geprueft } : null])),
  ausgefallen,
}
