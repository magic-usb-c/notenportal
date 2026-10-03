# GUI-R6 – Licht, Bewegung, Zahlen (verbindlicher Plan)

Stand 03.10.2026 · Chefdesign · Rückgrat: Entwurf «Material zuerst» (3 von 3 Jurystimmen), veredelt mit «Bewegung zuerst» und «Daten zuerst». Grundlagen: `docs/auftrag/messungen/r6-leistung-baseline.md` (Basislinie), `docs/auftrag/messungen/r6-verstehen/{inventare,synthese,refutationen}.json`. Jedes Apple-Zitat steht wörtlich mit Quelle in `inventare.json`; mit † gekennzeichnete Zitate stehen nicht dort, sondern wurden am 03.10.2026 von der Hauptsitzung direkt am Transkript von WWDC25/219 bzw. an den HIG-JSON-Seiten geprüft. Herkunft, Jury und Gegenprüfung des Plans: §12. Die Scheiben mit Dateien, Akzeptanz und Abhängigkeiten stehen in §11.

## 1 Auftrag

David: «mach einen geileren gui rebuild – es braucht viel geilere grafiken, programmiere geilere statistiken und so, sie müssen auch sinnvoll sein und anpassbar mit so filtern mässig. es braucht mehr transparenz, bewegung, animationen und so, so peak liquid glass mässig aber auch wirklich auf apple niveau … stell dir vor du musst mit diesem gui einen award gewinnen.»

Prüfbar übersetzt:
1. **Material:** Die funktionale Ebene (Seitenleiste, Symbolleiste, Kapseln, Menüs, Palette) bekommt Licht, Kante, Tiefe und Reaktion – bei weniger Glasfläche als heute (Ruhe 18.4 % → ≤ 8 % des Fensters bei 1920×1080).
2. **Bewegung:** Was sich öffnet, wächst aus seinem Auslöser; Rückmeldung durch Licht statt Grösse; jede Bewegung kurz, abbrechbar und unter «reduzierte Bewegung» eine Überblendung.
3. **Statistik:** 11 Darstellungen beantworten je eine Frage der Rolle – Filter in der URL, JSON aus derselben Route, Tabelle und Zusammenfassung mit echten Werten.
4. **Kein Rückschritt:** `kontrast.mjs` Exit 0, Bildzeiten nicht schlechter als die Basislinie, `php artisan test` grün, Rundgang Exit 0.

Massstab: Desktop 1920×1080 bis 2560×1440, dunkel. Hell und schmal dürfen nicht brechen, bekommen aber keine eigene Gestaltung.

## 2 Leitidee: Licht statt Fläche

- **Glas wird lebendiger, nicht grösser.** Lichtkante, Glanz unter dem Zeiger, Schatten, der beim Scrollen wächst, Glas, das zurücktritt, wenn das Fenster den Fokus verliert. Weichgezeichnet wird nur noch, wo wirklich Inhalt darunter liegt (Kapseln, Overlays, Schublade).
- **Die Inhaltsebene bleibt matt (`bg-card`) und wird reicher.** Verlauf je Prüfung statt je Semester, Hanteln für Veränderung, Punktstreifen für Verteilung, Zeitleiste mit nötiger Note.
- **Ein ruhiger Grund.** Hinter Seitenleiste und Inhalt liegt ein statischer Token-Verlauf (`np-grund`). Kein Inhalt läuft unter der Leiste durch, keine Orbs, keine Bilder.
- **Fallback zuerst.** Ohne JS, mit reduzierter Transparenz, reduzierter Bewegung, mehr Kontrast und im Kontrast-Theme ist jede Seite vollständig lesbar und bedienbar.

## 3 Grundsätze

