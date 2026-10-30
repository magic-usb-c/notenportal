export const meta = {
  name: 'notenportal-befunde-beheben',
  description: 'Bestätigte Befunde der Funktionsprüfung beheben: je Gruppe Umsetzung mit Regressionstest (sonnet high) → Review (sonnet high) → Nachbesserung nur bei blockierenden Befunden',
  whenToUse: 'Nach notenportal-funktionspruefung. args: { quelle: "<ergebnis.json mit bestaetigt[]>", gruppen: [{ name: "auth", befunde: [0, 1], dateien: ["routes/auth.php", "app/Http/Controllers/Auth/"] }], dbs: ["notenportal_w07_test", …], env: "<env.sh>", scratch: "<ordner>" }. Gruppen einer Welle teilen keine Dateien (ausser lang/*.json, dort nur einzelne Zeilen). Ganze Suite, Pint über alles, Screenshots und Commit macht die Hauptsitzung danach.',
  phases: [
    { title: 'Umsetzung', detail: 'eine Gruppe je Agent, Fix plus Regressionstest, nur der genannte Dateikreis', model: 'sonnet' },
    { title: 'Review', detail: 'Diff gegen Befund, Fix-Risiko und harte Regeln', model: 'sonnet' },
    { title: 'Nachbesserung', detail: 'nur bei blockierenden Befunden', model: 'sonnet' },
  ],
}

// args kann als JSON-Text ankommen (Tool-Aufruf mit String) – dann parsen
const A = typeof args === 'string' ? JSON.parse(args) : (args || {})
const GRUPPEN = Array.isArray(A.gruppen) ? A.gruppen : []
const QUELLE = A.quelle
const DBS = Array.isArray(A.dbs) && A.dbs.length ? A.dbs : null
const ENV = A.env
const SCRATCH = A.scratch
if (!GRUPPEN.length || !QUELLE || !DBS || !ENV || !SCRATCH) throw new Error('args.gruppen, quelle, dbs, env und scratch sind Pflicht')

const PFLICHT = `
Du arbeitest im Repository-Wurzelverzeichnis des Notenportals. Branch main.
ZUERST lesen: .claude/skills/notenportal-ui/SKILL.md und .claude/skills/notenportal-dunkelmodus/SKILL.md (bei Views/CSS) sowie die im Auftrag genannten Dateien.
HARTE REGELN: keine schreibenden git-Befehle (add/commit/stash/checkout/reset/restore), kein composer, kein npm install (auch kein dry-run), keine Subagents.
Nie tmp-testdaten/ oder .env anfassen, keine echten Namen/Noten in Fixtures oder Doku. Passwörter nur über Umgebungsvariablen (NP_TEST_PW), nie als Argument, nie in Ausgabe.
Massstab der Oberfläche: Desktop 1920×1080 bis 2560×1440 im Dunkelmodus nach Apple HIG; hell und schmal dürfen nicht brechen, bekommen aber keine eigene Arbeit.
Tests nur: DB_DATABASE=<eigene>_test php artisan test (bzw. php vendor/bin/phpunit --filter …); prüfen, dass diese DB wirklich benutzt wird.
Neue Routen zuerst registrieren und prüfen, erst dann in Layouts/Komponenten referenzieren; im gemeinsamen Head/Layout zusätzlich @if(Route::has('…')). Alpine-Komponenten aus resources/js: erst bauen (npm run build), dann in Views verwenden.
UI: Schweizer Hochdeutsch (ss statt ß), Texte mit __() + Englisch in lang/en.json bzw. lang/areas/*/en.json. Keine Hinweistexte, keine Entwicklernotizen in der Oberfläche.
Formulare mit benannten Submit-Knöpfen (name/value): nie synchron im @submit sperren (:disabled="loading" + loading = true) – ein gesperrter Knopf schickt seinen Wert nicht mit. Stattdessen setTimeout(() => loading = true).
Abschluss: vendor/bin/pint auf geänderte PHP-Dateien; Tests der eigenen DB grün; grep -rn "ß" resources/views lang leer; .claude/hooks/view-pruefung.sh auf geänderte Views (echo '{"tool_input":{"file_path":"<absoluter pfad>"}}' | bash .claude/hooks/view-pruefung.sh); npm run build nur wenn CSS/JS/neue Tailwind-Klassen (melden). Arbeitet parallel ein anderer Agent an resources/js oder resources/css, NICHT bauen, sondern nur Klassen verwenden, die schon im Build sind: grep -c "\\.size-10[{:,]" public/build/assets/*.css.
Sichtbare Änderung: NP_TEST_PW=… node tools/pruefung/shot.mjs <email@demo.example> "/pfad" --dir=<ordner> und das Bild mit Read ansehen; Befund im Bericht nennen.
Rückmeldung (max 12 Zeilen): geänderte/neue Dateien, Testzahl, was du gesehen hast, bewusst Weggelassenes (auch in docs/audit-backlog.md).
`

