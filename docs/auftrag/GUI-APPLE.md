# Auftrag: Oberfläche nach Apple-Art

Ergänzt `docs/gui-konzept.md`. Ziel ist nicht, wie macOS auszusehen, sondern nach denselben
Grundsätzen entschieden zu sein. Stand 01.10.2026.

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
- UI-Texte Schweizer Hochdeutsch, **ss statt ß**, keine Erklärtexte und keine Entwicklernotizen in
  der Oberfläche.

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

## Grundsätze G1–G21 (R6, Stand 03.10.2026)

Ergänzt «Harte Grenzen» und «Was zu tun ist». Plan und Umsetzung: `docs/auftrag/GUI-R6.md` §3, Material und Bewegung in `docs/gui-konzept.md`. Jedes Apple-Zitat in «Apple» steht wörtlich mit derselben Quelle in `docs/auftrag/messungen/r6-verstehen/inventare.json` (Bereich `apple`, `fakten` bzw. `risiken`); Auslassungen sind mit … gekennzeichnet. Wo der Plan sich auf eine Stelle stützt, die dort nicht wörtlich steht (im Plan mit † markiert: Transkript WWDC25/219 und die HIG-Seiten scroll-views, accessibility), steht hier eine sinngemässe Wiedergabe ohne Anführungszeichen. **Repo-Regel** heisst: eigener Entscheid des Portals, nicht Apple. Quellenkürzel: HIG = `developer.apple.com/design/human-interface-guidelines/<seite>`, WWDC25/219 und WWDC25/356 = `developer.apple.com/videos/play/wwdc2025/<nr>/`, Adopting = `developer.apple.com/documentation/technologyoverviews/adopting-liquid-glass`.

