export const meta = {
  name: 'notenportal-mail-pruefung',
  description: 'Alle Mailarten rendern (DE/EN, HTML und Text), hell und dunkel bei 640 und 390 px aufnehmen, jede Mail beurteilen, jeden Befund gegnerisch verifizieren',
  whenToUse: 'Nach Änderungen an resources/views/mail/*, app/Services/Notifications/* oder Mailtexten, oder wenn die Mails abgenommen werden sollen. args: { env: "<env.sh>", scratch: "<ordner>", db: "notenportal_wNN_test", max?: number }',
  phases: [
    { title: 'Rendern', detail: 'Wegwerf-Test in einer *_test-DB schreibt jede Mailart als HTML und Text, danach Playwright-Aufnahmen' },
    { title: 'Urteil', detail: 'je Mailart ein Prüfer: Bilder, HTML und Textfassung' },
    { title: 'Verifikation', detail: 'zwei Prüfer je Befund: Bild und Ursache im Code' },
  ],
}

// Rückgabe: { mails, bestaetigt, verworfen, ungeprueft } – bestaetigt[] ist direkt die quelle für
// notenportal-befunde-beheben (Indizes = Reihenfolge in bestaetigt).

const A = args || {}
const SCRATCH = A.scratch
const DB = A.db
const ENV = A.env ? `source ${A.env} && ` : ''
const MAX = A.max || 40
if (!SCRATCH || !DB || !/_test$/.test(DB)) throw new Error('args.scratch und args.db (*_test) sind Pflicht')
const MAILDIR = `${SCRATCH}/mails`

const REGELN = `Regeln: keine schreibenden git-Befehle, kein composer, kein npm install, keine Subagents. .env und tmp-testdaten/ nie lesen. Keine Passwörter in Ausgaben. Dateien nur unter ${MAILDIR} anlegen – nichts im Repository ändern.`

const RENDER_SCHEMA = {
  type: 'object',
  properties: {
    mails: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          typ: { type: 'string', description: 'Katalogschlüssel oder Kontomail-Name, mit Suffix _en für die englische Fassung' },
          betreff: { type: 'string' },
          html: { type: 'string', description: 'Absoluter Pfad der HTML-Datei' },
          text: { type: 'string', description: 'Absoluter Pfad der Textfassung' },
          bilder: { type: 'array', items: { type: 'string' }, description: 'Absolute PNG-Pfade (hell/dunkel × 640/390)' },
        },
        required: ['typ', 'betreff', 'html', 'text', 'bilder'],
      },
    },
    fehlend: { type: 'array', items: { type: 'string' }, description: 'Mailarten, die sich nicht rendern liessen, mit Grund' },
    fehler: { type: 'string' },
  },
  required: ['mails', 'fehlend', 'fehler'],
}

const URTEIL_SCHEMA = {
  type: 'object',
  properties: {
    befunde: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          titel: { type: 'string' },
          schwere: { type: 'string', enum: ['hoch', 'mittel', 'niedrig'] },
          art: { type: 'string', enum: ['layout', 'dunkel', 'mobil', 'text', 'textfassung', 'link', 'sprache'] },
          beschreibung: { type: 'string', description: 'Was zu sehen bzw. zu lesen ist, ≤ 50 Wörter' },
          bild: { type: 'string', description: 'Absoluter PNG-Pfad oder leer bei reinen Textbefunden' },
          fix: { type: 'string', description: 'Datei (resources/views/mail/…, app/Services/Notifications/…) und Änderung' },
        },
        required: ['titel', 'schwere', 'art', 'beschreibung', 'bild', 'fix'],
      },
    },
  },
  required: ['befunde'],
}

const VERDIKT_SCHEMA = {
  type: 'object',
  properties: {
    echt: { type: 'boolean', description: 'true nur, wenn der Befund im Bild/Text sichtbar UND die Ursache im Code belegt ist' },
    begruendung: { type: 'string' },
    datei: { type: 'string' },
    zeile: { type: 'integer' },
    fix: { type: 'string', description: 'Minimaler, konkreter Fix (leer, wenn nicht echt)' },
  },
  required: ['echt', 'begruendung', 'datei', 'zeile', 'fix'],
}

