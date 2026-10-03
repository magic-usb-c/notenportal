export const meta = {
  name: 'notenportal-r6-welle',
  description: 'Eine Welle des GUI-Rebuilds R6: je Scheibe Umsetzung (sonnet high) → Review gegen den Scheibenplan (sonnet high) → Nachbesserung nur bei blockierenden Befunden',
  whenToUse: 'Nach docs/auftrag/GUI-R6.md §7/§11. args: { scheiben: ["R6-01","R6-03"], dbs: ["notenportal_b_test","notenportal_c_test"], env: "<pfad zu env.sh>", scratch: "<ordner>", hinweise?: { "R6-01": "…" } }. Höchstens zwei Scheiben je Welle (4 CPUs); Scheiben einer Welle teilen keine Dateien. Bauen, Messen, Screenshots, Tests der ganzen Suite und Commit macht die Hauptsitzung danach.',
  phases: [
    { title: 'Umsetzung', detail: 'eine Scheibe je Agent, nur die im Plan genannten Dateien', model: 'sonnet' },
    { title: 'Review', detail: 'Diff gegen Ziel und Akzeptanz der Scheibe, Befunde mit Datei und Zeile', model: 'sonnet' },
    { title: 'Nachbesserung', detail: 'nur bei blockierenden Befunden, derselbe Dateikreis', model: 'sonnet' },
  ],
}

// args kann als JSON-Text ankommen (Tool-Aufruf mit String) – dann parsen
const A = typeof args === 'string' ? JSON.parse(args) : (args || {})
const SCHEIBEN = Array.isArray(A.scheiben) ? A.scheiben : []
const DBS = Array.isArray(A.dbs) ? A.dbs : ['notenportal_b_test', 'notenportal_c_test']
const ENV = A.env || '/tmp/claude-0/-home-user-notenportal/7db961ca-89ac-5783-9806-aac8c1a98bdd/scratchpad/env.sh'
const SCRATCH = A.scratch || '/tmp/claude-0/-home-user-notenportal/7db961ca-89ac-5783-9806-aac8c1a98bdd/scratchpad/r6'
const HINWEISE = A.hinweise || {}
if (!SCHEIBEN.length) throw new Error('args.scheiben fehlt')
if (SCHEIBEN.length > 2) log(`Achtung: ${SCHEIBEN.length} Scheiben – nur zwei laufen gleichzeitig, der Rest wartet`)

const PFLICHT = `
Du arbeitest im Repository-Wurzelverzeichnis des Notenportals (Cloud: $CLAUDE_PROJECT_DIR). Branch main.
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
UMGEBUNG DIESER WELLE: Vor jedem Bash-Befehl mit php/mysql/node zuerst \`source ${ENV}\` (setzt DB_* und PATH; ohne das: «Access denied root@localhost»).
Demo-Server: http://127.0.0.1:8099. Passwort nur so: export NP_TEST_PW=$(grep -oP "DEMO_PASSWORT\\s*=\\s*'\\K[^']+" database/seeders/DemoSeeder.php).
In dieser Welle arbeitet parallel ein zweiter Agent an anderen Dateien: KEIN npm run build (die Hauptsitzung baut nach der Welle und macht Screenshots, Leistungsmessung und die ganze Testsuite). Prüfe statisch und mit den Werkzeugen, die ohne Build auskommen (node tools/pruefung/kontrast.mjs liest theme.css/app.css direkt; node --check für reine Syntax; php vendor/bin/phpunit --filter …).
Screenshots entfallen deshalb für dich; schreibe stattdessen in den Bericht, welche Seiten die Hauptsitzung ansehen soll.
Scratch-Ordner (anlegen): ${SCRATCH}/<scheibe>/.
Der ganze Plan steht in docs/auftrag/GUI-R6.md: §2 Leitidee, §3 Grundsätze G1–G21, §4 Glas-Budget und Tokens, §5 Bewegungskatalog, §6 Statistik-Katalog, §9 Verworfenes, §10 Vorentscheidungen – und §11 der Abschnitt deiner Scheibe mit Ziel, Dateien und Akzeptanz. Lies §11 deiner Scheibe vollständig und zusätzlich §4 und §10; §2/§3/§5/§6 nur die Teile, auf die dein Abschnitt verweist.
Du änderst NUR die im Abschnitt «Dateien» deiner Scheibe genannten Dateien (plus Tests, falls der Abschnitt sie nennt). Alles andere ist tabu, auch docs/. Fehlt dir etwas in einer fremden Datei, nenne es im Bericht als «Lücke» statt es zu ändern.
Nichts raten: wo der Plan einen Wert vorgibt, nimm ihn; wo er schweigt, entscheide, nenne die Annahme im Bericht.
`

