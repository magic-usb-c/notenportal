# Auftrag: Oberfläche nach Apple-Art

Ergänzt `docs/gui-konzept.md`. Ziel ist nicht, wie macOS auszusehen, sondern nach denselben
Grundsätzen entschieden zu sein. Stand 01.10.2026; Grundsätze G1 bis G21 (R6) nachgeführt am 30.10.2026.

## Massstab

**Desktop-Browser, 1920×1080 bis 2560×1440, Dunkelmodus.** Alle Personen, die das Portal benutzen,
arbeiten so. Der Dunkelmodus wird nach den Human Interface Guidelines perfektioniert; der helle Modus
und schmale Fenster müssen mit denselben Tokens funktionieren, bekommen aber keine eigene
Gestaltungsarbeit und keine Abnahme. Keine Mobile- und keine Touch-Kriterien mehr.

## Quelle

`https://developer.apple.com/design/human-interface-guidelines/` – **selbst lesen, nicht aus dem
Gedächtnis arbeiten.** Die HTML-Seiten liefern nur eine JavaScript-Hülle; der Inhalt derselben Seite
liegt unter `https://developer.apple.com/tutorials/data/design/human-interface-guidelines/<seite>.json`.
Die für den Dunkelmodus massgebenden Regeln stehen mit Quelle im Skill `notenportal-dunkelmodus`,
die Tokens und Muster im Skill `notenportal-ui`. Besonders: Dark Mode, Color, Materials, Typography,
Layout, Accessibility, Sidebars, Toolbars, Lists and tables, Entering data, Feedback, Modality.

Übertragen auf eine Web-Anwendung heisst das: verstehen, *warum* dort etwas so entschieden wurde,
und dieselbe Frage für Blade und Tailwind beantworten. Nicht Screenshots nachbauen.

## Harte Grenzen

- **SF Pro und SF Symbols dürfen nicht eingebettet werden.** Apples Font-Lizenz erlaubt die
  Schriften nur für Mockups von Software auf Apple-Plattformen (`https://developer.apple.com/fonts/`).
  Inter Variable ist gesetzt; die System-Schriftfamilie darf als erster Eintrag im Font-Stack
  stehen, damit Apple-Geräte ihre eigene benutzen.
- **Apple-Systemfarben nicht hart kodieren.** Apple sagt selbst, die Werte schwanken je Version.
  Alles läuft über die bestehenden Tokens in `resources/css/theme.css`.
- Der Seitencontainer heisst `np-seite`, nie `max-w-7xl`.
- Kein Farb-Hardcode irgendwo. Der Hook `.claude/hooks/view-pruefung.sh` meckert sonst zu Recht.
- UI-Texte Schweizer Hochdeutsch, **ss statt Eszett**, keine Erklärtexte und keine Entwicklernotizen in
  der Oberfläche.

## Grundsätze G1 bis G21 (R6, Licht statt Fläche)

Verbindlich für Material, Bewegung und Diagramme seit dem Umbau R6 (Plan: `docs/auftrag/GUI-R6.md`). Jede Zeile nennt, was im Portal daraus folgt, und die Quelle bei Apple. Die Gestaltung bleibt eigenständig: Apple liefert die Frage, die Antwort ist Blade, Tailwind und Alpine.

**Quellenregel.** Was in « » steht, ist ein wörtliches Zitat und steht auch wörtlich in `docs/auftrag/messungen/r6-verstehen/inventare.json`. Aussagen, die am 03.10.2026 direkt am Transkript von WWDC25/219 oder an den HIG-JSON-Seiten geprüft wurden, aber nicht in `inventare.json` stehen, sind hier ohne Anführungszeichen sinngemäss wiedergegeben und mit † markiert. Kürzel: HIG = Human Interface Guidelines; WWDC25/219 und WWDC25/356 = die WWDC25-Sitzungen mit diesen Nummern (Fundstellen in `inventare.json`).

