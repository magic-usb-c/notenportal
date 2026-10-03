export const meta = {
  name: 'notenportal-r6-entwurf',
  description: 'R6 GUI-Rebuild: drei unabhängige Gesamtentwürfe (Material, Bewegung, Daten), Jury, Synthese zu GUI-R6.md, gegnerische Prüfung',
  phases: [
    { title: 'Entwürfe', detail: 'drei Blickwinkel, je ein vollständiges Konzept', model: 'opus' },
    { title: 'Jury', detail: 'drei Juroren bewerten jeden Entwurf nach festen Kriterien', model: 'opus' },
    { title: 'Synthese', detail: 'bester Entwurf plus die besten Ideen der anderen → Scheibenplan', model: 'opus' },
    { title: 'Gegenprüfung', detail: 'zwei Widerleger gegen Quellen und Messwerte', model: 'opus' },
  ],
}

const A = args || {}
const laenge = (x) => JSON.stringify(x ?? null, null, 1).length
const QUELLEN_STANDARD = [
  'docs/auftrag/messungen/r6-verstehen/synthese.json (Lagebild, 10 Widersprüche mit Apple-Quellen, 9 Scheibenvorschläge, 15 Statistik-Ideen, 14 Kernbehauptungen)',
  'docs/auftrag/messungen/r6-verstehen/inventare.json (6 Leser: diagramme, material, daten, seiten, apple mit 103 HIG-/WWDC-Fakten samt URL, browser)',
  'docs/auftrag/messungen/r6-verstehen/refutationen.json (2 Widerleger: widerlegt, bestaetigt, ungeprueft, luecken_im_plan)',
]
const QUELLEN = Array.isArray(A.quellen) && A.quellen.length ? A.quellen : QUELLEN_STANDARD
log(`Faktenbasis: ${QUELLEN.length} Dateien`)
const KONTEXT = `
Du arbeitest im Repository-Wurzelverzeichnis des Notenportals (Cloud: $CLAUDE_PROJECT_DIR, /home/user/notenportal). Du liest nur – keine Dateien ändern, keine git-/composer-/npm-Befehle, keine Subagents. Antworte auf Deutsch (Schweizer Hochdeutsch, ss statt ß).

AUFTRAG (David, Lernender Informatiker, Betreiber des Portals, wörtlich): «mach einen geileren gui rebuild – es braucht viel geilere grafiken, programmiere geilere statistiken und so, sie müssen auch sinnvoll sein und anpassbar mit so filtern mässig. es braucht mehr transparenz, bewegung, animationen und so, so peak liquid glass mässig aber auch wirklich auf apple niveau, dafür gibt es ja auch quellen mit ressourcen die dafür helfen von apple selbst. stell dir vor du musst mit diesem gui einen award gewinnen.»

PRODUKT: Webportal für Lehrbetriebe (Laravel 13, Blade, Tailwind 4 CSS-first, Alpine, Chart.js, Vite; Inter Variable). Lernende erfassen Noten (Fachunterricht, ÜK, BMS, ABU), Berufsbildner begleiten, Admins verwalten. Massstab: Desktop-Browser 1920×1080 bis 2560×1440 im Dunkelmodus nach Apple Human Interface Guidelines; hell und schmal dürfen nicht brechen, bekommen keine eigene Gestaltungsarbeit. 12 Themes × hell/dunkel, 7 Akzentfarben, persönliche Schalter «Bewegungen reduzieren» und «Transparenz reduzieren».

HARTE REGELN (nicht verhandelbar): keine Farb-Hardcodes, keine harten Schriftgrössen, Tokens nur in theme.css + @theme inline; keine SF Pro, keine SF Symbols, keine Apple-Systemfarbwerte abschreiben; UI-Texte Schweizer Hochdeutsch; Berechtigung serverseitig (Lernende nur eigene Daten, Berufsbildner nur aktiv betreute); Noten-Queries whereNull('geloescht_am'); alle Durchschnitte aus App\\Services\\Auswertung (docs/notenlogik.md), nie aus SQL/Controller/View; Betriebsspezifisches aus der DB; Routennamen englisch, Oberfläche deutsch; neue Routen in ZugriffsschutzTest/EnglischeSeitenTest/AbfragenAnzahlTest; prefers-reduced-motion und der persönliche Schalter müssen jede Bewegung auf Überblendung reduzieren; prefers-reduced-transparency und der persönliche Schalter müssen jedes Glas deckend machen. Lesbarkeit darf nie von einer Erweiterung (SVG-Refraktion, color-mix, @starting-style) abhängen – Fallback zuerst.
KEIN GLAS IN DER INHALTSEBENE – entschieden, nicht verhandelbar: HIG Materials «Don't use Liquid Glass in the content layer», WWDC25/219 «making it Liquid Glass would make it compete with other elements and muddy the hierarchy», Repo-Regel .claude/rules/oberflaeche.md («kein Glas auf Karten/Tabellen/Formularen»). Karten, Tabellen, Formulare, Diagrammflächen, Kennzahlkacheln bleiben matte Flächen (bg-card). Erlaubt und erwünscht sind genau diese Wege: (a) eine reichere Funktionsebene – Seitenleiste, Symbolleiste, Kapseln, Menüs, Popover, Palette, Toasts, Drawer-Kopf – mit Linsenkante/Lichtkante, adaptiver Tönung aus dem Theme, Aufleuchten bei Interaktion («illuminates from within»), Morph vom Knopf zum Menü/Popover, Scroll-Kante statt Vollfläche; (b) ein ruhiger, bewusst gestalteter Grund hinter der schwebenden Seitenleiste und unter dem Inhalt (sehr weiche themenfarbige Verläufe aus Tokens, keine Bilder, keine Dauerbewegung ohne Pausierbarkeit), damit das Glas etwas zu brechen hat; (c) die HIG-Ausnahme: Bedienelemente im Inhalt (Schieberegler, Segment-Marker, Schalter) heben sich NUR während der Interaktion ins Glas und ruhen matt («Elements can even lift up into Liquid Glass temporarily»). Kein Glas auf Glas (WWDC25/219): Elemente auf Glas bekommen Füllung/Transparenz/Vibrancy, kein zweites backdrop-filter.
BACKDROP-ROOT-FAKT (Spezifikation filter-effects-2 und css-view-transitions-1): opacity<1, filter, mask/clip-path, mix-blend-mode, will-change darauf oder ein view-transition-name auf dem Element oder einem Vorfahren machen das Element zur Backdrop Root – ein Glas darin sieht nur noch den Vorfahren, nicht mehr die Seite. Morph-Ideen (view-transition-name auf Menüs/Kapseln) kollidieren damit; np-glas-gruppe legt das Glas deshalb heute auf ::before. Jede Morph-Massnahme muss sagen, wie sie das umgeht (Glas auf dem morphenden Element selbst, Snapshot akzeptieren, oder Morph ohne Glas).

MESSWERTE (docs/auftrag/messungen/r6-leistung-baseline.md, 03.10.2026, Headless-Chromium):
- Heute 4 Glasflächen = 18.4 % des Fensters (Seitenleiste np-glas, Symbolleiste-Kante, 2 Kapseln). Befehlspalette offen: 5 Flächen = 34.6 %.
- Bilddauer in Ruhe p95 16.9 ms (60-Hz-Takt). Tippen in der Palette p95 ≈ 28–36 ms MIT Glas, 17 ms OHNE Glas → das Glas der Palette (blur 28px über 45 % des Fensters bei 540 px Höhe) kostet eine ganze Bildperiode. Scrollen unter Leiste/Kapseln: p95 +2 ms, max +9 ms.
- Kontrast (tools/pruefung/kontrast.mjs --minimum): kleinste Deckung von --card, bei der text UND muted 4.5:1 über JEDER Token-Unterlage (Grund, Karte, Akzent, Chart-, Notenfarben) halten: 0.72–0.91 (Gletscher hell 0.91, Gletscher dunkel 0.85). Heutige Materialien 0.74–0.92 → «mehr Transparenz» mit statischer Textfarbe ist über Inhalt NICHT möglich. MIT Scrollkante (Unterlage vorher mit --bg zu 0.72 abgeblendet): dunkle Themes 0.00–0.37 (Gletscher dunkel 0.26), helle 0.35–0.73. Unter der schwebenden Seitenleiste (position: fixed, Hauptspalte rückt per padding aus) liegt nur der Seitengrund – dort hält jede Deckung, aber das Glas hat nichts zu brechen, solange der Grund eine Vollfläche ist.
- ThemeKontrastTest prüft jetzt auch die Materialien (Deckungen direkt aus app.css); kontrast.mjs Exit 1 bei Verstoss.

FAKTENBASIS – LIES DIESE DATEIEN ZUERST UND VOLLSTÄNDIG (Ergebnis des Verstehen-Workflows: 6 Leser, Synthese, 2 Widerleger; Quellenangaben daraus übernehmen, nichts dazuerfinden; was ein Widerleger widerlegt hat, gilt nicht):
${QUELLEN.map((q) => `- ${q}`).join('\n')}

VORENTSCHEIDUNGEN DER HAUPTSITZUNG (gelten für alle Entwürfe; die Begründung kommt in GUI-R6.md, nur echte Produkt- und Rechtsfragen bleiben für David offen):
1. Inhalt läuft NICHT unter der Seitenleiste durch. Grund: Glas über scrollendem Inhalt ist der teuerste Fall (Baseline §3), die Seitenleiste müsste dann 0.72–0.91 deckend sein (kontrast.mjs --minimum) und wäre kein Glas mehr; HIG: «more opaque in larger elements like sidebars». Die Symbolleiste behält die Scrollkante (dort läuft Inhalt schon durch). Der Grund hinter der Seitenleiste und unter dem Inhalt darf gestaltet werden: ein ruhiger, kaum wahrnehmbarer Helligkeits- oder Tonverlauf aus Tokens (--bg, leicht aufgehellt oder mit sehr kleiner Akzentdeckung), je Theme, statisch; im Kontrast-Theme und bei reduzierter Transparenz entfällt er. Keine Orbs, keine Bilder, keine Dauerbewegung.
2. Kohortenvergleich für LERNENDE (eigene Position im Jahrgang oder Betrieb) kommt NICHT in R6 (Datenschutz; der Pilot mit 5 Lernenden erreicht keine Mindestgrösse). Berufsbildner und Admin sehen Gruppenwerte nur über die ihnen sichtbaren Personen; Median und Quartile erst ab 3 Personen, darunter Einzelpunkte.
3. Keine neue Auswertung des Erfassungsverhaltens je Person (Rhythmus, Wochentage, Verzug) für Berufsbildner; was heute sichtbar ist (Tage ohne Note, Filter «mit neuen Noten»), bleibt. Betriebsweite Summen (Aktivität je Woche) sind erlaubt. Die Frage geht an David.
4. Kein Vergleich der Berufsbildner untereinander für Admins in R6 (Personalfrage). Geht an David.
5. Keine Snapshot-Tabelle und keine Schemaänderung für Trends: Semestertrends kommen aus den Personenschnitten je Semester (Auswertung), Verläufe nach Datum aus Leistung::datum mit Stichtag-Auswertung.
6. Lichtbrechung am Glasrand höchstens als optionale letzte Stufe (nur Chromium, per JS-Erkennung, nie per @supports), wenn der Fallback vollständig ist, Text unverzerrt bleibt und der Kontrast sich nicht ändert. Ob sie in R6 gehört, entscheidet die Jury nach Nutzen und Kosten.
7. Filterzustand liegt in der URL (replaceState) und wird serverseitig als Whitelist gelesen; Daten kommen über dieselbe Route als JSON (Accept: application/json) oder per Voll-Reload. Neue Routen nur, wenn nötig: englische Namen, Einträge in ZugriffsschutzTest, EnglischeSeitenTest, AbfragenAnzahlTest.
8. Chart.js 4 bleibt die Diagrammbibliothek (keine neue Abhängigkeit); Blade-SVG für Sparkline, Bullet und Heatmap bleibt erlaubt. Jedes Diagramm hat eine Tabellenalternative.

Lies zusätzlich selbst, bevor du schreibst: docs/auftrag/GUI-APPLE.md, docs/gui-konzept.md (Abschnitte Elevation, Glas-Stufen, Motion, Diagramme), resources/css/app.css (Materialien ab «Materialien», Fensterstruktur, Bewegung), resources/css/theme.css (Kopf, Gletscher hell/dunkel), resources/js/np.js und resources/js/charts.js (Funktionsliste), die Views resources/views/dashboards/lernender.blade.php, resources/views/dashboards/berufsbildner.blade.php, resources/views/dashboards/admin.blade.php, resources/views/lernender/noten/index.blade.php, resources/views/verwaltung/lernende/_cockpit/uebersicht.blade.php, resources/views/admin/berichte/noten.blade.php, resources/views/components/diagramm.blade.php, resources/views/layouts/navigation.blade.php, app/Services/Auswertung/Auswertung.php und app/Services/Uebersicht.php (Methodenlisten) und docs/auftrag/messungen/r6-leistung-baseline.md.
`

