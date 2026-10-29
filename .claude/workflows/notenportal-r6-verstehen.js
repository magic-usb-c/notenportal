export const meta = {
  name: 'notenportal-r6-verstehen',
  description: 'R6 GUI-Rebuild: Bestand (Material, Bewegung, Diagramme, Auswertungs-API, Seiten) und Apple-Quellen (Liquid Glass, Motion, Charts) sowie Browser-Fähigkeiten parallel erfassen, dann verdichten und gegnerisch prüfen',
  whenToUse: 'Vor einem grossen Oberflächen-Umbau: liefert Lagebild, Widersprüche zu den geltenden Regeln mit Apple-Quelle, Scheibenvorschlag mit Abnahmekriterien. Nur lesend.',
  phases: [
    { title: 'Erfassen', detail: 'sechs Leseagenten parallel: Material/Bewegung, JS/Diagramme, Auswertungsdaten, Seiten, Apple-Quellen, Browser-Fähigkeiten' },
    { title: 'Verdichten', detail: 'ein Synthese-Agent (opus, xhigh), danach zwei Widerleger (opus, high)' },
  ],
}

const KONTEXT = `
Projekt: Notenportal, Laravel 13, MariaDB, Blade + Tailwind 4 (CSS-first: Tokens in resources/css/theme.css, Utilities in resources/css/app.css) + Alpine.js + Chart.js, Vite.
Arbeitsverzeichnis ist die Repo-Wurzel /home/user/notenportal (Cloud-Sitzung). Nichts ist produktiv.
Rollen und Pfade: Lernende ohne Präfix (/dashboard, /grades, /grades/calculator, /qualification, /modules, /documents, /settings), Berufsbildner unter /trainer, Admin unter /admin.
Massstab der Oberfläche: Desktop-Browser 1920×1080 bis 2560×1440 im Dunkelmodus nach Apple Human Interface Guidelines; hell und schmal dürfen nicht brechen, bekommen aber keine eigene Gestaltungsarbeit.
Geltende Regeln (lesen, nicht raten): CLAUDE.md (Repo-Wurzel), docs/auftrag/GUI-APPLE.md, docs/gui-konzept.md (Abschnitte «Stand Rebuild», c Token-System, f Diagramme), .claude/skills/notenportal-ui/SKILL.md, .claude/skills/notenportal-dunkelmodus/SKILL.md.
Neuer Auftrag des Betreibers (David, 03.10.2026), für den du den Boden bereitest: «GUI-Rebuild auf Apple-Niveau: Liquid Glass (Transparenz, Tiefe, Bewegung, Animationen) nach Apples eigenen Quellen, deutlich bessere Grafiken und sinnvolle, filterbare Statistiken – Anspruch: preiswürdig.»
Du bist nur Leser: lies Dateien, führe höchstens lesende Befehle aus (cat, sed -n, grep, ls, wc, git log/diff/show, curl/WebFetch für Quellen), ändere nichts, keine Git-, Composer-, npm-Befehle, keine Tests, keine Subagents. Nie tmp-testdaten/ oder .env lesen. Passwörter nie ausgeben.
Kein Befund ohne Quelle (Datei:Zeile oder URL). Lies die Stelle wirklich. Was du nicht findest, meldest du als Lücke, nicht als Vermutung.
Antworte auf Deutsch (Schweizer Hochdeutsch, ss statt ß). Deine letzte Ausgabe ist der Rückgabewert, kein Bericht an einen Menschen.`

const INVENTAR = {
  type: 'object',
  properties: {
    zusammenfassung: { type: 'string', description: 'höchstens 12 Sätze' },
    fakten: { type: 'array', items: { type: 'object', properties: { thema: { type: 'string' }, befund: { type: 'string' }, quelle: { type: 'string' } }, required: ['thema', 'befund', 'quelle'] } },
    luecken: { type: 'array', items: { type: 'string' }, description: 'was fehlt oder nicht gefunden wurde' },
    chancen: { type: 'array', items: { type: 'string' }, description: 'konkrete Verbesserungsmöglichkeiten für R6 mit Datei' },
    risiken: { type: 'array', items: { type: 'string' } },
  },
  required: ['zusammenfassung', 'fakten', 'luecken', 'chancen', 'risiken'],
}

