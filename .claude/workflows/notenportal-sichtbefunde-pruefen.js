export const meta = {
  name: 'notenportal-sichtbefunde-pruefen',
  description: 'Gesammelte Sichtbefunde (Bildprüfer, UI-Checker) gegnerisch prüfen: Bild nochmals lesen, Ursache im Code belegen, gegen Planentscheide abgleichen',
  whenToUse: 'Nach bildpruefer/ui-checker, bevor Sichtbefunde umgesetzt werden. args: { quelle: "<json mit bildordner und befunde[{id,schwere,titel,beschreibung,bilder[],vermutung}]>", ids: ["L1","B2"], plan?: "docs/auftrag/GUI-R6.md", code?: ["R1"] (Codebefunde ohne Bild, dann Pflicht: dbs[], env, scratch) }. Das Ergebnis (bestaetigt[]) ist direkt die quelle für notenportal-befunde-beheben (Indizes = Reihenfolge in bestaetigt).',
  phases: [
    { title: 'Bild', detail: 'Befund im Bild widerlegen oder bestätigen', model: 'sonnet' },
    { title: 'Ursache', detail: 'Ursache im Code belegen, gegen Planentscheide und Projektregeln abgleichen, Fix mit Risiko', model: 'opus' },
  ],
}

// args kann als JSON-Text ankommen – dann parsen
const A = typeof args === 'string' ? JSON.parse(args) : (args || {})
const QUELLE = A.quelle
const IDS = Array.isArray(A.ids) ? A.ids : []
const PLAN = A.plan || 'docs/auftrag/GUI-R6.md'
// Codebefunde (aus reviewer) haben kein Bild: statt der Bildlinse versucht ein Agent, sie mit einem Test zu reproduzieren
const CODE = new Set(Array.isArray(A.code) ? A.code : [])
const DBS = Array.isArray(A.dbs) ? A.dbs : []
const ENV = A.env
const SCRATCH = A.scratch
if (!QUELLE || !IDS.length) throw new Error('args.quelle und args.ids sind Pflicht')
if (CODE.size && (!DBS.length || !ENV || !SCRATCH)) throw new Error('Für args.code sind dbs, env und scratch Pflicht')

const VERDICT_SCHEMA = {
  type: 'object',
  properties: {
    id: { type: 'string' },
    real: { type: 'boolean' },
    begruendung: { type: 'string', description: 'max 60 Wörter, mit Bildstelle bzw. Datei:Zeile' },
    titel: { type: 'string', description: 'Titel des Befunds (aus der Quelle)' },
    schwere: { type: 'string', enum: ['hoch', 'mittel', 'niedrig'] },
    datei: { type: 'string', description: 'verursachende Datei, repo-relativ (leer, wenn nicht gefunden)' },
    zeile: { type: 'integer' },
    ursache: { type: 'string' },
    fix: { type: 'string', description: 'kleinster regelkonformer Fix (Tokens/Klassen aus notenportal-ui), oder leer' },
    fix_risiko: { type: 'string', description: 'was der Fix brechen könnte, welche anderen Aufrufer betroffen sind, Tests' },
    planentscheid: { type: 'string', description: 'Verweis auf einen Planentscheid (G-Nummer, S-Zeile, Scheibe), dem der Befund widerspricht – sonst leer' },
  },
  required: ['id', 'real', 'begruendung', 'titel', 'schwere', 'datei', 'zeile', 'ursache', 'fix', 'fix_risiko', 'planentscheid'],
}

const GEMEINSAM = (id) => `Du prüfst gegnerisch EINEN Sichtbefund im Notenportal. Lies die JSON-Datei ${QUELLE}: Befund mit id «${id}» (Feld befunde[]), die Bilder liegen unter «bildordner».
Nur lesen: keine Dateien ändern, keine git-, composer- oder npm-Befehle, keine Subagents. Passwörter nie ausgeben.
Ziel ist, den Befund zu WIDERLEGEN. real=true nur mit Beleg; bei Unsicherheit real=false. Gib die id «${id}» zurück.`

function pruefBild(id) {
  return `${GEMEINSAM(id)}
Linse BILD: Lies zuerst .claude/skills/notenportal-dunkelmodus/SKILL.md (Prüfliste §5). Öffne jedes Bild des Befunds mit Read. Ist der beschriebene Zustand eindeutig sichtbar, und verstösst er gegen eine HIG- oder Projektregel (nicht Geschmack)? Teilbehauptungen, die nicht stimmen, in der Begründung nennen. datei/zeile/ursache/fix/planentscheid leer bzw. 0, fix_risiko leer.`
}

