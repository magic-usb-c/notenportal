export const meta = {
  name: 'notenportal-audit',
  description: 'Audit des Notenportals in sechs Dimensionen (Dunkelmodus, Design, UX je Rolle, Korrektheit, Tastatur und Fokus), jeder Befund zweifach gegnerisch geprüft',
  whenToUse: 'Vor einem Blockabschluss oder nach grösseren Änderungen an Views, CSS oder Controllern. args: { dimensionen?: string[], max?: number }',
  phases: [
    { title: 'Audit', detail: 'ein Prüfagent je Dimension (sonnet, high)' },
    { title: 'Verifikation', detail: 'je Befund zwei Linsen: Nachweis im Code und Nutzen für die Person (opus, xhigh)' },
  ],
}

const KONTEXT = `
Projekt: Notenportal, Laravel 13, MariaDB, Blade + Tailwind 4 (CSS-first, Tokens in resources/css/theme.css und app.css) + Alpine.js.
Arbeitsverzeichnis ist die Repo-Wurzel (Branch main). Nichts ist produktiv.
Rollen und Pfade: Lernende ohne Präfix (/dashboard, /grades, /qualification, /modules, /documents, /settings),
Berufsbildner unter /trainer, Admin unter /admin. Views: resources/views/{lernender,verwaltung,admin,dashboards,noten,module,
dokumente,rechner,abschluss,settings,profile,auth,feedback,import,notifications,components,layouts}.
Controller: app/Http/Controllers/{Lernender,Verwaltung,Admin,Auth} und die Dateien direkt im Ordner. Rechenregeln: app/Services/Auswertung.
Massstab der Oberfläche: Desktop-Browser 1920×1080 bis 2560×1440 im Dunkelmodus nach Apple HIG. Hell und schmale Fenster
dürfen nicht brechen, bekommen aber keine eigene Gestaltungsarbeit. Keine Mobile- oder Touch-Kriterien.
Pflichtlektüre vor dem Urteil: .claude/skills/notenportal-ui/SKILL.md (Tokens, Typografie, Flächen, Muster) und
.claude/skills/notenportal-dunkelmodus/SKILL.md (HIG-Regeln mit Quelle, Prüfliste §5).
Kurzfassung: nur Token-Klassen (bg-bg, bg-card, bg-surface-2, bg-input, bg-fill, text-text, text-muted, text-faint, text-ghost,
border-border, border-border-strong, bg-accent + text-accent-contrast, text-accent-text, outline-ring, text-note-*).
Verboten: bg-white, bg-gray-*, bg-slate-*, text-black, text-white, #hex, Palettenfarben für Bedeutung, text-accent für Text,
uppercase tracking-widest, font-extrabold/black, harte Schriftgrössen, max-w-7xl, rounded-3xl, entfernte Klassen
(accent-glow, np-glow-*, np-card-lift, np-btn-tactile, glass-lift, glass-subtle, scale-Effekte, blur-3xl-Orbs).
Materialien: glass-bar nur Hauptnavigation und Sticky-Toolbar, glass-overlay nur Menüs, Paletten, Toasts, Popover;
Karten, Tabellen, Formulare, Diagramme ohne Glas. Notenfarben nur über NotenSkala::text()/badge() und text-note-*-Tokens.
Texte: Schweizer Hochdeutsch, ss statt ß, keine Hinweistexte oder Entwicklernotizen in der Oberfläche.
Daten: Noten-Queries mit whereNull('geloescht_am'); Lernende sehen nur eigene Daten (ID aus der Session), Berufsbildner nur
aktiv betreute Lernende; Durchschnitte nur aus App\\Services\\Auswertung; Betriebsspezifisches aus der DB.
Du bist nur Auditor: lies Dateien, führe höchstens lesende Befehle aus, ändere nichts, keine Git-, Composer- oder npm-Befehle.
Kein Befund ohne Datei und Zeile. Lies die Stelle wirklich, bevor du sie meldest.`