const ENTWURF_SCHEMA = {
  type: 'object',
  properties: {
    leitidee: { type: 'string', description: 'Der eine Satz, der den Entwurf trägt' },
    bild: { type: 'string', description: 'Wie eine Seite (Lernenden-Dashboard, 1920 dunkel) nach dem Umbau aussieht: Ebenen, Licht, Bewegung, in 10–15 Sätzen' },
    fundament: { type: 'array', items: { type: 'object', properties: { was: { type: 'string' }, datei: { type: 'string' }, css_oder_js_skizze: { type: 'string' }, apple_quelle: { type: 'string' }, fallback: { type: 'string' } }, required: ['was', 'datei', 'css_oder_js_skizze', 'apple_quelle', 'fallback'] } },
    massnahmen: { type: 'array', items: { type: 'object', properties: { id: { type: 'string' }, titel: { type: 'string' }, was: { type: 'string' }, wo: { type: 'array', items: { type: 'string' } }, apple_quelle: { type: 'string' }, akzeptanz: { type: 'array', items: { type: 'string' }, description: 'messbare Kriterien: Zahl, Werkzeug, Schwelle' }, risiko: { type: 'string' }, aufwand: { type: 'string', enum: ['S', 'M', 'L'] }, wirkung: { type: 'string', enum: ['hoch', 'mittel', 'niedrig'] } }, required: ['id', 'titel', 'was', 'wo', 'apple_quelle', 'akzeptanz', 'risiko', 'aufwand', 'wirkung'] } },
    statistiken: { type: 'array', items: { type: 'object', properties: { seite: { type: 'string' }, rolle: { type: 'string' }, frage: { type: 'string', description: 'Welche Frage beantwortet die Grafik dem Menschen davor?' }, darstellung: { type: 'string' }, filter: { type: 'array', items: { type: 'string' } }, daten: { type: 'string', description: 'woher (Auswertung-Methode, Tabellen), Berechtigung' }, interaktion: { type: 'string' }, apple_quelle: { type: 'string' } }, required: ['seite', 'rolle', 'frage', 'darstellung', 'filter', 'daten', 'interaktion', 'apple_quelle'] } },
    bewegung: { type: 'array', items: { type: 'object', properties: { ausloeser: { type: 'string' }, was_bewegt_sich: { type: 'string' }, dauer_ms: { type: 'number' }, kurve: { type: 'string' }, reduziert: { type: 'string', description: 'Verhalten bei reduced motion' }, apple_quelle: { type: 'string' } }, required: ['ausloeser', 'was_bewegt_sich', 'dauer_ms', 'kurve', 'reduziert', 'apple_quelle'] } },
    glas_budget: { type: 'object', properties: { flaechen: { type: 'array', items: { type: 'string' } }, deckungen: { type: 'string' }, max_anteil_fenster_prozent: { type: 'number' }, begruendung: { type: 'string' } }, required: ['flaechen', 'deckungen', 'max_anteil_fenster_prozent', 'begruendung'] },
    verworfen: { type: 'array', items: { type: 'object', properties: { idee: { type: 'string' }, warum: { type: 'string' } }, required: ['idee', 'warum'] } },
    offen_fuer_david: { type: 'array', items: { type: 'string' } },
  },
  required: ['leitidee', 'bild', 'fundament', 'massnahmen', 'statistiken', 'bewegung', 'glas_budget', 'verworfen', 'offen_fuer_david'],
}

