export const meta = {
  name: 'notenportal-r6-pruefung',
  description: 'R6-12 Schlussabnahme: Kontrast und Regel-Greps, Bilder je Rolle in allen Zuständen mit gegnerischer Prüfung, Rundgang, UI-Check je Rollenbereich, Bericht r6-leistung-nachher.md, Prüfer',
  whenToUse: 'Nach R6-08 bis R6-11, wenn die Leistungsmessung (tools/pruefung/r6-vergleich.sh) in einem ruhigen Fenster gelaufen ist. args: { scratch: "<ordner>", env: "<env.sh mit Cache-Variablen und NP_PLAYWRIGHT>", messung: "<ordner mit vergleich.md und JSON aus r6-vergleich.sh>" }. Die ganze Testsuite und der Commit bleiben bei der Hauptsitzung.',
  phases: [
    { title: 'Prüfen', detail: 'Kontrast und Greps, Bilder je Rolle, Rundgang, UI-Check je Rollenbereich' },
    { title: 'Gegenprüfung', detail: 'jeden Sichtbefund am Bild und im Code widerlegen', model: 'opus' },
    { title: 'Bericht', detail: 'r6-leistung-nachher.md mit Basislinie, nachher, Grenze, erfüllt' },
    { title: 'Prüfer', detail: 'Akzeptanz R6-12 gegnerisch nachprüfen', model: 'fable' },
  ],
}

const A = typeof args === 'string' ? JSON.parse(args) : (args || {})
const SCRATCH = A.scratch
const ENV = A.env
const MESSUNG = A.messung
if (!SCRATCH || !ENV || !MESSUNG) throw new Error('args.scratch, args.env und args.messung sind Pflicht')

const GRUND = `Notenportal, Abnahme R6-12 (docs/auftrag/GUI-R6.md §4.2, §8, §11 «R6-12»). Nur lesen und prüfen: keine Dateien im Repo ändern (ausser ausdrücklich genannt), keine git-, composer- oder npm-Befehle, keine Subagents, nie migrate/optimize/config:cache.
Vor jedem Bash-Befehl mit php/node: \`source ${ENV}\`. Browser-Werkzeuge nur gegen den Demo-Server (NP_URL leer lassen = http://127.0.0.1:8099). Passwort nur so übernehmen, nie ausgeben: \`export NP_TEST_PW=$(grep -oP "DEMO_PASSWORT\\s*=\\s*'\\K[^']+" database/seeders/DemoSeeder.php)\`.`

const BEFUND_SCHEMA = {
  type: 'object',
  properties: {
    befunde: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          id: { type: 'string' },
          schwere: { type: 'string', enum: ['hoch', 'mittel', 'niedrig'] },
          titel: { type: 'string' },
          beschreibung: { type: 'string', description: 'was, wo (Bild und Bildstelle bzw. Datei:Zeile), gegen welche Regel' },
          bilder: { type: 'array', items: { type: 'string' }, description: 'absolute Pfade' },
          vermutung: { type: 'string', description: 'verursachende Datei, falls bekannt' },
        },
        required: ['id', 'schwere', 'titel', 'beschreibung', 'bilder', 'vermutung'],
      },
    },
    geprueft: { type: 'array', items: { type: 'string' }, description: 'Befehle mit Exit-Code bzw. angesehene Bilder' },
    ergebnis: { type: 'string', description: 'max 80 Wörter' },
  },
  required: ['befunde', 'geprueft', 'ergebnis'],
}

const VERDICT_SCHEMA = {
  type: 'object',
  properties: {
    real: { type: 'boolean' },
    begruendung: { type: 'string', description: 'max 60 Wörter, mit Bildstelle bzw. Datei:Zeile' },
    datei: { type: 'string' },
    zeile: { type: 'integer' },
    fix: { type: 'string', description: 'kleinster regelkonformer Fix oder leer' },
    planentscheid: { type: 'string', description: 'G-Nummer, S-Zeile oder Scheibe, der der Befund widerspricht, sonst leer' },
  },
  required: ['real', 'begruendung', 'datei', 'zeile', 'fix', 'planentscheid'],
}