const FINDINGS_SCHEMA = {
  type: 'object',
  properties: {
    findings: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          titel: { type: 'string', description: 'Kurztitel des Problems' },
          datei: { type: 'string', description: 'Pfad relativ zur Repo-Wurzel' },
          zeile: { type: 'number', description: 'Zeilennummer, 0 wenn unbekannt' },
          schweregrad: { type: 'string', enum: ['hoch', 'mittel', 'niedrig'] },
          regel: { type: 'string', description: 'Verletzte Regel mit Fundstelle (Skill und Abschnitt, HIG-Seite oder CLAUDE.md)' },
          beschreibung: { type: 'string', description: 'Was genau falsch ist und was die Person im Browser sieht oder erlebt' },
          fix: { type: 'string', description: 'Konkreter, minimaler Fix' },
        },
        required: ['titel', 'datei', 'zeile', 'schweregrad', 'regel', 'beschreibung', 'fix'],
      },
    },
  },
  required: ['findings'],
}

const VERDICT_SCHEMA = {
  type: 'object',
  properties: {
    real: { type: 'boolean', description: 'true nur, wenn der Befund nach eigener Prüfung hält' },
    begruendung: { type: 'string', description: 'Was du selbst gelesen oder gerechnet hast, mit Datei und Zeile' },
    aufwand: { type: 'string', enum: ['klein', 'mittel', 'gross'] },
  },
  required: ['real', 'begruendung', 'aufwand'],
}