const BLICKWINKEL = [
  { key: 'material', titel: 'Material zuerst', prompt: 'Starte beim Material: Liquid Glass als eigene Schicht über dem Inhalt – Linse/Brechung (Rand), Spiegellicht, adaptive Tönung, Scroll-Kante, Hover-/Druck-Reaktion, Morph zwischen Zuständen; Tiefe im Dunkelmodus über Helligkeitsstufen, ein ruhiger, bewusst gestalteter Grund hinter der schwebenden Seitenleiste, damit das Glas etwas zu brechen hat. Bewegung und Statistik gehören dazu, aber dienen dem Material.' },
  { key: 'bewegung', titel: 'Bewegung zuerst', prompt: 'Starte bei der Bewegung: Choreografie beim Seitenwechsel und Erscheinen (gestaffelt, Federkurven über linear()), Zählwerke für Kennzahlen, Diagramme, die sich zeichnen, Morph der Glas-Elemente, Übergänge Tab/Segment/Drawer/Palette, Hover- und Druckantwort; alles kollabiert unter reduced motion zur Überblendung. Material und Statistik gehören dazu, aber dienen der Bewegung.' },
  { key: 'daten', titel: 'Daten zuerst', prompt: 'Starte bei den Statistiken: Welche Fragen hat eine Lernende, ein Berufsbildner, ein Admin wirklich (Stehe ich im Plan? Wo kippt es? Was braucht es noch? Wer braucht mich diese Woche?) und welche Grafik beantwortet sie auf einen Blick; Filter (Semester, Kategorie, Fach, Zeitraum, Lernende, Lehrjahr, Beruf) in der URL, JSON-Endpunkte mit serverseitiger Berechtigung, Durchschnitte nur aus Auswertung, Diagrammregeln der HIG «Charts»; «Als Tabelle» und Export bleiben. Material und Bewegung gehören dazu, aber dienen den Daten.' },
]