const LESER = [
  {
    key: 'material', model: 'sonnet', effort: 'high',
    prompt: `${KONTEXT}

Auftrag A – Material und Bewegung im Bestand. Lies vollständig: resources/css/theme.css (Token-Struktur: welche RGB-Tripel-Tokens gibt es, besonders --bg-rgb, --card-rgb, --surface-2-rgb, --accent-rgb, --border-rgb, --text-rgb, --chart-*; wie viele Themes und Modi, Reihenfolge der Blöcke), resources/css/app.css (ALLE @utility mit glass-*, np-glas*, np-symbolleiste, np-seitenleiste, np-scroll-edge, np-fade-in, np-loading, np-knopf*, np-segment, np-karte, Scrim; jede Rezeptur wörtlich: Deckung, blur, saturate, Rand, Schatten, @supports-Fallback; alle Motion-Tokens und --ease-*; alle @keyframes; die Behandlung von prefers-reduced-motion, prefers-reduced-transparency, [data-transparenz='reduziert'], [data-bewegung]; View Transitions (::view-transition*), Scrollbars), resources/js/praeferenzen.js (welche persönlichen Schalter gibt es: Darstellung, Bewegung, Transparenz, Dichte? wie werden sie gesetzt, Attribut am html-Element), resources/views/layouts/app.blade.php, resources/views/layouts/navigation.blade.php (wo welche Materialklassen eingesetzt sind, Seitenleiste vs. Topbar, Symbolleiste, Palette, Kontomenü), resources/views/components/{dropdown,drawer,modal,toast,feedback-widget}.blade.php (welche Transitions, Dauern, Transform-Origins).
Zähle mit grep, wie oft jede glass-/np-glas-Klasse in resources/views vorkommt.
Erfasse ausserdem jede Hover-/Press-Rückmeldung von Knöpfen (np-knopf-Varianten) und ob es irgendeine Form von Lichtkante (inset box-shadow, Gradient-Rand), Tönung aus dem Inhalt (color-mix), Morph-Übergang (ein Element wird zu einem anderen), Scroll-Kanten-Effekt oder Federung (linear()/spring) gibt.
Ergebnis: Fakten mit Datei:Zeile; Lücken gegenüber einem echten Liquid-Glass-Material (Lensing/Lichtbrechung am Rand, Glanzlicht, adaptive Tönung, Reaktion auf Hover/Druck, Morph zwischen Zuständen, Scroll-Kante) als «chancen» mit der Datei, wo sie hingehören.`,
  },
  {
    key: 'diagramme', model: 'sonnet', effort: 'high',
    prompt: `${KONTEXT}

Auftrag B – JavaScript und Diagramme im Bestand. Lies vollständig resources/js/charts.js, resources/js/np.js (tokenFarbe, notenFarbe, stufe, Registrierungen), resources/js/app.js, resources/js/rechner.js (wie die Treppenlinie entsteht), resources/views/components/diagramm.blade.php (falls vorhanden; sonst grep nach x-diagramm) und package.json (Versionen von chart.js, alpinejs, tailwindcss, vite; weitere Chart-Plugins?).
Finde mit grep -rn jede Stelle in resources/views, die ein Diagramm rendert (x-diagramm, canvas, x-data mit chart, data-chart, sparkline, bullet, heatmap) und liste je Stelle: Seite/Route, Diagrammtyp, woher die Daten kommen (Controller/Service, @js(...)), welche Optionen (hervorheben, Grenzlinie, Direktlabels, «Als Tabelle»), ob es Filter gibt und ob sie per Reload oder clientseitig wirken, Animationseinstellungen (Chart.js animation, bewegungReduziert()).
Prüfe, ob es irgendwo clientseitige Aktualisierung eines Diagramms ohne Seitenwechsel gibt (chart.update(), fetch nach JSON) und ob es JSON-Endpunkte für Auswertungsdaten gibt (grep in routes/*.php nach json|api|daten|chart).
Ergebnis: Diagramm-Inventar als Fakten; Lücken für interaktive, filterbare Statistik (Zustand in der URL, Aktualisierung ohne Reload, gestaffelte Einblendung, Tooltip-Gestaltung, Tastaturbedienung, Leer-/Ladezustand); Chancen mit Datei.`,
  },
  {
    key: 'daten', model: 'sonnet', effort: 'high',
    prompt: `${KONTEXT}

Auftrag C – Auswertungsdaten und Dienste. Lies die öffentliche API von app/Services/Auswertung/ (jede Klasse: Methoden, Parameter, Rückgabeform – kurz je Methode), app/Services/Uebersicht.php, app/Services/Bericht.php, app/Services/Lernstand*.php (oder wo der Lernstand berechnet wird), die Controller app/Http/Controllers/Lernender/{DashboardController,NotenController,RechnerController}.php (Namen prüfen, ggf. grep), app/Http/Controllers/Verwaltung/{LernendeController,LernendeNotenController}.php, app/Http/Controllers/Admin/BerichtController.php, docs/notenlogik.md (Regeln) und die Migrationen bzw. das Schema der Tabellen noten, semester, faecher, module, kategorien, lernende, pruefungen (database/migrations oder database/schema; nur Spaltennamen und Fremdschlüssel).
Beantworte mit Quelle: Welche Aggregate existieren bereits (Semesterschnitt, Gesamt, je Kategorie, je Fach/Modul, Zeugnisnoten, Promotion, Prognose, Trend, Verteilung, Erfassungsrhythmus)? Welche Dimensionen trägt jede Note (Datum, Semester, Kategorie, Fach/Modul, Gewicht, Prüfungsart, erfasst von, gesehen)? Wie fliessen Filter heute (Query-Parameter semester_id, kategorie_id, lehrberuf_id, berufsbildner_id, warnung, sort/dir, zeitraum)? Welche Autorisierungsmuster gelten (Policies, Gates wie noteAnlegen, Berufsbildner nur aktiv betreute Lernende, Lernende nur eigene ID aus der Session)? Welche Tests begrenzen Abfragen (tests/Feature/AbfragenAnzahlTest.php: Schwellen) und welche Tests müssen neue Routen aufnehmen (ZugriffsschutzTest, EnglischeSeitenTest, SchluesselTest)? Gibt es Caching (Cache::, remember) für Auswertungen?
Ergebnis: Datenlandkarte als Fakten; Lücken (welche Statistik-Fragen heute nicht beantwortbar sind: Verlauf über Zeit mit Fenster, Vergleich mit Kohorte anonymisiert, Heatmap Semester×Fach, Prognosekorridor, Erfassungsrhythmus je Person); Chancen: welcher Dienst erweitert werden sollte (nie SQL in Controllern/Views, Durchschnitte nur aus App\\Services\\Auswertung, Noten immer whereNull('geloescht_am')).`,
  },
  {
    key: 'seiten', model: 'sonnet', effort: 'high',
    prompt: `${KONTEXT}

Auftrag D – Seiteninventar der Zielseiten für R6. Lies die Views (und die zugehörigen Controller nur so weit nötig) für: Lernende /dashboard (resources/views/dashboards oder lernender – grep nach route-Namen learner.dashboard/dashboard), /grades (resources/views/noten/index* und Partials, inkl. Zeugnisübersicht), /grades/calculator (resources/views/rechner), Berufsbildner /trainer (Übersicht «Meine Lernenden»), /trainer/learners/{id} (Cockpit, resources/views/verwaltung/lernende/show*), Admin /admin (Startseite), /admin/reports/grades (resources/views/admin/berichte/noten.blade.php), /admin/learners (resources/views/verwaltung/lernende/index*).
Je Seite: Aufbau (Seitenkopf, Kacheln, Karten, Tabellen, Diagramme, Drawer), vorhandene Filter und wie sie wirken, Leer-/Lade-/Fehlerzustände, welche Fragen die Person dort hat (nutze docs/gui-konzept.md Abschnitte e «Seiten-Blueprints» und f «Diagramme» sowie docs/funktionsumfang.md) und was heute fehlt, um sie zu beantworten.
Erfasse ausserdem: wie Seitenwechsel heute animiert sind (View Transitions in app.css/app.js), welche Komponenten (x-kachel, x-karte, x-diagramm, x-status, x-note, x-leer, x-seitenkopf) es gibt (resources/views/components, nur Namen und Props), und ob Zahlen irgendwo animiert hochzählen oder Listen gestaffelt erscheinen.
Ergebnis: Fakten je Seite; Chancen = konkrete Statistik- und Gestaltungsideen je Seite mit Datenbedarf (Kategorie/Semester/Fach-Filter, Zeitfenster, Vergleich), priorisiert nach Nutzen für Lernende, Berufsbildner, Admin.`,
  },
  {
    key: 'apple', model: 'opus', effort: 'high',
    prompt: `${KONTEXT}

Auftrag E – Apples eigene Quellen zu Liquid Glass, Bewegung und Diagrammen, wörtlich belegt. Die HTML-Seiten von developer.apple.com liefern nur eine JavaScript-Hülle; der Inhalt derselben Seite liegt als JSON unter https://developer.apple.com/tutorials/data/design/human-interface-guidelines/<seite>.json (Textfelder in primaryContentSections[].content[] und sections[]; verschachtelte «inlineContent»/«text»-Felder zusammensetzen). Hole mit curl -sS (Proxy ist konfiguriert) oder WebFetch mindestens: materials, motion, charts, toolbars, sidebars, menus, popovers, sheets, buttons, segmented-controls, tab-bars, search-fields, dark-mode, color, layout, scroll-views, accessibility (Abschnitt Motion/Transparency). Zusätzlich die Technology Overviews: https://developer.apple.com/tutorials/data/documentation/technologyoverviews/adopting-liquid-glass.json und, falls vorhanden, https://developer.apple.com/tutorials/data/documentation/technologyoverviews/liquid-glass.json sowie https://developer.apple.com/tutorials/data/documentation/technologyoverviews/app-design-and-ui.json. Versuche ausserdem die WWDC25-Sitzungen 219 («Meet Liquid Glass») und 356 («Get to know the new design system») über https://developer.apple.com/videos/play/wwdc2025/219/ bzw. /356/ (Transkript im HTML; wenn nicht abrufbar, als Lücke melden).
Extrahiere je Quelle die Regeln, die eine Web-Anwendung übertragen kann, als kurze Zitate (höchstens 25 Wörter je Zitat) mit URL: Was ist Liquid Glass (Lensing, Adaptivität, Dynamik, regular vs. clear)? Wo gehört es hin und wo nicht (Navigations-/Bedienebene über dem Inhalt, nicht in der Inhaltsebene; Ausnahmen)? Lesbarkeit (Hintergrund, Kontrast, Vibrancy, Reduced Transparency)? Bewegung (Zweck, Dauer, Federn, Unterbrechbarkeit, Reduce Motion, welche Übergänge Apple empfiehlt: Morph von Knopf zu Menü, Scroll-Kante, Toolbar schrumpft beim Scrollen, Sheet-Präsentation)? Diagramme (HIG Charts: wann welcher Typ, Farbe, Interaktion, Beschriftung, Barrierefreiheit, Beschreibungstext)? Farbe im Dunkelmodus und Akzent auf Glas? Suchfeld/Palette, Menüs, Popover, Toolbar, Sidebar in der neuen Gestaltung?
Ergebnis: Fakten = Regel + Zitat + URL (mindestens 40, nach Thema sortiert); Lücken = Seiten, die nicht abrufbar waren; Chancen = welche Regeln das Notenportal heute verletzt oder ungenutzt lässt (mit der Datei im Repo, wenn du sie kennst: resources/css/app.css, resources/views/layouts/navigation.blade.php, resources/js/charts.js); Risiken = Regeln, die mehr Glas oder mehr Bewegung ausdrücklich begrenzen.`,
  },
  {
    key: 'browser', model: 'opus', effort: 'high',
    prompt: `${KONTEXT}

Auftrag F – Browser-Fähigkeiten und Web-Techniken für Liquid Glass, Bewegung und Diagramme, belegt mit Quellen (MDN, caniuse, Chrome/WebKit-Blogs, Chart.js-Doku). Nutze WebSearch/WebFetch oder curl. Zielbrowser: aktuelle Desktop-Browser (Chrome/Edge, Firefox, Safari) auf Windows und macOS; Stand Oktober 2026 – nenne jeweils die Version, ab der ein Feature unterstützt ist, und ob es hinter einem Flag steht.
Prüfe: backdrop-filter (blur, saturate, brightness) · backdrop-filter: url(#svg) mit feDisplacementMap/feTurbulence für Lichtbrechung (welche Browser; Leistungsfolgen) · SVG-Filter auf Elementen (filter: url()) · CSS linear() Easing (Federkurven) · @starting-style und transition-behavior: allow-discrete (Ein-/Ausblenden von display:none) · View Transitions API same-document (document.startViewTransition, view-transition-name, Morph zwischen Elementen; welche Browser) · Scroll-driven Animations (animation-timeline: scroll()/view()) · scroll-state() Container Queries (stuck/scrollable) · color-mix() und relative Farben · prefers-reduced-motion und prefers-reduced-transparency (welche Browser melden Transparenz) · light-dark() · @property für animierbare Custom Properties (Zahlen hochzählen, Verläufe animieren) · CSS mask/inset box-shadow für Lichtkanten · will-change/contain und bekannte Leistungsfallen von backdrop-filter (Backdrop Root, verschachtelte Filter, grosse Flächen, Scrollen über Glas) · Chart.js 4: animations (Delay je Datenpunkt/Dataset für gestaffelte Einblendung, Modi), Plugins (Annotation nötig?), Dekimation, Tooltip-Anpassung, Barrierefreiheit (Canvas-Fallback, aria) · Alternativen zu Canvas für kleine Diagramme (inline SVG, CSS) und wann sie besser sind.
Ergebnis: Fakten = Feature + Unterstützung (Browser/Version/Flag) + Quelle; Risiken = was in einem der drei Browser fehlt oder teuer ist; Chancen = empfohlene progressive Verbesserung (Grundstufe, die überall funktioniert und lesbar bleibt; Aufbaustufe mit Feature-Erkennung; was unter prefers-reduced-motion zu Überblendung wird).`,
  },
]