const UMSETZUNG_SCHEMA = {
  type: 'object',
  properties: {
    scheibe: { type: 'string' },
    dateien: { type: 'array', items: { type: 'string' }, description: 'geänderte oder neue Dateien, repo-relativ' },
    geprueft: { type: 'array', items: { type: 'string' }, description: 'ausgeführte Prüfbefehle mit Ergebnis (eine Zeile je Befehl)' },
    annahmen: { type: 'array', items: { type: 'string' } },
    luecken: { type: 'array', items: { type: 'string' }, description: 'was in fremden Dateien fehlt oder was die Hauptsitzung tun muss (bauen, ansehen, messen)' },
    weggelassen: { type: 'array', items: { type: 'string' } },
    ansehen: { type: 'array', items: { type: 'string' }, description: 'Pfade je Konto, die die Hauptsitzung per shot.mjs ansehen soll, z. B. "nina.huber:/learner"' },
    bericht: { type: 'string', description: 'max 12 Zeilen Deutsch' },
  },
  required: ['scheibe', 'dateien', 'geprueft', 'annahmen', 'luecken', 'weggelassen', 'ansehen', 'bericht'],
}

const REVIEW_SCHEMA = {
  type: 'object',
  properties: {
    scheibe: { type: 'string' },
    befunde: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          datei: { type: 'string' },
          zeile: { type: 'integer' },
          schwere: { type: 'string', enum: ['blockierend', 'empfehlung'] },
          text: { type: 'string', description: 'Was ist falsch, woran erkennbar, was stattdessen (kurz)' },
          akzeptanzpunkt: { type: 'string', description: 'welcher Akzeptanz- oder Zielsatz der Scheibe verletzt ist, wörtlich gekürzt' },
        },
        required: ['datei', 'zeile', 'schwere', 'text', 'akzeptanzpunkt'],
      },
    },
    erfuellt: { type: 'array', items: { type: 'string' }, description: 'Zielsätze, die nachweislich erfüllt sind (mit Datei:Zeile)' },
    urteil: { type: 'string', enum: ['annehmen', 'nachbessern'] },
  },
  required: ['scheibe', 'befunde', 'erfuellt', 'urteil'],
}

const NACHBESSERUNG_SCHEMA = {
  type: 'object',
  properties: {
    scheibe: { type: 'string' },
    behoben: { type: 'array', items: { type: 'string' } },
    nicht_behoben: { type: 'array', items: { type: 'string' }, description: 'Befund + Grund' },
    dateien: { type: 'array', items: { type: 'string' } },
    geprueft: { type: 'array', items: { type: 'string' } },
  },
  required: ['scheibe', 'behoben', 'nicht_behoben', 'dateien', 'geprueft'],
}