phase('Entwürfe')
const entwuerfe = await parallel(BLICKWINKEL.map((b, i) => () =>
  agent(`${KONTEXT}

DEINE ROLLE: Entwerferin ${i + 1} von 3 – Blickwinkel «${b.titel}». ${b.prompt}

Liefere EIN vollständiges R6-Konzept (nicht nur deinen Schwerpunkt): Fundament (Tokens, Utilities, JS-Bausteine mit Skizze), konkrete Massnahmen je Seite (Lernende: Übersicht, Noten, Rechner, Abschluss, Agenda; Berufsbildner: Übersicht, Lernende, Lernende-Detail; Admin: Übersicht, Bericht Noten, Lernende; gemeinsam: Seitenleiste, Symbolleiste, Palette, Menüs, Drawer, Dialoge, Toasts), Statistiken mit Filtern, Bewegungskatalog, Glas-Budget. Jede Massnahme braucht eine Apple-Quelle (HIG-Seite oder WWDC-Sitzung mit Abschnitt) aus der Faktenbasis oder deiner eigenen Lektüre und messbare Akzeptanzkriterien mit den vorhandenen Werkzeugen (tools/pruefung/leistung.mjs, kontrast.mjs, shot.mjs, rundgang.mjs, php artisan test). Respektiere das Kontrast-Budget: Glas, auf dem Text steht, nur mit Scrollkante oder über dem Grund dünner als heute. Sei konkret genug, dass ein Sonnet-Agent die Scheibe ohne Rückfrage umsetzen kann. Nenne, was du bewusst verwirfst und warum. Umfang: höchstens 25 Massnahmen, 12 Statistiken, 12 Bewegungen, 8 Fundament-Bausteine – lieber wenige, die tragen, als viele dünne. «Award-Niveau» heisst: jede Entscheidung hat einen Grund, den man an Apples Quellen oder einer Messung festmachen kann, und das Ergebnis ist ruhig, tief, lebendig – nicht laut.`,
    { label: `entwurf:${b.key}`, phase: 'Entwürfe', model: 'opus', effort: 'xhigh', schema: ENTWURF_SCHEMA }),
))
const gueltig = entwuerfe.map((e, i) => ({ ...BLICKWINKEL[i], entwurf: e })).filter((x) => x.entwurf)
log(`${gueltig.length}/3 Entwürfe liegen vor – ${gueltig.map((g) => `${g.key}: ${laenge(g.entwurf)} Zeichen, ${g.entwurf.massnahmen.length} Massnahmen, ${g.entwurf.statistiken.length} Statistiken`).join('; ')} (Cap 600k)`)

