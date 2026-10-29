export const meta = {
  name: 'notenportal-r6-pruefung',
  description: 'R6 Schlussabnahme: Messungen gegen die Basislinie (seriell), Screenshots je Rolle in allen Modi mit Bildurteil, ui-checker je Rollenbereich, pruefer über das Ganze',
  whenToUse: 'Nach docs/auftrag/GUI-R6.md §8 und R6-12, wenn alle Seiten-Scheiben committet sind. args: { env: "<pfad zu env.sh>", scratch: "<ordner>", nurMessen?: true, ohneMessen?: true }. Die Messphase läuft allein (ein Agent, keine Parallelität), weil Bildzeiten unter CPU-Last nicht vergleichbar sind; die Hauptsitzung prüft danach die JSON-Rohdaten, schreibt docs/auftrag/messungen/r6-leistung-nachher.md und lässt die ganze Suite laufen.',
  phases: [
    { title: 'Messen', detail: 'kontrast.mjs (alle Modi), Regel-Greps, Rundgang 4 Konten, leistung.mjs mit den Argumenten der Basislinie, Rohdaten als JSON', model: 'sonnet' },
    { title: 'Bilder', detail: 'shot.mjs je Rolle: dunkel 1920/2560, reduzierte Bewegung, reduzierte Transparenz, mehr Kontrast, hell als Stichprobe; Urteil bildpruefer', model: 'sonnet' },
    { title: 'Regeln', detail: 'ui-checker je Rollenbereich (Lernende, Berufsbildner, Admin)', model: 'opus' },
    { title: 'Gegenprüfung', detail: 'pruefer: Messtabelle, Bild- und Regelbefunde gegnerisch nachprüfen', model: 'fable' },
  ],
}

const A = args || {}
const ENV = A.env || '/tmp/claude-0/-home-user-notenportal/7db961ca-89ac-5783-9806-aac8c1a98bdd/scratchpad/env.sh'
const SCRATCH = A.scratch || '/tmp/claude-0/-home-user-notenportal/7db961ca-89ac-5783-9806-aac8c1a98bdd/scratchpad/r6/r612'

const PW = 'export NP_TEST_PW=$(grep -oP "DEMO_PASSWORT\\s*=\\s*\'\\K[^\']+" database/seeders/DemoSeeder.php)'

const PFLICHT = `Du arbeitest im Repository-Wurzelverzeichnis des Notenportals (Cloud: $CLAUDE_PROJECT_DIR). Vor jedem php/node/mysql-Befehl: source ${ENV}.
HARTE REGELN: keine schreibenden git-Befehle, kein composer, kein npm, keine Subagents, keine Änderung an Dateien im Repo (du prüfst nur). Nie tmp-testdaten/ oder .env anfassen.
Demo-Server: http://127.0.0.1:8099 (tools/pruefung/demo-server.sh status). Passwort nur so: ${PW} – nie als Argument, nie in Ausgabe, nie in Dateien.
Konten: nina.huber@demo.example (Lernende), livia.gerber@demo.example (Lernende ohne Noten), michael.baumann@demo.example (Berufsbildner), laura.frei@demo.example (Admin).
Massstab der Oberfläche: Desktop 1920×1080 bis 2560×1440 im Dunkelmodus nach Apple HIG; hell und schmal dürfen nicht brechen, bekommen aber keine eigene Arbeit.
Ablage für Rohdaten und Bilder: ${SCRATCH} (anlegen, falls er fehlt). Keine Datei zweimal lesen.`

const ROLLEN = [
  { rolle: 'lernende', email: 'nina.huber@demo.example', pfade: '/dashboard,/grades,/grades/calculator,/exams,/qualification', views: 'resources/views/{lernender,dashboards/lernender*,noten,rechner,abschluss,module,dokumente}' },
  { rolle: 'berufsbildner', email: 'michael.baumann@demo.example', pfade: '/trainer,/trainer/learners,/trainer/learners/1,/trainer/learners/1/grades', views: 'resources/views/{verwaltung,dashboards/berufsbildner*}' },
  { rolle: 'admin', email: 'laura.frei@demo.example', pfade: '/admin,/admin/reports/grades,/admin/learners', views: 'resources/views/{admin,dashboards/admin*}' },
]

