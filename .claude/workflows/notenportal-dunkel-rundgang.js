export const meta = {
  name: 'notenportal-dunkel-rundgang',
  description: 'Rundgang je Rolle im Dunkelmodus bei 1920 und 2560 px: Screenshots aufnehmen, jedes Bild gegen die HIG beurteilen, jeden Befund gegnerisch verifizieren',
  whenToUse: 'Vor einem Blockabschluss an der Oberfläche oder wenn ein Rollenbereich im Dunkelmodus abgenommen werden soll. Braucht den Demo-Server (tools/pruefung/demo-server.sh).',
  phases: [
    { title: 'Aufnahme', detail: 'rundgang.mjs je Rolle und Breite, Screenshots in einen Ordner' },
    { title: 'Bildurteil', detail: 'Agent bildpruefer je Bündel von Bildern, Prüfliste notenportal-dunkelmodus §5' },
    { title: 'Verifikation', detail: 'zwei Prüfer je Befund: Bild nochmals lesen, Ursache und Fix im Code belegen' },
  ],
}

// Aufruf: Workflow({name: 'notenportal-dunkel-rundgang'}) oder mit args:
//   { rollen: ['lernende','berufsbildner','admin'], breiten: [1920, 2560],
//     ordner: '/root/.notenportal/rundgang', maxBilder: 20, buendel: 4, pruefBuendel: 3, maxSeiten: 150 }
// Rückgabe: { bestaetigt, verworfen, ungeprueft, messung, unvollstaendig: { laeufe, befunde } } –
// «ungeprüft» sind Befunde, deren Prüfer abbrach (Nutzungslimit); nach dem Reset mit resumeFromRunId weiter.
// Kein Date.now() im Skript – wer einen Zeitstempel im Ordnernamen will, gibt ihn per args.ordner mit.

const A = args || {}

const ROLLEN = [
  { rolle: 'lernende', email: 'nina.huber@demo.example', start: '/dashboard' },
  { rolle: 'berufsbildner', email: 'michael.baumann@demo.example', start: '/trainer' },
  { rolle: 'admin', email: 'laura.frei@demo.example', start: '/admin' },
].filter(r => !A.rollen || A.rollen.includes(r.rolle))

const HOEHE = { 1920: 1080, 2560: 1440 }
const BREITEN = (A.breiten || [1920, 2560]).map(b => ({ breite: b, hoehe: HOEHE[b] || Math.round(b * 9 / 16) }))

const ORDNER = A.ordner || '/root/.notenportal/rundgang'
const MAX_BILDER = A.maxBilder || 20
const BUENDEL = A.buendel || 4
const PRUEF_BUENDEL = A.pruefBuendel || 3 // Befunde je Verifikationsrunde (zwei opus-xhigh-Agenten pro Befund)
const MAX_SEITEN = A.maxSeiten || 150

const PW = 'export NP_TEST_PW=$(grep -oP "DEMO_PASSWORT\\s*=\\s*\'\\K[^\']+" database/seeders/DemoSeeder.php)'

const AUFNAHME_SCHEMA = {
  type: 'object',
  properties: {
    exit: { type: 'integer', description: 'Exit-Code von rundgang.mjs (0 = keine Befunde, 1 = Befunde oder Abbruch)' },
    seiten: { type: 'integer', description: 'Zahl aus der Zeile «Besucht N Seiten»' },
    befunde: {
      type: 'array',
      description: 'Jede Befundzeile von rundgang.mjs (Tabulator-getrennt: Art, Pfad, Text)',
      items: {
        type: 'object',
        properties: { art: { type: 'string' }, pfad: { type: 'string' }, text: { type: 'string' } },
        required: ['art', 'pfad', 'text'],
      },
    },
    bilder: { type: 'array', items: { type: 'string' }, description: 'Absolute Pfade aller erzeugten PNG-Dateien' },
    fehler: { type: 'string', description: 'Leer, wenn alles lief; sonst was schiefging (ohne Passwort)' },
  },
  required: ['exit', 'seiten', 'befunde', 'bilder', 'fehler'],
}