const SYNTHESE = {
  type: 'object',
  properties: {
    lagebild: { type: 'string', description: 'Markdown, 60–120 Zeilen: Bestand, Apple-Regeln, Fähigkeiten, Datenlage – mit Quellen' },
    widersprueche: { type: 'array', items: { type: 'object', properties: { regel_heute: { type: 'string' }, quelle_heute: { type: 'string' }, apple_sagt: { type: 'string' }, apple_quelle: { type: 'string' }, vorschlag: { type: 'string' } }, required: ['regel_heute', 'quelle_heute', 'apple_sagt', 'apple_quelle', 'vorschlag'] } },
    scheiben: { type: 'array', items: { type: 'object', properties: { id: { type: 'string' }, titel: { type: 'string' }, ziel: { type: 'string' }, dateien: { type: 'array', items: { type: 'string' } }, abhaengig_von: { type: 'array', items: { type: 'string' } }, risiken: { type: 'array', items: { type: 'string' } }, akzeptanz: { type: 'array', items: { type: 'string' }, description: 'messbar: Kontrastwerte, Bildzeiten, Tests, Screenshots' } }, required: ['id', 'titel', 'ziel', 'dateien', 'abhaengig_von', 'risiken', 'akzeptanz'] } },
    statistik_ideen: { type: 'array', items: { type: 'object', properties: { seite: { type: 'string' }, frage: { type: 'string' }, darstellung: { type: 'string' }, filter: { type: 'array', items: { type: 'string' } }, daten: { type: 'string' }, nutzen: { type: 'string' } }, required: ['seite', 'frage', 'darstellung', 'filter', 'daten', 'nutzen'] } },
    offen_fuer_david: { type: 'array', items: { type: 'string' } },
    kernbehauptungen: { type: 'array', items: { type: 'string' }, description: '10 bis 14 prüfbare Behauptungen, auf denen der Plan ruht' },
  },
  required: ['lagebild', 'widersprueche', 'scheiben', 'statistik_ideen', 'offen_fuer_david', 'kernbehauptungen'],
}