const URTEIL_SCHEMA = {
  type: 'object',
  properties: {
    bewertungen: { type: 'array', items: { type: 'object', properties: { entwurf: { type: 'string' }, apple_treue: { type: 'number' }, messbarkeit: { type: 'number' }, lesbarkeit_budget: { type: 'number' }, umsetzbarkeit: { type: 'number' }, wirkung: { type: 'number' }, nutzen_statistik: { type: 'number' }, gesamt: { type: 'number' }, staerken: { type: 'array', items: { type: 'string' } }, schwaechen: { type: 'array', items: { type: 'string' } }, regelverstoesse: { type: 'array', items: { type: 'string' } } }, required: ['entwurf', 'apple_treue', 'messbarkeit', 'lesbarkeit_budget', 'umsetzbarkeit', 'wirkung', 'nutzen_statistik', 'gesamt', 'staerken', 'schwaechen', 'regelverstoesse'] } },
    beste_ideen_ueber_alle: { type: 'array', items: { type: 'object', properties: { idee: { type: 'string' }, aus_entwurf: { type: 'string' }, warum: { type: 'string' } }, required: ['idee', 'aus_entwurf', 'warum'] } },
    sieger: { type: 'string' },
    begruendung: { type: 'string' },
  },
  required: ['bewertungen', 'beste_ideen_ueber_alle', 'sieger', 'begruendung'],
}
const JUROREN = [
  { key: 'apple', fokus: 'Du bist Jurorin für Apple-Treue und Quellenlage: Stimmt jede Behauptung mit der HIG bzw. den WWDC-Sitzungen überein? Wo wird Apple nur behauptet? Wo widerspricht eine Massnahme der HIG (Glas auf Inhalt, Bewegung als Deko, Farbe ohne Bedeutung, Systemfarben abgeschrieben)?' },
  { key: 'technik', fokus: 'Du bist Juror für Umsetzbarkeit und Leistung in dieser Codebasis (Blade, Tailwind 4 CSS-first, Alpine, Chart.js, 12 Themes, Laravel-Berechtigung, Tests): Ist jede Massnahme in Blade/CSS/Alpine ohne neue Abhängigkeiten machbar, hält sie das Kontrast- und Bildraten-Budget, funktionieren Fallbacks (kein backdrop-filter, reduced motion/transparency, Firefox ohne SVG-Backdrop-Filter), sind die Akzeptanzkriterien mit den vorhandenen Werkzeugen prüfbar?' },
  { key: 'mensch', fokus: 'Du bist Jurorin für Nutzen und Wirkung: Beantworten die Statistiken echte Fragen der drei Rollen (Lernende 16–20 Jahre, Berufsbildner mit 5–20 Lernenden, Admin eines Lehrbetriebs), sind die Filter sinnvoll, bleibt die Oberfläche ruhig und lesbar, wirkt das Ergebnis preiswürdig oder nur aufgeregt? Würdest du es einem Designjury-Mitglied von Apple zeigen?' },
]