const UMGEBUNG = `
UMGEBUNG: VM, Apache liefert diese Arbeitskopie LIVE aus. Vor jedem Bash-Befehl mit php/mysql/node zuerst \`source ${ENV}\` (lenkt den Konfig-/Routen-Cache um; ohne das trifft artisan die Hauptdatenbank). Nie \`php artisan migrate\`, \`optimize\`, \`config:cache\` oder \`route:cache\` ausführen, nie gegen die Datenbank notenportal oder notenportal_demo schreiben.
Tests: \`DB_DATABASE=<deine>_test php vendor/bin/phpunit --filter …\` (phpunit.xml lenkt den Konfig-Cache selbst um). Nur gezielte Tests (eigene neue + die Testklassen der berührten Controller/Klassen), NICHT die ganze Suite – die läuft in der Hauptsitzung (Storage::fake teilt ein Verzeichnis zwischen Prozessen).
Parallel arbeiten andere Agents an anderen Dateien: KEIN npm run build. Du änderst NUR deinen Dateikreis (unten) plus neue Testdateien unter tests/ und einzelne Zeilen in lang/*.json bzw. lang/areas/*/*.json (direkt vor dem Edit neu lesen, nur einfügen, nie umsortieren). Fehlt dir etwas ausserhalb, nenne es als «Lücke».
Scratch-Ordner (anlegen): ${SCRATCH}/<gruppe>/. Sonden und Hilfsskripte nur dort, nie im Repo-Wurzelverzeichnis.
Jeder Befund bekommt einen Regressionstest unter tests/Feature/… (deutscher Testname wie im Bestand), der ohne den Fix rot und mit dem Fix grün ist – das rot vorher selbst zeigen (Fix kurz auskommentieren oder Test zuerst schreiben).
Der Befund enthält «fix» (Vorschlag des Finders) und «fix_risiko» (Einwände des Widerlegers). Folge dem Vorschlag nur, wo der Widerleger nicht widerspricht; wo er eine schlankere oder vollständigere Lösung nennt, nimm die. Nenne die Wahl unter «annahmen».
`

const UMSETZUNG_SCHEMA = {
  type: 'object',
  properties: {
    gruppe: { type: 'string' },
    behoben: { type: 'array', items: { type: 'string' }, description: 'je Befund: Titel – was geändert (Datei:Zeile) – welcher Test' },
    nicht_behoben: { type: 'array', items: { type: 'string' }, description: 'Befund + Grund' },
    dateien: { type: 'array', items: { type: 'string' }, description: 'geänderte oder neue Dateien, repo-relativ' },
    geprueft: { type: 'array', items: { type: 'string' }, description: 'Prüfbefehle mit Ergebnis, inkl. rot vorher / grün nachher' },
    annahmen: { type: 'array', items: { type: 'string' } },
    luecken: { type: 'array', items: { type: 'string' } },
    ansehen: { type: 'array', items: { type: 'string' }, description: 'Konto:Pfad für Screenshots der Hauptsitzung' },
    bericht: { type: 'string', description: 'max 12 Zeilen Deutsch' },
  },
  required: ['gruppe', 'behoben', 'nicht_behoben', 'dateien', 'geprueft', 'annahmen', 'luecken', 'ansehen', 'bericht'],
}