const REFUTATION = {
  type: 'object',
  properties: {
    widerlegt: { type: 'array', items: { type: 'object', properties: { behauptung: { type: 'string' }, beleg: { type: 'string' } }, required: ['behauptung', 'beleg'] } },
    bestaetigt: { type: 'array', items: { type: 'object', properties: { behauptung: { type: 'string' }, beleg: { type: 'string' } }, required: ['behauptung', 'beleg'] } },
    ungeprueft: { type: 'array', items: { type: 'string' } },
  },
  required: ['widerlegt', 'bestaetigt', 'ungeprueft'],
}

phase('Erfassen')
const roh = await parallel(LESER.map(l => () => agent(l.prompt, { label: `lesen:${l.key}`, phase: 'Erfassen', schema: INVENTAR, model: l.model, effort: l.effort })))
const inventare = {}
LESER.forEach((l, i) => { if (roh[i]) inventare[l.key] = roh[i]; else log(`Leser ${l.key} lieferte nichts`) })
log(`Erfasst: ${Object.keys(inventare).join(', ')}`)

phase('Verdichten')
const synthese = await agent(`${KONTEXT}

Auftrag G – Verdichten. Unten die sechs Inventare (JSON). Erstelle das Lagebild für R6 und den Scheibenvorschlag. Pflicht:
1. Lagebild (Markdown): Bestand (Material, Bewegung, Diagramme, Daten, Seiten), Apple-Regeln (nur mit URL), Browser-Fähigkeiten (nur mit Quelle), Datenlage. Keine Behauptung ohne Quelle aus den Inventaren; wo Inventare sich widersprechen, lies die Stelle selbst nach.
2. Widersprüche: jede geltende Regel (CLAUDE.md, GUI-APPLE.md, gui-konzept, Skills), die dem neuen Auftrag («mehr Transparenz, Bewegung, Animation, peak Liquid Glass auf Apple-Niveau») entgegensteht – mit Apple-Zitat, das die Regel stützt oder aufhebt, und einem Vorschlag, der Apple folgt (z. B. Glas bleibt auf der Bedienebene, bekommt aber Lensing, Glanzlicht, Tönung, Morph und Scroll-Kante; Bewegung mit Zweck, Federn, überall Überblendung bei Reduce Motion; Inhalt matt, aber mit Tiefe über Helligkeit).
3. Scheiben: 6 bis 10 umsetzbare Scheiben in Reihenfolge, zuerst Fundament (Tokens/Utilities/JS-Bausteine, ein Agent, dann Build), dann getrennte Seitenscheiben mit disjunkten Dateien (parallelisierbar), zuletzt Prüfung. Je Scheibe messbare Akzeptanz: Kontrast (Text ≥ 4.5:1, UI ≥ 3:1 auf jeder Fläche, auch auf Glas mit gegebener Deckung), Bildzeiten (p95 beim Scrollen über Glas nicht schlechter als Basislinie +20 %), Tests (ZugriffsschutzTest, EnglischeSeitenTest, AbfragenAnzahlTest, neue Feature-Tests), Screenshots dunkel 1920/2560 je Seite, Rundgang Exit 0, prefers-reduced-motion/-transparency und persönliche Schalter geprüft.
4. Statistik-Ideen: je Rolle 4 bis 6, jede mit Frage, Darstellung (Diagrammtyp nach HIG Charts/gui-konzept f), Filtern (URL-Zustand), Datenquelle (bestehender Dienst oder Erweiterung von App\\Services\\Auswertung) und Nutzen. Nur sinnvolle: keine Deko-Zahlen.
5. Offen für David: nur echte Produkt-/Rechtsfragen (z. B. Glas auch auf Inhaltskarten? Kohortenvergleich anonymisiert zulässig?).
6. Kernbehauptungen: 10–14 prüfbare Sätze, auf denen der Plan ruht (Fähigkeit, Datenlage, Regel).

Inventare:
${JSON.stringify(inventare).slice(0, 180000)}`, { label: 'synthese', phase: 'Verdichten', schema: SYNTHESE, model: 'opus', effort: 'xhigh' })