phase('Jury')
const urteile = await parallel(JUROREN.map((j) => () =>
  agent(`${KONTEXT}

${j.fokus}

Bewerte die ${gueltig.length} Entwürfe je Kriterium von 1 (schwach) bis 10 (herausragend); «gesamt» ist dein gewichtetes Urteil, nicht der Durchschnitt. Nenne Regelverstösse (harte Regeln oben) ausdrücklich – ein Regelverstoss deckelt «gesamt» bei 5. Sammle die besten einzelnen Ideen über alle Entwürfe, auch aus Verlierern.

ENTWÜRFE:
${JSON.stringify(gueltig.map((g) => ({ blickwinkel: g.titel, entwurf: g.entwurf })), null, 1).slice(0, 600000)}`,
    { label: `jury:${j.key}`, phase: 'Jury', model: 'opus', effort: 'high', schema: URTEIL_SCHEMA }),
))
const urteileOk = urteile.filter(Boolean)
log(`${urteileOk.length}/3 Jury-Urteile – Sieger: ${urteileOk.map((u) => u.sieger).join(', ')} (${laenge(urteileOk)} Zeichen, Cap 200k)`)

const SYNTHESE_SCHEMA = {
  type: 'object',
  properties: {
    dokument_md: { type: 'string', description: 'Vollständiger Inhalt von docs/auftrag/GUI-R6.md (Markdown, Deutsch): Auftrag, Leitidee, Grundsätze mit Apple-Quellen, Glas-Budget (Zahlen), Bewegungskatalog, Statistik-Katalog mit Filtern und Endpunkten, Scheibenplan mit Reihenfolge, Dateien, Abhängigkeiten und messbaren Akzeptanzkriterien je Scheibe, Prüfplan, Verworfenes mit Grund, Offen für David' },
    scheiben: { type: 'array', items: { type: 'object', properties: { id: { type: 'string' }, titel: { type: 'string' }, ziel: { type: 'string' }, dateien: { type: 'array', items: { type: 'string' } }, abhaengig_von: { type: 'array', items: { type: 'string' } }, akzeptanz: { type: 'array', items: { type: 'string' } }, agent_modell: { type: 'string' }, parallel_mit: { type: 'array', items: { type: 'string' } } }, required: ['id', 'titel', 'ziel', 'dateien', 'abhaengig_von', 'akzeptanz', 'agent_modell', 'parallel_mit'] } },
    kernbehauptungen: { type: 'array', items: { type: 'string' }, description: 'Behauptungen über Apple-Quellen und Messwerte, die ein Widerleger prüfen soll' },
    offen_fuer_david: { type: 'array', items: { type: 'string' } },
  },
  required: ['dokument_md', 'scheiben', 'kernbehauptungen', 'offen_fuer_david'],
}