phase('Rendern')
const render = await agent(`Du arbeitest in /var/www/notenportal (Laravel 13, Branch main). ${REGELN}

Ziel: Jede Mail, die das Portal verschicken kann, als fertig gerendertes HTML und als Textfassung in ${MAILDIR}/ ablegen und aufnehmen.

1. Lies app/Notifications/PortalMail.php, app/Services/Notifications/{Notifier,AccountMails,MailContent,NotificationCatalog}.php, alle Klassen in app/Services/Notifications/Messages/, app/Console/Commands/SendDigests.php, app/Http/Controllers/Admin/MailSettingsController.php (Testmail) und den Passwort-vergessen-Weg (grep -rn "PortalMail\\|Notifier::" app). Ermittle die vollständige Liste der Mailarten.
2. Schreibe ${MAILDIR}/MailRenderTest.php: Klasse im Namespace Tests\\Wegwerf, extends Tests\\TestCase (bringt RefreshDatabase und den DB-Guard mit). Im Test: $this->seed(\\Database\\Seeders\\DemoSeeder::class) (falls er BasisSeeder braucht, diesen zuerst), dann für JEDE Mailart realistische Daten aus dem Seeder nehmen (Lernende nina.huber, Berufsbildner michael.baumann, Admin laura.frei), MailContent über die echte Klasse bauen (nie von Hand nachbauen) und mit (new \\App\\Notifications\\PortalMail($typ, $content))->toMail($user)->render() das HTML erzeugen; die Textfassung über view('mail.portal-text', dieselben Daten wie in toMail) bzw. über das MailMessage-Objekt. Für die Tageszusammenfassung den echten Weg aus SendDigests nutzen (Notification::fake() bzw. Mail::fake() und die gefakte Nachricht abgreifen, wenn es nicht anders geht). Für jede Mailart zusätzlich eine englische Fassung (Empfänger mit locale/sprache 'en', so wie das Portal die Empfängersprache bestimmt – im Code nachsehen). Dateien: ${MAILDIR}/html/<typ>.html und ${MAILDIR}/text/<typ>.txt, dazu ${MAILDIR}/liste.json [{typ, betreff, html, text}].
3. Ausführen: ${ENV}DB_DATABASE=${DB} php vendor/bin/phpunit ${MAILDIR}/MailRenderTest.php – vorher prüfen, dass die Ausgabe nicht von einer anderen DB stammt (der Guard bricht sonst ab). Läuft es nicht, Ursache beheben (nur in deiner Datei), höchstens 4 Versuche.
4. Aufnahmen: Schreibe ${MAILDIR}/aufnahme.mjs (Playwright; Pfad zum Paket steht in $NP_PLAYWRIGHT nach «${ENV}true», sonst in tools/pruefung/browser.mjs nachsehen, wie die anderen Werkzeuge Playwright laden). Für jede HTML-Datei per file:// vier ganzseitige PNGs: colorScheme light/dark × Breite 640 (Mailfenster am Desktop) / 390 (Telefon), deviceScaleFactor 1. Dateiname ${MAILDIR}/bilder/<typ>-<hell|dunkel>-<640|390>.png. Externe Bilder (Logo-URL) dürfen fehlen; notiere es nur.
5. Ein Bild deiner Wahl mit Read ansehen, um zu prüfen, dass die Aufnahme taugt (nicht leer, nicht abgeschnitten).

Rückgabe: alle Mails mit Pfaden; in «fehlend» jede Mailart, die du gefunden, aber nicht rendern konntest, mit Grund.`, { label: 'rendern', phase: 'Rendern', schema: RENDER_SCHEMA, model: 'sonnet', effort: 'high' })

if (!render || !render.mails || !render.mails.length) {
  return { mails: [], bestaetigt: [], verworfen: [], ungeprueft: [], fehler: render ? render.fehler : 'Render-Agent ohne Ergebnis' }
}
log(`${render.mails.length} Mails gerendert, fehlend: ${render.fehlend.length}`)

const PRUEFLISTE = `Prüfliste Mail (Massstab: Apple Mail, Outlook, Gmail; Desktop UND Telefon):
1 Layout: eine Spalte, Inhaltsbreite ≤ 600 px, ruhige Abstände, klare Hierarchie (Titel > Text > Fakten > Fuss), Logo/Betriebsname sauber, nichts abgeschnitten oder überlappend, keine leeren Blöcke.
2 Mobil 390 px: kein horizontales Scrollen, Text ≥ 14 px, Knopf ≥ 44 px hoch und gut treffbar, Tabellen/Fakten brechen lesbar um, keine Wörter oder Zahlen mitten durchgebrochen.
3 Dunkel: Text und Knopf in prefers-color-scheme: dark lesbar (Kontrast ≥ 4.5:1 sichtbar), keine weissen Kästen auf dunklem Grund, Logo bleibt sichtbar, Linien nicht grell.
4 Text: Schweizer Hochdeutsch, ss statt ß, korrekte Anrede mit Vorname, Betreff sagt in einem Satz, worum es geht, keine Entwicklersprache, keine Platzhalter (:name, {x}), Daten im Format TT.MM.JJJJ, Noten mit einer Nachkommastelle wie im Portal, kein doppelter Inhalt zwischen Titel und erstem Absatz.
5 Fuss: Hinweis, warum man die Mail bekommt (Anlass), Abmelden-Link nur bei abbestellbaren Anlässen, Link zu den Einstellungen.
6 Textfassung: gleiche Inhalte, sauber gegliedert, Links ausgeschrieben, keine HTML-Reste.
7 Englische Fassung: vollständig Englisch (keine deutschen Reste ausser Eigennamen/Fachnamen aus der DB), natürlich formuliert.`