if (!synthese) { log('Synthese fehlgeschlagen'); return { inventare, synthese: null, refutationen: [] } }

const refutationen = await parallel([0, 1].map(i => () => agent(`${KONTEXT}

Auftrag H${i + 1} – Widerlegen. Unten die Kernbehauptungen und Scheiben eines Plans. Versuche jede Kernbehauptung zu widerlegen, indem du die Stelle im Repo liest (Datei:Zeile) oder die Quelle abrufst (URL). ${i === 0 ? 'Linse: Code und Daten – stimmt, was über den Bestand, die Dienste, die Tests und die Dateien gesagt wird?' : 'Linse: Quellen und Fähigkeiten – stimmen die Apple-Zitate (URL abrufen, Zitat suchen) und die Browser-Angaben (MDN/caniuse)?'} Im Zweifel «ungeprüft», nie raten. Nenne für jede widerlegte Behauptung den Beleg; für bestätigte ebenso.

Kernbehauptungen:
${JSON.stringify(synthese.kernbehauptungen)}

Scheiben:
${JSON.stringify(synthese.scheiben).slice(0, 40000)}

Widersprüche:
${JSON.stringify(synthese.widersprueche).slice(0, 30000)}`, { label: `widerlegen:${i === 0 ? 'code' : 'quellen'}`, phase: 'Verdichten', schema: REFUTATION, model: 'opus', effort: 'high' })))

return { inventare, synthese, refutationen: refutationen.filter(Boolean) }