| # | Grundsatz | Umsetzung im Portal | Apple |
|---|---|---|---|
| G1 | Glas nur in der funktionalen Ebene | Karten, Tabellen, Formulare, Diagrammflächen, Kacheln bleiben `bg-card` | «Don’t use Liquid Glass in the content layer.» (HIG materials#Liquid-Glass); Glas ist für die Navigationsebene über dem Inhalt gedacht (WWDC25/219 †) |
| G2 | Inhalt hebt sich nur während der Bedienung in Glas | `np-glas-moment` auf Schieber- und Schalterknopf, nur während des Drückens | «Elements can even lift up into Liquid Glass temporarily … This lets the resting state stay visually quiet» (WWDC25/219) |
| G3 | Rückmeldung durch Licht | `np-glanz` plus `--np-licht`; Press-State Pflicht. **Repo-Regel, nicht Apple:** Knöpfe skalieren nie; Apple verformt das Material bei Berührung (WWDC25/219 †), das Portal übernimmt nur das Licht | «the material illuminates from within as a form of feedback» (WWDC25/219); «Always include a press state for a custom button.» (HIG buttons#Best-practices) |
| G4 | Sparsam | Glas-Budget in `docs/gui-konzept.md` (Ruhe höchstens 2 % des Fensters). Die HIG verlangt sparsamen Einsatz auf den wichtigsten funktionalen Elementen (HIG materials#Liquid-Glass †); das SwiftUI-Zitat ist eine Leistungsregel und stützt nur das Budget | «Limit the use of Liquid Glass effects onscreen at the same time» (SwiftUI, applying-liquid-glass-to-custom-views) |
| G5 | Kein Glas auf Glas | Kapsel- und Seitenleistenglas ohne Weichzeichner, solange die Palette offen ist; Inhalte auf Glas nur Füllungen | «always avoid glass on glass» (WWDC25/219) |
| G6 | Grosse Flächen deckender | Schublade `card/0.92`; angedockte Seitenleiste `card/0.68` dunkel gegenüber den Kapseln `card/0.58`. Angedockt liegt nur `np-grund` unter der Leiste: dort ist die Deckung eine Gestaltungsfrage | «Liquid Glass appears more opaque in larger elements like sidebars to preserve legibility over complex backgrounds» (HIG color#Liquid-Glass-color) |
| G7 | Scrollkante statt Leistenfläche, eine je Scrollbereich, Schatten wächst | `np-symbolleiste` mit `--np-kante` im Seitenscroll, `np-scroll-edge` im eigenen Scrollbereich | «Apply one scroll edge effect per view.» (HIG scroll-views#Scroll-edge-effects); «As text scrolls underneath, shadows become more prominent» (WWDC25/219). Nur einsetzen, wo Inhalt hinter schwebenden Elementen läuft (HIG scroll-views †) |
| G8 | Harte Kante für angeheftete Tabellenköpfe nur in eigenen Scrollcontainern | `np-kante-hart` auf `thead` in eigenem Scrollbereich; im Seitenscroll deckende Fläche `bg-card` ohne Kanteneffekt. Entscheid: hart, weil macOS-Massstab und Desktop-Tabellen | «Hard … ideal for interactive text, controls without backgrounds, or pinned table headers» (WWDC25/356) |
| G9 | Menüs wachsen aus dem Knopf | `transform-origin` Knopfmitte, Start in Knopfgrösse (`--np-von-sx/-sy`), `--dauer-morph` | «buttons fluidly morph into menus and popovers.» (adopting-liquid-glass); «the bubble simply pops open … right where you just tapped» (WWDC25/219). Beim Wachsen wird das Material dicker und wirft tiefere Schatten (WWDC25/219 †) |
| G10 | Abdunkelung bei Unterbrechung, immer nur eines | Scrim bei Palette, Dialog und Bearbeitungs-Drawer (`glass-scrim`); nie zwei Überlagerungen. Der Scrim der Navigationsschublade ist Repo-Entscheid (Fokusfalle, Klick ausserhalb schliesst), nicht Apple | «Show one popover at a time.» (HIG popovers); «Display only one sheet at a time» (HIG sheets). Unterbricht eine Aufgabe den Ablauf, dunkelt ein Scrim ab; läuft sie parallel, genügt die Trennung durch das Glas (WWDC25/356 †) |
| G11 | Fenster ohne Fokus tritt zurück | `html[data-fenster='inaktiv']` setzt `--np-aktiv` auf 0.4 und dämpft Glanz und Schatten | «Liquid Glass shifts its appearance and visually recedes to guide attention.» (WWDC25/219) |
| G12 | Höchstens drei Gruppen, eine Hauptaktion rechts, Farbe auf die Fläche | `data-hauptaktion` genau einmal je Leiste, Symbole nie eingefärbt | «aim for a maximum of three» (HIG toolbars#Item-groupings); «Only specify one primary action, and put it on the trailing side» (HIG toolbars#Actions); «apply color to the background rather than to symbols or text» (HIG color#Liquid-Glass-color) |
| G13 | Konzentrische Radien | Innenradius = Aussenradius minus Abstand (`np-glas-gruppe`) | «concentric shapes calculate their radius by subtracting padding from the parent’s» (WWDC25/356) |
| G14 | Bewegung mit Zweck, kurz, abbrechbar, nicht bei Häufigem | Bewegungskatalog in `docs/gui-konzept.md`; keine Bewegung beim Tippen, Sortieren, Zeilenwechsel | «Don’t add motion for the sake of adding motion.» · «Let people cancel motion.» · «generally avoid adding motion to UI interactions that occur frequently» (HIG motion) |
| G15 | Reduzierte Bewegung = Überblendung, nie Unschärfe animieren | Variante `ruhig:` (ersetzt `motion-reduce:`), Weichzeichner fest | «Replacing transitions in x-, y-, and z-axes with fades» · «Avoiding animating into and out of blurs» (HIG accessibility#Cognitive) |
| G16 | Alle Einstellungen prüfen | Prüfflags `--bewegung`, `--transparenz`, `--kontrast` (Skill `notenportal-pruefwerkzeuge`) | «Ensure you test your app’s custom elements, colors, and animations with different configurations of these settings.» (adopting-liquid-glass) |
| G17 | Diagramme einfach, Details auf Wunsch, Zusammenfassung mit Zahlen, Filter als Detailstufen | Tooltip, Tastatur, Satz mit echten Werten, `<details>` mit Tabelle. Wer keine Audio Graphs anbietet, braucht eine Übersicht über Aufbau und Zweck des Diagramms (HIG charts#Enhancing-the-accessibility-of-a-chart †). **Repo-Entscheid, nicht Apple:** die Tabelle gibt es immer (Vorentscheidung 8 in `GUI-R6.md`); Apples Hinweis auf Liste oder Tabelle meint eine Tabelle statt eines Diagramms | «don’t require interaction to reveal critical information» · «expanding the hit target to include the entire plot area» (HIG charts); «you might let people choose to view different levels of detail or subsets of data to match their interest» (HIG charting-data); «consider offering the data … in a list or table» (HIG charts) |
| G18 | Achsen | Notenachse fest 1 bis 6, Notenbalken ab 1, Anzahlen ab 0; Veränderung als Hantel | «Consider using a fixed range when specific minimum and maximum values are meaningful» · «bar charts can work well when you use zero for the lower bound» (HIG charts#Axes) |
| G19 | Nie nur Farbe, echte Werte | Stufe als Text, Zusammenfassung mit Zahlen | «Avoid relying solely on color» (HIG charts#Color); «use actual values in your descriptions» (HIG charts#Enhancing-the-accessibility-of-a-chart) |
| G20 | Menüs: Symbole bei allen oder keinem | gilt für jedes `x-dropdown` | «provide icons for all menu items in a group, or none of them» (HIG menus#Icons) |
| G21 | Ruhiger Grund hinter der Seitenleiste, kein Inhalt darunter | `np-grund` statt Inhalt unter der Leiste. In ruhigen Zuständen soll sich Inhalt nicht mit Glas überschneiden; man ordnet ihn um oder skaliert ihn (WWDC25/219 †). **Nur angelehnt:** Apples «background extension effect» (HIG sidebars#Best-practices) spiegelt angrenzenden Inhalt und zeichnet ihn weich; `np-grund` ist ein statischer Token-Verlauf. Farbiges Licht aus der Nähe darf leicht auf die Oberfläche fallen (WWDC25/219 †): Bild für die Tönung der Leiste aus dem Grund | «background extension effect» (HIG sidebars#Best-practices) |

**Strenger als Apple (bewusste Repo-Entscheide).** Reduzierte Transparenz macht bei Apple das Glas frostiger und verdeckt mehr vom Inhalt dahinter (WWDC25/219 †); das Portal setzt alle Deckungen auf 1, schaltet jeden `backdrop-filter` ab und macht `np-grund` flach: ein Schalter, ein messbarer Zustand, derselbe für Systemeinstellung und persönlichen Schalter. Mehr Kontrast ergänzt eine 1-px-Kante («highlights them with a contrasting border», WWDC25/219).

**Nicht übernommen:** SF Pro, SF Symbols, Apple-Systemfarbwerte (Abschnitt «Harte Grenzen»), die Clear-Variante des Glases (gilt nur über bildreichem Inhalt, das Portal hat keinen). Die HIG-Regel «Avoid offering an app-specific appearance setting» steht gegen die zwölf Themes des Portals: bestehende Entscheidung ausserhalb von R6, hier nicht als Stütze zitiert.

## Was zu tun ist

Vor jeder Arbeit an Views oder CSS: Skills `notenportal-ui` und `notenportal-dunkelmodus`.

Geh die drei Rollenbereiche selbst durch – Lernende, Berufsbildner, Admin – und arbeite die Stellen
ab, an denen die Oberfläche im Dunkeln unruhig, unklar oder bei 1920 und 2560 px schlecht
ausgenutzt ist.

Worauf es dabei besonders ankommt:

- **Tiefe über Helligkeit, nicht über Schatten.** Grund, Karte, Tabellenkopf und Overlay sind vier
  erkennbar verschiedene Helligkeiten; Schatten tragen im Dunkeln fast nichts.
- **Glas nur für die funktionale Ebene** (Navigation, Toolbar, Menüs, Toasts). Karten, Tabellen,
  Formulare und Diagramme sind matt.
- **Hierarchie statt Rahmen.** Nicht alles ist eine Karte. Abstand, Grösse und Gewicht tragen die
  Gliederung; Rahmen werden gezielt für das ausgegeben, was wirklich hervorstehen soll.
- **Dichte mit Luft.** Tabellen und Listen dürfen dicht sein, aber Zeilenhöhe und Ausrichtung der
  Zahlen müssen stimmen – Ziffernspalten mit `tabular-nums`, rechtsbündig.
- **Ein Akzent.** Farbe bedeutet etwas oder sie ist weg. Genau eine Primäraktion je Ansicht;
  semantische Farben für gut, knapp, ungenügend bleiben getrennt vom Akzent des Themes und tragen
  ihre Bedeutung immer zusätzlich über Form oder Text.
- **Die Oberfläche erklärt sich durch Anordnung**, nicht durch Hinweistexte.
- **Tastatur und Fokus:** sichtbarer Fokusring überall (auch im Dunkeln ≥ 3:1), Escape schliesst,
  `prefers-reduced-motion` respektiert.
- **Zustände:** leer, lädt, Fehler, viel zu viele Daten. Alle vier müssen gut aussehen, nicht nur
  der Normalfall.
- **Nichts Wichtiges am unteren Fensterrand** (macOS-Regel: Fenster werden unter die Kante
  geschoben) – kein fixierter Fuss mit Primäraktion.

## Entschieden

Die Hauptnavigation ist eine **Seitenleiste** nach HIG und je Person umschaltbar auf die Tableiste
oben (Einstellungen → Darstellung). Standard für neue Konten ist die Seitenleiste
(`app/Support/Darstellung.php`, `NAVIGATION_SEITE`). Die Topbar bleibt als Variante erhalten.

## Beweis

Für jeden Bereich, der angefasst wurde:

1. `node tools/pruefung/rundgang.mjs <email>` je betroffener Rolle mit Exit 0 (keine JS-Fehler,
   kein Überlauf, kein abgeschnittener Text, keine Querscrollleiste), bei 1920 px.
2. Screenshots der betroffenen Seiten **dunkel bei 1920 und 2560 px** mit
   `node tools/pruefung/shot.mjs <email> "/pfad" --breite=1920|2560`, jedes Bild mit `Read`
   angesehen und gegen die Prüfliste in `notenportal-dunkelmodus` §5 beurteilt (Agent `bildpruefer`).
3. Hell einmal stichprobenartig (`--hell`): darf nicht brechen.
4. Vor einem Blockabschluss der Workflow `notenportal-dunkel-rundgang` über alle drei Rollen.

Werkzeuge liegen im Repo unter `tools/pruefung/` (Skill `notenportal-pruefwerkzeuge`); der
Demo-Server kommt aus `tools/pruefung/demo-server.sh`, das Passwort nur aus `NP_TEST_PW`. Ohne
laufenden Server wird nichts als geprüft gemeldet – dann die Prüfung als offenen Punkt ins
Übergabebrett schreiben.