const URTEIL_SCHEMA = {
  type: 'object',
  properties: {
    ok: { type: 'array', items: { type: 'string' }, description: 'Bilder ohne Befund (absolute Pfade)' },
    befunde: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          titel: { type: 'string' },
          frage: { type: 'string', description: 'Nummer der Prüffrage aus notenportal-dunkelmodus §5 oder bildpruefer (1–10)' },
          schwere: { type: 'string', enum: ['hoch', 'mittel', 'niedrig'] },
          beschreibung: { type: 'string', description: 'Was im Bild zu sehen ist, in ≤ 40 Wörtern, mit HIG-Regel in drei Wörtern' },
          stelle: { type: 'string', description: 'Wo im Bild (oben links, Tabelle Zeile 3, Fuss der Karte …)' },
          fix: { type: 'string', description: 'Vermutete View (resources/views/…) oder CSS-Datei und was zu ändern wäre' },
          bild: { type: 'string', description: 'Absoluter Pfad des Bildes' },
        },
        required: ['titel', 'frage', 'schwere', 'beschreibung', 'stelle', 'fix', 'bild'],
      },
    },
  },
  required: ['ok', 'befunde'],
}

const VERDICT_SCHEMA = {
  type: 'object',
  properties: {
    real: { type: 'boolean' },
    begruendung: { type: 'string' },
    datei: { type: 'string', description: 'Datei, die den Befund verursacht (leer, wenn nicht gefunden)' },
    zeile: { type: 'integer' },
    fix: { type: 'string', description: 'Regelkonformer Fix mit Tokens/Klassen aus notenportal-ui, oder leer' },
  },
  required: ['real', 'begruendung', 'datei', 'zeile', 'fix'],
}

function aufnahmePrompt(r, b, dir) {
  return `Du nimmst Screenshots des Notenportals auf. Rolle ${r.rolle} (${r.email}), Fenster ${b.breite}×${b.hoehe}, Dunkelmodus.

Schritte, jeder in einem eigenen Bash-Aufruf, ausser Schritt 3:
1. \`bash tools/pruefung/demo-server.sh status\`; meldet er «läuft nicht», dann \`bash tools/pruefung/demo-server.sh start\` und nochmals status.
2. \`mkdir -p ${dir} && find ${dir} -name '*.png' -delete\`
3. Genau EIN Bash-Aufruf, Passwort nur in der Variable, nie ausgeben:
   \`${PW}; node tools/pruefung/rundgang.mjs ${r.email} --breite=${b.breite} --hoehe=${b.hoehe} --start=${r.start} --max=${MAX_SEITEN} --shots=${dir}; echo "EXIT=$?"\`
   Timeout 600000 ms.
4. \`ls ${dir}/*.png\`

Verboten: \`echo $NP_TEST_PW\`, \`env\`, \`printenv\`, das Passwort in irgendeiner Ausgabe oder im Ergebnis.
Ergebnis: exit aus «EXIT=», seiten aus «Besucht N Seiten», jede Befundzeile (Tabulator-getrennt Art/Pfad/Text) als Objekt, alle PNG-Pfade absolut. Läuft etwas nicht, steht es in «fehler» – nichts raten, nichts nachbessern.`
}

function urteilPrompt(r, b, bilder) {
  return `Sichtprüfung Notenportal, Rolle ${r.rolle}, ${b.breite}×${b.hoehe}, Dunkelmodus.
Lies zuerst \`.claude/skills/notenportal-dunkelmodus/SKILL.md\` (Prüfliste §5) und \`.claude/agents/bildpruefer.md\`.
Öffne dann JEDES dieser Bilder mit Read und beurteile es nach der Prüfliste; der Dateiname nennt den Seitenpfad (Sonderzeichen durch «_» ersetzt):
${bilder.map(p => '- ' + p).join('\n')}

Befund nur, was im Bild sichtbar ist und gegen eine HIG-Regel oder Projektregel verstösst – kein Geschmack. Je Befund den absoluten Bildpfad angeben. Bilder ohne Befund in «ok» auflisten. Höchstens 10 Befunde je Bündel, nach Sichtbarkeit geordnet.`
}

function pruefBild(f) {
  return `Gegnerische Prüfung eines Sichtbefunds. Lies das Bild ${f.bild} mit Read und versuche den Befund zu WIDERLEGEN.
Befund: «${f.titel}» – ${f.beschreibung} (Stelle: ${f.stelle}, Prüffrage ${f.frage}).
Lies davor \`.claude/skills/notenportal-dunkelmodus/SKILL.md\` §5. real=true nur, wenn der Befund im Bild eindeutig zu sehen ist und wirklich gegen die genannte Regel verstösst. Bei Unsicherheit real=false. datei/zeile/fix leer lassen.`
}