| # | Grundsatz | Im Portal | Apple (wörtlich) und Quelle |
|---|---|---|---|
| G1 | Glas nur in der funktionalen Ebene | Karten, Tabellen, Formulare, Diagrammflächen, Kacheln bleiben `bg-card` | «Don’t use Liquid Glass in the content layer.» – HIG materials#Liquid-Glass. Sinngemäss (†, WWDC25/219): Liquid Glass ist der Navigationsebene vorbehalten, die über dem Inhalt schwebt. |
| G2 | Inhalt hebt sich nur während der Bedienung in Glas | `np-glas-moment` auf Schieber- und Schalterknopf, nur `:active` | «Elements can even lift up into Liquid Glass temporarily, such as when you interact with a component. This lets the resting state stay visually quiet» – WWDC25/219 |
| G3 | Rückmeldung durch Licht, Press-State Pflicht | `np-glanz` mit `--np-licht`; Knöpfe skalieren nie (**Repo-Regel**; Apple verformt das Material zusätzlich, das Portal übernimmt nur das Licht) | «When you interact with Liquid Glass, the material illuminates from within as a form of feedback.» – WWDC25/219 · «Always include a press state for a custom button.» – HIG buttons#Best-practices |
| G4 | Sparsam | Glas-Budget (`docs/gui-konzept.md`, «Glas») | «Use Liquid Glass effects sparingly … Limit these effects to the most important functional elements» – HIG materials#Liquid-Glass · «Limit the use of Liquid Glass effects onscreen at the same time.» – SwiftUI applying-liquid-glass-to-custom-views (Leistungsregel, stützt nur das Budget) |
| G5 | Kein Glas auf Glas | Ist die Palette offen, verlieren Seitenleiste und Kapseln den Weichzeichner; Inhalte auf Glas nur Füllungen | «always avoid glass on glass … use fills, transparency, and vibrancy for the top elements to make them feel like a thin overlay» – WWDC25/219 |
| G6 | Grosse Flächen deckender | Schublade card 0.92; angedockt 0.68 über ruhigem Grund (dort Gestaltungs-, keine Lesbarkeitsfrage) | «Liquid Glass appears more opaque in larger elements like sidebars to preserve legibility over complex backgrounds» – HIG color#Liquid-Glass-color |
| G7 | Scrollkante nur, wo Inhalt unter schwebenden Elementen läuft; eine je Ansicht; der Schatten wächst | `np-scroll-edge` je Scrollbereich, Schatten über `--np-kante` | «Apply one scroll edge effect per view.» – HIG scroll-views#Scroll-edge-effects · «As text scrolls underneath, shadows become more prominent to create additional separation.» – WWDC25/219 · «shouldn’t be used where there aren’t any floating UI elements» – WWDC25/356 (so im Bestand zitiert, `risiken`) · «Instead of applying a solid or semi-opaque background color beneath controls, use a scroll edge effect to visually elevate controls above content.» – HIG layout#Visual-hierarchy |
| G8 | Harte Kante für angeheftete Tabellenköpfe nur in eigenen Scrollcontainern | `np-kante-hart`; im Seitenscroll deckende Fläche `bg-card` ohne Kanteneffekt, weil dort die Symbolleiste die eine Kante ist (**Repo-Entscheid**: hart, wegen macOS-Massstab und Desktop-Tabellen) | «Hard is mostly used on macOS. It creates a stronger, more opaque boundary—ideal for interactive text, controls without backgrounds, or pinned table headers» – WWDC25/356 |
| G9 | Menüs wachsen aus dem Knopf | `transform-origin` Knopfmitte, Start in Knopfgrösse (`--np-von-sx/-sy`) | «buttons fluidly morph into menus and popovers.» – Adopting · «the bubble simply pops open to reveal the content contained within. This lightweight, in-line transition keeps everything right where you just tapped.» – WWDC25/219 |
| G10 | Abdunkelung bei Unterbrechung, immer nur ein Overlay | Scrim bei Palette, Dialog und Bearbeitungs-Schublade; nie zwei Overlays. Der Scrim der Navigations-Schublade ist **Repo-Entscheid** (Fokusfalle, Klick ausserhalb schliesst) | «When a task interrupts the main flow, pair Liquid Glass with a dimming layer to help center attention» – WWDC25/356 · «Show one popover at a time. … Never show a cascade or hierarchy of popovers» – HIG popovers#Best-practices · «Display only one sheet at a time from the main interface.» – HIG sheets#Best-practices |
| G11 | Fenster ohne Fokus tritt zurück | `html[data-fenster='inaktiv']` dämpft Glanz und Schatten (`--np-aktiv`) | «when a window loses focus on the Mac or iPad, Liquid Glass shifts its appearance and visually recedes to guide attention.» – WWDC25/219 |
| G12 | Höchstens drei Gruppen, eine Hauptaktion rechts, Farbe auf die Fläche | `data-hauptaktion` genau einmal je Leiste, Symbole nie eingefärbt | «Minimize the number of groups. … In general, aim for a maximum of three.» – HIG toolbars#Item-groupings · «Only specify one primary action, and put it on the trailing side of the toolbar.» – HIG toolbars#Actions · «To emphasize primary actions, apply color to the background rather than to symbols or text.» – HIG color#Liquid-Glass-color |
| G13 | Konzentrische Radien | Innenradius = Aussenradius − Abstand (`np-glas-gruppe`) | «concentric shapes calculate their radius by subtracting padding from the parent’s.» – WWDC25/356 |
| G14 | Bewegung mit Zweck, kurz, abbrechbar, nicht bei Häufigem | Bewegungskatalog B1–B17 in `docs/gui-konzept.md`; keine Bewegung beim Tippen, Sortieren, Zeilenwechsel | «Don’t add motion for the sake of adding motion.» · «In apps, generally avoid adding motion to UI interactions that occur frequently.» · «Let people cancel motion. As much as possible, don’t make people wait for an animation to complete before they can do anything» – HIG motion#Best-practices bzw. #Providing-feedback |
| G15 | Reduzierte Bewegung = Überblendung, nie Unschärfe animieren | Variante `ruhig:`, Weichzeichner fix | «Replacing transitions in x-, y-, and z-axes with fades to avoid motion» · «Avoiding animating into and out of blurs» – HIG accessibility#Cognitive |
| G16 | Alle Einstellungen prüfen | Flags `--bewegung`, `--transparenz`, `--kontrast` der Prüfwerkzeuge | «Ensure you test your app’s custom elements, colors, and animations with different configurations of these settings.» – Adopting |
| G17 | Diagramme einfach, Details auf Wunsch, Zusammenfassung mit Zahlen, Filter als Detailstufen | Tooltip, Tastatur, Satz mit echten Werten, `<details>` mit Tabelle. Tabelle immer ist **Repo-Entscheid**; Apples Satz zur Tabelle meint eine Tabelle statt eines Diagramms | «Let people interact with the data when it makes sense, but don’t require interaction to reveal critical information.» · «consider expanding the hit target to include the entire plot area, letting people scrub across the area to reveal various values.» – HIG charts#Best-practices · «you might let people choose to view different levels of detail or subsets of data to match their interest.» – HIG charting-data#Best-practices · «identify the chart’s type … explain what each axis represents, and describe details like the upper and lower axis bounds.» – HIG charts#Enhancing-the-accessibility-of-a-chart · «If you simply need to provide data … consider offering the data in other ways, such as in a list or table» – HIG charting-data |
| G18 | Achsen | Notenachse fest 1–6, Notenbalken ab 1, Anzahlen ab 0; Veränderung als Hantel | «Consider using a fixed range when specific minimum and maximum values are meaningful for all possible data values.» · «bar charts can work well when you use zero for the lower bound of the Y axis» – HIG charts#Axes |
| G19 | Nie nur Farbe, echte Werte | Stufe als Text, Zusammenfassung mit Zahlen | «Avoid relying solely on color to differentiate between different pieces of data or communicate essential information in a chart.» – HIG charts#Color · «Avoid using subjective terms. … To help people form their own interpretations, use actual values in your descriptions.» – HIG charts#Enhancing-the-accessibility-of-a-chart |
| G20 | Menüs: Symbole bei allen oder keinem | gilt für jedes `x-dropdown` | «Apply a uniform visual treatment across menu items in the same group. … provide icons for all menu items in a group, or none of them.» – HIG menus#Icons |
| G21 | Ruhiger Grund hinter der Seitenleiste, kein Inhalt darunter | `np-grund` (statischer Token-Verlauf) statt Inhalt darunter | «In steady states, such as when an app first launches, avoid intersections between content and Liquid Glass.» – WWDC25/219 · «To reinforce the separation, you can extend content beneath the sidebar either by letting it horizontally scroll or by applying a background extension effect.» – HIG sidebars#Best-practices. Nur angelehnt: Apples Hintergrunderweiterung spiegelt angrenzenden Inhalt, `np-grund` ist ein Verlauf, kein Spiegel. Sinngemäss (†, WWDC25/219): Licht farbigen Inhalts in der Nähe kann auf die Oberfläche des Glases fallen; das ist das Bild für die Tönung der Leiste aus dem Grund. |

