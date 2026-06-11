export const meta = {
  name: 'notenportal-audit-v2',
  description: 'Mehrdimensionales Audit: Design-Konsistenz, Light-Mode, UX pro Rolle, Korrektheit, A11y/Mobile — adversarial verifiziert',
  phases: [
    { title: 'Audit', detail: '6 parallele Prüf-Agenten je Dimension' },
    { title: 'Verifikation', detail: 'jedes Finding wird adversarial geprüft' },
  ],
}

const KONTEXT = `
Projekt: Notenportal — Laravel 12 Webportal bei /var/www/notenportal (Git-Repo, Branch feature/claude-fertigstellung).
Ersetzt Excel-Notentabellen. 3 Rollen: Admin (/admin), Berufsbildner (/berufsbildner), Lernende (/noten).
Frontend: Blade-Templates (resources/views/), Tailwind v3 mit Design-Tokens, Alpine.js.
Design-System (PFLICHT, definiert in CLAUDE.md im Repo-Root — lies sie zuerst):
- Nur Token-Klassen: bg-bg, bg-card, bg-input, bg-accent, text-text, text-muted, text-accent, border-border
- NIEMALS hardcoded: bg-white, bg-gray-800/900, text-black, text-white (ausser SVG/Print), #hex in Blade-Views
- Liquid-Glass-Klassen in resources/css/app.css: .glass, .glass-subtle, .glass-btn, .glass-lift, .np-card-lift, .accent-glow
- CSS Custom Properties in resources/css/theme.css (Hell + Dunkel getrennt)
- Notenfarben: >=5.0 grün, >=4.0 emerald, >=3.5 gelb, <3.5 rot
- UI-Sprache: Schweizer Hochdeutsch (ss statt ß — ß ist verboten)
DB: Soft-Delete via geloescht_am auf noten (Queries brauchen whereNull). Berechtigungen via abort_if in Controllern.
BB darf nur Lernende mit aktiver Betreuung sehen (betreuungen: gueltig_von <= heute, gueltig_bis null oder >= heute).
Du bist NUR Auditor: Lies Dateien, ändere NICHTS.`

const FINDINGS_SCHEMA = {
  type: 'object',
  properties: {
    findings: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          titel: { type: 'string', description: 'Kurztitel des Problems' },
          datei: { type: 'string', description: 'Pfad relativ zum Repo-Root' },
          zeile: { type: 'number', description: 'Zeilennummer falls bekannt, sonst 0' },
          schweregrad: { type: 'string', enum: ['hoch', 'mittel', 'niedrig'] },
          beschreibung: { type: 'string', description: 'Was genau ist das Problem, was sieht der Benutzer' },
          fix: { type: 'string', description: 'Konkreter Fix-Vorschlag' },
        },
        required: ['titel', 'datei', 'schweregrad', 'beschreibung', 'fix'],
      },
    },
  },
  required: ['findings'],
}

const VERDICT_SCHEMA = {
  type: 'object',
  properties: {
    real: { type: 'boolean', description: 'true nur wenn das Problem nachweislich existiert und ein Fix klaren Nutzen hat' },
    begruendung: { type: 'string' },
    aufwand: { type: 'string', enum: ['klein', 'mittel', 'gross'] },
  },
  required: ['real', 'begruendung', 'aufwand'],
}