const MESSUNG_SCHEMA = {
  type: 'object',
  properties: {
    kontrast: { type: 'array', description: 'je Aufruf (ohne Flag, --minimum, --glanz=0.07, --stufen): Exit-Code und letzte Zeile', items: { type: 'object', properties: { aufruf: { type: 'string' }, exit: { type: 'integer' }, zeile: { type: 'string' } }, required: ['aufruf', 'exit', 'zeile'] } },
    greps: { type: 'array', description: 'jeder Regel-Grep aus GUI-R6.md §8 mit Trefferzahl', items: { type: 'object', properties: { grep: { type: 'string' }, treffer: { type: 'integer' } }, required: ['grep', 'treffer'] } },
    rundgang: { type: 'array', items: { type: 'object', properties: { konto: { type: 'string' }, exit: { type: 'integer' }, seiten: { type: 'integer' }, befunde: { type: 'string' } }, required: ['konto', 'exit', 'seiten', 'befunde'] } },
    seiten: {
      type: 'array',
      description: 'eine Zeile je Seite und Argumentensatz, Werte wie leistung.mjs sie ausgibt (Median aus --laeufe=3); Basislinie und Grenze aus GUI-R6.md §4.2',
      items: {
        type: 'object',
        properties: {
          konto: { type: 'string' }, pfad: { type: 'string' }, argumente: { type: 'string' },
          lcp: { type: 'number' }, knoten: { type: 'integer' }, glasAnzahl: { type: 'integer' }, glasProzent: { type: 'number' },
          ruheP95: { type: 'number' }, scrollP95: { type: 'number' }, scrollWeg: { type: 'number' }, langeBilder: { type: 'string' },
          tippenP95: { type: 'number' }, tippenMin3: { type: 'number', description: 'Minimum des Tippen-p95 aus drei Wiederholungen (nur /dashboard und /admin/reports/grades)' },
          paletteGlasProzent: { type: 'number' }, menueGlasProzent: { type: 'number' },
          json: { type: 'string', description: 'Pfad der --json-Datei' },
        },
        required: ['konto', 'pfad', 'argumente', 'json'],
      },
    },
    fehler: { type: 'string', description: 'leer, wenn alles lief; sonst was fehlte (ohne Passwort)' },
  },
  required: ['kontrast', 'greps', 'rundgang', 'seiten', 'fehler'],
}

const BEFUNDE_SCHEMA = {
  type: 'object',
  properties: {
    befunde: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          schwere: { type: 'string', enum: ['blockierend', 'empfehlung'] },
          titel: { type: 'string' }, bild: { type: 'string', description: 'Bildpfad oder Datei:Zeile' },
          beleg: { type: 'string' }, fix: { type: 'string' },
        },
        required: ['schwere', 'titel', 'bild', 'beleg', 'fix'],
      },
    },
    geprueft: { type: 'string', description: 'was angesehen wurde (Bilder, Dateien) in einer Zeile' },
  },
  required: ['befunde', 'geprueft'],
}

const URTEIL_SCHEMA = {
  type: 'object',
  properties: {
    bestaetigt: { type: 'array', items: { type: 'string' } },
    widerlegt: { type: 'array', items: { type: 'object', properties: { behauptung: { type: 'string' }, beleg: { type: 'string' } }, required: ['behauptung', 'beleg'] } },
    luecken: { type: 'array', items: { type: 'string' } },
  },
  required: ['bestaetigt', 'widerlegt', 'luecken'],
}