### Einstellungen und Material (Begründung der Abweichungen)

- **Reduzierte Transparenz und Kontrast-Theme** setzen jede Deckung auf 1 und schalten `backdrop-filter` ab. Das ist strenger als Apple: «Reduced Transparency, makes Liquid Glass frostier … Increased contrast, makes elements predominantly black or white and highlights them with a contrasting border» (WWDC25/219). Ein Schalter, ein messbarer Zustand, derselbe für Systemeinstellung und persönlichen Schalter (**Repo-Entscheid**). Mehr Kontrast ergänzt eine 1-px-Kante in `--border-strong`.
- **Reduzierte Bewegung:** «Reduced Motion decreases the intensity of some effects and disables any elastic properties for the material.» (WWDC25/219). Im Portal wird die Feder zur gewöhnlichen Auslaufkurve, Wege entfallen, Skalierung entfällt (Variante `ruhig:`).
- **Farbe:** «Avoid using hard-coded color values or colors that don’t adapt.» (HIG dark-mode#Dark-Mode-colors); Kontrast «no lower than 4.5:1» und «strive for a contrast ratio of 7:1» (HIG dark-mode#Dark-Mode-colors); Materialien und Deckungen rechnet `node tools/pruefung/kontrast.mjs`.
- **Nicht übernommen:** SF Pro, SF Symbols, Apple-Systemfarbwerte (siehe «Harte Grenzen»), die Clear-Variante des Glases. «Avoid offering an app-specific appearance setting.» (HIG dark-mode#Best-practices) steht gegen die zwölf Themes des Portals; das ist eine bestehende Entscheidung ausserhalb von R6 und wird hier nicht als Stütze zitiert.