const DIMENSIONEN = [
  {
    key: 'dunkelmodus',
    prompt: `${KONTEXT}
Dimension: DUNKELMODUS nach Apple HIG. Lies resources/css/theme.css (Block .dark) und resources/css/app.css vollständig, dann die Layouts und Komponenten in resources/views/layouts und resources/views/components, danach Stichproben je Rolle.
1. Ebenen: sind bg-bg, bg-card, bg-surface-2 und die Overlay-Flächen im Dunkeln vier erkennbar verschiedene Helligkeiten (hellere Fläche = vorne)? Rechne die Luminanz aus den RGB-Tripeln.
2. Kontrast: text-text und text-muted auf bg-bg, bg-card, bg-surface-2, bg-input und auf glass-bar/glass-overlay mindestens 4.5:1 (WCAG-Formel, selbst rechnen, z. B. mit node -e). text-faint/text-ghost für lesbaren Inhalt verwendet?
3. Reines Schwarz oder Weiss als Fläche oder Text; weisse Bildhintergründe (Logos, Diagramme, Mail-Vorschau), die im Dunkeln leuchten.
4. Glas auf Inhaltsflächen (Karten, Tabellen, Formulare, Diagramme) oder Transparenz auf farbigen Zuständen (bg-accent, Notenfarben).
5. dark:-Varianten in Views, die ein Token schon abdeckt; Schatten als einzige Tiefe im Dunkeln.
6. Akzent mehrfach als Hintergrund in einer Ansicht; Farbe als einziger Träger einer Bedeutung.
Höchstens ${'${MAX}'} Befunde, sortiert nach Sichtbarkeit bei 1920 px.`,
  },
  {
    key: 'design',
    prompt: `${KONTEXT}
Dimension: DESIGN-KONSISTENZ mit dem UI-Skill. Durchsuche resources/views systematisch mit Grep und lies Treffer.
1. Verbotene Klassen und Hardcodes (Liste im Kontext), entfernte Klassen, max-w-7xl, rounded-3xl, text-[..px], style="color/font-size".
2. Abweichungen von den Mustern in UI-Skill §7: Primärknopf (genau einer je Ansicht), Sekundär-/Tertiärknopf, Formularfeld mit label/for und feldgenauem @error, Karte (rounded-xl border border-border bg-card), Tabelle (bg-surface-2-Kopf, text-2xs, tabular-nums, Zahlen rechtsbündig), Note-Badge, Leerzustand mit nächstem Schritt.
3. Seitenkopf nicht über <x-seitenkopf>, Container nicht np-seite, Flash nicht über <x-toast>, Menü nicht über <x-dropdown>.
4. Typografie: Versalien-Labels, font-extrabold/black, Grössen ausserhalb der Skala, mehr als eine Heldenzahl je Seite.
5. ß in UI-Texten, Hinweistexte oder Entwicklernotizen in der Oberfläche.
Höchstens ${'${MAX}'} Befunde, sortiert nach Häufigkeit und Sichtbarkeit.`,
  },
  {
    key: 'ux-lernender',
    prompt: `${KONTEXT}
Dimension: UX LERNENDE. Lies resources/views/dashboards/lernender*.blade.php, resources/views/{lernender,noten,module,dokumente,rechner,abschluss} und die Controller in app/Http/Controllers/Lernender sowie die Noten-/Modul-/Dokument-Controller direkt im Ordner.
1. Sackgassen: Seiten ohne Weg zurück oder weiter, Aktionen vom Dashboard, die im Bereich selbst fehlen (oder umgekehrt).
2. Zustände: leer (mit nächstem Schritt?), lädt, Fehler, sehr viele Noten – alle vier je Seite.
3. Formulare: old()-Werte nach Validierungsfehler, Fehler je Feld, Pflichtfeld-Stern, Doppelabsenden (loading), Rückmeldung nur als Toast.
4. Affordanz: Klickbares sieht klickbar aus, Hover/Fokus vorhanden, Primäraktion eindeutig, Reihenfolge nach Wichtigkeit (oben links zuerst).
5. Rechner, Druckansicht, Export: hängt die Kette zusammen, stimmen Rückwege?
Urteile als anspruchsvolle 17-jährige Person am 1920-px-Monitor. Höchstens ${'${MAX}'} Befunde.`,
  },
  {
    key: 'ux-trainer-admin',
    prompt: `${KONTEXT}
Dimension: UX BERUFSBILDNER UND ADMIN. Lies resources/views/dashboards/{berufsbildner,admin}*.blade.php, resources/views/{verwaltung,admin,import,settings} und app/Http/Controllers/{Verwaltung,Admin}.
1. Verknüpfung: Dashboard-Aktionen ohne Entsprechung im Bereich, fehlende Links zwischen zusammengehörigen Seiten (Lernende ↔ Noten ↔ Kommentare ↔ Berichte).
2. Tabellen: Leerzustand, Sortierung/Filter bei langen Listen, Aktionen nur per Hover ohne Tastaturweg (group-focus-within), Spaltenausrichtung.
3. Formulare und Stammdaten: Konsistenz der gemeinsamen _formular-Views, Löschbestätigung über den HIG-Dialog (kein window.confirm), old()/Fehler je Feld.
4. Einstellungen: sofort speichernde Schalter dort, wo macOS es täte; Speichern-Knopf nur bei Formularen mit Validierung.
5. Berichte und Import: Verständlichkeit, Drill-down, Rückmeldung bei Fehlern.
Höchstens ${'${MAX}'} Befunde, sortiert nach Alltagsnutzen.`,
  },
  {
    key: 'korrektheit',
    prompt: `${KONTEXT}
Dimension: KORREKTHEIT UND SICHERHEIT. Lies alle Controller unter app/Http/Controllers, die Form Requests, Policies und app/Services vollständig.
1. Noten-Queries ohne whereNull('geloescht_am'); Durchschnitte ausserhalb von App\\Services\\Auswertung (SQL, Controller, View).
2. Berechtigung: Lernenden-ID aus dem Request statt aus der Session, fehlender Betreuungs-Check für Berufsbildner, IDOR über Routenparameter, fehlende Policy.
3. Validierung: fehlende max-Längen, fehlende exists-Regeln, Datumslogik (Ende vor Beginn), Mass Assignment.
4. Raw-SQL ohne Binding; N+1 in Schleifen; Division durch null bei leeren Notenlisten.
5. Betriebsspezifisches (Firmenname, Grenzwerte, Fristen) hart im Code statt aus der DB.
6. Mutierende Actions ohne Redirect mit success/error-Flash.
Höchstens ${'${MAX}'} Befunde; hoch = Daten- oder Sicherheitsfehler mit konkretem Angriffs- oder Fehlerfall.`,
  },
  {
    key: 'tastatur-fokus',
    prompt: `${KONTEXT}
Dimension: TASTATUR, FOKUS UND BARRIEREFREIHEIT AM DESKTOP. Durchsuche resources/views und resources/js.
1. Aktionen nur per Hover (opacity-0 group-hover) ohne group-focus-within oder Tastaturweg.
2. Icon-Knöpfe ohne aria-label; Eingaben ohne <label for>; Fehler ohne aria-describedby.
3. Alpine-Dialoge, Dropdowns, Drawer, Befehlspalette: Escape schliesst, Fokus wandert hinein und zurück, Fokusfalle im Modal, aria-modal/role.
4. Fokusring: focus-visible mit outline-ring auf jedem Bedienelement, nicht durch outline-none entfernt; Ring im Dunkeln sichtbar (≥ 3:1).
5. Trefferfläche unter 24 px, Tab-Reihenfolge entgegen der Lesereihenfolge, fehlender Browser-Titel je Seite, prefers-reduced-motion nicht respektiert.
Keine Touch- oder Mobile-Kriterien. Höchstens ${'${MAX}'} Befunde.`,
  },
]