| # | Grundsatz | Umsetzung im Portal | Apple (wörtlich, Quelle) |
|---|---|---|---|
| G1 | Glas nur in der funktionalen Ebene | Karten, Tabellen, Formulare, Diagrammflächen, Kacheln bleiben `bg-card` | «Don’t use Liquid Glass in the content layer.» – HIG materials#Liquid-Glass; «it is best reserved for the navigation layer that floats above the content of your app» – WWDC25/219 † |
| G2 | Inhalt hebt sich nur während der Bedienung in Glas | `np-glas-moment` auf Schieber- und Schalterknopf, nur `:active` | «Elements can even lift up into Liquid Glass temporarily … This lets the resting state stay visually quiet» – WWDC25/219 |
| G3 | Rückmeldung durch Licht | `np-glanz` + `--np-licht`; Press-State Pflicht | «the material illuminates from within as a form of feedback» – WWDC25/219; «Always include a press state for a custom button.» – HIG buttons#Best-practices. **Repo-Regel, nicht Apple:** Knöpfe skalieren nie (`app.css`, Kommentar vor `@utility np-knopf`: «kein Skalieren») – Apple selbst verformt («responds to interaction by instantly flexing and energizing with light» – WWDC25/219 †); wir nehmen nur das Licht. |
| G4 | Sparsam | Glas-Budget §4.2 | «Use Liquid Glass effects sparingly. … Limit these effects to the most important functional elements in your app.» – HIG materials#Liquid-Glass (Gestaltung); das SwiftUI-Zitat «Limit the use of Liquid Glass effects onscreen at the same time» ist eine Leistungsregel und stützt nur das Budget in §4.2 |
| G5 | Kein Glas auf Glas | Kapsel- und Schubladenglas aus, solange die Palette offen ist; Inhalte auf Glas nur Füllungen | «always avoid glass on glass» – WWDC25/219 |
| G6 | Grosse Flächen deckender | Schubladen-Seitenleiste 0.92 deckend (Glas über Inhalt); angedockt 0.68 > Kapseln 0.58 (dunkel) | «Liquid Glass appears more opaque in larger elements like sidebars to preserve legibility over complex backgrounds» – HIG color#Liquid-Glass-color. Gilt nur für die Schublade: angedockt liegt nur `np-grund` unter der Leiste, dort ist die Deckung eine Gestaltungs-, keine Lesbarkeitsfrage (§4.1) |
| G7 | Scrollkante statt Leistenfläche, nur wo Inhalt unter schwebenden Elementen läuft, Schatten wächst | eine `np-scroll-edge` je Scrollansicht, Schatten über `--np-kante` | «Only use a scroll edge effect when a scroll view is behind floating interface elements.» · «Apply one scroll edge effect per view.» – HIG scroll-views#Scroll-edge-effects †; «As text scrolls underneath, shadows become more prominent» – WWDC25/219; «Scroll edge effects work in concert with Liquid Glass» – WWDC25/219 (Kante ist ein eigener Effekt neben dem Glas) |
| G8 | Harte Kante für angeheftete Tabellenköpfe nur in eigenen Scrollcontainern | `np-kante-hart` auf `thead` **nur**, wenn die Tabelle in einem eigenen Scrollbereich läuft (dann ist das die eine Kante dieser Ansicht); angeheftete Köpfe im Seitenscroll bekommen eine deckende Fläche `bg-card` ohne Kanteneffekt, weil die Symbolleiste dort schon die eine Kante je Ansicht ist (G7) | «Hard … ideal for interactive text, controls without backgrounds, or pinned table headers» – WWDC25/356; HIG scroll-views empfiehlt sonst den automatischen Stil («Prefer the automatic scroll edge effect style») – Entscheid: hart, weil macOS-Massstab und Desktop-Tabellen |
| G9 | Menüs wachsen aus dem Knopf | `transform-origin` Knopfmitte, Start in Knopfgrösse | «buttons fluidly morph into menus and popovers.» – adopting-liquid-glass; «the bubble simply pops open … right where you just tapped» – WWDC25/219 |
| G10 | Abdunkelung bei Unterbrechung, immer nur eines | Scrim bei Palette, Dialog und Bearbeitungs-Drawer (`x-drawer`: unterbricht den Ablauf); nie zwei Overlays. Der Scrim der Navigations-Schublade (unter 1024 px / `data-navigation='oben'`) ist Repo-Entscheid (Fokusfalle, Klick ausserhalb schliesst), nicht Apple | «When a task interrupts the main flow, pair Liquid Glass with a dimming layer to help center attention … when a task happens in parallel, Liquid Glass creates a natural separation» – WWDC25/356; «Show one popover at a time.» – HIG popovers; «Display only one sheet at a time» – HIG sheets |
| G11 | Fenster ohne Fokus tritt zurück | `html[data-fenster='inaktiv']` dämpft Glanz und Schatten | «Liquid Glass shifts its appearance and visually recedes to guide attention.» – WWDC25/219 |
| G12 | Höchstens drei Gruppen, eine Hauptaktion rechts, Farbe auf die Fläche | `data-hauptaktion` genau einmal je Leiste, Symbole nie eingefärbt | «aim for a maximum of three» – HIG toolbars#Item-groupings; «Only specify one primary action, and put it on the trailing side» – toolbars#Actions; «apply color to the background rather than to symbols or text» – color#Liquid-Glass-color |
| G13 | Konzentrische Radien | Innenradius = Aussenradius − Abstand | «concentric shapes calculate their radius by subtracting padding from the parent’s» – WWDC25/356 |
| G14 | Bewegung mit Zweck, kurz, abbrechbar, nicht bei Häufigem | Katalog §5; keine Bewegung beim Tippen, Sortieren, Zeilenwechsel | «Don’t add motion for the sake of adding motion.» · «Let people cancel motion.» · «generally avoid adding motion to UI interactions that occur frequently» – HIG motion |
| G15 | Reduzierte Bewegung = Überblendung, nie Unschärfe animieren | Variante `ruhig`, Weichzeichner fix | «Replacing transitions in x-, y-, and z-axes with fades» · «Avoiding animating into and out of blurs» – HIG accessibility#Cognitive |
| G16 | Alle Einstellungen prüfen | Flags `--bewegung`, `--transparenz`, `--kontrast` (R6-00) | «Ensure you test your app’s custom elements, colors, and animations with different configurations of these settings.» – adopting-liquid-glass |
| G17 | Diagramme einfach, Details auf Wunsch, Zusammenfassung mit Zahlen, Filter als Detailstufen | Tooltip, Tastatur, Satz mit echten Werten, `<details>` mit Tabelle | «don’t require interaction to reveal critical information» · «expanding the hit target to include the entire plot area» – HIG charts; «If you don’t use Audio Graphs, you need to provide an overview of the chart’s structure and purpose.» – HIG charts#Enhancing-the-accessibility-of-a-chart †; «you might let people choose to view different levels of detail or subsets of data to match their interest» – HIG charting-data † (Filter). **Repo-Entscheid, nicht Apple:** «Tabelle immer» (Vorentscheidung 8) – Apples Satz «consider offering the data … in a list or table» meint eine Tabelle *statt* eines Diagramms |
| G18 | Achsen | Notenachse fest 1–6, Notenbalken ab 1; Anzahlen ab 0; Veränderung als Hantel | «Consider using a fixed range when specific minimum and maximum values are meaningful» · «bar charts can work well when you use zero for the lower bound» – HIG charts#Axes |
| G19 | Nie nur Farbe, echte Werte | Stufe als Text, Zusammenfassung mit Zahlen | «Avoid relying solely on color» – charts#Color; «use actual values in your descriptions» – charts#Enhancing-the-accessibility-of-a-chart |
| G20 | Menüs: Symbole bei allen oder keinem | gilt für jedes `x-dropdown` | «provide icons for all menu items in a group, or none of them» – HIG menus#Icons |
| G21 | Ruhiger Grund hinter der Seitenleiste, kein Inhalt darunter | `np-grund` statt Inhalt darunter (Vorentscheidung 1) | «In steady states, such as when an app first launches, avoid intersections between content and Liquid Glass. Instead, reposition or scale the content to maintain separation.» – WWDC25/219 (trägt die Vorentscheidung). **Nur angelehnt:** Apples «background extension effect» (HIG sidebars#Best-practices, adopting-liquid-glass) spiegelt angrenzenden Inhalt und zeichnet ihn weich; `np-grund` ist ein statischer Token-Verlauf, kein Spiegel. «Light from colorful content nearby can subtly spill onto its surface» – WWDC25/219 † ist das Bild für die Tönung der Leiste aus dem Grund |

Nicht übernommen: SF Pro, SF Symbols, Apple-Systemfarbwerte (`GUI-APPLE.md:31`), die Clear-Variante. HIG dark-mode «Avoid offering an app-specific appearance setting» steht gegen die 12 Themes – bestehende Entscheidung ausserhalb von R6, wird hier nicht als Stütze zitiert.

## 4 Glas-Budget

### 4.1 Materialien (`resources/css/app.css`)
Deckungen bleiben **Zahlen-Literale** (erstes Literal je Modus im `@utility`-Block), sonst lesen `kontrast.mjs:40-66` und `ThemeKontrastTest:134-157` sie nicht. Tönung per `color-mix` nur als zweite Deklaration.

| Utility | Einsatz | Deckung dunkel / hell | Weichzeichner | Nachweis (Basislinie §4) |
|---|---|---|---|---|
| `np-glas` angedockt | Seitenleiste ≥ 64rem | card/0.68 / card/0.80 | **keiner** (`backdrop-filter: none`) | liegt nur über `np-grund`: nötig 0.00 |
| `np-glas` Schublade | `html[data-schublade]` (Regel nach der Basisregel) | card/0.92 / card/0.92 | 24px, `saturate(var(--glas-saettigung))` | über Inhalt: höchstes Minimum 0.91 (Gletscher hell) |
| `np-glas-gruppe::before` | Kapseln Symbol- und Tableiste | card/0.58 / card/0.78 | wie heute | hinter Scrollkante nötig dunkel ≤ 0.37, hell ≤ 0.73 |
| `glass-overlay` | Menüs, Palette, Tastenkürzel, Diagramm-Tipp | surface-2/0.92 / card/0.92 (heute hell 0.86) | 28px | über jeder Token-Unterlage: ≤ 0.91 |
| `np-glas-moment` | Schieber-/Schalterknopf nur beim Drücken | card/0.74 / card/0.78 | 8px | kein Text; Kante `--glas-kante` ≥ 3:1 zu `surface-2` |
| `np-glanz` | Lichtschicht in Kapseln und Knöpfen | Licht ≤ 0.07 | – | `kontrast.mjs --glanz=0.07` Exit 0 |
| `np-symbolleiste` | Scrollkante | Verlauf `bg` bis 0.72 bleibt | **keiner** (heute 10px, `:419`) | Voraussetzung für die Kapselwerte |
| `glass-scrim` | Abdunkelung | `rgb(var(--scrim) / α)` | keiner | – |

Reduzierte Transparenz (`prefers-reduced-transparency`, `data-transparenz='reduziert'`) und Kontrast-Theme: alle Deckungen 1, kein `backdrop-filter`, `np-grund` flach. Das ist **strenger als Apple**, das nur sagt «Reduced Transparency, makes Liquid Glass frostier and obscures more of the content behind it» (WWDC25/219 †) – bewusster Repo-Entscheid (ein Schalter, ein messbarer Zustand, derselbe für Systemeinstellung und persönlichen Schalter). Mehr Kontrast (`prefers-contrast: more`): zusätzlich 1px `--border-strong` (WWDC25/219 «highlights them with a contrasting border»). Die Regel «≥ 0.78» (`app.css:277`, `gui-konzept.md:115`, Commit bffe93b, ohne Messquelle) entfällt zugunsten der gemessenen Minima.

### 4.2 Fläche und Bildzeiten (`leistung.mjs`, Median aus 3 Läufen, nur gleiche Argumente vergleichen)

| Grösse | Argumente | Basislinie | Grenze | Ziel |
|---|---|---|---|---|
| Glasanteil Ruhe | 1920×1080 | 4 Flächen, 18.4 % | ≤ 8 % | ≈ 5 % (nur Kapseln) |
| Glasanteil Palette offen | 1920×1080 `--palette` | 5 Flächen, 34.6 % | ≤ 18 % | Panel 40rem allein |
| Glasanteil Menü offen | 1920×1080 `--menue=<Auslöser>` | neu (R6-00) | ≤ 12 % | – |
| Tippen p95 Palette | 1920×1080 `--laeufe=3 --palette` | `/dashboard` 35.2 · `/grades` 35.9 · `/grades/calculator` 35.5 · `/exams` 32.9 · `/admin` 40.2 · `/admin/reports/grades` 38.3 · `/admin/learners` 36.5 · `/trainer` 31.5* · `/trainer/learners` 31.2* · `/trainer/learners/1` 33.7* ms | ≤ Basislinie | ≤ 25 ms |
| Scroll p95 | 1920×540 `--hoehe=540 --laeufe=3` | `/dashboard` 20.2 · `/grades` 16.9 · `/grades/calculator` 19.2 · `/admin/reports/grades` 20.3 · `/admin/learners` 19.2 · `/trainer` 17.6* · `/trainer/learners/1` 19.6* · `/trainer/learners/1/grades` 19.8* ms | ≤ Basislinie + T, lange Bilder 0/150 | – |
| Ruhe p95 / Animationen | 1920×1080 | 16.9 ms (`/dashboard` 17.0) / 0 | ≤ 17.0 ms / 0 | – |
| LCP | 1920×1080 | `/dashboard` 164 · `/grades` 168 · `/grades/calculator` 176 · `/exams` 136 · `/admin` 148 · `/admin/reports/grades` 188 · `/admin/learners` 172 · `/trainer` 148 · `/trainer/learners` 144 ms | ≤ Basislinie + 10 % | – |
| DOM-Knoten | 1920×1080 | `/dashboard` 11 458 · `/grades` 18 971 · `/admin/learners` 14 897 | ≤ Basislinie + 10 % | – |

\* Berufsbildner-Werte stehen in Basislinie §5; sie wurden parallel zu zwei laufenden Workflow-Agents gemessen (CPU-Last) und werden in R6-00 mit denselben Argumenten nachgemessen. Leistungsregel von Apple: «Creating too many Liquid Glass effect containers … can degrade performance. Limit the use of Liquid Glass effects onscreen at the same time.» (SwiftUI applying-liquid-glass-to-custom-views). **T** = Streuung der Mediane aus 3 Wiederholungen, gemessen in R6-00, höchstens 1.7 ms (10 % der Bildperiode). Scroll-Werte stammen aus §2 der Basislinie (massgeblich), nicht aus §3.

### 4.3 Neue Tokens (`theme.css`, alle 24 Theme-Blöcke, RGB-Tripel wie `--bg`)
`--glas-licht`, `--glas-kante`, `--glas-schatten`, `--scrim`, `--grund-hoch` (Helligkeit streng zwischen `--bg` und `--card`), `--schalter-knopf` als Tripel; `--glas-licht-staerke`, `--glas-kante-staerke`, `--glas-glanz-staerke` (≤ 0.07), `--glas-saettigung` (Kontrast-Theme 1), `--grund-akzent` (0–0.04) als Zahl. Danach steht in `app.css` kein `rgb(255 255 255 / …)` und kein `rgb(0 0 0 / …)` mehr. Gletscher hell/dunkel verlieren die übernommenen Apple-Werte (`theme.css:16-75`) und bekommen eigene Werte mit gleichen Kontrastschwellen.

## 5 Bewegungskatalog

**Tokens** (`app.css`): `--dauer-1` 100ms · `--dauer-2` 150ms · `--dauer-3` 200ms · `--dauer-4` 300ms · `--dauer-morph` 360ms · `--ease-out`/`--ease-in` (bleiben, `:133-134`) · `--ease-feder: linear(…)` (Dämpfung 0.82, Überschwingen ≤ 1.1 %) · `--np-weg: 1` · `--np-skala-von: 0.96` · `--np-staffel: 30ms`.
**Reduktion** (`@custom-variant ruhig` = `prefers-reduced-motion: reduce` **oder** `html[data-bewegung='reduziert']`): `--ease-feder` → `--ease-out`, Dauern ≥ 200ms → 150ms, `--np-weg: 0`, `--np-skala-von: 1`, `--np-staffel: 0ms`. Ersetzt die 0.01-ms-Regel (`app.css:209-216`); die Ausnahmen `:217-224` bleiben. Jedes `x-transition` mit translate oder scale bekommt ein `ruhig:`-Gegenstück; `motion-reduce:` verschwindet. `bewegungRuhig()` aus `np.js` ist die einzige JS-Abfrage.

| # | Moment | Bewegung | Dauer · Kurve | unter `ruhig` | Quelle |
|---|---|---|---|---|---|
| B1 | Menü/Popover öffnen | Start in Knopfgrösse (`--np-von-sx/-sy`), `transform-origin` Knopfmitte, + Deckkraft; das Panel ist dicker als die Kapsel (Overlay-Deckung 0.92, Schatten `--elev-3` statt `--elev-2`) | `--dauer-morph` `--ease-feder`; zu `--dauer-2` `--ease-in` | Überblendung 150ms | G9; «When glass flexes and morphs to larger sizes – like when presenting a menu from a toolbar button – its material characteristics change to simulate a thicker, more substantial material. It casts deeper, richer shadows» – WWDC25/219 † |
| B2 | Befehlspalette | Panel `scale(var(--np-skala-von))`→1 + Deckkraft, Scrim nur Deckkraft, Weichzeichner fix | 200 / zu 150 | Überblendung | G10, G15 |
| B3 | Schublade, Dialog | Versatz vom Rand, CSS-Transition (mitten im Lauf umkehrbar), keine Feder | 300 ease-out / zu 200 ease-in | Überblendung 150 | motion «Let people cancel motion» |
| B4 | Toast | 8px × `--np-weg` + Deckkraft | 200 / zu 150 | Überblendung | – |
| B5 | Auswahlmarke Leiste/Tab | View Transition nur auf leeren Marker-Spans `np-leiste-auswahl`, `np-tab-auswahl` | 200 `--ease-feder` | Überblendung | angelehnt an WWDC25/219 «Liquid Glass dynamically morphs between the controls in each context» (dort: Wechsel der Bedienelemente zwischen App-Zuständen, keine Vorgabe für Auswahlmarken) |
| B6 | Segmentmarke | `np-segment-marke` gleitet (`--np-segment-x`, Breite) | 200 `--ease-feder` | sofort | wie B5 (angelehnt) |
| B7 | Seitenwechsel | bestehende Überblendung (`app.css:249-257`) | 180 | keine (`:259`) | – |
| B8 | Licht unter dem Zeiger | `--np-licht-x/-y` folgt (rAF), nur `hover:hover` und `pointer:fine` | Stärke 150 ease-out | Licht ruht in der Mitte | G3; motion «more subdued effect … trackpad» |
| B9 | Drücken | `--np-licht` → 1, keine Skalierung | 100 | gleich | G3 |
| B10 | Glas-Moment | Knopf wird beim Ziehen `np-glas-moment` | 100 | Materialwechsel ohne Grösse | G2 |
| B11 | Fenster inaktiv | `--np-aktiv` 1 → 0.4 dämpft Glanz und Schatten | 200 | gleich (keine Bewegung) | G11 |
| B12 | Scrollkante | Schatten wächst mit `--np-kante` 0..1 (JS, passiv, rAF) | ohne Transition | gleich | G7 |
| B13 | Diagramm, erstes Zeichnen | gestaffelt `--np-staffel` je Punkt, gesamt ≤ 500ms | ease-out | `update('none')` | charts «highlight the changes in other ways, too» |
| B14 | Diagramm, Filterwechsel | `chart.update()` an Ort, Punkte wandern | 300 ease-out | `update('none')` + `aria-live` | dito |
| B15 | SVG einzeichnen | `stroke-dashoffset` 1→0, `pathLength="1"`, `np-einzeichnen` | 300 ease-out | keine | – |
| B16 | Agenda Monatswechsel | View-Transition-Typen `vor`/`zurueck`, seitlicher Versatz | 200 | Überblendung | – |
| B17 | Rechner-Scrub | Markierung folgt Zeiger und ←/→ (0.25) ohne Animation | – | gleich | charts «scrub across the area» |

Im Ruhezustand läuft nichts (`ruhe.animationen` = 0): kein Dauerlicht, kein animierter Grund, keine Schleifen.

## 6 Statistik-Katalog

**Endpunkt-Vertrag (Vorentscheidung 7):** keine neue Route. Dieselbe GET-Route liefert mit `Accept: application/json` (`$request->wantsJson()`) `{filter, diagramm, tabelle, zusammenfassung, meta}` mit `Vary: Accept` und `Cache-Control: private, no-store`; die HTML-Antwort trägt ebenfalls `Vary: Accept`. Filter laufen durch `App\Support\StatistikFilter::aus($request, $regeln)` (Whitelist; unbekannte Schlüssel und Werte → Standard, nie Fehler; IDs nur aus der sichtbaren Menge). Lernenden-ID immer aus der Session, Berufsbildner nur über `Lernender::sichtbarFuer` (`app/Models/Lernender.php:98`). Durchschnitte nur aus `App\Services\Auswertung`; Median und Quartile aus `App\Services\Auswertung\Verteilung` (Quantil Typ 7, `null` bei n < 3). Notenabfragen mit `whereNull('geloescht_am')`.
**Client:** `x-filterleiste` bleibt GET-Formular (funktioniert ohne JS). `npFilter` (`resources/js/statistik.js`) fängt Änderungen ab → `history.replaceState` → 150ms Entprellung → `fetch` mit `AbortController` → Ereignis `np-statistik` → `npChart.setze()` und Tabelle. Links mit `data-behalte-filter` (Sortierung, Export, Druck) übernehmen die Query. Fehler → normaler Seitenaufruf.

| # | Seite · Route | Frage | Darstellung | Filter (Whitelist) | Daten | Scheibe |
|---|---|---|---|---|---|---|
| S1 | `/learner` (`learner.dashboard`), Cockpit `trainer.learners.show` / `admin.learners.show` | Wie entwickeln sich meine Noten Prüfung für Prüfung? | `x-verlauf`: Prüfungspunkte auf Datumsachse, laufender Schnitt als Linie, Genügend-Linie, Semesterbänder; Positionen ohne Datum als Liste darunter | `zeitraum=semester\|lehrjahr\|alles`, `kategorie`, `fach`, `achse=datum\|semester` | `Statistik::stichtagsreihe()` über `Rechenkern::auswerten`; Monatsenden ≤ 60, sonst Quartale (`meta.vergroebert`); `meta.ohneDatum` | 07a, 07b, 08, 09 |
| S2 | `/learner` «Wo stehe ich» | Wo ziehe ich den Schnitt herunter? | Balken schwächste zuerst, Genügend-Linie, Stufe als Text; Tabelle folgt dem Umschalter | `modus=semester\|lehrzeit`, `kategorie` | `Uebersicht::balkenDiagramm` (`:488`) | 06 (Fehler), 08 |
| S3 | `/grades` (`learner.grades.index`) | Besser oder schlechter als im Vorsemester? | `x-hantel` je Fach, Pfeil und Delta als Zahl, sortiert | `semester`, `kategorie`, `sort=delta\|fach` | `Statistik::hanteln()` aus `Auswertung::semester` beider Semester | 07a, 08 |
| S4 | `/grades/calculator` | Welche Note brauche ich, wie gross ist der Spielraum? | Kurve + Band schlechteste/beste Endnote, Scrub über die ganze Fläche ohne neue Anfrage, ←/→ 0.25 | Formularzustand (POST bleibt) | `Zielrechner::kurve` über `learner.grades.calculator.calculate` | 04, 08 |
| S5 | `/qualification` (`learner.qualification.index`) | Was fehlt mir zum Bestehen? | je offene Position «bestanden» oder «benötigt X.X», Bullet je Position | – | `Statistik::benoetigt()` über `Zielrechner::loese`, Grenze aus DB | 07a, 08 |
| S6 | `/exams` (`learner.exams.index`) | Welche Note brauche ich in der nächsten Prüfung? | `x-zeitleiste` kommender Prüfungen mit nötiger Note für genügend als Zahl | `zeitraum=4w\|8w\|alle` (Standard 8w) | `Statistik::benoetigt()`; geplante Prüfungen tragen ein Datum (`NotenQuelle.php:158`) | 07a, 08 |
| S7 | `/trainer` (`trainer.dashboard`) | Wer hat sich bewegt? | `x-hantel` je betreute Person, Vorsemester → aktuell, nach Delta; unter Genügend mit Symbol und Text | `status=kritisch\|alle`, `kategorie`, `sort=delta\|name` | `LernstandRechner::fuer` (`Lernstand::delta`) über `sichtbarFuer` | 07a, 09 |
| S8 | `/trainer/learners`, `/admin/learners` (`*.learners.index`) | Wer braucht Aufmerksamkeit? | `x-punktstreifen` über der Liste (Median ab n ≥ 3), je Zeile Mini-`x-bullet` und `x-sparkline`; Filter-Marken mit × | `status`, `lehrjahr`, `lehrberuf`, `kategorie`, `sort=schnitt\|delta\|name` | `Uebersicht::berufsbildner`, `Verteilung` | 07a, 09 |
| S9 | `/admin` (`admin.dashboard`) | Wird regelmässig erfasst? | Säulen je Woche (ab 0) + Median-Linie, nur Betriebssummen | `zeitraum=12w\|26w\|52w` | `Uebersicht::admin(int $wochen = 12)` → `aktivitaet(int $wochen)` (`:680`), `Verteilung` | 07a, 10 |
| S10 | `/admin/reports/grades` (`admin.reports.grades`) | Wie sind die Noten verteilt? | Histogramm in Viertelnoten (ab 0), Median ab n ≥ 3, Genügend-Linie | `kategorie`, `lehrjahr`, `semester`, `lehrberuf` | neu `Bericht::verteilung` (Histogramm heute als Buckets in `aggregate()`, `Bericht.php:142-148`) mit `Verteilung::histogramm` | 07a, 10 |
| S11 | `/admin/reports/grades` | Wie unterscheiden sich die Lehrjahre? | Streifen je Lehrjahr (Small Multiples, gleiche Achse 1–6), Median ab n ≥ 3 | `lehrberuf`, `kategorie` | `Bericht::nachLehrjahr` (`:176`), `Verteilung` | 07a, 10 |

Jede Darstellung: Zusammenfassung in einem Satz mit echten Werten, Tabelle in `<details>` (nativ, ohne JS), Tastatur ←/→/Home/End, `aria-live="polite"`, Leerzustand ohne leeres Diagramm (Konto livia.gerber). Kein Kreis, Ring oder Tacho. Zurückgestellt: Heatmap Fach × Lehrjahr und Betriebstrend (im Pilot n < 3 je Zelle; Trend bräuchte Schema, Vorentscheidung 5). `Uebersicht::jahrgangsDiagramm` (`:698`, privat, ungenutzt, mittelt selbst) wird gelöscht.

## 7 Scheibenplan

| Welle | Scheibe | Inhalt | Kern-Dateien | nach | parallel mit | Agent |
|---|---|---|---|---|---|---|
| 0 | R6-00 | Prüfwerkzeuge, BB-Basislinie, Toleranz T | `tools/pruefung/*.mjs`, `ThemeKontrastTest`, Basislinie §5 | – | – | sonnet high |
| 1 | R6-01 | Tokens, Grund, Bewegungsregel | `theme.css`, `app.css` (Basis), `layouts/app` | 00 | 03 | sonnet high |
| 1 | R6-03 | JS-Bausteine | `np.js`, `app.js` | 00 | 01, 02 | sonnet high |
| 1 | R6-02 | Materialien, Bedienelemente | `app.css` | 01 | 03, 04 | sonnet high |
| 1 | R6-04 | npChart v2 | `charts.js`, `rechner.js`, `diagramm`, `JsTexte`, `lang/en.json` | 03 | 02 | sonnet high |
| 2 | R6-05 | Funktionsebene in Views | `navigation`, `layouts/app`, Overlays, `suche.js`, `bestaetigung.js` | 02, 03, 04 | 06 | sonnet high |
| 2 | R6-06 | Seitenschliff ohne neue Statistik | Dashboards Lernende/Admin, Symbolleisten-Seiten | 02, 04 | 05 | sonnet high |
| 3 | R6-07a | Statistik-Service | `Verteilung`, `Statistik`, `StatistikFilter`, `Uebersicht`, `Bericht` | 05, 06 | – | sonnet high (opus xhigh bei Rechenfehlern) |
| 3 | R6-07b | Endpunkte, `npFilter`, SVG-Bausteine | Controller, `statistik.js`, `x-verlauf/-hantel/-punktstreifen/-zeitleiste` | 07a | – | sonnet high |
| 4 | R6-08 | Lernende-Seiten S1–S6 | Lernenden-Views | 07b | 09, 10, 11 | sonnet high |
| 4 | R6-09 | Berufsbildner + Lernendenliste S1, S7, S8 | BB-Dashboard, `verwaltung/lernende/*` | 07b | 08, 10, 11 | sonnet high |
| 4 | R6-10 | Admin S9–S11 | Admin-Dashboard, Bericht | 07b | 08, 09, 11 | sonnet high |
| 4 | R6-11 | Doku und Skills | `docs/*`, `.claude/skills/*`, `.claude/rules/oberflaeche.md` | 07b | 08, 09, 10 | sonnet high |
| 5 | R6-12 | Schlussabnahme | Workflow `notenportal-r6-pruefung.js`, `r6-leistung-nachher.md` | 08–11 | – | sonnet high, `pruefer` |
| opt. | R6-13 | Lichtbrechung | `app.css`, `np.js`, `layouts/app` | 12 + Ja von David | – | opus xhigh |

Regeln für alle Scheiben: Parallele Scheiben teilen keine Datei. Die Cloud-Sitzung hat 4 CPUs – ein Workflow führt höchstens 2 Agents gleichzeitig aus, «parallel» heisst darum Zweierwellen. Controller- und Service-Änderungen gehören nur R6-07a/b. Neue UI-Texte: R6-04 (JS), R6-05 (Funktionsebene) und R6-07b (ganzer Statistik-Katalog) pflegen `lang/en.json` und `JsTexte::SCHLUESSEL`; R6-06 und R6-08–10 verwenden nur diese Texte und melden fehlende der Hauptsitzung. Controller-Aliase in `routes/web.php:5-29` (`LernenderNotenController` = `app/Http/Controllers/Lernender/NotenController.php`, `AdminBerichtController` = `Admin/BerichtController.php`). Die Hauptsitzung führt `npm run build` (muss «built in» zeigen) aus und committet; Subagents schreiben nicht in Git, Composer oder npm. Je Scheibe zusätzlich Pflicht: `php artisan test` grün, `grep -rn "ß" resources/views/ lang/` leer, Hook `view-pruefung.sh` ohne Befund, `vendor/bin/pint --dirty`, einmal `reviewer`.

## 8 Prüfplan

| Prüfung | Werkzeug und Argumente | Schwelle | wann |
|---|---|---|---|
| Kontrast | `node tools/pruefung/kontrast.mjs` | Exit 0, 0 Pflichtverstösse | jede CSS-Scheibe, R6-12 |
| Glas-Minima | `kontrast.mjs --minimum` | Kapsel ≥ Wert «mit Scrollkante», Overlay und Schublade ≥ Wert «über jeder Token-Unterlage» je Theme | R6-01, 02, 12 |
| Glanz, Tiefe | `kontrast.mjs --glanz=0.07`, `--stufen` | Exit 0; dunkel `bg` < `grund-hoch` < `card` < `surface-2` | R6-01, 02 |
| Bildzeiten | `node tools/pruefung/leistung.mjs <email> "<pfade>" --laeufe=3 --palette` und `--hoehe=540 --laeufe=3`, `--menue=<Auslöser>`, `--ohne-glas` | §4.2 | R6-02, 05, 08–10, 12 |
| Screenshots | `shot.mjs` dunkel 1920; `--breite=2560 --hoehe=1440`; `--bewegung=reduziert`; `--transparenz=reduziert`; `--kontrast=mehr`; `--hell` als Stichprobe | `bildpruefer` ohne Befund «unlesbar» oder «abgeschnitten» | jede Seiten-Scheibe |
| Verhalten | `klick.mjs`: Menü-Ursprung, Schublade umkehren, Filter → URL, Tastatur im Diagramm | wie in der Scheibe | R6-04, 05, 08–10 |
| Rundgang | `rundgang.mjs` für nina.huber, livia.gerber, michael.baumann, laura.frei | Exit 0 | jede Scheibe ab R6-01 |
| Tests | `php artisan test`; neu: `VerteilungTest`, `StatistikTest`, `StatistikFilterTest`, `StatistikEndpunkteTest`, `StatistikIsolationTest`, `StatistikAbfragenTest` (Muster «klein gegen gross, Toleranz +2»), je Rolle ein Statistik-Feature-Test | grün; `ZugriffsschutzTest` deckt jede neue `role:`-Route automatisch | jede Scheibe |
| Regel-Greps | `grep -rn "motion-reduce:" resources/views` · `grep -nE "rgb\((255 255 255\|0 0 0) ?/" resources/css/app.css` · `grep -nE "systemGray\|system[A-Z]\|apple\.com\|#0066cc" resources/css/theme.css` · `grep -rnE "glass-bar\|glass-seitenleiste\|glass-btn\|np-btn-primary\|np-fade-in\|data-autohide" resources/` · `grep -n ":is([^)]*::" resources/css/app.css` | alle leer | R6-12 |
| Übergänge | `grep -rn "view-transition-name" resources/` | nur `np-leiste-auswahl`, `np-tab-auswahl` | R6-05, 12 |
| Sichtprüfung | `ui-checker` je Rollenbereich, `pruefer` vor Fertigmeldung | ohne offenen Befund | R6-05, 08–10, 12 |

Emulation: `reducedMotion` und `contrast` über Playwright `emulateMedia`; `prefers-reduced-transparency` über CDP `Emulation.setEmulatedMedia` – die Feature-Liste enthält immer auch `prefers-color-scheme`, sonst springt der Lauf auf hell; wirkt CDP nicht, setzt das Werkzeug `data-transparenz='reduziert'` und meldet das.

## 9 Verworfenes

| Idee | Herkunft | Grund |
|---|---|---|
| Glas auf Karten, Kacheln, Diagrammen | Entwurfsideen | G1; Text über Akzent- und Diagrammfarben bräuchte 0.72–0.91 Deckung (Basislinie §4) |
| Inhalt unter der Seitenleiste | Synthese | Vorentscheidung 1; Ersatz `np-grund` (G21) |
| Clear-Variante, 35-%-Abdunkelung | HIG | gilt nur für Clear über bildreichem Inhalt; das Portal hat keinen |
| Orbs, Hintergrundbilder, animierter Grund | Bewegung zuerst | Vorentscheidung 1; Ruhe-Animationen müssen 0 bleiben |
| Zählwerk (Zahlen zählen hoch) | Bewegung zuerst | motion «Don’t add motion for the sake of adding motion»; Zahl muss sofort stimmen |
| Gestaffelter Kartenaufbau (`sibling-index`) | Bewegung zuerst | bei jedem Seitenaufruf, also häufig (G14) |
| Toast wegwischen | Bewegung zuerst | Desktop-Massstab, Schliessen-Knopf reicht |
| Knöpfe skalieren | Bewegung zuerst | G3; bestehende Regel `app.css:702-705` |
| Pfad-Morph über `d` | Bewegung zuerst | `chart.update()` an Ort leistet dasselbe ohne eigene Pfadlogik |
| Scroll-getriebene Animationen | Bewegung zuerst | G14 («Don’t add motion for the sake of adding motion», «generally avoid adding motion to UI interactions that occur frequently» – HIG motion); «peripheral motion» (accessibility#Cognitive) gilt nur als Pflicht unter reduzierter Bewegung; `--np-kante` per JS genügt |
| Deckungen als `var()` | Material zuerst | `kontrast.mjs` und `ThemeKontrastTest` lesen nur Literale |
| Federkurve auf der Schublade | Bewegung zuerst | Arbeitsfläche, Feder ohne Zweck; CSS-Transition bleibt umkehrbar |
| Weichzeichner animieren | Bewegung zuerst | G15 und Kosten: Glas kostet beim Tippen +10–12 ms p95 (Basislinie §3) |
| Weichzeichner in der Scrollkante | heute `app.css:419` | Repo-Regel: kein zweiter `backdrop-filter` unter Elementen, die selbst Glas tragen; dazu Kosten (Basislinie §3). Die Kante ist ein eigener Effekt neben dem Glas («Scroll edge effects work in concert with Liquid Glass» – WWDC25/219), kein Glas-auf-Glas-Fall; «aren’t decorative» (HIG scroll-views) belegt nur, dass die Kante nur dort hingehört, wo Inhalt unter schwebenden Elementen läuft |
| View Transitions für Listenzeilen | Bewegung zuerst | `view-transition-name` macht das Element zur Backdrop Root (css-view-transitions-1 §2.1.1); bis 19 000 Knoten; häufige Interaktion |
| Snapshot-Tabelle, Betriebstrend | Synthese | Vorentscheidung 5 (kein Schema) |
| Heatmap Fach × Lehrjahr | Daten zuerst | n < 3 je Zelle im Pilot; zurückgestellt |
| Kohortenvergleich für Lernende | Synthese | Vorentscheidung 2 |
| Erfassungsverhalten je Person, BB-Vergleich | Synthese | Vorentscheidungen 3 und 4 |
| Neue Diagrammbibliothek, eigene Route je Statistik | Entwürfe | Vorentscheidungen 7 und 8 |
| Kreis, Ring, Tacho | Daten zuerst | G18; Balken vergleichen Kategorien (HIG charts#Marks) |
| Lichtbrechung per `@supports` erkennen | Material zuerst | Firefox-Bug 1961378 (NEW) zeichnet `url(#…)` in `backdrop-filter` nicht; die Falschmeldung von `@supports` ist nur in Kommentar 0 des Duplikats 1995195 beobachtet, Safari ungeprüft → Erkennung per JS |
| Apple-Systemfarben in Gletscher | Bestand `theme.css:16-75` | `GUI-APPLE.md:31`, `LAGE.md:250` |

## 10 Entschieden ohne David (gilt bis Widerspruch) und Offen für David

Die Synthese hatte acht Fragen; sechs sind Gestaltungs- oder Messfragen, die die Hauptsitzung nach `/weiter`-Regel selbst entscheidet (Annahme nennen, weiterarbeiten). Nur zwei sind Produkt- bzw. Datenschutzfragen.

**Vorentscheidungen 9–14 der Hauptsitzung (Fortsetzung der acht aus dem Jury-Kontext):**

9. Lichtbrechung am Kapselrand (R6-13) kommt erst nach R6-12 und nur, wenn die Bildzeiten Luft lassen (Tippen p95 ≤ 25 ms); nur Chrome/Edge per JS-Erkennung, Firefox und Safari zeigen normales Glas.
10. Grund-Akzent: 2 % (`--grund-akzent: 0.02`) in Gletscher, je Theme 0–0.04; Kontrast-Theme 0.
11. Die angedockte Seitenleiste trägt keinen Weichzeichner (`backdrop-filter: none`) – über dem ruhigen Grund sieht Tönung plus Lichtkante gleich aus und spart den grössten Glasanteil (Basislinie §1: 18.4 % → ≈ 5 %). Echtes Glas nur als Schublade.
12. Gletscher verliert die übernommenen Apple-Werte (`theme.css:16-75`, Verbot `GUI-APPLE.md:31`) und bekommt eigene Werte mit denselben gemessenen Kontrasten (`kontrast.mjs` Exit 0, `--minimum` je Theme nicht schlechter). Blau und Grau verschieben sich leicht; das ist Regelkonformität, keine neue Gestaltung.
13. Positionen ohne Datum (IPA, Schlussprüfung) stehen als Liste unter dem Verlauf, nie still einsortiert (Widerleger-Korrektur zu `Leistung::$datum`).
14. Standardfenster der Erfassung im Admin: 12 Wochen (`zeitraum=12w`), Auswahl 26 und 52.

**Offen für David** (bis zur Antwort gilt der Vorschlag):

1. Dürfen Berufsbildner die persönlichen Ziele der Lernenden sehen? Erst dann zeigt die Berufsbildner-Seite «nötige Note fürs Ziel»; ohne Ja nur «nötige Note für genügend» (Vorschlag).
2. Erfassungsverhalten je Person für Berufsbildner und ein Vergleich der Berufsbildner für Admins bleiben ausgeschlossen (Vorentscheidungen 3 und 4). Soll das mit Datenschutz und HR geklärt werden, oder bleibt es dauerhaft draussen?

## 11 Scheiben (Arbeitsaufträge)

Aus der strukturierten Synthese; jede Scheibe nennt ihre Dateien (Grenze für parallele Agents), Abhängigkeiten und Akzeptanzkriterien. Modellwahl laut Synthese; die Hauptsitzung setzt Modell und Effort im Auftrag explizit (Skill `notenportal-agentauftrag`).

### R6-00 – Prüfwerkzeuge, Berufsbildner-Basislinie und Toleranz T

**Ziel:** Werkzeuge so erweitern, dass jede spätere Scheibe ihre Akzeptanz messen kann, bevor sich an CSS oder Views etwas ändert. browser.mjs (heute nur width/height/colorScheme, :68-72): Flags --bewegung=reduziert (Playwright emulateMedia({reducedMotion:'reduce'}) und zusätzlich html[data-bewegung='reduziert']), --transparenz=reduziert (CDP Emulation.setEmulatedMedia mit features [{name:'prefers-reduced-transparency',value:'reduce'},{name:'prefers-color-scheme',value:<aktueller Modus>}]; wirkt matchMedia danach nicht, html[data-transparenz='reduziert'] setzen und 'Ersatz: data-transparenz' ausgeben), --kontrast=mehr (emulateMedia({contrast:'more'})); nach dem Laden page.bringToFront() und document.documentElement.dataset.fenster ausgeben. shot.mjs, klick.mjs, rundgang.mjs reichen die Flags durch. leistung.mjs: --menue=<CSS-Selektor des Auslösers> klickt den Auslöser, misst glas.anteilProzent und Hover-p95 im offenen Menü (Ausgabe menue.glas, menue.hover). kontrast.mjs: Materialliste (:68) = ['np-glas','glass-overlay','np-glas-gruppe','np-glas-moment'] plus alte Namen, solange vorhanden (fehlende still überspringen wie heute 'if (d)'); Unterlage grund-hoch, falls das Token existiert; --glanz=<alpha> legt rgb(--glas-licht/alpha) über das Material, bevor text/muted geprüft werden; --stufen prüft im Dunkelmodus Helligkeit bg < grund-hoch < card < surface-2 je Theme (Exit 1 bei Verstoss); neue Paare: glas-kante gegen surface-2 >= 3:1, text auf accent/0.16 über card >= 4.5:1 (aktive Leistenzeile), Fokusring gegen jedes Material >= 3:1. ThemeKontrastTest (:112) dieselbe Materialliste, fehlende Utilities überspringen. Basislinie: neuer Abschnitt §5 in docs/auftrag/messungen/r6-leistung-baseline.md mit den Berufsbildner-Werten (Rohdaten heute nur im Scratch: Tippen p95 /trainer 31.5, /trainer/learners 31.2, /trainer/learners/1 33.7 ms bei 1920x1080; Scroll p95 bei 1920x540 /trainer 17.6, /trainer/learners/1 19.6, /trainer/learners/1/grades 19.8 ms), nachgemessen mit denselben Argumenten, dazu T = max-min der Mediane aus 3 Wiederholungen von leistung.mjs auf /dashboard (nina.huber) und /admin/reports/grades (laura.frei), gedeckelt auf 1.7 ms. Keine Änderung unter resources/.

**Dateien:** `tools/pruefung/browser.mjs`, `tools/pruefung/shot.mjs`, `tools/pruefung/klick.mjs`, `tools/pruefung/rundgang.mjs`, `tools/pruefung/leistung.mjs`, `tools/pruefung/kontrast.mjs`, `tests/Feature/ThemeKontrastTest.php`, `docs/auftrag/messungen/r6-leistung-baseline.md`  
**Nach:** – · **Parallel mit:** – · **Agent:** sonnet (high)

**Akzeptanz:**
- node tools/pruefung/kontrast.mjs: Exit 0, weiterhin 0 Pflichtverstösse, Paarzahl >= 3628 (neue Paare kommen dazu)
- node tools/pruefung/kontrast.mjs --minimum gibt dieselben 24 Zeilen wie Basislinie §4 aus (unverändertes CSS)
- node tools/pruefung/kontrast.mjs --stufen läuft; Ergebnis je Theme in Basislinie §5 festgehalten (Gate erst ab R6-01)
- NP_TEST_PW gesetzt, node tools/pruefung/shot.mjs nina.huber@demo.example "/learner" --bewegung=reduziert --transparenz=reduziert --kontrast=mehr --dir=<scratch>: Ausgabe meldet matchMedia reduced-motion=true, contrast more=true, reduced-transparency=true oder 'Ersatz: data-transparenz', Screenshot ist dunkel
- shot.mjs gibt dataset.fenster aus (vor R6-03 leer, danach 'aktiv')
- node tools/pruefung/leistung.mjs laura.frei@demo.example "/admin" --menue=<Benutzermenü-Auslöser> --json enthält menue.glas.anteilProzent
- Basislinie §5 enthält Berufsbildner-Tabelle (Tippen p95 @1920x1080 --laeufe=3 --palette; Scroll p95 @1920x540 --hoehe=540 --laeufe=3) und den Wert T <= 1.7 ms
- php vendor/bin/phpunit --filter ThemeKontrastTest grün; git diff --stat zeigt keine Datei unter resources/

### R6-01 – Fundament: Tokens, ruhiger Grund, Bewegungsregel

**Ziel:** theme.css: in allen 24 Theme-Blöcken (12 Themes x hell/dunkel) neue Tokens als RGB-Tripel --glas-licht, --glas-kante, --glas-schatten, --scrim, --grund-hoch (Helligkeit streng zwischen --bg und --card), --schalter-knopf und als Zahl --glas-licht-staerke, --glas-kante-staerke, --glas-glanz-staerke (<= 0.07), --glas-saettigung (1.8; [data-theme='kontrast'] 1), --grund-akzent (0.02, höchstens 0.04). Gletscher hell/dunkel (theme.css:16-75): übernommene Apple-Werte (systemGray*, systemBlue, apple.com #1d1d1f/#0066cc/#f5f5f7) durch eigene Werte mit denselben Schwellen ersetzen (Text >= 4.5:1, UI/Grafik >= 3:1, Weiss auf Akzent >= 4.5:1), Apple-Verweise in Kommentaren entfernen. app.css: im @theme --ease-feder: linear(...) (Feder, Dämpfung 0.82, Überschwingen <= 1.1 %), in :root --dauer-1 100ms, --dauer-2 150ms, --dauer-3 200ms, --dauer-4 300ms, --dauer-morph 360ms, --np-weg 1, --np-skala-von 0.96, --np-staffel 30ms, --np-aktiv 1. @custom-variant ruhig { @media (prefers-reduced-motion: reduce) { @slot; } &:where([data-bewegung='reduziert'], [data-bewegung='reduziert'] *) { @slot; } }. Die 0.01-ms-Regel (app.css:209-216) wird ersetzt durch Token-Reduktion unter html[data-bewegung='reduziert'] und @media (prefers-reduced-motion: reduce): --ease-feder: var(--ease-out), --dauer-3/--dauer-4/--dauer-morph: 150ms, --np-weg 0, --np-skala-von 1, --np-staffel 0ms; Ausnahmen :217-224 bleiben. @utility np-grund: position fixed, inset 0, z-index -1, pointer-events none, background linear-gradient(168deg, rgb(var(--grund-hoch)) 0%, rgb(var(--bg)) 62%); nur innerhalb @supports (color: color-mix(in oklab, red, blue)) eine zweite Ebene radial-gradient mit rgb(var(--accent) / var(--grund-akzent)); flach rgb(var(--bg)) unter [data-theme='kontrast'], html[data-transparenz='reduziert'] und @media (prefers-reduced-transparency: reduce). Kein background-attachment: fixed, keine Animation. layouts/app.blade.php: <div class="np-grund" aria-hidden="true"></div> als erstes Kind von body; body behält bg-bg als Rückfall, kein Wrapper zwischen body und Inhalt trägt bg-bg; totes [data-autohide]-Skript (:197-207) entfernen.

**Dateien:** `resources/css/theme.css`, `resources/css/app.css`, `resources/views/layouts/app.blade.php`  
**Nach:** `R6-00` · **Parallel mit:** `R6-03` · **Agent:** sonnet (high)

**Akzeptanz:**
- node tools/pruefung/kontrast.mjs Exit 0
- node tools/pruefung/kontrast.mjs --minimum: kein Theme braucht über jeder Token-Unterlage mehr als 0.92 (Overlay-Deckung), hinter der Scrollkante dunkel <= 0.40 und hell <= 0.75
- node tools/pruefung/kontrast.mjs --stufen Exit 0 für alle 12 Themes
- grep -c 'glas-licht:' resources/css/theme.css >= 24; grep -nE 'systemGray|system[A-Z]|apple\.com|#0066cc' resources/css/theme.css leer; grep -rn 'data-autohide' resources/ leer
- Screenshots dunkel 1920 und --breite=2560 --hoehe=1440 von /learner (nina.huber), /trainer (michael.baumann), /admin (laura.frei): Verlauf hinter Seitenleiste sichtbar, Karten unverändert matt; mit --transparenz=reduziert und Theme Kontrast flacher Grund (bildpruefer)
- leistung.mjs nina.huber "/dashboard,/grades" --laeufe=3: Ruhe p95 <= 17.0 ms, ruhe.animationen 0, LCP <= Basislinie + 10 % (164/168 ms)
- rundgang.mjs Exit 0 für nina.huber, livia.gerber, michael.baumann, laura.frei
- php artisan test grün (ThemeKontrastTest, FarbeKontrastTest, DarstellungProfilTest)

### R6-03 – JS-Bausteine für Licht, Fenster, Scrollkante und Morph

**Ziel:** np.js: export function bewegungRuhig() = matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.dataset.bewegung === 'reduziert' (charts.js:28-30 nutzt es ab R6-04). export function registriereLicht(Alpine): Alpine.directive('np-licht') (x-np-licht) nur wenn matchMedia('(hover: hover) and (pointer: fine)').matches; Rechteck bei pointerenter cachen, pointermove per requestAnimationFrame gedrosselt --np-licht-x/--np-licht-y (Prozent) direkt auf das Kind .np-glanz schreiben (die @property-Werte erben nicht), --np-licht 1 bei Eintritt und pointerdown, 0 bei Austritt; unter bewegungRuhig() keine Positionsverfolgung (50 %/0 %); cleanup entfernt alle Listener. export function registriereFenster(): html[data-fenster]='aktiv'|'inaktiv' über window focus/blur und visibilitychange. scrollKante (np.js:124) erweitern: schreibt --np-kante 0..1 (Scrollweg / 24px, begrenzt) auf np-symbolleiste bzw. np-scroll-edge, passiver Listener, rAF. export function morphUrsprung(ausloeser, panel): setzt am Panel --np-von-sx/--np-von-sy (Auslösergrösse / Panelgrösse) und --np-ursprung (Auslösermitte relativ zum Panel). export function mitRichtung(richtung, aktualisieren): document.startViewTransition({update: aktualisieren, types: [richtung]}) falls vorhanden und nicht ruhig, sonst aktualisieren() (für die Agenda, B16). app.js: registriereLicht(Alpine), registriereFenster() registrieren. Keine Dauerschleife: rAF nur innerhalb von Ereignissen.

**Dateien:** `resources/js/np.js`, `resources/js/app.js`  
**Nach:** `R6-00` · **Parallel mit:** `R6-01`, `R6-02` · **Agent:** sonnet (high)

**Akzeptanz:**
- npm run build durch die Hauptsitzung zeigt 'built in'
- shot.mjs nina.huber "/learner" gibt dataset.fenster = 'aktiv' aus
- leistung.mjs nina.huber "/dashboard" --laeufe=3: ruhe.animationen 0, Ruhe p95 <= 17.0 ms
- grep -n 'requestAnimationFrame' resources/js/np.js: jeder Treffer liegt in einem Ereignis-Handler (reviewer)
- php vendor/bin/phpunit --filter SchluesselTest grün (keine neuen np.t-Texte)
- php artisan test grün

### R6-02 – Materialien und Bedienelemente (CSS)

**Ziel:** Nur resources/css/app.css. (1) Jedes rgb(255 255 255 / a) und rgb(0 0 0 / a) durch rgb(var(--glas-licht) / a), rgb(var(--glas-schatten) / a) oder rgb(var(--scrim) / a) ersetzen, a bleibt Literal. (2) np-glas (:280): angedockt backdrop-filter none, dunkel rgb(var(--card) / 0.68), hell 0.80 als erste Literale, innere Lichtkante inset 0 0.5px 0 rgb(var(--glas-kante) / var(--glas-kante-staerke)); Schubladenregel (html[data-schublade], :507-519) NACH der Basisregel: 0.92, blur(24px) saturate(var(--glas-saettigung)). Kommentar '>= 0.78' (:277) entfernen. (3) np-glas-gruppe::before (:433-459): dunkel 0.58, hell 0.78; Radien konzentrisch (innen = calc(Aussenradius - Abstand)); Glanzschicht np-glanz: @property --np-licht-x/--np-licht-y (<percentage>, inherits: false, 50 %/0 %), --np-licht (<number>, inherits: false, 0); radial-gradient an (x,y) mit rgb(var(--glas-licht) / calc(var(--glas-glanz-staerke) * var(--np-licht) * var(--np-aktiv))). (4) np-glas-moment: card/0.74 dunkel, 0.78 hell, blur 8px, nur :active bzw. [data-gedrueckt]; np-schalter-Knopf background rgb(var(--schalter-knopf)) statt white (:982), beim Drücken np-glas-moment; Schieberknopf ebenso. (5) glass-overlay (:333): dunkel surface-2/0.92, hell card/0.92 (heute 0.86), --elev-3, @starting-style mit scale(var(--np-skala-von)) und opacity 0, transform-origin var(--np-ursprung, top), Menü-Start scale(var(--np-von-sx,0.96), var(--np-von-sy,0.96)) mit --dauer-morph/--ease-feder; glass-scrim (:359) ohne backdrop-filter, rgb(var(--scrim) / a). (6) np-symbolleiste (:405): backdrop-filter auf ::before (:419) entfernen, Verlaufsstopp --bg 0.72 bleibt, Schatten 0 1px 0 rgb(var(--glas-schatten) / calc(var(--np-kante, 0) * 0.12)) plus weicher Schatten proportional zu --np-kante. (7) Neue Utilities: np-kante-hart (angehefteter thead: bg-card deckend, 1px --border unten), Hauptaktion [data-hauptaktion] in Kapseln (Fläche rgb(var(--accent)), Text rgb(var(--accent-contrast)), Symbol nicht eingefärbt), np-leistenzeile aktiv (:566) rgb(var(--accent) / 0.16) + innere Lichtkante, Symbolfarbe unverändert, np-segment-marke (gleitet über --np-segment-x und Breite, --dauer-3 --ease-feder), np-einzeichnen (stroke-dasharray 1, @starting-style stroke-dashoffset 1, Transition --dauer-4; ruhig: keine), Regeln für html:active-view-transition-type(vor|zurueck) (seitlicher Versatz x --np-weg, 200ms). (8) html[data-palette]: np-glas-gruppe::before und Schubladen-np-glas ohne backdrop-filter (deckend 0.92). html[data-fenster='inaktiv']: --np-aktiv 0.4. (9) Die 7 Blöcke für reduzierte Transparenz (:297, 311, 327, 350, 422, 468, 688) zu einem Regelsatz zusammenführen: alle Materialien Deckung 1, kein backdrop-filter, np-grund flach; .np-glas-gruppe::before als eigener Selektor, nie innerhalb :is(). @media (prefers-contrast: more) und [data-theme='kontrast']: zusätzlich 1px rgb(var(--border-strong)). (10) Tote Utilities löschen: glass-bar (:303), glass-seitenleiste (:320), glass-btn (:858), np-btn-primary (:872), np-fade-in (:1510). Knöpfe skalieren weiterhin nicht (:702-705).

**Dateien:** `resources/css/app.css`  
**Nach:** `R6-00`, `R6-01` · **Parallel mit:** `R6-03`, `R6-04` · **Agent:** sonnet (high)

**Akzeptanz:**
- grep -nE 'rgb\((255 255 255|0 0 0) ?/' resources/css/app.css leer
- grep -rnE 'glass-bar|glass-seitenleiste|glass-btn|np-btn-primary|np-fade-in' resources/ leer; grep -n ':is([^)]*::' resources/css/app.css leer
- node tools/pruefung/kontrast.mjs Exit 0; Zeile 'Materialien aus app.css' zeigt np-glas 0.68/0.80, np-glas-gruppe 0.58/0.78, glass-overlay 0.92/0.92, np-glas-moment 0.74/0.78
- kontrast.mjs --minimum: Kapselwert >= Wert 'mit Scrollkante' jedes Themes, 0.92 >= Wert 'über jeder Token-Unterlage' jedes Themes; --glanz=0.07 Exit 0; --stufen Exit 0
- leistung.mjs @1920x1080 --laeufe=3 --palette für nina.huber "/dashboard,/grades,/grades/calculator,/exams", laura.frei "/admin,/admin/reports/grades,/admin/learners", michael.baumann "/trainer,/trainer/learners": Glasanteil Ruhe <= 8 %, Tippen p95 <= Basislinie (§1/§5), Ruhe p95 <= 17.0 ms, ruhe.animationen 0
- leistung.mjs --hoehe=540 --laeufe=3 auf denselben Seiten: Scroll p95 <= Basislinie §2/§5 + T, lange Bilder 0/150; --ohne-glas: glas.anzahl 0
- Screenshots dunkel 1920 und 2560, --transparenz=reduziert, --kontrast=mehr, --hell als Stichprobe auf /learner, /trainer, /admin/reports/grades: bildpruefer ohne Befund 'unlesbar'
- php artisan test grün (ThemeKontrastTest)

### R6-04 – npChart v2: Aktualisieren an Ort, Glas-Tipp, Tastatur, Scrub

**Ziel:** charts.js: npChart (Alpine.data, :440) bekommt setze(daten): gleicher Typ und gleiche Zahl Datensätze -> chart.data.labels und datasets[i].data ersetzen und chart.update(bewegungRuhig() ? 'none' : undefined); sonst zeichne() neu (heute immer destroy() + new Chart(), :453-460); Neuaufbau zusätzlich nur bei Themewechsel. Externer Tooltip: tooltip {enabled:false, external} rendert in <div class="np-diagramm-tipp glass-overlay"> des x-diagramm (heute card/0.96, :44). Tastatur: Hülle tabindex=0, ←/→/Home/End setzen chart.setActiveElements und tooltip.setActiveElements; Ansage in aria-live="polite" ('np-diagramm-ansage'). Erstes Zeichnen gestaffelt: animation.delay = min(dataIndex * 30, 500) nur bei mode 'default'; unter bewegungRuhig() animation false. Typ kurve (Rechner, rechner/index:228): Band zwischen schlechtester und bester Endnote (Filler fill '-1'), Plugin npScrub: interaction {mode:'index', intersect:false} über die ganze Plotfläche, senkrechte Linie, Wert linear aus den Kurvenpunkten interpoliert ohne neue Anfrage, ←/→ in 0.25, Home/End. Tote Typen gruppen (:358) und spark (:414) löschen. rechner.js: Ergebnis von postJson (:107) per setze(kurve) statt neuer Instanz. diagramm.blade.php: Props typ, daten, optionen, zusammenfassung (sichtbarer Satz mit echten Werten), Slot tabelle in <details> (nativ, ohne JS). Neue Texte in JsTexte::SCHLUESSEL und lang/en.json. Achsenregel: Notenachse fest 1-6 (Notenbalken ab 1), Anzahlen ab 0.

**Dateien:** `resources/js/charts.js`, `resources/js/rechner.js`, `resources/views/components/diagramm.blade.php`, `app/Support/JsTexte.php`, `lang/en.json`  
**Nach:** `R6-00`, `R6-03` · **Parallel mit:** `R6-02` · **Agent:** sonnet (high)

**Akzeptanz:**
- Alle 5 Aufrufstellen zeichnen: Screenshots dunkel 1920 und 2560 von /learner und /grades/calculator (nina.huber), /admin und /admin/reports/grades (laura.frei), je auch mit --bewegung=reduziert
- klick.mjs auf /grades/calculator: Zielwert 10x ändern, das vorher markierte <canvas> (data-np-probe) ist danach noch im DOM (kein Neuaufbau)
- klick.mjs: Tab auf das Diagramm, Pfeil rechts ändert den Text in aria-live; Home/End springen an Anfang/Ende
- grep -n 'gruppen\|spark' resources/js/charts.js ohne Diagrammtyp-Treffer
- leistung.mjs nina.huber "/grades/calculator" --hoehe=540 --laeufe=3: Scroll p95 <= 19.2 ms + T; ruhe.animationen 0
- php vendor/bin/phpunit --filter 'SchluesselTest|EnglischeSeitenTest' grün; php artisan test grün

### R6-05 – Funktionsebene in den Views: Leiste, Kapseln, Menüs, Palette, Toasts, Schubladen

**Ziel:** navigation.blade.php: Seitenleiste (:28) bleibt np-glas; leerer Marker <span class="np-leiste-auswahl"> nur in der aktiven Zeile, Tabs np-tab-auswahl (view-transition-name nur auf diesen leeren Spans). Kapseln (:96, :176, feedback-widget :16) mit <span class="np-glanz" aria-hidden="true"> und x-np-licht; höchstens 3 Gruppen. Menüs (:106-109, :137-140, dropdown.blade.php:34): x-transition nur mit Deckkraft durch die CSS aus glass-overlay ersetzen, beim Öffnen morphUrsprung(ausloeser, panel); Escape schliesst, Fokus zurück zum Auslöser; Symbole bei allen Einträgen einer Gruppe oder keinem. Palette (:183-215, npBefehl in layouts/app.blade.php:60-123): Panel max-w-[40rem], Ergebnisse max-h-[28rem] (höchstens 8 sichtbar), Glas nur am Panel, Scrim glass-scrim ohne Weichzeichner; html[data-palette] erst nach transitionend des Scrims setzen, beim Schliessen entfernen. Toasts (toast.blade.php): Erfolg role=status, 5 s, Pause bei Hover und Fokus; Fehler role=alert, bleibt bis Schliessen; höchstens 3 gleichzeitig. drawer, noten-drawer, modal, bestaetigung: deckend bg-card, CSS-Transition über Klasse (mitten im Lauf umkehrbar), öffnen 300ms ease-out, schliessen 200ms ease-in, keine Feder; Kopf/Fuss mit np-scroll-edge (eine je Ansicht). segment-auswahl: np-segment-marke. seitenkopf: Slot für Hauptaktion mit data-hauptaktion. Alle motion-reduce: durch ruhig: ersetzen (modal:76, toast:16, drawer:58/62, feedback-widget:24); jedes x-transition mit translate/scale bekommt ein ruhig:-Gegenstück. Neue Texte in lang/en.json.

**Dateien:** `resources/views/layouts/navigation.blade.php`, `resources/views/layouts/app.blade.php`, `resources/views/components/dropdown.blade.php`, `resources/views/components/dropdown-link.blade.php`, `resources/views/components/toast.blade.php`, `resources/views/components/drawer.blade.php`, `resources/views/components/noten-drawer.blade.php`, `resources/views/components/modal.blade.php`, `resources/views/components/bestaetigung.blade.php`, `resources/views/components/feedback-widget.blade.php`, `resources/views/components/seitenkopf.blade.php`, `resources/views/components/tastenkuerzel.blade.php`, `resources/views/components/segment-auswahl.blade.php`, `resources/js/suche.js`, `resources/js/bestaetigung.js`, `lang/en.json`  
**Nach:** `R6-02`, `R6-03`, `R6-04` · **Parallel mit:** `R6-06` · **Agent:** sonnet (high)

**Akzeptanz:**
- grep -rn 'motion-reduce:' resources/views leer
- grep -rn 'view-transition-name' resources/ nur np-leiste-auswahl, np-tab-auswahl (und root)
- leistung.mjs @1920x1080 --laeufe=3 --palette auf allen Seiten aus §4.2: Glasanteil Palette <= 18 %, Tippen p95 <= Basislinie (§1/§5), Abstand zum Ziel 25 ms im Bericht
- leistung.mjs laura.frei "/admin" --menue=<Benutzermenü-Auslöser>: menue.glas <= 12 %
- klick.mjs: Benutzermenü öffnen -> berechnetes transform-origin liegt im Auslöser; Escape -> Fokus auf Auslöser; Schublade öffnen und nach 100 ms schliessen -> Endzustand geschlossen ohne Sprung
- Screenshots dunkel 1920/2560, --bewegung=reduziert, --transparenz=reduziert mit offener Palette, offenem Menü, offener Schublade und Toast (klick.mjs), bildpruefer ohne Befund
- rundgang.mjs Exit 0 für 4 Konten; php vendor/bin/phpunit --filter 'Benutzermenue|Tastenkuerzel|Bestaetigung|Suche|Leerzustand' grün; php artisan test grün
- ui-checker einmal über die Funktionsebene aller drei Rollen ohne offenen Befund

### R6-06 – Seitenschliff ohne neue Statistik

**Ziel:** dashboards/lernender.blade.php: Fehler «Wo stehe ich» beheben – der Umschalter (:131-133) wechselt Semester/Lehrzeit, die Tabelle (:159) rendert aber nur $balken[$balkenModus]; beide Tabellen serverseitig rendern, Sichtbarkeit per x-show am Modus, ohne JS gilt der Servermodus. Seiten mit np-symbolleiste (abschluss/index:76, admin/feedback/index:153, verwaltung/noten/index:180, verwaltung/pruefungen/index:75, module/index:22): höchstens 3 Gruppen, genau eine Hauptaktion mit data-hauptaktion am rechten Ende, angeheftete Tabellenköpfe mit np-kante-hart. dashboards/admin.blade.php und components/kachel.blade.php: Kennzahl-Kacheln matt bg-card, Zahlen tabular-nums, kein np-glas* in der Inhaltsebene. Keine neuen UI-Texte (fehlende an die Hauptsitzung melden).

**Dateien:** `resources/views/dashboards/lernender.blade.php`, `resources/views/dashboards/admin.blade.php`, `resources/views/components/kachel.blade.php`, `resources/views/abschluss/index.blade.php`, `resources/views/admin/feedback/index.blade.php`, `resources/views/verwaltung/noten/index.blade.php`, `resources/views/verwaltung/pruefungen/index.blade.php`, `resources/views/module/index.blade.php`, `tests/Feature/Lernender/WoStehIchTabelleTest.php`  
**Nach:** `R6-02`, `R6-04` · **Parallel mit:** `R6-05` · **Agent:** sonnet (high)

**Akzeptanz:**
- WoStehIchTabelleTest: /learner enthält beide Tabellen (Semester und Lehrzeit) mit x-show; Standardmodus sichtbar ohne JS
- klick.mjs nina.huber /learner: Umschalten Semester/Lehrzeit ändert die sichtbaren Tabellenzeilen
- grep -c 'data-hauptaktion' je Symbolleisten-View = 1; grep -rn 'np-glas' resources/views/dashboards resources/views/components/kachel.blade.php leer
- Screenshots dunkel 1920/2560 der 6 Seiten, bildpruefer ohne Befund; angehefteter Tabellenkopf beim Scrollen deckend (shot nach Scroll)
- rundgang.mjs Exit 0 für 4 Konten; php vendor/bin/phpunit --filter 'Leerzustand|DashboardKarten' grün; php artisan test grün

### R6-07a – Statistik-Service: Verteilung, Stichtagsreihe, Filter-Whitelist

**Ziel:** Neu app/Services/Auswertung/Verteilung.php: median(array): ?float, quartile(array): ?array{q1,q2,q3} (Quantil Typ 7, lineare Interpolation), histogramm(array, float $breite = 0.25, float $von = 1.0, float $bis = 6.0): array; null bei n < 3; Rundung über Rundung. Neu app/Services/Auswertung/Statistik.php: stichtagsreihe(array $leistungen, Konfiguration $k, Zielgroesse $z, Carbon $von, Carbon $bis): array – Stichtage Monatsenden (höchstens 60, sonst Quartalsenden und meta.vergroebert = true), je Stichtag Rechenkern::auswerten(Leistungen mit datum <= Stichtag)->wert($z); Punkte je Prüfung (datum, note, fach); Leistungen mit datum null (Leistung.php:23, position() :33-36: IPA, Schlussprüfung, Notenbaum ohne Datum) in meta.ohneDatum, nicht in der Reihe. hanteln(Auswertung $a, int $semesterId, ?int $vorsemesterId, ?int $kategorieId): array je Fach {fach, vorher, jetzt, delta}, nach delta sortiert, aus Auswertung::semester beider Semester. benoetigt(...): je offene Position bzw. kommende Prüfung die nötige Note für genügend über Zielrechner::loese, Grenze aus der DB-Konfiguration. Neu app/Support/StatistikFilter.php: static aus(Request $r, array $regeln, array $standard): self; Regeln als Liste erlaubter Werte oder ['id' => erlaubte IDs]; unbekannte Schlüssel/Werte -> Standard; wert(string), query(): array für Links. Uebersicht.php: admin(int $wochen = 12) (:199) reicht wochen an aktivitaet(int $wochen = 12) (:680) durch, erlaubt 12|26|52, Raw-SQL bleibt gebunden, dazu Median über Verteilung; berufsbildner() (:168) liefert je Person delta, Semesterschnitte für die Sparkline und Punktstreifen-Werte nur über sichtbarFuer; jahrgangsDiagramm (:698) löschen. Bericht.php: verteilung (:150-173) über Verteilung::histogramm + Median ab n >= 3, nachLehrjahr (:176) liefert Personenschnitte je Lehrjahr, Filter lehrjahr. Alle Durchschnitte aus Auswertung, Notenabfragen mit whereNull('geloescht_am').

**Dateien:** `app/Services/Auswertung/Verteilung.php`, `app/Services/Auswertung/Statistik.php`, `app/Support/StatistikFilter.php`, `app/Services/Uebersicht.php`, `app/Services/Bericht.php`, `tests/Unit/Auswertung/VerteilungTest.php`, `tests/Unit/Auswertung/StatistikTest.php`, `tests/Unit/Support/StatistikFilterTest.php`, `tests/Feature/Performance/StatistikAbfragenTest.php`  
**Nach:** `R6-05`, `R6-06` · **Parallel mit:** – · **Agent:** sonnet (high); opus (xhigh) bei Rechenabweichungen gegen docs/notenlogik.md

**Akzeptanz:**
- php vendor/bin/phpunit --filter 'VerteilungTest|StatistikTest|StatistikFilterTest' grün mit: n = 0, 1, 2 -> null; [1,2,3,4] -> q1 1.75, Median 2.5, q3 3.25; 70 Monate -> Quartale und meta.vergroebert; Leistung ohne Datum in meta.ohneDatum, nicht in der Reihe; gelöschte Note zählt nicht
- StatistikAbfragenTest nach Muster AbfragenAnzahlTest: Berufsbildner-Dashboard und Bericht mit 2 gegen 8 Lernenden, Abfragezahl unterscheidet sich um höchstens 2
- grep -rn 'jahrgangsDiagramm' app/ leer; grep -rniE 'avg\(' app/Services app/Http ohne neuen Treffer
- Werte von Uebersicht::lernender/berufsbildner/admin für bestehende Felder unverändert (bestehende Feature-Tests grün)
- php artisan test grün; vendor/bin/pint --dirty ohne Befund

### R6-07b – Endpunkte, Filter-Client, SVG-Bausteine und Katalogtexte

**Ziel:** Controller (Aliase in routes/web.php:5-29 beachten): DashboardController::lernender|berufsbildner|admin, Verwaltung/LernendeController::index|show, Lernender/NotenController::index, Lernender/AbschlussController::index, Lernender/PruefungenController::index, Admin/BerichtController::noten lesen StatistikFilter::aus(...) und antworten bei $request->wantsJson() mit response()->json(['filter','diagramm','tabelle','zusammenfassung','meta']) plus Vary: Accept und Cache-Control: private, no-store; HTML-Antwort ebenfalls Vary: Accept. Keine neue Route; Lernenden-ID aus der Session, Berufsbildner nur über Lernender::sichtbarFuer. resources/js/statistik.js: export function registriereStatistik(Alpine) mit Alpine.data('npFilter', (cfg) => ...): change/input im x-filterleiste-Formular abfangen, Query bauen, history.replaceState, 150ms entprellen, fetch(url, {headers:{Accept:'application/json'}, signal}) mit AbortController (vorherige Anfrage abbrechen), $dispatch('np-statistik', json); a[data-behalte-filter] übernehmen die Query; Filter-Marken np-filter-token mit ×; Fehler -> location.assign(url). In app.js registrieren. x-filterleiste: optionale Prop statistik (setzt x-data="npFilter(...)"), ohne JS bleibt es ein GET-Formular mit Absenden. Neue Komponenten: x-verlauf (Hülle um x-diagramm typ verlauf, Zusammenfassung, Tabelle, Liste ohne Datum), x-hantel (vorher/jetzt, Pfeil, Delta-Text, Notenachse 1-6), x-punktstreifen (Punkt je Person, Genügend-Linie, Median erst ab n >= 3, Namen in <title> und Tabelle), x-zeitleiste (Prüfungen auf Datumsachse, nötige Note als Text); sparkline und bullet um Mini-Variante und np-einzeichnen ergänzen. Alle Texte des Statistik-Katalogs (Titel, Filterbeschriftungen, Zusammenfassungsvorlagen) in lang/en.json und, wo JS sie braucht, in JsTexte::SCHLUESSEL.

**Dateien:** `app/Http/Controllers/DashboardController.php`, `app/Http/Controllers/Verwaltung/LernendeController.php`, `app/Http/Controllers/Lernender/NotenController.php`, `app/Http/Controllers/Lernender/AbschlussController.php`, `app/Http/Controllers/Lernender/PruefungenController.php`, `app/Http/Controllers/Admin/BerichtController.php`, `resources/js/statistik.js`, `resources/js/app.js`, `app/Support/JsTexte.php`, `lang/en.json`, `resources/views/components/filterleiste.blade.php`, `resources/views/components/verlauf.blade.php`, `resources/views/components/hantel.blade.php`, `resources/views/components/punktstreifen.blade.php`, `resources/views/components/zeitleiste.blade.php`, `resources/views/components/sparkline.blade.php`, `resources/views/components/bullet.blade.php`, `tests/Feature/Statistik/StatistikEndpunkteTest.php`, `tests/Feature/Statistik/StatistikIsolationTest.php`  
**Nach:** `R6-07a` · **Parallel mit:** – · **Agent:** sonnet (high)

**Akzeptanz:**
- StatistikEndpunkteTest: jede der 10 Routen (learner.dashboard, learner.grades.index, learner.qualification.index, learner.exams.index, trainer.dashboard, trainer.learners.index, trainer.learners.show, admin.dashboard, admin.learners.index, admin.reports.grades) mit Accept: application/json -> 200, Schlüssel filter/diagramm/tabelle/zusammenfassung/meta, Header Vary enthält Accept, Cache-Control enthält no-store; ?zeitraum=xyz -> 200 mit Standardwert
- StatistikIsolationTest: Lernende A mit ?lernender=<B> und ?lernender_id=<B> erhält nur eigene Werte; Berufsbildner auf trainer.learners.show einer nicht betreuten Person -> derselbe Status wie die HTML-Seite heute; Median über sichtbarFuer unverändert, wenn eine fremde Person mit Extremnote existiert; Median null bei n = 2
- HTML-GET mit ?zeitraum=lehrjahr rendert die gefilterte Tabelle serverseitig (Feature-Test prüft Text)
- php vendor/bin/phpunit --filter 'Zugriffsschutz|Datenisolation|Sichtbarkeit|AbfragenAnzahl|StatistikAbfragen|Schluessel|EnglischeSeiten' grün; php artisan test grün
- klick.mjs: Filter auf /admin/reports/grades ändern -> URL enthält den Wert, keine neue Navigation, Export-Link mit data-behalte-filter trägt dieselbe Query

### R6-08 – Lernende-Seiten: Verlauf, Wo stehe ich, Hanteln, Rechner, Qualifikation, Prüfungen (S1-S6)

**Ziel:** Nur Views. dashboards/lernender.blade.php: Verlaufsabschnitt (:164-228) durch x-verlauf mit npFilter (zeitraum, kategorie, fach, achse) ersetzen; «Wo stehe ich» Filter kategorie. lernender/noten/index.blade.php: x-hantel je Fach (semester, kategorie, sort). rechner/index.blade.php: Band und Scrub aus R6-04 einbinden, Zusammenfassung mit Spielraum. abschluss/index.blade.php: je offene Position «bestanden» oder «benötigt X.X» mit Mini-Bullet. lernender/agenda/index.blade.php und _eintrag: x-zeitleiste mit nötiger Note, Filter zeitraum=4w|8w|alle (Standard 8w), Monatswechsel über mitRichtung('vor'|'zurueck', ...). Nur Texte aus R6-07b; fehlende an die Hauptsitzung melden.

**Dateien:** `resources/views/dashboards/lernender.blade.php`, `resources/views/lernender/noten/index.blade.php`, `resources/views/rechner/index.blade.php`, `resources/views/abschluss/index.blade.php`, `resources/views/lernender/agenda/index.blade.php`, `resources/views/lernender/agenda/_eintrag.blade.php`, `tests/Feature/Statistik/LernendeStatistikTest.php`  
**Nach:** `R6-07b` · **Parallel mit:** `R6-09`, `R6-10`, `R6-11` · **Agent:** sonnet (high)

**Akzeptanz:**
- LernendeStatistikTest: jede Darstellung S1-S6 rendert Zusammenfassung mit Zahl und <details>-Tabelle; livia.gerber sieht Leerzustand statt leerem Diagramm
- klick.mjs nina.huber /learner: zeitraum auf lehrjahr -> URL enthält zeitraum=lehrjahr, kein Seitenwechsel, dasselbe <canvas>, Tabellenzeilen ändern sich; Neuladen der URL zeigt denselben Zustand
- Screenshots nina.huber "/learner,/grades,/grades/calculator,/qualification,/exams" dunkel 1920 und 2560, --bewegung=reduziert, --transparenz=reduziert; livia.gerber dieselben Seiten; bildpruefer ohne Befund
- leistung.mjs nina.huber dieselben Seiten @1920x1080 --laeufe=3 --palette und --hoehe=540 --laeufe=3: Tippen p95 <= Basislinie, Scroll p95 <= Basislinie + T, LCP und Knoten <= Basislinie + 10 %, ruhe.animationen 0
- rundgang.mjs nina.huber und livia.gerber Exit 0; ui-checker Lernende ohne offenen Befund; php artisan test grün

### R6-09 – Berufsbildner und Lernendenliste: Wer hat sich bewegt, Punktstreifen, Cockpit-Verlauf (S1, S7, S8)

**Ziel:** Nur Views. dashboards/berufsbildner.blade.php: x-hantel je betreute Person nach Delta, unter Genügend mit Symbol und Text, Klick ins Cockpit; Filter status, kategorie, sort. verwaltung/lernende/index.blade.php (gilt für trainer.learners.index und admin.learners.index): x-punktstreifen über der Liste, je Zeile Mini-x-bullet und x-sparkline, Filter-Marken mit ×, Sortier- und Exportlinks mit data-behalte-filter. verwaltung/lernende/_cockpit/uebersicht.blade.php (und show, falls nötig): x-verlauf wie bei den Lernenden, gleiche Farben und Achsen. Keine Gruppenwerte unter n = 3, kein Erfassungsverhalten je Person.

**Dateien:** `resources/views/dashboards/berufsbildner.blade.php`, `resources/views/verwaltung/lernende/index.blade.php`, `resources/views/verwaltung/lernende/show.blade.php`, `resources/views/verwaltung/lernende/_cockpit/uebersicht.blade.php`, `tests/Feature/Statistik/BerufsbildnerStatistikTest.php`  
**Nach:** `R6-07b` · **Parallel mit:** `R6-08`, `R6-10`, `R6-11` · **Agent:** sonnet (high)

**Akzeptanz:**
- BerufsbildnerStatistikTest: mit 2 betreuten Personen keine Median-Linie, mit 3 eine; nicht betreute Person erscheint in keinem Punkt, keiner Tabelle, keinem JSON
- Screenshots michael.baumann "/trainer,/trainer/learners,/trainer/learners/{id}" und laura.frei "/admin/learners" dunkel 1920 und 2560, --bewegung=reduziert, --transparenz=reduziert; bildpruefer ohne Befund
- leistung.mjs michael.baumann dieselben Seiten mit den Argumenten aus Basislinie §5: Tippen p95 <= Basislinie, Scroll p95 <= Basislinie + T, LCP und Knoten <= Basislinie + 10 %
- php vendor/bin/phpunit --filter 'Datenisolation|Sichtbarkeit|Zugriffsschutz' grün; rundgang.mjs michael.baumann und laura.frei Exit 0; ui-checker Berufsbildner ohne offenen Befund; php artisan test grün

### R6-10 – Admin: Erfassung je Woche, Notenverteilung, Lehrjahres-Streifen (S9-S11)

**Ziel:** Nur Views. dashboards/admin.blade.php: Säulen je Woche (ab 0) mit Median-Linie, Segment zeitraum=12w|26w|52w, nur Betriebssummen, Tabelle. admin/berichte/noten.blade.php: Histogramm in Viertelnoten (:103 umbauen), Median ab n >= 3, Genügend-Linie, Filter kategorie/lehrjahr/semester/lehrberuf über x-filterleiste statistik; Lehrjahres-Streifen als Small Multiples mit gleicher Achse 1-6; Export (reports.grades.export) und Druck mit data-behalte-filter.

**Dateien:** `resources/views/dashboards/admin.blade.php`, `resources/views/admin/berichte/noten.blade.php`, `tests/Feature/Statistik/AdminStatistikTest.php`  
**Nach:** `R6-07b` · **Parallel mit:** `R6-08`, `R6-09`, `R6-11` · **Agent:** sonnet (high)

**Akzeptanz:**
- AdminStatistikTest: ?zeitraum=26w liefert 26 Wochenwerte; Histogramm-Klassen summieren zur Zahl der Noten (ohne gelöschte); Median null bei n < 3
- Screenshots laura.frei "/admin,/admin/reports/grades" dunkel 1920 und 2560, --bewegung=reduziert, --transparenz=reduziert, --kontrast=mehr; bildpruefer ohne Befund
- leistung.mjs laura.frei "/admin,/admin/reports/grades" @1920x1080 --laeufe=3 --palette und --hoehe=540 --laeufe=3: Tippen p95 <= 40.2/38.3 ms, Scroll p95 /admin/reports/grades <= 20.3 ms + T, LCP <= 148/188 ms + 10 %
- rundgang.mjs laura.frei Exit 0; ui-checker Admin ohne offenen Befund; php artisan test grün

### R6-11 – Doku und Skills nachführen

**Ziel:** docs/gui-konzept.md: Glasstufen (:107-118) durch die Materialtabelle aus §4.1 ersetzen (heute G1 blur 16px/saturate 140 %, Code 24px/1.8), Regel '>= 0.78' (:115) durch gemessene Minima mit Quelle ersetzen, Bewegungstokens und Variante ruhig, Glas-Budget, Statistik-Katalog. docs/auftrag/GUI-APPLE.md: Grundsätze G1-G21 mit Quellen. docs/notenlogik.md: Stichtagsreihe, Quantil Typ 7, n >= 3. docs/funktionsumfang.md: neue Statistiken und Filter. Skills notenportal-ui und notenportal-dunkelmodus, .claude/rules/oberflaeche.md: Glas nur funktional, Literale für Deckungen, ruhig statt motion-reduce, view-transition-name nur auf Marker-Spans, Achsenregel. Skill notenportal-pruefwerkzeuge: neue Flags.

**Dateien:** `docs/gui-konzept.md`, `docs/auftrag/GUI-APPLE.md`, `docs/notenlogik.md`, `docs/funktionsumfang.md`, `.claude/skills/notenportal-ui/SKILL.md`, `.claude/skills/notenportal-dunkelmodus/SKILL.md`, `.claude/skills/notenportal-pruefwerkzeuge/SKILL.md`, `.claude/rules/oberflaeche.md`  
**Nach:** `R6-07b` · **Parallel mit:** `R6-08`, `R6-09`, `R6-10` · **Agent:** sonnet (high)

**Akzeptanz:**
- Jede Deckungs- und Weichzeichnerzahl in gui-konzept.md stimmt mit app.css überein (reviewer vergleicht per grep)
- grep -n '0.78' docs/gui-konzept.md ohne Regeltext '>= 0.78'; grep -rn 'motion-reduce' .claude/ docs/gui-konzept.md nur als 'ersetzt durch ruhig'
- grep -rn 'ß' docs/gui-konzept.md docs/auftrag/GUI-APPLE.md docs/notenlogik.md .claude/skills .claude/rules leer
- Jedes Apple-Zitat in GUI-APPLE.md steht wörtlich in docs/auftrag/messungen/r6-verstehen/inventare.json (pruefer)

### R6-12 – Schlussabnahme R6

**Ziel:** Workflow .claude/workflows/notenportal-r6-pruefung.js: kontrast (alle Modi), leistung.mjs mit den Argumenten der Basislinie für alle Seiten aus §4.2, Screenshots (1920/2560 dunkel, reduzierte Bewegung, reduzierte Transparenz, mehr Kontrast, hell als Stichprobe), Rundgang 4 Konten, Regel-Greps, php artisan test; Ergebnis als docs/auftrag/messungen/r6-leistung-nachher.md mit denselben Tabellen wie die Basislinie und Spalte Differenz.

**Dateien:** `.claude/workflows/notenportal-r6-pruefung.js`, `docs/auftrag/messungen/r6-leistung-nachher.md`  
**Nach:** `R6-08`, `R6-09`, `R6-10`, `R6-11` · **Parallel mit:** – · **Agent:** sonnet (high); pruefer fable (low)

**Akzeptanz:**
- Alle Grenzen aus §4.2 gehalten (Tabelle vorher/nachher/Grenze, jede Zeile 'ja')
- kontrast.mjs Exit 0; --minimum, --glanz=0.07, --stufen Exit 0
- Regel-Greps aus §8 alle leer
- rundgang.mjs Exit 0 für nina.huber, livia.gerber, michael.baumann, laura.frei; php artisan test grün
- pruefer ohne offenen Befund; ui-checker je Rollenbereich ohne offenen Befund

### R6-13 – Lichtbrechung am Kapselrand (optional)

**Ziel:** Nur nach Ja von David und wenn R6-12 Luft lässt. layouts/app.blade.php: unsichtbares SVG mit filter #np-brechung (feDisplacementMap). np.js: Erkennung per JS (Chromium-Engine und Probe-Element), nie per @supports (Firefox-Bug 1961378); setzt html[data-brechung]. app.css: nur np-glas-gruppe::before unter [data-brechung], nie unter ruhig, reduzierter Transparenz, mehr Kontrast oder Kontrast-Theme.

**Dateien:** `resources/css/app.css`, `resources/js/np.js`, `resources/views/layouts/app.blade.php`  
**Nach:** `R6-12` · **Parallel mit:** – · **Agent:** opus (xhigh)

**Akzeptanz:**
- leistung.mjs mit data-brechung: Tippen p95 und Scroll p95 <= Werte aus r6-leistung-nachher.md + T
- Ohne data-brechung (Erkennung negativ) sind Screenshots pixelgleich zu R6-12
- kontrast.mjs Exit 0; php artisan test grün

## 12 Herkunft, Jury und Gegenprüfung

- **Verstehen-Workflow** `notenportal-r6-verstehen` (Lauf `wf_a3d84e3c-723`, 9 Agents, 41 min): 6 Leser (Diagramme, Material, Daten, Seiten, Apple-Quellen mit 103 Fakten samt URL, Browser), Synthese, 2 Widerleger. Ergebnis: `docs/auftrag/messungen/r6-verstehen/{inventare,synthese,refutationen}.json`. Die Widerleger korrigierten u. a.: Inhalt liegt nur am Massstab (≥ 64rem, `data-navigation='seite'`) nie unter der Seitenleiste, als Schublade schon; der Zielrechner bekommt seine Kurve schon als JSON; `Leistung::$datum` ist nullable; der Umschalter «Wo stehe ich» wechselt den Zeitraum; Firefox 1995195 ist ein Duplikat von 1961378; Playwright `emulateMedia` kennt `reducedMotion` und `contrast` nativ.
- **Entwurfs-Workflow** `notenportal-r6-entwurf` (Lauf `wf_692f548b-1d2`, 9 Agents, 92 min, 1.83 Mio. Subagent-Tokens): drei unabhängige Gesamtentwürfe (Material, Bewegung, Daten; opus xhigh, je 25 Massnahmen und 12 Statistiken), drei Juroren (Apple-Treue, Technik, Mensch; opus high) – alle drei wählten «Material zuerst» (Gesamt 8 / 8 / 8.7; Bewegung 5 / 5 / 6.6; Daten 7 / 4 / 7.5). Regelverstösse der Jury: Zählwerk mit Schnitt im Blade (Bewegung), Pseudo-Element in `:is()` und Glas-auf-Glas über der Scrollkante (Daten, Bewegung), Tabellenalternative nur per JS (Daten) – alle in diesem Plan ausgeschlossen (§9, §4.1, G5, G17). Synthese opus xhigh. Ergebnis: `docs/auftrag/messungen/r6-entwurf/{entwuerfe,urteile,synthese,refutationen}.json`.
- **Gegenprüfung Quellen** (opus high, alle Apple-Seiten am 03.10.2026 neu abgerufen): 43 Zitate wörtlich bestätigt, 9 Zuschreibungen widerlegt (G3, G4, G10, G17, G21, B5, zwei §9-Begründungen, ein Anker) und 8 Lücken benannt – alle in §3, §4, §5, §9 eingearbeitet und als Repo-Entscheid bzw. «angelehnt» gekennzeichnet. Drei zusätzliche WWDC25/219-Sätze (†) hat die Hauptsitzung am Transkript geprüft.
- **Gegenprüfung Codebasis**: der Agent scheiterte am Nutzungslimit der Sitzung (nicht gelaufen). Ersatz durch die Hauptsitzung: 23 Zeilenverweise stichprobenartig geprüft, 19 stimmen; korrigiert wurden `Bericht::verteilung` (Methode existiert noch nicht, Histogramm liegt in `aggregate()`), die Fundstelle der Knopfregel (Kommentar vor `@utility np-knopf`, nicht `:702-705`), `ThemeKontrastTest` (Materialliste `:112`, Deckung `:134-157` stimmt), `app.css` «Schalterknopf `:982`» (Licht-/Schattenliterale liegen bei `:284-292`, `:339-342`, `:361-363`). Jede Scheibe liest ihre Dateien ohnehin selbst; die vollständige Code-Gegenprüfung läuft in R6-12 (Workflow-Resume `resumeFromRunId: wf_692f548b-1d2`).