const REVIEW_SCHEMA = {
  type: 'object',
  properties: {
    gruppe: { type: 'string' },
    befunde: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          datei: { type: 'string' },
          zeile: { type: 'integer' },
          schwere: { type: 'string', enum: ['blockierend', 'empfehlung'] },
          text: { type: 'string' },
        },
        required: ['datei', 'zeile', 'schwere', 'text'],
      },
    },
    erfuellt: { type: 'array', items: { type: 'string' }, description: 'je Ursprungsbefund: behoben ja/nein mit Datei:Zeile und Test' },
    urteil: { type: 'string', enum: ['annehmen', 'nachbessern'] },
  },
  required: ['gruppe', 'befunde', 'erfuellt', 'urteil'],
}

const NACHBESSERUNG_SCHEMA = {
  type: 'object',
  properties: {
    gruppe: { type: 'string' },
    behoben: { type: 'array', items: { type: 'string' } },
    nicht_behoben: { type: 'array', items: { type: 'string' } },
    dateien: { type: 'array', items: { type: 'string' } },
    geprueft: { type: 'array', items: { type: 'string' } },
  },
  required: ['gruppe', 'behoben', 'nicht_behoben', 'dateien', 'geprueft'],
}

const befundText = (g) => `Lies in ${QUELLE} (JSON, Feld «bestaetigt», 0-basiert) die Einträge ${JSON.stringify(g.befunde)} vollständig – Titel, Wirkung, Ursache, fix, fix_risiko, Sonde. Beispiel: node -e 'const r=require("${QUELLE}");for(const i of ${JSON.stringify(g.befunde)})console.log(JSON.stringify(r.bestaetigt[i],null,1))'`
const kreis = (g) => `DEIN DATEIKREIS (nur diese Dateien/Ordner ändern): ${g.dateien.join(', ')}` + (g.hinweis ? `\nHINWEIS DER HAUPTSITZUNG: ${g.hinweis}` : '')