const ergebnisse = await pipeline(
  SCHEIBEN,
  (id, _item, i) => agent(`Scheibe ${id} des GUI-Rebuilds R6 umsetzen.
${PFLICHT}
${UMGEBUNG}
Deine Test-DB: ${DBS[i % DBS.length]} (existiert; CREATE DATABASE IF NOT EXISTS, falls nicht).
${HINWEISE[id] ? `HINWEISE DER HAUPTSITZUNG ZU ${id}:\n${HINWEISE[id]}\n` : ''}
Vorgehen: (1) Abschnitt «### ${id}» in docs/auftrag/GUI-R6.md §11 lesen, dazu §4 und §10. (2) Die genannten Dateien an den genannten Zeilen lesen (Zeilennummern im Plan sind Stand 03.10. und können um einige Zeilen abweichen – nach dem Inhalt suchen). (3) Umsetzen. (4) Jeden Akzeptanzpunkt, der ohne Build und ohne Browser prüfbar ist, selbst ausführen und das Ergebnis in «geprueft» festhalten; die übrigen unter «luecken» mit dem genauen Befehl für die Hauptsitzung. (5) Kein git add/commit, kein npm run build.
Wenn ein Zielsatz des Plans in der Codebasis nachweislich nicht so umsetzbar ist, wie er dasteht (Zeile existiert nicht, Selektor anders, Regel würde einen Test brechen): die nächstliegende Umsetzung wählen, die den Zweck erfüllt, und das als Annahme nennen. Nie den Zweck streichen.`,
    { label: `umsetzen:${id}`, phase: 'Umsetzung', model: 'sonnet', effort: 'high', schema: UMSETZUNG_SCHEMA }),

  (umsetzung, id) => umsetzung ? agent(`Review der Scheibe ${id} des GUI-Rebuilds R6 (docs/auftrag/GUI-R6.md §11, Abschnitt «### ${id}»).
Du bist nur Prüfer: lies, führe höchstens lesende Befehle aus, ändere nichts, keine Git-Schreibbefehle, kein composer, kein npm. Vor Befehlen mit php/node/mysql: source ${ENV}.
Der Umsetzungsagent meldet: Dateien ${JSON.stringify(umsetzung.dateien)}; Annahmen ${JSON.stringify(umsetzung.annahmen)}; Lücken ${JSON.stringify(umsetzung.luecken)}; Bericht: ${umsetzung.bericht}
Prüfe mit \`git diff -- <dateien>\` und \`git status --short\`:
1. Jeder Zielsatz und jeder Akzeptanzpunkt des Abschnitts: erfüllt (Datei:Zeile nennen), nicht erfüllt (Befund) oder nur mit Build/Browser prüfbar (dann nicht bewerten, sondern unter erfuellt mit Vermerk «Hauptsitzung» führen).
2. Harte Regeln des Projekts (CLAUDE.md, Skills notenportal-ui/-dunkelmodus): keine Farb-Hardcodes, keine harten Schriftgrössen, ss statt ß, Opazitäten der Materialien als numerische Literale (kontrast.mjs und ThemeKontrastTest lesen das erste Literal je Modus), keine Apple-Systemfarbwerte, kein Glas in der Inhaltsebene, @utility statt ungelayertes CSS, keine Dauerschleifen (requestAnimationFrame nur in Ereignis-Handlern), keine Datei ausserhalb des Dateikreises der Scheibe geändert.
3. Was bricht: Tests, die auf die geänderten Stellen zeigen (grep in tests/), andere CSS-Regeln, die auf gelöschte Utilities zeigen (grep in resources/), JS-Importe.
Blockierend ist, was einen Akzeptanzpunkt, eine harte Regel oder einen Test verletzt; alles andere ist Empfehlung. Kein Befund ohne Datei und Zeile; lies die Stelle wirklich. Urteil «nachbessern» nur bei mindestens einem blockierenden Befund.`,
    { label: `review:${id}`, phase: 'Review', model: 'sonnet', effort: 'high', schema: REVIEW_SCHEMA }).then((review) => ({ umsetzung, review })) : null,

  async (paar, id, i) => {
    if (!paar) return { scheibe: id, status: 'umsetzung fehlgeschlagen', umsetzung: null, review: null, nachbesserung: null }
    const { umsetzung, review } = paar
    if (!review) return { scheibe: id, status: 'review fehlgeschlagen', umsetzung, review: null, nachbesserung: null }
    const blocker = review.befunde.filter((b) => b.schwere === 'blockierend')
    if (review.urteil !== 'nachbessern' || !blocker.length) {
      log(`${id}: Review angenommen (${review.befunde.length} Befunde, 0 blockierend)`)
      return { scheibe: id, status: 'angenommen', umsetzung, review, nachbesserung: null }
    }
    log(`${id}: ${blocker.length} blockierende Befunde → Nachbesserung`)
    const nach = await agent(`Nachbesserung der Scheibe ${id} des GUI-Rebuilds R6 (docs/auftrag/GUI-R6.md §11, Abschnitt «### ${id}»).
${PFLICHT}
${UMGEBUNG}
Deine Test-DB: ${DBS[i % DBS.length]}.
Der Review hat diese blockierenden Befunde (jeden prüfen, beheben oder mit Grund ablehnen):
${blocker.map((b, n) => `${n + 1}. ${b.datei}:${b.zeile} – ${b.text} (verletzt: ${b.akzeptanzpunkt})`).join('\n')}
Empfehlungen (mitnehmen, wenn billig):
${review.befunde.filter((b) => b.schwere === 'empfehlung').map((b) => `- ${b.datei}:${b.zeile} – ${b.text}`).join('\n') || '-'}
Nur Dateien aus dem Dateikreis der Scheibe ändern. Danach dieselben statischen Prüfungen wie in der Akzeptanz erneut laufen lassen.`,
      { label: `nachbessern:${id}`, phase: 'Nachbesserung', model: 'sonnet', effort: 'high', schema: NACHBESSERUNG_SCHEMA })
    return { scheibe: id, status: nach ? 'nachgebessert' : 'nachbesserung fehlgeschlagen', umsetzung, review, nachbesserung: nach }
  },
)

const umsetzungen = ergebnisse.filter(Boolean)
log(`Welle fertig: ${umsetzungen.map((e) => `${e.scheibe} ${e.status}`).join(' · ')}`)
return { scheiben: ergebnisse }