const DIMENSIONEN = [
  {
    key: 'design',
    prompt: `${KONTEXT}
Dimension: DESIGN-KONSISTENZ. Durchsuche resources/views/ systematisch:
1. Hardcodierte Farben (bg-white, bg-gray-*, text-black, text-white ausserhalb SVG/Print/drucken-Views, #hex)
2. Cards/Panels mit blossem bg-card wo vergleichbare Seiten .glass nutzen — Inkonsistenz zwischen Seiten derselben Rolle
3. Buttons/Badges/Form-Labels die vom CLAUDE.md-Standard abweichen (Primär-Button, Note-Badge, Label-Klassen)
4. Verbotenes ß in UI-Texten
5. Seiten die "flach" wirken (keine Glass/Lift/Tiefe) im Vergleich zu polierten Nachbarseiten
Nutze grep/Glob gezielt, lies auffällige Dateien. Max 8 Findings, sortiert nach Sichtbarkeit für Endbenutzer.`,
  },
  {
    key: 'lightmode',
    prompt: `${KONTEXT}
Dimension: LIGHT-MODE. Das Glass-System wurde primär im Dark-Mode entwickelt. Prüfe:
1. resources/css/theme.css — Hell-Werte der Glass-Variablen (--glass-border-alpha, --glass-highlight-alpha, --glass-shadow-alpha) und Token-Farben: Sind Glass-Panels im Hellen noch als Panels erkennbar (genug Kontrast zu --bg)?
2. resources/css/app.css — .glass/.glass-subtle/.glass-btn/.accent-glow: Funktionieren die rgba-Formeln mit Hell-Werten? Weisse Highlights (rgba(255,255,255,...)) auf hellem Grund = unsichtbar oder milchig?
3. Glows/Shadows die im Hellen zu schwach oder zu schmutzig wirken
4. Notenfarben-Klassen: dark:-Varianten vorhanden, aber stimmen die Hell-Varianten auf bg-card?
5. text-muted Kontrast im Hellen auf Glass-Hintergründen
Lies beide CSS-Dateien VOLLSTÄNDIG und beurteile die konkreten Zahlenwerte. Max 8 Findings.`,
  },
  {
    key: 'ux-lernender',
    prompt: `${KONTEXT}
Dimension: UX LERNENDER. Prüfe resources/views/lernender/, resources/views/dashboards/lernender.blade.php und app/Http/Controllers/Lernender/:
1. Workflow-Lücken: Sackgassen, fehlende Zurück-Links, fehlende Empty-States, fehlende Erfolgs-/Fehler-Rückmeldung
2. Formulare: fehlende old()-Werte nach Validierungsfehler, fehlende Fehleranzeige pro Feld, unklare Pflichtfelder
3. Fehlende Affordances: Dinge die klickbar sein sollten aber nicht sind, fehlende Hover-States
4. Druckansicht/Export/Rechner: Funktioniert die Kette, fehlen Hinweise?
5. Redundante oder verwirrende Elemente
Lies die Views wirklich, urteile als anspruchsvoller 17-jähriger Endbenutzer. Max 8 Findings.`,
  },
  {
    key: 'ux-bb-admin',
    prompt: `${KONTEXT}
Dimension: UX BERUFSBILDNER + ADMIN. Prüfe resources/views/berufsbildner/, resources/views/admin/, dashboards/berufsbildner.blade.php, dashboards/admin.blade.php und die zugehörigen Controller:
1. Workflow-Lücken: Aktionen die vom Dashboard aus gehen aber nicht vom jeweiligen Tab (oder umgekehrt), fehlende Verlinkungen zwischen zusammengehörigen Seiten
2. Tabellen: fehlende Empty-States, Aktionen nur per Hover sichtbar (Touch!), fehlende Sortierung/Filter wo Listen lang werden
3. Formulare: Validierungs-Feedback, old()-Werte, Pflichtfeld-Kennzeichnung
4. Stammdaten-CRUD (admin/stammdaten/): Konsistenz untereinander, Lösch-Bestätigungen
5. Berichte: Verständlichkeit, fehlende Drill-Downs
Lies die Views wirklich. Max 8 Findings, sortiert nach Alltagsnutzen für BB/Admin.`,
  },
  {
    key: 'korrektheit',
    prompt: `${KONTEXT}
Dimension: KORREKTHEIT/SICHERHEIT. Prüfe alle Controller in app/Http/Controllers/ und app/Services/:
1. Noten-Queries ohne whereNull('geloescht_am') — Soft-Delete-Leak
2. Fehlende Berechtigungsprüfung: Lernender-Controller ohne Eigentums-Check, BB-Controller ohne Betreuungs-Check, IDOR über Route-Parameter
3. Validierungslücken: fehlende max-Längen, fehlende exists-Rules auf Fremdschlüssel, Datums-Logik (ende >= beginn)
4. N+1-Queries in Schleifen über Collections
5. Raw-SQL ohne Bindings, Mass-Assignment-Risiken
6. Division durch Null bei Durchschnittsberechnungen (leere Notenlisten)
Lies die Controller vollständig. Max 8 Findings, hoch = echte Sicherheits-/Datenfehler.`,
  },
  {
    key: 'a11y-mobile',
    prompt: `${KONTEXT}
Dimension: ACCESSIBILITY + MOBILE. Prüfe resources/views/:
1. Interaktive Elemente nur per Hover erreichbar (opacity-0 group-hover Muster) — auf Touch unbenutzbar
2. Icon-only Buttons ohne aria-label/title
3. Formular-Inputs ohne verknüpftes <label>
4. Tabellen die auf Mobile horizontal überlaufen ohne overflow-x-auto Wrapper
5. Zu kleine Touch-Targets (<40px) bei wichtigen Aktionen
6. Fehlende focus-visible Ringe auf Custom-Buttons
7. Modals/Dropdowns ohne Escape-Handling (Alpine)
Nutze grep für Muster (opacity-0, group-hover, overflow-x), lies Treffer-Dateien. Max 8 Findings.`,
  },
]

phase('Audit')
const ergebnisse = await pipeline(
  DIMENSIONEN,
  d => agent(d.prompt, { label: `audit:${d.key}`, phase: 'Audit', schema: FINDINGS_SCHEMA }),
  (res, dim) => {
    if (!res || !res.findings || !res.findings.length) return []
    const top = res.findings.slice(0, 8)
    log(`${dim.key}: ${top.length} Findings`)
    return parallel(top.map(f => () =>
      agent(`${KONTEXT}
Du bist adversarialer Verifizierer. Ein Audit-Agent behauptet folgendes Problem im Repo /var/www/notenportal:
Titel: ${f.titel}
Datei: ${f.datei}${f.zeile ? ` (ca. Zeile ${f.zeile})` : ''}
Beschreibung: ${f.beschreibung}
Vorgeschlagener Fix: ${f.fix}

Lies die genannte Datei (und bei Bedarf verwandte Dateien) SELBST und versuche das Finding zu WIDERLEGEN.
real=false wenn: das Problem nicht existiert, bereits gelöst ist, reine Geschmackssache ohne klaren Nutzerwert ist, oder der Fix mehr schadet als nützt (z.B. Print-Views brauchen weisse Farben absichtlich).
real=true NUR wenn du das Problem in der Datei konkret nachweisen kannst. Im Zweifel real=false.`,
        { label: `verify:${dim.key}:${f.titel.slice(0, 30)}`, phase: 'Verifikation', schema: VERDICT_SCHEMA })
        .then(v => ({ ...f, dimension: dim.key, verdict: v }))
    ))
  }
)

const bestaetigt = ergebnisse
  .filter(Boolean)
  .flat()
  .filter(Boolean)
  .filter(f => f.verdict && f.verdict.real)

log(`Bestätigt: ${bestaetigt.length} Findings`)
return {
  bestaetigt: bestaetigt.map(f => ({
    dimension: f.dimension,
    titel: f.titel,
    datei: f.datei,
    zeile: f.zeile || null,
    schweregrad: f.schweregrad,
    beschreibung: f.beschreibung,
    fix: f.fix,
    aufwand: f.verdict.aufwand,
    verifikation: f.verdict.begruendung,
  })),
}