const ROLLEN = [
  { key: 'lernende', konto: 'nina.huber', seiten: '/dashboard,/grades,/grades/calculator,/exams,/qualification' },
  { key: 'ohne-noten', konto: 'livia.gerber', seiten: '/dashboard,/grades,/exams,/qualification' },
  { key: 'berufsbildner', konto: 'michael.baumann', seiten: '/trainer,/trainer/learners,/trainer/learners/1,/trainer/learners/1/grades' },
  { key: 'admin', konto: 'laura.frei', seiten: '/admin,/admin/reports/grades,/admin/learners,/admin/mail-log' },
]

const BEREICHE = [
  { key: 'lernende', views: 'resources/views/dashboards/lernender.blade.php, resources/views/lernender/**, resources/views/rechner/**, resources/views/abschluss/**, resources/views/components/{verlauf,hantel,zeitleiste,diagramm,bullet,sparkline}.blade.php' },
  { key: 'berufsbildner', views: 'resources/views/dashboards/berufsbildner.blade.php, resources/views/verwaltung/**, resources/views/components/{punktstreifen,hantel,heatmap}.blade.php' },
  { key: 'admin', views: 'resources/views/dashboards/admin.blade.php, resources/views/admin/** (Schwerpunkt admin/berichte/noten.blade.php)' },
]

function maschinell() {
  return `${GRUND}
Aufgabe MASCHINELL: Führe aus und notiere je Befehl Exit-Code und Kernausgabe in geprueft[]:
1. node tools/pruefung/kontrast.mjs ; node tools/pruefung/kontrast.mjs --minimum ; node tools/pruefung/kontrast.mjs --glanz=0.07 ; node tools/pruefung/kontrast.mjs --stufen (je Exit 0 verlangt; bei --minimum die Quelle surface-2 für das dunkle Overlay, siehe §4.1/§8).
2. Regel-Greps aus §8, alle müssen leer sein: grep -rn "motion-reduce:" resources/views · grep -nE "rgb\\((255 255 255|0 0 0) ?/" resources/css/app.css · grep -nE "systemGray|system[A-Z]|apple\\.com|#0066cc" resources/css/theme.css · grep -rnE "glass-bar|glass-seitenleiste|glass-btn|np-btn-primary|np-fade-in|data-autohide" resources/ · grep -n ":is([^)]*::" resources/css/app.css
3. grep -rn "view-transition-name" resources/ – nur np-leiste-auswahl und np-tab-auswahl erlaubt.
4. grep -rn "ß" resources/views/ lang/ – muss leer sein.
Jede Abweichung ist ein Befund (id M1, M2 …, bilder leer, vermutung = Datei). ergebnis: Tabelle in Worten (Prüfung → erfüllt ja/nein).`
}

function bilder(r) {
  const dir = `${SCRATCH}/bilder/${r.key}`
  return `${GRUND}
Aufgabe BILDER für ${r.konto}@demo.example. Lies zuerst .claude/skills/notenportal-dunkelmodus/SKILL.md (Prüfliste §5) und .claude/skills/notenportal-pruefwerkzeuge/SKILL.md.
Nimm mit tools/pruefung/shot.mjs die Seiten «${r.seiten}» auf, Ordner ${dir} (mit --dir und je Zustand --name oder Unterordner):
(a) dunkel 1920 (Standard), (b) --breite=2560 --hoehe=1440, (c) --bewegung=reduziert, (d) --transparenz=reduziert, (e) --kontrast=mehr, (f) --hell nur für die erste Seite.
Öffne JEDES Bild mit Read und beurteile es gegen die HIG und die Projektregeln (Hierarchie, Flächenstufen, Kontrast, Abstände, Ausrichtung, abgeschnittener oder überlappender Text, Glas nur in der Funktionsebene, unter reduzierter Transparenz kein Glas, unter mehr Kontrast Rahmen). Nur echte Mängel melden, keinen Geschmack; Befund-ids ${r.key.toUpperCase()}-1, -2 … mit absoluten Bildpfaden.
Bewusst entschieden, NICHT melden: Notenachse fest 1–6 (G18), Kartenbreite max-w-7xl für Nicht-Admin-Listen (3d63e7e), Filterleiste zeigt nur den Wert (gui-konzept.md:427), Namensreihenfolge Nachname Vorname in Listen, Segment-Auswahl über der Diagrammkarte, Medianlinie fehlt bei Median 0.`
}