phase('Synthese')
const synthese = await agent(`${KONTEXT}

DEINE ROLLE: Chefdesignerin. Aus ${gueltig.length} Entwürfen und ${urteileOk.length} Jury-Urteilen entsteht der verbindliche Plan docs/auftrag/GUI-R6.md. Nimm den Sieger als Rückgrat und veredle ihn mit den besten Ideen der anderen; streiche alles, was ein Juror als Regelverstoss markiert hat oder das Kontrast-/Bildraten-Budget reisst. Jede Scheibe: eigene Dateien (disjunkt zu parallelen Scheiben), Reihenfolge (Fundament zuerst: theme.css/app.css/np.js; dann Seiten; dann Statistik-Service und Endpunkte; Doku und Skills als eigene Scheibe), Akzeptanz als Zahl + Werkzeug + Schwelle (Kontrast exit 0, kontrast --minimum, leistung.mjs Tippen p95/Scroll p95/lang/Anim 0 nicht schlechter als Baseline, Screenshots 1920/2560 dunkel + reduced motion/transparency, rundgang Exit 0, php artisan test, ZugriffsschutzTest für neue Routen). Schreibe das Dokument so, dass ein Sonnet-Agent eine Scheibe ohne Rückfrage umsetzen kann (Klassennamen, Tokens, Alpine-Komponenten, Routennamen, Methodennamen). Lesbarkeit hängt nie von einer Erweiterung ab. Entwicklerjargon aus Oberflächentexten raushalten. UMFANG: dokument_md höchstens 350 Zeilen – Grundsätze, Budgets, Kataloge und der Scheibenplan als Tabellen; die Details je Scheibe (Dateien, Akzeptanz, Abhängigkeiten) stehen vollständig im Feld «scheiben». AUSNAHME VOM LESEVERBOT: Schreibe dasselbe Dokument zusätzlich mit dem Write-Werkzeug nach /tmp/claude-0/-home-user-notenportal/7db961ca-89ac-5783-9806-aac8c1a98bdd/scratchpad/r6/GUI-R6.entwurf.md (Scratchpad, nicht Repo), BEVOR du die strukturierte Ausgabe lieferst – so geht nichts verloren, falls die Ausgabe abbricht.

ENTWÜRFE:
${JSON.stringify(gueltig.map((g) => ({ blickwinkel: g.titel, entwurf: g.entwurf })), null, 1).slice(0, 600000)}

JURY:
${JSON.stringify(urteileOk, null, 1).slice(0, 200000)}`,
  { label: 'synthese', phase: 'Synthese', model: 'opus', effort: 'xhigh', schema: SYNTHESE_SCHEMA })