// ── Messen: ein Agent, nichts läuft parallel (Bildzeiten) ───────────────────────────────────────
let messung = null
if (!A.ohneMessen) {
  phase('Messen')
  messung = await agent(`${PFLICHT}
Lies zuerst docs/auftrag/GUI-R6.md §4.2 und §8 sowie docs/auftrag/messungen/r6-leistung-baseline.md §1, §2 und §5 (Argumente und Seiten der Basislinie).
Führe seriell aus und sichere jede Ausgabe unter ${SCRATCH}:
1. node tools/pruefung/kontrast.mjs · --minimum · --glanz=0.07 · --stufen (Exit-Code und letzte Zeile je Aufruf).
2. Regel-Greps aus §8 (motion-reduce, rgb(255 255 255|0 0 0)/, systemGray|system[A-Z]|apple.com|#0066cc in theme.css, glass-bar|glass-seitenleiste|glass-btn|np-btn-primary|np-fade-in|data-autohide, :is( mit ::, view-transition-name ausser np-leiste-auswahl/np-tab-auswahl) – Trefferzahl je Grep.
3. node tools/pruefung/rundgang.mjs für nina.huber, livia.gerber, michael.baumann, laura.frei – Exit, Seitenzahl, Befundzeilen.
4. node tools/pruefung/leistung.mjs je Konto mit den Seiten aus §4.2, genau mit den Argumenten der Basislinie und --json=${SCRATCH}/leistung-<name>.json:
   a) 1920×1080 --laeufe=3 --palette: nina.huber "/dashboard,/grades,/grades/calculator,/exams" · laura.frei "/admin,/admin/reports/grades,/admin/learners" · michael.baumann "/trainer,/trainer/learners,/trainer/learners/1".
   b) --hoehe=540 --laeufe=3: nina.huber "/dashboard,/grades,/grades/calculator" · laura.frei "/admin/reports/grades,/admin/learners" · michael.baumann "/trainer,/trainer/learners/1,/trainer/learners/1/grades".
   c) Tippen als Minimum aus drei Wiederholungen: nina.huber "/dashboard" und laura.frei "/admin/reports/grades", je dreimal --laeufe=3 --palette.
   d) Menü: laura.frei "/admin" --menue='button[aria-label^="Konto"]'.
   e) 2560×1440: nina.huber "/dashboard,/grades" --breite=2560 --hoehe=1440.
Lies die Werte aus den JSON-Dateien, nicht aus der Erinnerung. Erfinde keinen Wert: fehlt einer, bleibt das Feld weg und steht in «fehler».`,
  { label: 'messen', phase: 'Messen', model: 'sonnet', effort: 'high', schema: MESSUNG_SCHEMA })
  log(`Messen: ${messung ? messung.seiten.length + ' Zeilen' : 'abgebrochen'}`)
}

if (A.nurMessen) return { messung }

// ── Bilder und Regeln: je Rolle parallel ─────────────────────────────────────────────────────────
const bilder = await parallel(ROLLEN.map(r => () => agent(`${PFLICHT}
Lies .claude/skills/notenportal-dunkelmodus/SKILL.md (§5 Prüfliste) und .claude/skills/notenportal-pruefwerkzeuge/SKILL.md.
Rolle ${r.rolle}, Konto ${r.email}, Seiten "${r.pfade}". Ordner ${SCRATCH}/bilder-${r.rolle} (anlegen).
Nimm mit node tools/pruefung/shot.mjs auf: dunkel 1920 (--name=d), --breite=2560 --hoehe=1440 (--name=g), --bewegung=reduziert (--name=b), --transparenz=reduziert (--name=t), --kontrast=mehr (--name=k), --hell nur für die erste Seite (--name=h).
Sieh jedes Bild mit Read an und beurteile nach der Prüfliste: Ebenen, kein reines Schwarz/Weiss, Lesbarkeit, Glas nur Navigation/Overlays, ein Akzent, Zustände, Fokus, abgeschnittener oder überlappender Text, Diagramme (Notenachse 1–6, Anzahlen ab 0, Stufe nie nur als Farbe). Blockierend ist nur «unlesbar», «abgeschnitten», «überlappt», «Glas auf Inhalt», «Farbe ohne Bedeutung»; alles andere Empfehlung. Kein Befund ohne Bildpfad.`,
  { label: `bilder:${r.rolle}`, phase: 'Bilder', agentType: 'bildpruefer', model: 'sonnet', effort: 'high', schema: BEFUNDE_SCHEMA })))