function pruefUrsache(f, r) {
  return `Ursachenprüfung eines Sichtbefunds im Notenportal (Rolle ${r.rolle}, Bild ${f.bild}).
Befund: «${f.titel}» – ${f.beschreibung} (Stelle: ${f.stelle}). Vermutung des Bildprüfers: ${f.fix}.
Finde die verursachende Stelle: View unter resources/views/, Komponente, resources/css/app.css oder theme.css. Lies \`.claude/skills/notenportal-ui/SKILL.md\` §2–§5 und \`.claude/skills/notenportal-dunkelmodus/SKILL.md\`.
real=true nur, wenn du die Ursache belegst (datei + zeile) UND ein Fix existiert, der nur Tokens und Klassen aus notenportal-ui nutzt (kein Farb-Hardcode, kein dark:-Sonderfall, kein Glas auf Inhalt). Gib diesen Fix an. Ändere nichts.`
}

function teile(liste, n) {
  const out = []
  for (let i = 0; i < liste.length; i += n) out.push(liste.slice(i, i + n))
  return out
}

const LAEUFE = []
for (const r of ROLLEN) for (const b of BREITEN) LAEUFE.push({ r, b, dir: `${ORDNER}/${r.rolle}-${b.breite}` })

log(`${LAEUFE.length} Läufe: ${ROLLEN.map(r => r.rolle).join(', ')} × ${BREITEN.map(b => b.breite).join('/')} px, Bilder nach ${ORDNER}`)

const ergebnisse = await pipeline(
  LAEUFE,
  async ({ r, b, dir }) => {
    const a = await agent(aufnahmePrompt(r, b, dir), {
      label: `aufnahme:${r.rolle}@${b.breite}`, phase: 'Aufnahme', schema: AUFNAHME_SCHEMA, model: 'sonnet', effort: 'medium',
    })
    if (!a) return null
    log(`${r.rolle}@${b.breite}: Exit ${a.exit}, ${a.seiten} Seiten, ${a.befunde.length} Messbefunde, ${a.bilder.length} Bilder${a.fehler ? ' – FEHLER: ' + a.fehler : ''}`)
    return { r, b, dir, aufnahme: a }
  },
  async (lauf) => {
    if (!lauf) return null
    const { r, b, aufnahme } = lauf
    const alle = aufnahme.bilder
    const bilder = alle.slice(0, MAX_BILDER)
    const weggelassen = alle.slice(MAX_BILDER)
    if (weggelassen.length) log(`${r.rolle}@${b.breite}: ${weggelassen.length} Bilder über maxBilder=${MAX_BILDER} NICHT beurteilt`)
    const urteile = await parallel(teile(bilder, BUENDEL).map((buendel, i) => async () => {
      const opts = { label: `bild:${r.rolle}@${b.breite}#${i + 1}`, phase: 'Bildurteil', schema: URTEIL_SCHEMA }
      try {
        return await agent(urteilPrompt(r, b, buendel), { ...opts, agentType: 'bildpruefer' })
      } catch (e) {
        log(`bildpruefer nicht verfügbar (${e && e.message ? e.message : e}) – Ersatz opus xhigh`)
        return await agent(urteilPrompt(r, b, buendel), { ...opts, model: 'opus', effort: 'xhigh' })
      }
    }))
    const befunde = urteile.filter(Boolean).flatMap(u => u.befunde || [])
    const ok = urteile.filter(Boolean).flatMap(u => u.ok || [])
    log(`${r.rolle}@${b.breite}: ${befunde.length} Sichtbefunde, ${ok.length} Bilder ok`)
    return { ...lauf, befunde, ok, weggelassen }
  },
  async (lauf) => {
    if (!lauf) return null
    const { r, b, befunde } = lauf
    // Prüfer in Bündeln statt alle auf einmal: parallele opus-xhigh-Agenten brauchen das
    // Nutzungslimit der Sitzung in Minuten auf (SETUP-CLAUDE.md §12.11). Fällt ein Prüfer aus
    // (agent() → null), gilt der Befund als ungeprüft, nie als verworfen.
    const geprueft = []
    let n = 0
    for (const buendel of teile(befunde, PRUEF_BUENDEL)) {
      const teil = await parallel(buendel.map((f) => async () => {
        const i = ++n
        const [bild, ursache] = await parallel([
          () => agent(pruefBild(f), { label: `bild-check:${r.rolle}@${b.breite}#${i}`, phase: 'Verifikation', schema: VERDICT_SCHEMA, model: 'opus', effort: 'xhigh' }),
          () => agent(pruefUrsache(f, r), { label: `ursache:${r.rolle}@${b.breite}#${i}`, phase: 'Verifikation', schema: VERDICT_SCHEMA, model: 'opus', effort: 'xhigh' }),
        ])
        const ungeprueft = !bild || !ursache
        const real = !ungeprueft && !!(bild.real && ursache.real)
        return { ...f, rolle: r.rolle, breite: b.breite, real, ungeprueft, bild_urteil: bild, ursache_urteil: ursache }
      }))
      geprueft.push(...teil.filter(Boolean))
      const offen = teil.filter(t => t && t.ungeprueft).length
      if (offen) log(`${r.rolle}@${b.breite}: ${offen} Befunde ohne Prüferurteil (Agent abgebrochen)`)
    }
    return { ...lauf, geprueft }
  },
)