phase('Urteil')
const urteile = await pipeline(
  render.mails,
  (m) => agent(`Beurteile eine Mail des Notenportals. ${REGELN}

Mail: ${m.typ} – Betreff «${m.betreff}»
HTML: ${m.html}
Textfassung: ${m.text}
Bilder (mit Read ansehen, jedes einzelne): ${m.bilder.join(', ')}
Vorlagen zum Nachsehen der Ursache: resources/views/mail/portal.blade.php, resources/views/mail/portal-text.blade.php, app/Services/Notifications/Messages/*, app/Services/Notifications/AccountMails.php, lang/en.json, lang/areas/*/en.json.

${PRUEFLISTE}

Melde nur, was du im Bild oder in der Datei wirklich siehst, mit Fundstelle. Geschmacksfragen ohne Regelbezug weglassen. Gleiche Ursache in mehreren Bildern = ein Befund. Leere Liste ist ein gültiges Ergebnis.`, { label: `urteil:${m.typ}`, phase: 'Urteil', schema: URTEIL_SCHEMA, model: 'sonnet', effort: 'high' })
    .then(u => (u && u.befunde ? u.befunde.map(b => ({ ...b, typ: m.typ, html: m.html, text: m.text })) : [])),
)

// Gleiche Ursache in mehreren Mails (Layout) nur einmal verifizieren
const alle = urteile.filter(Boolean).flat()
const gesehen = new Set()
const eindeutig = []
for (const b of alle) {
  const schluessel = (b.art + '|' + b.titel.toLowerCase().replace(/[^a-zäöü]+/g, ' ').trim().split(' ').slice(0, 5).join(' '))
  if (gesehen.has(schluessel)) continue
  gesehen.add(schluessel)
  eindeutig.push(b)
}
const zuPruefen = eindeutig.sort((a, b) => ['hoch', 'mittel', 'niedrig'].indexOf(a.schwere) - ['hoch', 'mittel', 'niedrig'].indexOf(b.schwere)).slice(0, MAX)
if (eindeutig.length > MAX) log(`${eindeutig.length - MAX} Befunde über max=${MAX} nicht verifiziert`)
log(`${alle.length} Befunde, ${eindeutig.length} eindeutig, ${zuPruefen.length} in Verifikation`)

phase('Verifikation')
const geprueft = await pipeline(
  zuPruefen,
  (b) => parallel([
    () => agent(`Versuche diesen Mail-Befund zu WIDERLEGEN. ${REGELN}
Befund (${b.schwere}, ${b.art}) in Mail ${b.typ}: «${b.titel}» – ${b.beschreibung}
Bild: ${b.bild || '(kein Bild)'} · HTML: ${b.html} · Text: ${b.text}
Sieh dir Bild und Datei selbst an. Prüfe, ob der Befund wirklich sichtbar ist und ob er für echte Mailprogramme zählt (z. B. ist ein in Playwright fehlendes externes Logo kein Befund; ein bewusst gesetztes Mail-Detail mit Begründung im Code auch nicht). Im Zweifel echt=false.`, { label: `sicht:${b.typ}`, phase: 'Verifikation', schema: VERDIKT_SCHEMA, model: 'opus', effort: 'high' }),
    () => agent(`Belege oder widerlege die Ursache dieses Mail-Befunds im Code. ${REGELN}
Befund (${b.schwere}, ${b.art}) in Mail ${b.typ}: «${b.titel}» – ${b.beschreibung}
Vermuteter Fix: ${b.fix}
Lies resources/views/mail/portal.blade.php bzw. portal-text.blade.php und die Nachrichtenklasse in app/Services/Notifications/. Nenne Datei und Zeile der Ursache und den kleinsten Fix, der keine andere Mailart verschlechtert (Mailprogramme: kein flex/grid, Inline-Styles, Tabellenlayout). Gibt es keine Ursache im Code oder ist es Absicht (Kommentar, Test, docs/), echt=false.`, { label: `code:${b.typ}`, phase: 'Verifikation', schema: VERDIKT_SCHEMA, model: 'opus', effort: 'high' }),
  ]).then(v => ({ befund: b, voten: v })),
)

const bestaetigt = []
const verworfen = []
const ungeprueft = []
for (const g of geprueft.filter(Boolean)) {
  const v = g.voten.filter(Boolean)
  if (v.length < 2) { ungeprueft.push(g.befund); continue }
  if (v.every(x => x.echt)) {
    const code = v[1]
    bestaetigt.push({ ...g.befund, datei: code.datei, zeile: code.zeile, fix: code.fix || v[0].fix, begruendung: v.map(x => x.begruendung).join(' | ') })
  } else {
    verworfen.push({ titel: g.befund.titel, typ: g.befund.typ, gruende: v.filter(x => !x.echt).map(x => x.begruendung) })
  }
}
log(`bestätigt ${bestaetigt.length}, verworfen ${verworfen.length}, ungeprüft ${ungeprueft.length}`)
return { mails: render.mails.map(m => m.typ), fehlend: render.fehlend, bestaetigt, verworfen, ungeprueft }