const gewaehlt = Array.isArray(args?.dimensionen) && args.dimensionen.length
  ? DIMENSIONEN.filter(d => args.dimensionen.includes(d.key))
  : DIMENSIONEN
const MAX = Number.isInteger(args?.max) && args.max > 0 ? args.max : 8
log(`Audit über ${gewaehlt.map(d => d.key).join(', ')} mit höchstens ${MAX} Befunden je Dimension`)

const linsen = [
  {
    key: 'nachweis',
    text: `Linse NACHWEIS. Öffne die genannte Datei an der genannten Zeile und die Dateien, die sie einbindet. Versuche den Befund zu widerlegen: existiert die Stelle, tut sie wirklich das Behauptete, ist es nicht längst anders gelöst (Komponente, Token, Layout)? real=false, wenn die Stelle fehlt, die Behauptung nicht aus dem Code folgt oder eine Ausnahme greift (Mail-Vorlagen, Notenblatt-Druck, SVG). real=true nur mit Datei und Zeile in der Begründung.`,
  },
  {
    key: 'nutzen',
    text: `Linse NUTZEN UND REGEL. Prüfe, ob der Befund für eine Person am 1920- bis 2560-px-Monitor im Dunkelmodus spürbar ist, gegen welche konkrete Regel er verstösst (nenne Skill und Abschnitt oder die HIG-Seite; lies die Regel nach) und ob der vorgeschlagene Fix regelkonform und minimal ist. real=false bei reiner Geschmacksfrage, bei einem Fix, der eine andere Regel verletzt, oder wenn die Regel die Stelle ausdrücklich erlaubt.`,
  },
]

// Verifikation in Bündeln: je Befund zwei opus-xhigh-Agenten; alle auf einmal reissen das
// Nutzungslimit der Sitzung in Minuten (SETUP-CLAUDE.md §12.11)
const PRUEF_BUENDEL = (args && args.pruefBuendel) || 3
const teile = (liste, n) => Array.from({ length: Math.ceil(liste.length / n) }, (_, i) => liste.slice(i * n, i * n + n))