const laeufe = ergebnisse.filter(Boolean)
const RANG = { hoch: 0, mittel: 1, niedrig: 2 }
const alleBefunde = laeufe.flatMap(l => l.geprueft)
const bestaetigt = alleBefunde.filter(f => f.real).sort((x, y) => RANG[x.schwere] - RANG[y.schwere])
const verworfen = alleBefunde.filter(f => !f.real && !f.ungeprueft)
const ungeprueft = alleBefunde.filter(f => f.ungeprueft)

const messung = laeufe.map(l => ({
  rolle: l.r.rolle, breite: l.b.breite, hoehe: l.b.hoehe, ordner: l.dir,
  exit: l.aufnahme.exit, seiten: l.aufnahme.seiten, fehler: l.aufnahme.fehler,
  messbefunde: l.aufnahme.befunde,
  bilder: l.aufnahme.bilder.length, beurteilt: Math.min(l.aufnahme.bilder.length, MAX_BILDER), weggelassen: l.weggelassen,
}))

const fehlend = LAEUFE.length - laeufe.length
if (fehlend) log(`${fehlend} Läufe ohne Ergebnis (Agent abgebrochen) – nicht als geprüft zählen`)
if (ungeprueft.length) log(`${ungeprueft.length} Sichtbefunde ohne Prüferurteil (Agent abgebrochen, z. B. Nutzungslimit) – ungeprüft, nicht verworfen; nach dem Reset mit resumeFromRunId fortsetzen`)
log(`Fertig: ${bestaetigt.length} bestätigte Sichtbefunde, ${verworfen.length} verworfen, ${ungeprueft.length} ungeprüft, ${messung.reduce((s, m) => s + m.messbefunde.length, 0)} Messbefunde aus rundgang.mjs`)

return {
  bestaetigt: bestaetigt.map(f => ({
    rolle: f.rolle, breite: f.breite, schwere: f.schwere, titel: f.titel, beschreibung: f.beschreibung, stelle: f.stelle, bild: f.bild,
    datei: f.ursache_urteil.datei, zeile: f.ursache_urteil.zeile, fix: f.ursache_urteil.fix,
  })),
  verworfen: verworfen.map(f => ({ rolle: f.rolle, breite: f.breite, titel: f.titel, bild: f.bild, grund: [f.bild_urteil && f.bild_urteil.begruendung, f.ursache_urteil && f.ursache_urteil.begruendung].filter(Boolean).join(' | ') })),
  ungeprueft: ungeprueft.map(f => ({ rolle: f.rolle, breite: f.breite, schwere: f.schwere, titel: f.titel, stelle: f.stelle, bild: f.bild, fehlt: [!f.bild_urteil && 'Bildprüfer', !f.ursache_urteil && 'Ursachenprüfer'].filter(Boolean).join(' + ') })),
  messung,
  // Läufe ohne Aufnahme und Befunde ohne Prüferurteil – beides heisst «nicht geprüft», nicht «in Ordnung»
  unvollstaendig: { laeufe: fehlend, befunde: ungeprueft.length },
}