const ergebnisse = await pipeline(
  GRUPPEN,
  (g, _item, i) => agent(`Befunde der Funktionsprüfung beheben, Gruppe «${g.name}».
${PFLICHT}
${UMGEBUNG}
Deine Test-DB: ${DBS[i % DBS.length]} (existiert).
${kreis(g)}
${befundText(g)}
Vorgehen: (1) Befunde lesen, Sonde ansehen, Ursache im Code an der genannten Stelle bestätigen. (2) Regressionstest schreiben, rot zeigen. (3) Fix. (4) Test grün, dazu die bestehenden Testklassen der berührten Controller/Klassen (grep in tests/ nach Klassen- und Routennamen) grün. (5) vendor/bin/pint auf deine PHP-Dateien. Kein git add/commit, kein npm run build.
Ist ein Befund bei genauem Lesen kein Fehler (Verhalten gewollt, durch Test abgesichert, Doku sagt anders): nicht ändern, unter «nicht_behoben» mit Beleg (Datei:Zeile) begründen.`,
  { label: `umsetzen:${g.name}`, phase: 'Umsetzung', model: 'sonnet', effort: 'high', schema: UMSETZUNG_SCHEMA }),

  (umsetzung, g, i) => umsetzung ? agent(`Review der Fix-Gruppe «${g.name}» (Befunde der Funktionsprüfung).
Du bist nur Prüfer: lies, führe höchstens lesende Befehle und gezielte Tests aus, ändere nichts, keine Git-Schreibbefehle, kein composer, kein npm. Vor Befehlen mit php/node/mysql: source ${ENV}. Tests nur mit DB_DATABASE=${DBS[i % DBS.length]} php vendor/bin/phpunit --filter …
${befundText(g)}
${kreis(g)}
Der Umsetzungsagent meldet: Dateien ${JSON.stringify(umsetzung.dateien)}; behoben ${JSON.stringify(umsetzung.behoben)}; nicht behoben ${JSON.stringify(umsetzung.nicht_behoben)}; Annahmen ${JSON.stringify(umsetzung.annahmen)}.
Prüfe mit \`git diff -- <dateien>\` und \`git status --short\` (andere Agents ändern parallel andere Dateien – nur deinen Kreis bewerten):
1. Ist jeder Befund an der Ursache behoben (nicht nur das Symptom)? Deckt der Regressionstest genau die Wirkung des Befunds ab und wäre er ohne Fix rot?
2. Sind die Einwände aus «fix_risiko» berücksichtigt (z. B. Tests, die brechen könnten; Randfälle)? Lass die Testklassen laufen, die der Widerleger nennt.
3. Harte Regeln: Berechtigung serverseitig, Lernende-ID aus der Session, Noten-Queries mit whereNull('geloescht_am'), kein Raw-SQL ohne Binding, Redirect mit ->with('success'|'error', …), Texte mit __() und Englisch in lang, ss statt ß, Durchschnitte nur aus App\\Services\\Auswertung, keine Datei ausserhalb des Kreises (ausser neue Tests und einzelne lang-Zeilen).
Blockierend: Befund nicht behoben, Test fehlt oder prüft nichts, harte Regel verletzt, bestehender Test bricht. Sonst Empfehlung. Kein Befund ohne Datei und Zeile.`,
    { label: `review:${g.name}`, phase: 'Review', model: 'sonnet', effort: 'high', schema: REVIEW_SCHEMA }).then((review) => ({ umsetzung, review })) : null,

  async (paar, g, i) => {
    if (!paar) return { gruppe: g.name, status: 'umsetzung fehlgeschlagen', umsetzung: null, review: null, nachbesserung: null }
    const { umsetzung, review } = paar
    if (!review) return { gruppe: g.name, status: 'review fehlgeschlagen', umsetzung, review: null, nachbesserung: null }
    const blocker = review.befunde.filter((b) => b.schwere === 'blockierend')
    if (review.urteil !== 'nachbessern' || !blocker.length) {
      log(`${g.name}: Review angenommen (${review.befunde.length} Hinweise, 0 blockierend)`)
      return { gruppe: g.name, status: 'angenommen', umsetzung, review, nachbesserung: null }
    }
    log(`${g.name}: ${blocker.length} blockierende Befunde → Nachbesserung`)
    const nach = await agent(`Nachbesserung der Fix-Gruppe «${g.name}».
${PFLICHT}
${UMGEBUNG}
Deine Test-DB: ${DBS[i % DBS.length]}.
${kreis(g)}
${befundText(g)}
Der Review hat diese blockierenden Befunde (jeden prüfen, beheben oder mit Beleg ablehnen):
${blocker.map((b, n) => `${n + 1}. ${b.datei}:${b.zeile} – ${b.text}`).join('\n')}
Empfehlungen (mitnehmen, wenn billig):
${review.befunde.filter((b) => b.schwere === 'empfehlung').map((b) => `- ${b.datei}:${b.zeile} – ${b.text}`).join('\n') || '-'}
Danach die Regressionstests und die berührten Testklassen erneut laufen lassen.`,
      { label: `nachbessern:${g.name}`, phase: 'Nachbesserung', model: 'sonnet', effort: 'high', schema: NACHBESSERUNG_SCHEMA })
    return { gruppe: g.name, status: nach ? 'nachgebessert' : 'nachbesserung fehlgeschlagen', umsetzung, review, nachbesserung: nach }
  },
)

log(`Fertig: ${ergebnisse.filter(Boolean).map((e) => `${e.gruppe} ${e.status}`).join(' · ')}`)
return { gruppen: ergebnisse }