if (!synthese) return { fehler: 'Synthese fehlgeschlagen', entwuerfe: gueltig, urteile: urteileOk }

const REFUTATION_SCHEMA = {
  type: 'object',
  properties: {
    widerlegt: { type: 'array', items: { type: 'object', properties: { behauptung: { type: 'string' }, beleg: { type: 'string' }, folge_fuer_plan: { type: 'string' } }, required: ['behauptung', 'beleg', 'folge_fuer_plan'] } },
    bestaetigt: { type: 'array', items: { type: 'object', properties: { behauptung: { type: 'string' }, beleg: { type: 'string' } }, required: ['behauptung', 'beleg'] } },
    ungeprueft: { type: 'array', items: { type: 'string' } },
    luecken_im_plan: { type: 'array', items: { type: 'string' } },
  },
  required: ['widerlegt', 'bestaetigt', 'ungeprueft', 'luecken_im_plan'],
}

phase('Gegenprüfung')
const refutationen = await parallel([
  { key: 'quellen', fokus: 'Prüfe jede Apple-Quellenangabe des Plans gegen die Originalseite (HIG-JSON unter https://developer.apple.com/tutorials/data/design/human-interface-guidelines/<seite>.json, Technology Overviews, WWDC-Transkripte). Was steht dort wirklich? Zitiere den Satz. Standard bei Unsicherheit: widerlegt.' },
  { key: 'codebasis', fokus: 'Prüfe jede Behauptung des Plans über die Codebasis und die Messwerte: existieren die genannten Dateien, Klassen, Methoden, Routen, Tokens? Stimmen die Zahlen mit docs/auftrag/messungen/r6-leistung-baseline.md überein? Sind die Scheiben wirklich dateidisjunkt? Reisst eine vorgeschlagene Deckung das Kontrast-Budget (rechne mit node tools/pruefung/kontrast.mjs --glas=<a> nach)? Standard bei Unsicherheit: widerlegt.' },
].map((r) => () =>
  agent(`${KONTEXT}

DEINE ROLLE: Widerleger «${r.key}». ${r.fokus}

PLAN (GUI-R6.md):
${synthese.dokument_md.slice(0, 200000)}

KERNBEHAUPTUNGEN:
${JSON.stringify(synthese.kernbehauptungen, null, 1)}`,
    { label: `widerlegen:${r.key}`, phase: 'Gegenprüfung', model: 'opus', effort: 'high', schema: REFUTATION_SCHEMA }),
))

return { entwuerfe: gueltig, urteile: urteileOk, synthese, refutationen: refutationen.filter(Boolean) }
