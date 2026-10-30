export const meta = {
  name: 'notenportal-blockpruefung',
  description: 'Blockabschluss: Bilder der geänderten Seiten (bildpruefer) und Views (ui-checker) je Rolle prüfen, jeden Befund zweifach gegnerisch verifizieren',
  whenToUse: 'Nach einem Fix- oder Feature-Block mit geänderten Views, wenn die Bilder der geänderten Seiten schon aufgenommen sind (tools/pruefung/shot.mjs je Rolle und Breite in eigene Ordner). args: { rollen: [{ rolle, pfade: ["/grades", …], fokus: "<was im Block geändert wurde>", bilder: ["<abs. png>"], views: ["resources/views/…"] }], diff: "<von>..<bis>", env?: "<env.sh>" }',
  phases: [
    { title: 'Finden', detail: 'bildpruefer je Rolle und Bildbündel, ui-checker je Rollenbereich' },
    { title: 'Verifizieren', detail: 'je Befund zwei Gegenprüfer (Bild bzw. Code, Ursache und Fix)' },
  ],
}

const A = args || {}
const ROLLEN = A.rollen || []
const DIFF = A.diff || 'origin/main~1..origin/main'
const ENV = A.env ? `Vor jedem Bash-Befehl mit php/node: \`source ${A.env}\` (VM: lenkt Konfig-Cache um). ` : ''

const BEFUNDE = {
  type: 'object',
  properties: {
    befunde: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          schwere: { type: 'string', enum: ['hoch', 'mittel', 'niedrig'] },
          titel: { type: 'string' },
          beschreibung: { type: 'string', description: 'Was sichtbar/im Code falsch ist und gegen welche Regel (Skill-Abschnitt nennen)' },
          bild: { type: 'string', description: 'Absoluter Bildpfad, leer bei reinem Codebefund' },
          datei: { type: 'string', description: 'resources/views/… oder resources/js/…, falls bekannt' },
          zeile: { type: 'string' },
          vorschlag: { type: 'string', description: 'Konkreter Fix nur mit Tokens/Klassen aus notenportal-ui' },
        },
        required: ['schwere', 'titel', 'beschreibung', 'bild', 'datei', 'zeile', 'vorschlag'],
      },
    },
    ok: { type: 'array', items: { type: 'string' }, description: 'Bilder bzw. Views ohne Befund' },
  },
  required: ['befunde', 'ok'],
}

const URTEIL = {
  type: 'object',
  properties: {
    real: { type: 'boolean' },
    begruendung: { type: 'string' },
    datei: { type: 'string' },
    zeile: { type: 'string' },
    fix: { type: 'string' },
  },
  required: ['real', 'begruendung', 'datei', 'zeile', 'fix'],
}

const NUR_LESEN = 'Nur lesen: keine Dateien ändern, keine git-Schreibbefehle, keine Tests, keine Subagents.'

function bildAuftrag(r, bilder) {
  return `Sichtprüfung Notenportal nach einem Fix-Block, Rolle ${r.rolle}, Dunkelmodus, Desktop (Dateiname endet auf -1920 oder -2560).
Lies zuerst .claude/skills/notenportal-dunkelmodus/SKILL.md (Prüfliste §5) und .claude/skills/notenportal-ui/SKILL.md.
Öffne JEDES dieser Bilder mit Read und beurteile es:
${bilder.map(p => '- ' + p).join('\n')}
Die Seiten (gleiche Reihenfolge je Breite): ${r.pfade.join(', ')}.
Im Block geändert (darauf besonders achten): ${r.fokus}.
Befund nur, was im Bild eindeutig sichtbar ist und gegen eine HIG- oder Projektregel verstösst (Kontrast, Hierarchie, Abstände, Ausrichtung, abgeschnittener/überlappender Text, tote Leerflächen, Hinweistexte in der Oberfläche, ß). Kein Geschmack. Bild ohne Befund → ok. Höchstens 10 Befunde, nach Sichtbarkeit. ${NUR_LESEN}`
}

function uiAuftrag(r) {
  return `UI-Prüfung Notenportal nach einem Fix-Block, Rollenbereich ${r.rolle}.
Geänderte Views: ${r.views.join(', ')}. Diff: \`git diff ${DIFF} -- ${r.views.join(' ')}\` (dazu resources/js/rechner.js, resources/js/tastenkuerzel.js, resources/js/np.js, falls für diese Rolle relevant).
Prüfe NUR die geänderten Stellen und ihr direktes Umfeld gegen .claude/skills/notenportal-ui/SKILL.md, .claude/skills/notenportal-dunkelmodus/SKILL.md und .claude/rules/oberflaeche.md: Tokens statt Farb-Hardcodes, keine harten Schriftgrössen, Zustände (leer, lädt, Fehler), Tastatur und Fokus, aria (invalid/describedby/live/busy), ruhig:-Varianten, Texte mit __() und Englisch in lang/*.json, keine Hinweistexte.
Befund nur mit Datei und Zeile und einem Fix, der nur Tokens/Klassen aus notenportal-ui nutzt. bild leer lassen. ${NUR_LESEN}`
}

function pruefBildAuftrag(f) {
  return `Gegnerische Prüfung eines Sichtbefunds im Notenportal. Lies das Bild ${f.bild} mit Read und versuche den Befund zu WIDERLEGEN.
Befund: «${f.titel}» – ${f.beschreibung}
Lies davor .claude/skills/notenportal-dunkelmodus/SKILL.md §5. real=true nur, wenn der Befund im Bild eindeutig zu sehen ist UND gegen eine genannte Regel verstösst (nicht Geschmack, nicht bewusst so entschieden laut docs/audit-backlog.md oder docs/auftrag/GUI-R6.md). Bei Unsicherheit real=false. datei/zeile/fix leer lassen. ${NUR_LESEN}`
}