const regeln = await parallel(ROLLEN.map(r => () => agent(`${PFLICHT}
Lies .claude/skills/notenportal-ui/SKILL.md und .claude/skills/notenportal-dunkelmodus/SKILL.md, dann die Views unter ${r.views} und die gemeinsamen Komponenten, die sie nutzen (resources/views/components).
Prüfe den Rollenbereich ${r.rolle} auf: Token-Klassen (keine Farb-Hardcodes, keine Palettenfarben für Bedeutung, keine harten Schriftgrössen, keine Breakpoint-Varianten ausser max-xl: in der Navigation), Glas nur funktional, Bewegung über ruhig: und Tokens, Tastatur und Fokus, Zustände (leer, lädt, Fehler, viele Daten), Live-Regionen (genau eine je Statistik-Baustein), Diagrammregeln (Notenachse 1–6, Anzahlen ab 0, Satz mit echten Werten, Tabelle in details), Schweizer Hochdeutsch ohne ß, keine Hinweistexte. Kein Befund ohne Datei und Zeile; lies die Stelle wirklich.`,
  { label: `regeln:${r.rolle}`, phase: 'Regeln', agentType: 'ui-checker', model: 'opus', effort: 'high', schema: BEFUNDE_SCHEMA })))

const befunde = [...bilder, ...regeln].filter(Boolean).flatMap((b, i) => b.befunde.map(f => ({ ...f, quelle: i < ROLLEN.length ? 'bild' : 'regel' })))
log(`Befunde: ${befunde.filter(f => f.schwere === 'blockierend').length} blockierend, ${befunde.filter(f => f.schwere === 'empfehlung').length} Empfehlungen`)

// ── Gegenprüfung ─────────────────────────────────────────────────────────────────────────────────
phase('Gegenprüfung')
const urteil = await agent(`${PFLICHT}
Prüfe gegnerisch, nur lesend. Behauptungen:
A) Die Messung (JSON unter ${SCRATCH}/leistung-*.json) hält jede Grenze aus docs/auftrag/GUI-R6.md §4.2 gegen docs/auftrag/messungen/r6-leistung-baseline.md (Glasanteil Ruhe ≤ 2 %, Palette ≤ 18 %, Menü ≤ 12 %, Tippen ≤ Basislinie, Scroll ≤ Basislinie + 1.1 ms mit 0 langen Bildern, Ruhe ≤ 17.0, LCP ≤ +10 %, Knoten ≤ +10 %). Rechne jede Zeile selbst nach: ${JSON.stringify(messung ? messung.seiten : 'Messung lag nicht vor')}.
B) kontrast.mjs in allen vier Modi Exit 0, Regel-Greps leer, Rundgang aller vier Konten Exit 0: ${JSON.stringify(messung ? { kontrast: messung.kontrast, greps: messung.greps, rundgang: messung.rundgang } : 'nicht gemessen')}.
C) Diese Befunde aus Bild- und Regelprüfung sind echt und korrekt eingestuft (blockierend nur, wenn eine Person etwas nicht lesen, erreichen oder verstehen kann): ${JSON.stringify(befunde)}.
Widerlege, wo du kannst (falsche Zeile, Grenze falsch gerechnet, Befund nicht reproduzierbar). Nenne Lücken, die du nicht prüfen konntest.`,
  { label: 'pruefer', phase: 'Gegenprüfung', agentType: 'pruefer', model: 'fable', effort: 'low', schema: URTEIL_SCHEMA })

return { messung, befunde, urteil }