function rundgang() {
  return `${GRUND}
Aufgabe RUNDGANG: node tools/pruefung/rundgang.mjs <konto>@demo.example für nina.huber, livia.gerber, michael.baumann, laura.frei (nacheinander, Ausgabeordner unter ${SCRATCH}/rundgang). Notiere je Konto Exit-Code und gemeldete Layoutbefunde. Jeder Befund des Werkzeugs, der kein Messartefakt ist (prüfe mit dem Bild, das das Werkzeug ablegt), wird ein Befund (id RG-1 …) mit Bildpfad.`
}

function uiCheck(b) {
  return `Prüfe den Rollenbereich «${b.key}» des Notenportals: ${b.views}. Massstab Desktop 1920–2560 dunkel nach Apple HIG, Skills notenportal-ui und notenportal-dunkelmodus, Regeln .claude/rules/oberflaeche.md. Prüfe Tokens statt Farb-Hardcodes, keine harten Schriftgrössen, Tastaturbedienung und Fokus, Zustände (leer, lädt, Fehler), Tabellen mit caption und th scope, Zahlen rechtsbündig mit tabular-nums, Diagramme mit Zusammenfassung und Tabelle in <details>, Stufe nie nur Farbe. Nur lesen.
Bewusst entschieden, NICHT melden: G18 Notenachse fest 1–6, max-w-7xl für Nicht-Admin-Listen (3d63e7e), Filterleiste nur Wert (gui-konzept.md:427), Server-Blade-Säulen statt npChart auf /admin, Segment über der Karte.
Gib höchstens 12 Befunde zurück (ids UI-${b.key}-1 …, bilder leer, vermutung = Datei).`
}

function gegen(f) {
  return `${GRUND}
Du prüfst gegnerisch EINEN Befund der Abnahme R6-12 und versuchst, ihn zu WIDERLEGEN. real=true nur mit Beleg, bei Unsicherheit false.
Befund ${f.id} (${f.schwere}): ${f.titel}
${f.beschreibung}
Bilder: ${f.bilder.join(', ') || '–'} · Vermutung: ${f.vermutung || '–'}
Öffne die Bilder mit Read (falls vorhanden), suche die verursachende Stelle im Code und gleiche mit docs/auftrag/GUI-R6.md (G1–G21, S1–S11, §9 Verworfenes) und .claude/skills/notenportal-ui/SKILL.md ab. Folgt der Zustand einem Planentscheid: real=false und planentscheid ausfüllen.`
}

phase('Prüfen')
log(`${ROLLEN.length} Rollen mit Bildern, ${BEREICHE.length} UI-Bereiche, Rundgang 4 Konten, maschinelle Prüfungen`)

const AUFGABEN = [
  { art: 'maschinell', label: 'maschinell', prompt: maschinell(), opts: { model: 'sonnet', effort: 'high' } },
  ...ROLLEN.map(r => ({ art: 'bilder', label: `bilder:${r.key}`, prompt: bilder(r), opts: { model: 'sonnet', effort: 'high', agentType: 'bildpruefer' } })),
  { art: 'rundgang', label: 'rundgang', prompt: rundgang(), opts: { model: 'sonnet', effort: 'high' } },
  ...BEREICHE.map(b => ({ art: 'ui', label: `ui:${b.key}`, prompt: uiCheck(b), opts: { model: 'opus', effort: 'high', agentType: 'ui-checker' } })),
]

const gepruefte = await pipeline(
  AUFGABEN,
  a => agent(a.prompt, { label: a.label, phase: 'Prüfen', schema: BEFUND_SCHEMA, ...a.opts }),
  (res, a) => {
    if (!res) return { aufgabe: a.label, ausgefallen: true, befunde: [] }
    if (!res.befunde.length) return { aufgabe: a.label, ergebnis: res.ergebnis, geprueft: res.geprueft, befunde: [] }
    return parallel(res.befunde.map(f => () =>
      agent(gegen(f), { label: `gegen:${f.id}`, phase: 'Gegenprüfung', schema: VERDICT_SCHEMA, model: 'opus', effort: 'high' })
        .then(v => ({ ...f, urteil: v })),
    )).then(urteile => ({ aufgabe: a.label, ergebnis: res.ergebnis, geprueft: res.geprueft, befunde: urteile.filter(Boolean) }))
  },
)