function pruefCodeAuftrag(f, r) {
  return `Ursachenprüfung eines Befunds im Notenportal (Rolle ${r.rolle}${f.bild ? ', Bild ' + f.bild : ''}). ${ENV}
Befund: «${f.titel}» – ${f.beschreibung}. Ort laut Finder: ${f.datei || '?'}:${f.zeile || '?'}. Vorschlag: ${f.vorschlag}.
Versuche den Befund zu WIDERLEGEN: Lies die Stelle im Code, .claude/skills/notenportal-ui/SKILL.md und .claude/skills/notenportal-dunkelmodus/SKILL.md, prüfe docs/audit-backlog.md (bewusst gelassen?) und docs/auftrag/GUI-R6.md (Planentscheid?).
real=true nur, wenn du die Ursache belegst (datei + zeile), sie kein bewusster Entscheid ist UND ein Fix existiert, der nur Tokens/Klassen aus notenportal-ui nutzt (kein Farb-Hardcode, kein dark:-Sonderfall, kein Glas auf Inhalt). Gib diesen Fix konkret an. Bei Unsicherheit real=false. ${NUR_LESEN}`
}

const FINDER = []
for (const r of ROLLEN) {
  if (r.bilder && r.bilder.length) FINDER.push({ r, art: 'bild', label: `bild:${r.rolle}` })
  if (r.views && r.views.length) FINDER.push({ r, art: 'ui', label: `ui:${r.rolle}` })
}
log(`${FINDER.length} Finder: ${FINDER.map(f => f.label).join(', ')}`)

const ergebnisse = await pipeline(
  FINDER,
  async (fi) => {
    const prompt = fi.art === 'bild' ? bildAuftrag(fi.r, fi.r.bilder) : uiAuftrag(fi.r)
    const opts = { label: fi.label, phase: 'Finden', schema: BEFUNDE }
    let res = null
    try {
      res = await agent(prompt, { ...opts, agentType: fi.art === 'bild' ? 'bildpruefer' : 'ui-checker' })
    } catch (e) {
      log(`${fi.label}: Agenttyp nicht verfügbar – Ersatz opus high`)
      res = await agent(prompt, { ...opts, model: 'opus', effort: 'high' })
    }
    if (!res) { log(`${fi.label}: abgebrochen – NICHT geprüft`); return null }
    log(`${fi.label}: ${res.befunde.length} Befunde, ${res.ok.length} ok`)
    return { ...fi, befunde: res.befunde, ok: res.ok }
  },
  async (fund) => {
    if (!fund) return null
    const geprueft = await parallel(fund.befunde.map((f, i) => async () => {
      const pruefer = [() => agent(pruefCodeAuftrag(f, fund.r), { label: `code:${fund.label}#${i + 1}`, phase: 'Verifizieren', schema: URTEIL, model: 'opus', effort: 'high' })]
      if (f.bild) pruefer.push(() => agent(pruefBildAuftrag(f), { label: `bild-check:${fund.label}#${i + 1}`, phase: 'Verifizieren', schema: URTEIL, model: 'opus', effort: 'high' }))
      else pruefer.push(() => agent(pruefCodeAuftrag(f, fund.r) + '\nZweite, unabhängige Prüfung: lies die Stelle selbst neu, verlass dich auf nichts.', { label: `code2:${fund.label}#${i + 1}`, phase: 'Verifizieren', schema: URTEIL, model: 'sonnet', effort: 'high' }))
      const urteile = await parallel(pruefer)
      const ungeprueft = urteile.some(u => !u)
      const real = !ungeprueft && urteile.every(u => u.real)
      const code = urteile[0]
      return { rolle: fund.r.rolle, quelle: fund.art, ...f, real, ungeprueft, datei: (code && code.datei) || f.datei, zeile: (code && code.zeile) || f.zeile, fix: (code && code.fix) || f.vorschlag, gruende: urteile.filter(Boolean).map(u => u.begruendung) }
    }))
    return { label: fund.label, ok: fund.ok, geprueft: geprueft.filter(Boolean) }
  },
)

const fertig = ergebnisse.filter(Boolean)
const fehlend = FINDER.length - fertig.length
const alle = fertig.flatMap(e => e.geprueft)
const RANG = { hoch: 0, mittel: 1, niedrig: 2 }
const bestaetigt = alle.filter(f => f.real).sort((a, b) => RANG[a.schwere] - RANG[b.schwere])
const verworfen = alle.filter(f => !f.real && !f.ungeprueft)
const ungeprueft = alle.filter(f => f.ungeprueft)
if (fehlend) log(`${fehlend} Finder ohne Ergebnis – nicht als geprüft zählen`)
log(`Fertig: ${bestaetigt.length} bestätigt, ${verworfen.length} verworfen, ${ungeprueft.length} ungeprüft`)
return {
  bestaetigt: bestaetigt.map(f => ({ rolle: f.rolle, quelle: f.quelle, schwere: f.schwere, titel: f.titel, beschreibung: f.beschreibung, bild: f.bild, datei: f.datei, zeile: f.zeile, fix: f.fix })),
  verworfen: verworfen.map(f => ({ rolle: f.rolle, quelle: f.quelle, titel: f.titel, grund: f.gruende.join(' | ') })),
  ungeprueft: ungeprueft.map(f => ({ rolle: f.rolle, titel: f.titel })),
  ok: fertig.map(e => ({ label: e.label, ok: e.ok.length })),
  unvollstaendig: fehlend,
}