phase('Audit')
const ergebnisse = await pipeline(
  gewaehlt,
  d => agent(d.prompt.replaceAll('${MAX}', String(MAX)), {
    label: `audit:${d.key}`, phase: 'Audit', schema: FINDINGS_SCHEMA, model: 'sonnet', effort: 'high',
  }),
  async (res, dim) => {
    const roh = (res && res.findings) ? res.findings.slice(0, MAX) : []
    if (!roh.length) { log(`${dim.key}: keine Befunde`); return [] }
    log(`${dim.key}: ${roh.length} Befunde, Verifikation mit zwei Linsen in Bündeln zu ${PRUEF_BUENDEL}`)
    const geprueft = []
    for (const buendel of teile(roh, PRUEF_BUENDEL)) geprueft.push(...await parallel(buendel.map(f => () =>
      parallel(linsen.map(l => () =>
        agent(`${KONTEXT}
Du bist gegnerischer Verifizierer. Ein Prüfagent behauptet:
Titel: ${f.titel}
Datei: ${f.datei}${f.zeile ? ` (Zeile ${f.zeile})` : ''}
Regel: ${f.regel}
Beschreibung: ${f.beschreibung}
Vorgeschlagener Fix: ${f.fix}

${l.text}
Im Zweifel real=false.`,
          { label: `verify:${l.key}:${dim.key}:${f.titel.slice(0, 28)}`, phase: 'Verifikation', schema: VERDICT_SCHEMA, model: 'opus', effort: 'xhigh' })
      )).then(urteile => ({
        ...f,
        dimension: dim.key,
        // Ausgefallener Prüfer (agent() → null, z. B. Nutzungslimit): real=null heisst ungeprüft, nie verworfen
        urteile: linsen.map((l, i) => ({ linse: l.key, ...(urteile[i] || { real: null, begruendung: 'kein Urteil – Agent abgebrochen', aufwand: null }) })),
      }))
    )))
    return geprueft
  }
)

const alle = ergebnisse.filter(Boolean).flat().filter(Boolean)
const bestaetigt = alle.filter(f => f.urteile.every(u => u.real === true))
// Eine Linse mit real=false verwirft; fehlt ein Urteil und keine Linse hat verworfen, bleibt der Befund ungeprüft
const verworfenListe = alle.filter(f => f.urteile.some(u => u.real === false))
const ungeprueft = alle.filter(f => !f.urteile.some(u => u.real === false) && f.urteile.some(u => u.real !== true))
const rang = { hoch: 0, mittel: 1, niedrig: 2 }
bestaetigt.sort((a, b) => (rang[a.schweregrad] ?? 3) - (rang[b.schweregrad] ?? 3))
if (ungeprueft.length) log(`${ungeprueft.length} Befunde ohne vollständiges Prüferurteil (Agent abgebrochen, z. B. Nutzungslimit) – ungeprüft, nicht verworfen; nach dem Reset mit resumeFromRunId fortsetzen`)
log(`Bestätigt: ${bestaetigt.length} von ${alle.length} Befunden (${verworfenListe.length} verworfen, ${ungeprueft.length} ungeprüft)`)

return {
  bestaetigt: bestaetigt.map(f => ({
    dimension: f.dimension,
    schweregrad: f.schweregrad,
    titel: f.titel,
    datei: f.datei,
    zeile: f.zeile || null,
    regel: f.regel,
    beschreibung: f.beschreibung,
    fix: f.fix,
    aufwand: f.urteile.find(u => u.linse === 'nachweis')?.aufwand || null,
    nachweis: f.urteile.find(u => u.linse === 'nachweis')?.begruendung || '',
    nutzen: f.urteile.find(u => u.linse === 'nutzen')?.begruendung || '',
  })),
  verworfen: verworfenListe.map(f => ({
    dimension: f.dimension, titel: f.titel, datei: f.datei,
    grund: f.urteile.filter(u => u.real === false).map(u => `${u.linse}: ${u.begruendung}`).join(' | '),
  })),
  // Ohne vollständiges Urteil: nicht in Ordnung, sondern offen – im nächsten Lauf neu prüfen
  ungeprueft: ungeprueft.map(f => ({
    dimension: f.dimension, schweregrad: f.schweregrad, titel: f.titel, datei: f.datei, zeile: f.zeile || null,
    fehlt: f.urteile.filter(u => u.real !== true).map(u => u.linse).join(' + '),
  })),
}