const alle = gepruefte.filter(Boolean)
const ausgefallen = alle.filter(g => g.ausgefallen).map(g => g.aufgabe)
const bestaetigt = alle.flatMap(g => g.befunde.filter(f => f.urteil && f.urteil.real).map(f => ({ aufgabe: g.aufgabe, ...f })))
const verworfen = alle.flatMap(g => g.befunde.filter(f => f.urteil && !f.urteil.real).map(f => ({ id: f.id, titel: f.titel, grund: f.urteil.begruendung, planentscheid: f.urteil.planentscheid })))
const ungeprueft = alle.flatMap(g => g.befunde.filter(f => !f.urteil).map(f => f.id))
if (ausgefallen.length) log(`ausgefallen (nicht geprüft): ${ausgefallen.join(', ')}`)
log(`${bestaetigt.length} bestätigt, ${verworfen.length} verworfen, ${ungeprueft.length} ungeprüft`)

phase('Bericht')
const zusammenfassung = JSON.stringify({
  aufgaben: alle.map(g => ({ aufgabe: g.aufgabe, ergebnis: g.ergebnis, geprueft: g.geprueft })),
  bestaetigt: bestaetigt.map(f => ({ id: f.id, aufgabe: f.aufgabe, titel: f.titel, schwere: f.schwere, datei: f.urteil.datei, zeile: f.urteil.zeile, fix: f.urteil.fix })),
  verworfen, ungeprueft, ausgefallen,
})
const bericht = await agent(`${GRUND}
Aufgabe BERICHT: Schreibe docs/auftrag/messungen/r6-leistung-nachher.md (diese eine Datei darfst du anlegen bzw. überschreiben).
Quellen: docs/auftrag/messungen/r6-leistung-baseline.md (Basislinie), docs/auftrag/GUI-R6.md §4.2 (Grenzen), ${MESSUNG}/vergleich.md und die JSON-Dateien dort (nachher-*, vorher-*: gleiche Maschine, abwechselnd gemessen), und dieses Prüfergebnis: ${zusammenfassung}
Aufbau: (1) Messbedingungen (Datum, Argumente, ruhiges Fenster laut vergleich.md); (2) dieselben Tabellen wie die Basislinie (Glasanteil Ruhe/Palette/Menü, Tippen p95, Scroll p95, Ruhe p95, LCP, DOM-Knoten) mit Spalten Basislinie | vorher (dieselbe Maschine) | nachher | Differenz | Grenze | erfüllt (ja/nein) – Werte nur aus den Quellen, fehlende Werte als «nicht gemessen» kennzeichnen, nie schätzen; (3) Kontrast und Greps; (4) Bilder, Rundgang, UI-Check: bestätigte Befunde mit Datei:Zeile, verworfene mit Grund in einer Zeile; (5) Offen. Schweizer Hochdeutsch, ss statt ß.
Rückgabe: Pfad der Datei und je Akzeptanzpunkt von R6-12 (GUI-R6.md §11) «erfüllt»/«nicht erfüllt»/«nicht geprüft» mit Beleg.`, { label: 'bericht', phase: 'Bericht', model: 'sonnet', effort: 'high' })

phase('Prüfer')
const pruefer = await agent(`Prüfe gegnerisch die Behauptung «R6-12 Schlussabnahme ist erfüllt» im Notenportal. Lies docs/auftrag/GUI-R6.md (§4.2, §8, §11 R6-12), docs/auftrag/messungen/r6-leistung-nachher.md und ${MESSUNG}/vergleich.md. Prüfe Stichproben: stimmen die Zahlen im Bericht mit den JSON-Dateien unter ${MESSUNG} überein, sind Grenzen richtig verglichen, ist «erfüllt» nirgends ohne Beleg gesetzt? Führe die Regel-Greps aus §8 selbst aus. Nur lesen, keine git-Befehle. Antworte: Urteil (hält / hält nicht / teilweise), höchstens 8 Zeilen Belege mit Datei:Zeile.
Bericht des Schreibers: ${bericht}`, { label: 'pruefer', phase: 'Prüfer', model: 'fable', effort: 'low', agentType: 'pruefer' })

return { bestaetigt, verworfen, ungeprueft, ausgefallen, bericht, pruefer }