function pruefRepro(id, db) {
  return `${GEMEINSAM(id)}
Linse REPRODUKTION (Codebefund ohne Bild): Lies die genannten Stellen und versuche, das behauptete Fehlverhalten mit einem Test oder einer Sonde zu zeigen.
Vor jedem Bash-Befehl mit php/node: \`source ${ENV}\`. Tests nur gegen die Datenbank ${db}: \`DB_DATABASE=${db} php vendor/bin/phpunit <pfad>\`. Sonden und Testdateien NUR unter ${SCRATCH}/${id}/ (anlegen), nie im Repo; ein Test ausserhalb von tests/ läuft mit vollem Pfad. Nie migrate/optimize/config:cache, nie gegen notenportal oder notenportal_demo schreiben.
Reine Test- oder Kommentarqualität (kein Laufzeitverhalten): real=true nur, wenn die Behauptung am Code stimmt und der Test dadurch einen konkreten Fehler nicht fangen würde – dann den Fall nennen.
Laufzeitverhalten (500, falscher Wert, falscher Request): real=true nur, wenn du es zeigst (Befehl und Ausgabe in der Begründung) oder es aus dem Code zwingend folgt (Kette Datei:Zeile). datei/zeile/fix wie gefunden, planentscheid leer.`
}

function pruefUrsache(id) {
  return `${GEMEINSAM(id)}
Linse URSACHE: Lies .claude/skills/notenportal-ui/SKILL.md und ${PLAN} (Grundsätze G1–G21, Statistik-Katalog S1–S11, Scheiben R6-04 bis R6-10). Finde die verursachende Stelle (View, Komponente unter resources/views/components, resources/js, resources/css, app/Support/StatistikDaten.php, lang). real=false, wenn (a) der Code den behaupteten Zustand nicht erzeugt, (b) der Zustand einem Planentscheid folgt (dann planentscheid ausfüllen, z. B. «G18 Notenachse fest 1–6») oder (c) es keinen Fix gibt, der nur Tokens/Klassen aus notenportal-ui nutzt. Sonst real=true mit datei, zeile, ursache, dem kleinsten Fix und fix_risiko (andere Aufrufer der Komponente, betroffene Tests unter tests/Feature/Statistik u. a.). Teilbehauptungen getrennt beurteilen: ist nur ein Teil real, real=true und den Fix auf diesen Teil beschränken, den Rest in der Begründung als widerlegt nennen.`
}

log(`${IDS.length} Befunde, je zwei Linsen (Bild bzw. Reproduktion sonnet high, Ursache opus high)`)

const ergebnisse = await pipeline(
  IDS,
  async (id, _, i) => {
    const erste = CODE.has(id)
      ? () => agent(pruefRepro(id, DBS[i % DBS.length]), { label: `repro:${id}`, phase: 'Bild', schema: VERDICT_SCHEMA, model: 'sonnet', effort: 'high' })
      : () => agent(pruefBild(id), { label: `bild:${id}`, phase: 'Bild', schema: VERDICT_SCHEMA, model: 'sonnet', effort: 'high' })
    const [bild, ursache] = await parallel([
      erste,
      () => agent(pruefUrsache(id), { label: `ursache:${id}`, phase: 'Ursache', schema: VERDICT_SCHEMA, model: 'opus', effort: 'high' }),
    ])
    const ungeprueft = !bild || !ursache
    const real = !ungeprueft && !!(bild.real && ursache.real)
    log(`${id}: ${ungeprueft ? 'ungeprüft' : real ? 'bestätigt' : 'verworfen'}${ursache && ursache.planentscheid ? ' (Plan: ' + ursache.planentscheid + ')' : ''}`)
    return { id, real, ungeprueft, bild, ursache }
  },
)

const alle = ergebnisse.filter(Boolean)
const RANG = { hoch: 0, mittel: 1, niedrig: 2 }
const bestaetigt = alle.filter(e => e.real)
  .sort((x, y) => (RANG[x.ursache.schwere] ?? 3) - (RANG[y.ursache.schwere] ?? 3))
  .map(e => ({
    id: e.id, dim: 'oberflaeche', titel: e.ursache.titel, schwere: e.ursache.schwere,
    datei: e.ursache.datei, zeile: e.ursache.zeile,
    wirkung: e.bild.begruendung, ursache: e.ursache.ursache, fix: e.ursache.fix,
    fix_risiko: [e.ursache.fix_risiko, e.ursache.begruendung].filter(Boolean).join(' | '),
  }))
const verworfen = alle.filter(e => !e.real && !e.ungeprueft).map(e => ({
  id: e.id, titel: (e.ursache && e.ursache.titel) || (e.bild && e.bild.titel),
  planentscheid: e.ursache && e.ursache.planentscheid,
  grund: [e.bild && !e.bild.real && 'Bild: ' + e.bild.begruendung, e.ursache && !e.ursache.real && 'Ursache: ' + e.ursache.begruendung].filter(Boolean).join(' | '),
}))
// Ein ausgefallener Prüfer heisst «nicht geprüft», nie «verworfen»
const ungeprueft = alle.filter(e => e.ungeprueft).map(e => ({ id: e.id, fehlt: [!e.bild && 'Bild', !e.ursache && 'Ursache'].filter(Boolean).join(' + ') }))
const fehlend = IDS.filter(id => !alle.some(e => e.id === id))
if (fehlend.length) log(`ohne Ergebnis: ${fehlend.join(', ')}`)
log(`Fertig: ${bestaetigt.length} bestätigt, ${verworfen.length} verworfen, ${ungeprueft.length} ungeprüft`)

return { bestaetigt, verworfen, ungeprueft, fehlend }
