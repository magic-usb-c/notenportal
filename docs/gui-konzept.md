# GUI-Konzept Notenportal

Stand 11.09.2026. Ziel: ruhiger, klarer, eigenständiger – weg vom Einheitslook generierter Dashboards («Glas überall, Kachelreihe, Glow, Grossbuchstaben-Labels»), hin zu dem, was Linear, Stripe, GitHub oder Datawrapper tun: wenige, gut gewählte Flächen, Hierarchie über Typografie, Farbe nur mit Bedeutung.

Screenshots des Ist-Stands: `~/tools/out/konzept/{admin,bb,lernende}/*.png` (1366 px, hell/dunkel/390 px). Paletten und Kontraste: `~/tools/kontrast/` (`paletten.json` = Quelle, `kontrast.mjs` = WCAG/APCA-Prüfung mit Autokorrektur, `search.mjs` = Reihenfolge der Chartfarben gegen den dataviz-Validator, `gen-md.mjs` erzeugt Abschnitt d).

## a) Diagnose

| # | Befund | Beleg |
|---|---|---|
| D1 | **Alles gleich laut.** Jeder Block ist `.glass` mit Rand, Schatten, Blur und `rounded-2xl/3xl`. KPI-Kachel, Liste, Diagramm und leere Karte haben dasselbe Gewicht – das Auge findet keinen Einstieg. | `lernende/lernender.png`, `bb/berufsbildner.png`, `admin/admin.png` |
| D2 | **Farbe ohne Information.** Fast jede Zahl ist grün (4.8 / 4.9 / 4.8 / 4.8, alle «Letzte Noten»), dazu Text-Glow. Grün wird zur Tapete; Abweichungen fallen nicht mehr auf. `green-700` und `emerald-700` (gut/genügend) haben fast dieselbe Luminanz (0.159 / 0.141) und sind kaum unterscheidbar. | `lernende/lernender.png`, `admin/admin-lernende.png` |
| D3 | **Alarm am falschen Ort.** Eine 4.8 erscheint im Rechner riesig in Rot («Nicht erreichbar»), das Ziel im Dashboard rot – bei einer genügenden Lernenden. Rot bedeutet überall sonst «ungenügend». | `lernende/noten-rechner.png`, `lernende/lernender.png` |
| D4 | **Deko-KPIs.** «Kritisch 0», «Beobachten 0», «Offene Meldungen 0», «Neue Noten 179» (Importrest) beantworten keine Frage und doppeln die Filterleiste darunter. | `bb/berufsbildner.png`, `admin/admin.png` |
| D5 | **Leere Flächen.** «Wo es kippt – Nichts auffällig» und «Lehrende bald – Keine» belegen je ~500×200 px; «Zu tun» ist zu 70 % leer; Loch im Raster neben der BMS-Karte; «Ziele – Keine Ziele gesetzt» im Cockpit. | `bb/berufsbildner.png`, `bb/berufsbildner-lernende-7.png` |
| D6 | **Springende Seitenachse.** Kopfband `max-w-7xl` (Titel bei x=75), Inhalt `max-w-6xl` bzw. schmaler und zentriert (x=139/203/331). Titel und Inhalt stehen nie bündig. | `lernende/noten.png`, `lernende/pruefungen.png`, `admin/admin-betrieb.png` |
| D7 | **Diagramme ohne Aussage.** Verlauf: vier fast deckungsgleiche Linien, hohle Legendenkreise, rote gestrichelte Grenze. «Wo stehe ich»: fünf gleich grüne Balken ohne Schwelle. Admin: Säulen mit einem Import-Ausreisser und schräg gestellten KW-Labels; Lehrjahr-Diagramm mit vier Farben und Lücken. BB-Vergleich: drei gleiche Balken. | `lernende/lernender.png`, `admin/admin.png`, `bb/berufsbildner.png` |
| D8 | **Tabellen zu hoch und zu bunt.** Admin-Lernende: 89 px pro Zeile (Name, E-Mail, Badges, umbrechender Lehrberuf), 14 blaue Primärbuttons «Noten» auf einem Bildschirm, «n neu»-Badges überall. Filter als eigene Karte mit «Filtern»-Button. Positiv: Notenbericht ist dicht, rechtsbündig, ruhig – Referenz. | `admin/admin-lernende.png`, `admin/admin-berichte-noten.png` |
| D9 | **Typografie im Vorlagen-Stil.** 11-px-Labels in Versalien mit `tracking-widest` auf jeder Karte, dazu `font-extrabold`. Figtree ist nur in 400/500/600 geladen (`layouts/app.blade.php:45`) – `font-bold/extrabold` wird vom Browser synthetisch verfettet. | alle Screenshots |
| D10 | **Dunkelmodus.** Primärbutton: weisser Text auf `59 130 246` = **3.68:1** (unter 4.5:1). Fast schwarzes `--bg` (2 6 23) mit gesättigtem Neongrün «flimmert» (Material: gesättigte Farben auf Dunkel vibrieren). | `bb/berufsbildner-dunkel.png`, `lernende/lernender-dunkel.png` |
| D11 | **Cockpit mischt Lesen und Verwalten.** Profil, Konto, Betreuungs- und Track-Formular mit vollbreiten Primärbuttons auf derselben 2200-px-Seite wie Noten und Verlauf. | `bb/berufsbildner-lernende-7.png` |
| D12 | **Mobil.** Lernende-Dashboard 2539 px lang; drei Kategorie-Karten je volle Breite; Feedback-Button überdeckt Inhalt; Chart-Legenden brechen um. | `lernende/lernender-mobil.png` |
| D13 | **Agenda fehlt.** Schulnetz-Kalender werden synchronisiert (`CalendarEvent`: Lektion/Termin/Prüfung, `SyncCalendars`), aber nirgends angezeigt. «Prüfungen» ist ein Dauerformular links und eine Liste rechts. | `lernende/pruefungen.png` |
| D14 | Entwicklerhinweis in der Oberfläche: «Server aus .env: …» unter dem SMTP-Feld (verstösst gegen die UI-Regel). | `admin/admin-betrieb.png` |

## b) Prinzipien

1. **Eine Frage pro Block, sortiert nach Dringlichkeit.** Erst Überblick, dann Zoomen/Filtern, Details auf Anfrage – Shneiderman, «The Eyes Have It» (1996): <https://hci.stanford.edu/courses/cs448b/papers/shneiderman96eyes.pdf>. Ein Dashboard zeigt das Wichtigste «at a glance» auf einem Bildschirm; Deko und Widgets ohne Kontext sind Fehler Nr. 1 – Stephen Few, *Common Pitfalls in Dashboard Design*: <https://www.perceptualedge.com/articles/Whitepapers/Common_Pitfalls.pdf>. Blöcke ohne Inhalt verschwinden, statt «Keine» zu melden.
2. **Farbe ist Information.** Grundzustand neutral; Farbe nur für Abweichung (knapp/ungenügend), Status und die eine Primäraktion. «Grey is the most important color in data visualization» – Datawrapper: <https://www.datawrapper.de/blog/colors-for-data-vis-style-guides>. Linear hat das UI 2025 gezielt beruhigt (gedimmte Navigation, weichere Ränder, weniger Icons, weniger Sättigung): <https://linear.app/now/behind-the-latest-design-refresh>.
3. **Glas nur für die schwebende Ebene.** Apple führt Liquid Glass als «distinct functional layer that sits above apps» für Controls und Navigation ein: <https://www.apple.com/newsroom/2025/06/apple-introduces-a-delightful-and-elegant-new-software-design/>. NN/g belegt, was passiert, wenn Transparenz auf Inhalt trifft: «pretty from a distance, but frustrating» – <https://www.nngroup.com/articles/liquid-glass/>. Also: Glas für Navigation, Befehlspalette, Menüs, Toasts; Inhalt auf festen Flächen.
4. **Hierarchie über Typografie und Raum, nicht über Rahmen.** Eine Heldenzahl pro Seite, Gruppierung durch Abstand im 4/8-px-Raster (<https://www.designsystems.com/space-grids-and-layouts/>), Rahmen nur, wo sie trennen.
5. **Zahlen sind das Produkt.** Rechtsbündig und `tabular-nums` in Tabellen (MDN: <https://developer.mozilla.org/en-US/docs/Web/CSS/font-variant-numeric>; GitHub Primer: «Right-aligned columns are used for numbers» – <https://primer.style/product/components/data-table/>). Jede Zahl mit Kontext: Schwelle, Ziel, Verlauf – Bullet Graph (Few, <https://en.wikipedia.org/wiki/Bullet_graph>) und Sparkline mit Normalbereich (Tufte, <https://www.edwardtufte.com/notebook/sparkline-theory-and-practice-edward-tufte/>).
6. **Progressive Disclosure, höchstens zwei Ebenen.** Standardansicht schlank, Seltenes hinter Tab, Drawer oder «Mehr» – NN/g: <https://www.nngroup.com/articles/progressive-disclosure/>. Leerzustände erklären den Zustand und bieten den nächsten Schritt an – <https://www.nngroup.com/articles/empty-state-interface-design/>.
7. **Zugänglich per Konstruktion.** Farben in OKLCH gebaut (gleiche Helligkeit = gleiche Wirkung über alle Farbtöne; <https://evilmartians.com/chronicles/oklch-in-css-why-quit-rgb-hsl>; Stripe: <https://stripe.com/blog/accessible-color-systems>), Kontraste gerechnet statt geschätzt (WCAG 1.4.3: 4.5:1 Text, 3:1 ab 24 px bzw. 18.5 px fett – <https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html>; APCA Lc ≥ 75 für Fliesstext als Zusatzblick – <https://git.apcacontrast.com/documentation/APCA_in_a_Nutshell.html>), nie nur Farbe, Bewegung reduzierbar.

## c) Token-System

### Typografie
Schrift: **Inter Variable**, selbst gehostet (`@fontsource-variable/inter`, kein externer CDN-Aufruf mehr). Für UI gebaut, stufenlos 100–900, `tnum`, `case`, `cv11` (<https://rsms.me/inter/>). Alternative mit mehr Charakter: Geist (OFL, <https://vercel.com/font>). Figtree fällt weg (D9).

Die Skala war ursprünglich an 1.2 (kleine Terz) angelehnt. Seit dem 28.09.2026 trägt sie
stattdessen die **Text Styles der Apple Human Interface Guidelines** (Abschnitt
«Specifications», iOS/iPadOS Dynamic Type, Stufe «Large (default)»), weil das eine über
Jahre an echten Geräten geprüfte Lesbarkeitsstaffel ist statt einer rein rechnerischen.
Das Tracking stammt aus Apples macOS-Tabelle (Werte in 1/1000 em). Die Klassennamen sind
unverändert geblieben, es musste keine View umgeschrieben werden.

| Token | px / Zeile | Apple Text Style | Gewicht | Einsatz |
|---|---|---|---|---|
| `text-2xs` | 12 / 16 | Caption 1 | 500 | Tabellenkopf, Achsen, Meta |
| `text-xs` | 13 / 18 | Footnote | 400/500 | Hilfetext, Sekundärzeile |
| `text-sm` | 15 / 20 | Subhead | 400 | **UI-Grundschrift**, Tabellen, Buttons (500) |
| `text-base` | 17 / 26 | Body | 400 | Formulare mobil, Fliesstext |
| `text-lg` | 20 / 25 | Title 3 | 600 | Kartentitel gross, Drawer-Titel |
| `text-xl` | 22 / 28 | Title 2 | 600 | Seitentitel (h1) |
| `text-2xl` | 28 / 34 | Title 1 | 600 | Kennzahl in Statuszeile |
| `text-display` | 48 / 1 | – | 600 | eine Heldenzahl pro Seite, proportionale Ziffern |

Zwei bewusste Abweichungen von Apple, beide im Stylesheet begründet:
- `text-base` behält 26 px Zeilenhöhe statt Apples 22. Apples 17/22 ist für kurze Zeilen
  auf Gerätebreite gedacht; im Browser laufen Absätze über die volle Kartenbreite.
- Das Tracking ist bis 22 px von Apple übernommen, darüber eigene Werte: Apples Tabelle
  gilt für SF Pro mit optischen Graden, Inter hat andere Metriken, und Apples positive
  Werte ab 24 px liessen es locker wirken.

Nicht übernommen wurden **SF Pro** und **SF Symbols**: Apples Lizenz deckt Apps für
Apple-Plattformen ab, nicht eine Web-Anwendung. Es bleibt bei Inter und eigenen Icons.
Ebenso wenig Apples Systemfarben – Apple gibt sie nur als Farbfelder aus und schreibt
ausdrücklich «Avoid hard-coding system color values … The actual color values may
fluctuate from release to release». Die zwölf gerechneten Themes bleiben also; von Apple
kommt die *Struktur* (abgestufte Label- und Füllebenen), nicht der Wert.

Regeln: keine Versalien-Labels mit `tracking-widest` mehr. Labels in Satzschreibung, `text-sm font-medium`. 700 nur für die Heldenzahl, sonst 400/500/600. `tabular-nums` nur, wo Zahlen untereinander stehen – nicht auf der Heldenzahl (dataviz-Skill, Anti-Patterns).

### Abstände, Breiten
4-px-Basis: 1·2·3·4·5·6·8·10·12·16 (= 4…64 px). Karteninnen 16/20 (`p-4`/`p-5`), zwischen Karten 16 (`gap-4`), zwischen Abschnitten 32–40, Seitenrand 16/24/32. **Ein Container für alle Seiten:** `np-seite mx-auto px-4 sm:px-6 lg:px-8` (Utility in `app.css`), Seitenkopf im selben Container wie der Inhalt. Der Container füllt das Fenster und ist bei 2048 px gedeckelt (#13; vorher fix 1280 px, auf einem 2560-px-Schirm blieb die Hälfte ungenutzt). Keine Zwischenstufen – die wären beim Ziehen des Fensters ein sichtbarer Sprung. Lese- und Formularseiten links bündig mit `max-w-3xl` statt zentriert (behebt D6).

### Radien
`rounded-md` 6 px (Badges, kleine Controls) · `rounded-lg` 8 px (Buttons, Inputs, Segmente) · `rounded-xl` 12 px (Karten, Tabellencontainer) · `rounded-2xl` 16 px (Drawer, Modal, Palette) · `rounded-full` (Avatar, Statuspunkt). `rounded-3xl` entfällt. Verschachtelt: innen = aussen − Polster.

### Elevation
| Stufe | Hell | Dunkel | Einsatz |
|---|---|---|---|
| E0 flach | `bg-card border border-border`, kein Schatten | `card` heller als `bg` | alle Inhaltskarten |
| E1 gehoben | `shadow-[0_1px_2px_rgb(0_0_0/0.05)]` | `surface-2` | Hover klickbarer Zeilen/Karten, Sticky-Tabellenkopf |
| E2 schwebend | `shadow-[0_8px_24px_-8px_rgb(0_0_0/0.18)]` | `surface-2` + Rand `border-strong/40` | Dropdown, Popover, Toast |
| E3 Dialog | `shadow-[0_24px_48px_-12px_rgb(0_0_0/0.25)]` | `surface-2` + Scrim | Drawer, Modal, Palette |

Dunkel wird Tiefe über hellere Flächen gezeigt, nicht über Schatten; kein reines Schwarz (Material Dark Theme, <https://m2.material.io/design/color/dark-theme.html>).

### Glas-Stufen
| Stufe | Rezept | Wo | Nie |
|---|---|---|---|
| G0 | kein Glas | Karten, Tabellen, Formulare, Diagramme | – |
| G1 Leiste | `rgb(var(--card-rgb)/0.78)` + `blur(16px) saturate(140%)`, Unterkante `border` | Hauptnavigation, Sticky-Toolbar | über Text-Inhalt ohne Scrollbewegung |
| G2 Overlay | `rgb(var(--card-rgb)/0.88)` + `blur(24px)`, Rand `border-strong/30` | Befehlspalette, Menüs, Toasts, Popover | grossflächige Panels |
| Scrim | `rgb(var(--bg-rgb)/0.6)` + `blur(2px)` | hinter Drawer/Modal | – |

Deckung mindestens 0.78, damit Text die Token-Kontraste hält. `@supports not (backdrop-filter: blur(1px))` und `@media (prefers-reduced-transparency: reduce)` → volle Deckung. Entfallen: `accent-glow`, `np-glow-*`, `np-text-glow-*`, `glass-lift`, `np-card-lift`, der Radial-Gradient auf `body`, `np-btn-primary`-Scale.

### Motion
Dauer: 100 ms Rückmeldung (Hover, Druck) · 150 ms klein (Menü, Tooltip, Segment) · 200 ms mittel (Akkordeon, Drawer) · 300 ms gross (Modal, Palette). NN/g: 100–400 ms, über 500 ms wirkt es wie Verzögerung (<https://www.nngroup.com/articles/animation-duration/>); Material 3: klein 100–200, gross 300–500 ms (<https://m3.material.io/styles/motion/easing-and-duration/applying-easing-and-duration>). Easing: Eintritt `cubic-bezier(0.2,0,0,1)`, Austritt `cubic-bezier(0.3,0,1,1)`. Keine Scale-Effekte auf Buttons. Die globale `transition` auf `*` entfällt (verursacht Farbwischen beim Themewechsel), Transitions nur pro Komponente. `prefers-reduced-motion`: Bewegung durch Überblendung ersetzen, nicht alles abschalten (MDN: <https://developer.mozilla.org/en-US/docs/Web/CSS/@media/prefers-reduced-motion>).

## d) Themes

Einrasten in `resources/css/theme.css`: `:root, [data-theme="gletscher"] { … }` und `.dark, [data-theme="gletscher"].dark { … }`, weitere Themes als `[data-theme="…"]` / `[data-theme="…"].dark`. Werte bleiben RGB-Tripel (`--bg: 245 247 249;`), damit `rgb(var(--x) / a)`, alle `-rgb`-Aliase und Chart.js (`tokenFarbe`) unverändert funktionieren; OKLCH steht als Kommentar daneben und ist die Quelle. Bestehend: `--bg --card --text --muted --border --input --accent --ring --note-* --chart-1…6`. **Neu:** `--surface-2` (Tabellenkopf, Hover, Segment-Grund), `--border-strong` (Eingabefeld-Rand, ≥ 3:1 nach WCAG 1.4.11), `--accent-contrast` (Text auf Accent – ersetzt `text-white`), `--accent-text` (Links, aktive Navigation). In `@theme inline` als `--color-surface-2`, `--color-border-strong`, `--color-accent-contrast`, `--color-accent-text`, `--color-note-gut` … ergänzen.

Wahl: Betrieb wählt das Theme (Einstellung in der DB, Seite «Betrieb» – betriebsspezifisch). Benutzer wählen zusätzlich, ab Paket «Persönliche Darstellung» (11.09.2026), ein eigenes Theme (inkl. «Kontrast», das damit keine separate Checkbox mehr ist, sondern ein Theme wie jedes andere – sticht das alte `kontrast`-Flag, das weiterhin als Fallback für Altdaten/den Schema-Guard existiert), eine persönliche Akzentfarbe, eine Schriftgrösse und «Bewegungen reduzieren»; siehe `App\Support\Darstellung` und Profil → Darstellung. Das Muster «Theme aus wenigen Grundwerten ableiten» nutzt auch Linear (LCH, <https://linear.app/now/how-we-redesigned-the-linear-ui>); Tailwind 4 selbst arbeitet in OKLCH (<https://tailwindcss.com/blog/tailwindcss-v4>). Die Stufen folgen der Radix-Logik (App-Grund → Komponenten-Grund → Rand → Solid → Text; <https://www.radix-ui.com/colors/docs/palette-composition/understanding-the-scale>).

**Dichte** (Paket «Mehr persönliche Konfiguration», 11.09.2026): normal/kompakt als `[data-dichte="kompakt"]` am `<html>` (nur gesetzt, wenn nicht Standard, wie `data-schrift`), Overrides in `resources/css/app.css` in `@layer utilities` (Tailwind-Utilities liegen selbst in diesem Layer – ein Override in `@layer base` würde nie greifen, unabhängig von der Spezifität). Wenige gemeinsame Selektoren statt Änderungen an einzelnen Views: `tr.h-11`/`td.h-11` (Tabellenzeile 44px → 34px), `th.h-9` (36px → 32px), `.p-5`/`.p-4`/`.px-5`/`.pb-5`/`.py-2` (Karten-Padding) und `.gap-4`/`.gap-5` (Abstand zwischen Karten). Touch-Ziele (`h-9`/`h-11`-Knöpfe) bleiben ≥ 40px, da nur Tabellenzeilen/-köpfe und Innenabstände verkleinert werden, keine interaktiven Höhen. Dashboard-Karten (`App\Support\DashboardKarten`, stabile Schlüssel je Rolle) lassen sich im Profil-Abschnitt «Übersicht» einzeln ausblenden (mind. eine Karte bleibt sichtbar); ausgeblendete Karten werden serverseitig nicht gerendert (`$sichtbar` je Dashboard-Controller), ihre Daten aber weiterhin berechnet (`App\Services\Uebersicht` liefert alles in einem Rutsch je Rolle).

| Theme | Idee |
|---|---|
| **Gletscher** (Standard) | Weiterentwicklung des heutigen Blau: kühles, kaum getöntes Grau, Accent etwas tiefer (`oklch 0.53 0.2 263`), Dunkel ohne Schwarz, Accent-Button dunkel beschriftet. |
| **Sandstein** | Warmes Papiergrau, Accent Petrol. Wirkt wie ein gedrucktes Zeugnis, ruhig und erwachsen; gut für Betriebe mit warmem CI. |
| **Pflaume** | Neutrales Grau mit Violett-Stich, Accent Violett. Eigenständig, jugendlich ohne verspielt zu sein; kollidiert mit keiner Notenfarbe. |
| **Graphit** | Monochrom: Accent = Tinte (schwarz/weiss), Fokusring blau. Farbe bleibt ausschliesslich den Noten und Diagrammen – maximaler Fokus auf Daten (Vercel-Prinzip). Links hier immer unterstrichen. |
| **Wald** | Grün/Tanne, gedeckt. Ruhig-natürlich, gute Unterscheidung von den Notenfarben (`gut` bleibt eigenständig grün). |
| **Abendrot** | Koralle/warm. Wärmster der Accent-Töne, ohne ins Rot der Notenfarbe `ungenuegend` zu kippen. |
| **Papier** | Sepia, warmes Papier, bewusst gedämpft für lange Lesesitzungen. |
| **Mitternacht** | Indigo; Dunkelmodus mit echtem Schwarz-nahem Grund (OLED-tauglich). |
| **Kontrast** (AAA) | Ziel 7:1 für allen Text inkl. `muted` und Notenfarben, kräftige Ränder, Fokusring = Accent. Für Sehschwäche, Beamer, Sonnenlicht. Persönliches Theme, keine Betriebs-Option; `data-akzent` wird dabei nie gesetzt. |

**Persönliche Akzentfarbe** (Paket «Persönliche Darstellung», 11.09.2026): sieben Presets (Blau, Petrol, Grün, Violett, Magenta, Orange, Rot), je hell/dunkel als `[data-akzent="…"]` / `[data-akzent="…"].dark` **nach** allen Theme-Blöcken in `theme.css` (Spezifität beider Selektorformen ist gleich, 0,2,0 – die Reihenfolge entscheidet). Überschrieben werden `--accent`, `--accent-contrast`, `--accent-text`, `--ring`, `--chart-1`; jeder Preset erfüllt die Kontrastziele auf `bg`/`card`/`surface-2`/`input` aller Nicht-Kontrast-Themes (geprüft in `tests/Feature/ThemeKontrastTest.php`, analog zu `kontrast.mjs`). Beim Theme «Kontrast» bleibt `data-akzent` serverseitig ungesetzt, die Akzentwahl im Profil ist dort deaktiviert (aber gespeichert, falls später ein anderes Theme gewählt wird).

**Notenfarben** (alle Themes): `gut` grün, `genuegend` gedämpftes Schieferblau mit wenig Chroma (klar unterscheidbar von gut; seit Paket 8, massgebliche Werte aller Paletten in `resources/css/theme.css`, die Tabellen unten zeigen den Entwurf), `knapp` Bernstein, `ungenuegend` Rot. Einsatzregel: in Tabellen und Listen tragen nur `knapp` und `ungenuegend` Farbe; `gut` höchstens als Punkt, `genuegend` in `--text`. Badges: Text in Notenfarbe auf 14 % Tint derselben Farbe über `card` (gerechnet unten). `ungenuegend` zusätzlich mit Form (▼-Marker bzw. `underline decoration-2`), nie nur Farbe (WCAG 1.4.1).

**Chartfarben:** Reihenfolge ist Teil der Palette und wurde mit dem Validator des dataviz-Skills geprüft (Helligkeitsband, Chroma-Untergrenze, Farbfehlsichtigkeit benachbarter Paare, Normalsicht, Kontrast ≥ 3:1): alle zehn Varianten bestehen, einzig «Kontrast hell» hat bei Violett↔Magenta eine Protan-Warnung (ΔE 7.9) → dort Direktbeschriftung Pflicht. `chart-6` ist das Kontextgrau. Graphit: `chart-1` = Tinte als Heldenreihe.

Alle 240 WCAG-Paare ≥ Ziel (Text 4.5:1, Kontrast-Theme 7:1, UI/Grafik 3:1). Die Tabellen unten sind maschinell aus `~/tools/kontrast/ergebnis.json` erzeugt (Format: `L C H · R G B`).

#### Gletscher (Standard) – `data-theme="gletscher"`

| Token | hell OKLCH · RGB | dunkel OKLCH · RGB |
|---|---|---|
| bg | 0.975 0.004 255 · 245 247 249 | 0.17 0.015 262 · 12 15 22 |
| card | 1 0 0 · 255 255 255 | 0.215 0.018 262 · 21 26 34 |
| surface-2 | 0.957 0.007 255 · 238 241 246 | 0.26 0.02 262 · 31 36 46 |
| input | 1 0 0 · 255 255 255 | 0.19 0.016 262 · 16 20 27 |
| text | 0.21 0.03 262 · 17 24 38 | 0.94 0.008 255 · 232 235 241 |
| muted | 0.49 0.025 258 · 88 97 111 | 0.72 0.02 255 · 156 165 177 |
| border | 0.915 0.008 255 · 223 227 232 | 0.31 0.02 262 · 43 49 59 |
| border-strong | 0.66 0.015 255 · 140 147 155 | 0.51 0.02 262 · 96 102 114 |
| accent | 0.53 0.2 263 · 40 96 221 | 0.7 0.15 255 · 89 160 249 |
| accent-contrast | 1 0 0 · 255 255 255 | 0.17 0.015 262 · 12 15 22 |
| accent-text | 0.5 0.2 263 · 31 87 211 | 0.74 0.136 255 · 109 173 255 |
| ring | 0.6 0.19 260 · 51 122 239 | 0.72 0.148 255 · 96 167 255 |
| note-gut | 0.5 0.13 150 · 19 119 56 | 0.78 0.15 150 · 103 210 131 |
| note-genuegend | 0.5 0.06 200 · 53 110 113 | 0.8 0.07 200 · 134 204 207 |
| note-knapp | 0.52 0.116 65 · 150 88 2 | 0.83 0.13 80 · 243 189 92 |
| note-ungenuegend | 0.52 0.19 27 · 190 35 35 | 0.72 0.17 25 · 253 115 109 |
| chart-1 | 0.53 0.2 263 · 40 96 221 | 0.66 0.16 255 · 70 147 241 |
| chart-2 | 0.64 0.16 50 · 214 105 25 | 0.66 0.16 50 · 221 111 35 |
| chart-3 | 0.6 0.104 185 · 11 148 136 | 0.66 0.11 185 · 32 168 155 |
| chart-4 | 0.5 0.17 295 · 109 71 184 | 0.62 0.16 295 · 143 110 219 |
| chart-5 | 0.58 0.19 350 · 198 60 138 | 0.64 0.18 350 · 216 85 155 |
| chart-6 | 0.6 0.02 255 · 120 129 140 | 0.62 0.02 255 · 126 135 146 |

Kontrast WCAG 2.x (:1) hell: text/bg 16.54 · muted/bg 5.83 · muted/card 6.26 · muted/surface-2 5.53 · accent-contrast/accent 5.52 · accent-text/card 6.25 · Noten/card 5.64/5.8/5.69/6.08 · Noten/Badge-Tint 4.62/4.79/4.68/4.82 · border-strong/card 3.11 · ring/bg 3.78 · chart/card min 3.56  
dunkel: text/bg 16.05 · muted/bg 7.7 · muted/card 7.01 · muted/surface-2 6.24 · accent-contrast/accent 7.14 · accent-text/card 7.54 · Noten/card 9.25/9.6/10.18/6.53 · Noten/Badge-Tint 6.97/7.16/7.56/5.35 · border-strong/card 3.03 · ring/bg 7.73 · chart/card min 4.51
Vom Skript korrigiert: dunkel border-strong: L 0.500 -> 0.510 (Ziel 3:1 auf card)

#### Sandstein – `data-theme="sandstein"`

| Token | hell OKLCH · RGB | dunkel OKLCH · RGB |
|---|---|---|
| bg | 0.97 0.008 80 · 248 245 239 | 0.18 0.008 70 · 20 17 14 |
| card | 0.995 0.003 85 · 254 253 251 | 0.225 0.009 70 · 31 27 23 |
| surface-2 | 0.945 0.01 80 · 240 236 229 | 0.27 0.01 70 · 42 38 33 |
| input | 0.995 0.003 85 · 254 253 251 | 0.2 0.008 70 · 24 21 18 |
| text | 0.24 0.015 60 · 37 30 24 | 0.93 0.01 85 · 235 231 224 |
| muted | 0.5 0.02 65 · 108 97 88 | 0.72 0.015 80 · 170 164 154 |
| border | 0.9 0.012 80 · 226 221 213 | 0.32 0.01 70 · 54 50 45 |
| border-strong | 0.65 0.015 70 · 149 142 134 | 0.515 0.012 70 · 108 102 96 |
| accent | 0.47 0.083 215 · 0 102 120 | 0.74 0.09 205 · 93 187 198 |
| accent-contrast | 1 0 0 · 255 255 255 | 0.18 0.008 70 · 20 17 14 |
| accent-text | 0.46 0.081 215 · 1 99 116 | 0.77 0.09 205 · 103 197 207 |
| ring | 0.56 0.098 215 · 5 130 152 | 0.76 0.09 205 · 100 194 204 |
| note-gut | 0.5 0.12 145 · 47 116 52 | 0.78 0.14 145 · 122 207 126 |
| note-genuegend | 0.5 0.05 230 · 69 105 122 | 0.8 0.05 230 · 157 196 216 |
| note-knapp | 0.52 0.11 70 · 145 92 8 | 0.83 0.12 80 · 240 190 103 |
| note-ungenuegend | 0.52 0.18 30 · 186 45 31 | 0.73 0.16 30 · 252 124 105 |
| chart-1 | 0.58 0.102 215 · 3 137 160 | 0.66 0.1 210 · 51 163 180 |
| chart-2 | 0.64 0.15 55 · 207 111 25 | 0.66 0.15 55 · 214 117 35 |
| chart-3 | 0.55 0.14 330 · 158 79 152 | 0.64 0.14 330 · 187 106 180 |
| chart-4 | 0.62 0.12 110 · 139 140 39 | 0.66 0.12 110 · 151 152 54 |
| chart-5 | 0.5 0.13 280 · 86 88 171 | 0.62 0.13 280 · 120 123 210 |
| chart-6 | 0.6 0.015 70 · 134 127 119 | 0.62 0.015 70 · 140 133 125 |

Kontrast WCAG 2.x (:1) hell: text/bg 15.11 · muted/bg 5.53 · muted/card 5.92 · muted/surface-2 5.11 · accent-contrast/accent 6.62 · accent-text/card 6.8 · Noten/card 5.63/5.81/5.53/5.93 · Noten/Badge-Tint 4.64/4.79/4.55/4.75 · border-strong/card 3.18 · ring/bg 4.15 · chart/card min 3.48  
dunkel: text/bg 15.26 · muted/bg 7.6 · muted/card 6.91 · muted/surface-2 6.07 · accent-contrast/accent 8.41 · accent-text/card 8.53 · Noten/card 9.01/9.22/9.99/6.7 · Noten/Badge-Tint 6.8/6.86/7.34/5.32 · border-strong/card 3.02 · ring/bg 9.08 · chart/card min 4.53
Vom Skript korrigiert: dunkel border-strong: L 0.510 -> 0.515 (Ziel 3:1 auf card)

#### Pflaume – `data-theme="pflaume"`

| Token | hell OKLCH · RGB | dunkel OKLCH · RGB |
|---|---|---|
| bg | 0.975 0.005 300 · 247 246 250 | 0.17 0.02 300 · 17 13 23 |
| card | 1 0 0 · 255 255 255 | 0.215 0.022 300 · 27 23 34 |
| surface-2 | 0.952 0.01 300 · 240 238 245 | 0.26 0.025 300 · 38 33 46 |
| input | 1 0 0 · 255 255 255 | 0.19 0.02 300 · 21 18 28 |
| text | 0.22 0.03 300 · 29 23 39 | 0.94 0.01 300 · 236 234 241 |
| muted | 0.5 0.03 300 · 102 96 114 | 0.73 0.025 300 · 170 164 182 |
| border | 0.915 0.012 300 · 228 225 234 | 0.31 0.025 300 · 50 46 59 |
| border-strong | 0.66 0.02 300 · 148 144 157 | 0.51 0.03 300 · 104 98 117 |
| accent | 0.5 0.19 298 · 115 63 191 | 0.74 0.13 300 · 184 151 240 |
| accent-contrast | 1 0 0 · 255 255 255 | 0.17 0.02 300 · 17 13 23 |
| accent-text | 0.48 0.19 298 · 110 56 184 | 0.77 0.12 300 · 192 162 245 |
| ring | 0.58 0.18 298 · 137 91 213 | 0.76 0.13 300 · 190 157 247 |
| note-gut | 0.5 0.13 150 · 19 119 56 | 0.78 0.15 150 · 103 210 131 |
| note-genuegend | 0.5 0.06 200 · 53 110 113 | 0.8 0.07 200 · 134 204 207 |
| note-knapp | 0.52 0.116 65 · 150 88 2 | 0.83 0.13 80 · 243 189 92 |
| note-ungenuegend | 0.52 0.19 27 · 190 35 35 | 0.72 0.17 25 · 253 115 109 |
| chart-1 | 0.5 0.19 298 · 115 63 191 | 0.66 0.15 300 · 161 122 223 |
| chart-2 | 0.64 0.15 60 · 204 114 0 | 0.66 0.11 190 · 24 167 161 |
| chart-3 | 0.6 0.102 190 · 10 147 142 | 0.66 0.15 60 · 211 120 18 |
| chart-4 | 0.52 0.146 250 · 3 107 185 | 0.64 0.18 0 · 222 83 136 |
| chart-5 | 0.58 0.19 0 · 205 57 118 | 0.64 0.13 250 · 71 144 216 |
| chart-6 | 0.6 0.02 300 · 130 126 139 | 0.62 0.02 300 · 136 132 145 |

Kontrast WCAG 2.x (:1) hell: text/bg 16.21 · muted/bg 5.61 · muted/card 6.03 · muted/surface-2 5.24 · accent-contrast/accent 6.58 · accent-text/card 7.19 · Noten/card 5.64/5.8/5.69/6.08 · Noten/Badge-Tint 4.62/4.79/4.68/4.82 · border-strong/card 3.12 · ring/bg 4.32 · chart/card min 3.51  
dunkel: text/bg 16.1 · muted/bg 7.95 · muted/card 7.29 · muted/surface-2 6.49 · accent-contrast/accent 7.98 · accent-text/card 8.16 · Noten/card 9.33/9.69/10.27/6.58 · Noten/Badge-Tint 7.11/7.3/7.66/5.32 · border-strong/card 3.01 · ring/bg 8.55 · chart/card min 4.78
Vom Skript korrigiert: dunkel border-strong: L 0.500 -> 0.510 (Ziel 3:1 auf card)

#### Graphit – `data-theme="graphit"`

| Token | hell OKLCH · RGB | dunkel OKLCH · RGB |
|---|---|---|
| bg | 0.985 0 0 · 250 250 250 | 0.16 0 0 · 13 13 13 |
| card | 1 0 0 · 255 255 255 | 0.205 0 0 · 23 23 23 |
| surface-2 | 0.962 0 0 · 242 242 242 | 0.25 0 0 · 34 34 34 |
| input | 1 0 0 · 255 255 255 | 0.18 0 0 · 18 18 18 |
| text | 0.2 0 0 · 22 22 22 | 0.95 0 0 · 238 238 238 |
| muted | 0.5 0 0 · 99 99 99 | 0.71 0 0 · 161 161 161 |
| border | 0.91 0 0 · 225 225 225 | 0.3 0 0 · 46 46 46 |
| border-strong | 0.66 0 0 · 146 146 146 | 0.505 0 0 · 101 101 101 |
| accent | 0.24 0 0 · 31 31 31 | 0.95 0 0 · 238 238 238 |
| accent-contrast | 1 0 0 · 255 255 255 | 0.18 0 0 · 18 18 18 |
| accent-text | 0.24 0 0 · 31 31 31 | 0.95 0 0 · 238 238 238 |
| ring | 0.55 0.18 258 · 31 109 216 | 0.7 0.15 255 · 89 160 249 |
| note-gut | 0.5 0.13 150 · 19 119 56 | 0.78 0.15 150 · 103 210 131 |
| note-genuegend | 0.5 0.06 200 · 53 110 113 | 0.8 0.07 200 · 134 204 207 |
| note-knapp | 0.52 0.116 65 · 150 88 2 | 0.83 0.13 80 · 243 189 92 |
| note-ungenuegend | 0.52 0.19 27 · 190 35 35 | 0.72 0.17 25 · 253 115 109 |
| chart-1 | 0.3 0 0 · 46 46 46 | 0.92 0 0 · 228 228 228 |
| chart-2 | 0.55 0.19 258 · 21 108 221 | 0.66 0.16 255 · 70 147 241 |
| chart-3 | 0.6 0.104 185 · 11 148 136 | 0.66 0.11 185 · 32 168 155 |
| chart-4 | 0.64 0.16 50 · 214 105 25 | 0.66 0.16 50 · 221 111 35 |
| chart-5 | 0.58 0.19 350 · 198 60 138 | 0.64 0.18 350 · 216 85 155 |
| chart-6 | 0.665 0 0 · 148 148 148 | 0.55 0 0 · 113 113 113 |

Kontrast WCAG 2.x (:1) hell: text/bg 17.34 · muted/bg 5.76 · muted/card 6.01 · muted/surface-2 5.37 · accent-contrast/accent 16.48 · accent-text/card 16.48 · Noten/card 5.64/5.8/5.69/6.08 · Noten/Badge-Tint 4.62/4.79/4.68/4.82 · border-strong/card 3.11 · ring/bg 4.75 · chart/card min 3.03  
dunkel: text/bg 16.75 · muted/bg 7.52 · muted/card 6.94 · muted/surface-2 6.16 · accent-contrast/accent 16.15 · accent-text/card 15.45 · Noten/card 9.5/9.86/10.45/6.7 · Noten/Badge-Tint 7.24/7.44/7.8/5.45 · border-strong/card 3.08 · ring/bg 7.24 · chart/card min 3.67
Vom Skript korrigiert: hell chart-6: L 0.700 -> 0.665 (Ziel 3:1 auf card); dunkel border-strong: L 0.500 -> 0.505 (Ziel 3:1 auf card)

#### Kontrast (AAA) – `data-theme="kontrast"`

| Token | hell OKLCH · RGB | dunkel OKLCH · RGB |
|---|---|---|
| bg | 1 0 0 · 255 255 255 | 0.13 0 0 · 7 7 7 |
| card | 1 0 0 · 255 255 255 | 0.17 0.01 262 · 13 15 20 |
| surface-2 | 0.955 0.005 262 · 238 240 244 | 0.22 0.012 262 · 24 27 32 |
| input | 1 0 0 · 255 255 255 | 0.13 0 0 · 7 7 7 |
| text | 0.15 0.02 262 · 7 11 20 | 0.98 0 0 · 248 248 248 |
| muted | 0.38 0.02 262 · 61 67 77 | 0.84 0.01 262 · 199 203 209 |
| border | 0.6 0.02 262 · 122 129 141 | 0.6 0.02 262 · 122 129 141 |
| border-strong | 0.5 0.02 262 · 93 100 111 | 0.7 0.02 262 · 152 159 171 |
| accent | 0.42 0.2 263 · 6 60 183 | 0.82 0.092 250 · 150 201 254 |
| accent-contrast | 1 0 0 · 255 255 255 | 0.13 0 0 · 7 7 7 |
| accent-text | 0.4 0.2 263 · 1 53 177 | 0.84 0.082 250 · 162 207 255 |
| ring | 0.42 0.2 263 · 6 60 183 | 0.85 0.076 250 · 168 210 254 |
| note-gut | 0.395 0.108 150 · 1 86 36 | 0.85 0.15 150 · 127 233 152 |
| note-genuegend | 0.4 0.05 200 · 34 80 82 | 0.86 0.06 200 · 163 221 224 |
| note-knapp | 0.415 0.096 60 · 113 61 2 | 0.88 0.13 85 · 255 209 107 |
| note-ungenuegend | 0.42 0.17 27 · 148 2 13 | 0.8 0.114 25 · 255 160 152 |
| chart-1 | 0.45 0.2 263 · 16 70 193 | 0.66 0.14 250 · 70 151 228 |
| chart-2 | 0.55 0.156 45 · 184 75 3 | 0.65 0.17 350 · 216 92 158 |
| chart-3 | 0.6 0.108 180 · 5 149 131 | 0.66 0.15 55 · 214 117 35 |
| chart-4 | 0.45 0.16 295 · 94 59 163 | 0.66 0.11 185 · 32 168 155 |
| chart-5 | 0.52 0.2 350 · 181 28 121 | 0.65 0.14 295 · 150 124 219 |
| chart-6 | 0.45 0.02 262 · 79 86 97 | 0.7 0.02 262 · 152 159 171 |

Kontrast WCAG 2.x (:1) hell: text/bg 19.68 · muted/bg 9.96 · muted/card 9.96 · muted/surface-2 8.73 · accent-contrast/accent 8.98 · accent-text/card 9.79 · Noten/card 8.91/8.98/8.86/9.24 · Noten/Badge-Tint 7.01/7.12/7.03/7.03 · border-strong/card 5.97 · ring/bg 8.98 · chart/card min 3.73  
dunkel: text/bg 18.97 · muted/bg 12.37 · muted/card 11.77 · muted/surface-2 10.6 · accent-contrast/accent 11.59 · accent-text/card 11.78 · Noten/card 12.79/12.76/13.31/9.8 · Noten/Badge-Tint 9.52/9.49/9.9/7.73 · border-strong/card 7.19 · ring/bg 12.77 · chart/card min 5.43
Vom Skript korrigiert: hell note-gut: L 0.400 -> 0.395 (Ziel 7:1 auf tint:note-gut); hell note-knapp: L 0.420 -> 0.415 (Ziel 7:1 auf tint:note-knapp)

## e) Seiten-Blueprints

Raster: 12 Spalten, `gap-4`, Container `np-seite` (füllt das Fenster, Deckel 2048 px, siehe «Abstände, Breiten»). Seitenkopf im Container: Titel links, Metazeile darunter, rechts höchstens eine Primär- und zwei Sekundäraktionen, Rest im «⋯»-Menü.

### Lernende – Übersicht (`/learner`)
```
Hallo Nina                                               [Prüfung planen] [+ Note]
Informatiker/in EFZ · 4. Lehrjahr · noch 323 Tage
┌─ Stand (8) ─────────────────────────────────┐ ┌─ Als Nächstes (4) ─────────┐
│ 4.8  Gesamtschnitt       ▁▂▃▄▆ Sparkline    │ │ ● 17 Noten mit Kommentar   │
│ [==========|====▌=====]  1 ─ knapp│genügend│gut ─ 6   Ziel ▏│ │ 14.9. Deutsch  in 3 T 100% │
│ Semester 26/27-1  5.8 ▲0.4                  │ │ 23.9. Englisch       100%  │
│ Fachunterricht 4.9 ▁▃▅ · ÜK 4.8 ▁▂▄ · BMS 4.8 ▁▃▆ │ │ 29.9. D210 …          60%  │
└─────────────────────────────────────────────┘ └────────────────────────────┘
┌─ Wo stehe ich (8) – schwächste zuerst ──────┐ ┌─ Ziele (4) ────────────────┐
│ Deutsch        ─────────●  5.5   │4.0       │ │ Gesamt ≥ 5.0   4.8 ▏       │
│ D210 …         ─────────●  5.25            │ │ höchstens 4.8 erreichbar   │
└─────────────────────────────────────────────┘ └────────────────────────────┘
┌─ Verlauf (8) ───────────────────────────────┐ ┌─ Letzte Noten (4) ─────────┐
```
- Stand: «Wie stehe ich insgesamt?» – eine Heldenzahl, Bullet Graph mit Grenzen aus den Einstellungen, Kategorien als eine Zeile mit Sparklines (ersetzt drei Karten). «Ungenügend 0» fliegt raus; ist die Zahl > 0, erscheint sie unter «Als Nächstes».
- Als Nächstes: «Was muss ich tun?» – «Zu tun» und «Nächste Prüfungen» vereint, Überfälliges oben. Lehrzeit-Balken wird Metazeile.
- Ziele: nicht erreichbare Ziele neutral mit Hinweis «höchstens 4.8», nicht rot (D3). Kein Ziel: Karte entfällt, Befehl in Palette/Rechner.
- Mobil: Stand → Als Nächstes → Wo stehe ich → Ziele → Verlauf → Letzte Noten; Verlauf und Letzte Noten zugeklappt (`<details>`).

### Lernende – Noten (`/grades`)
- Kopf: «Noten» + Semesterwechsler direkt neben dem Titel; Aktionen: `+ Note` (primär), Import, «⋯» (Drucken, CSV).
- Statuszeile statt vier Kacheln: `Semester 5.8 · Gesamt 4.8 · Fachunterricht 5.5 · BMS 5.9` (Zahl `text-2xl`, Label `text-xs muted`).
- Eine Werkzeugzeile: Segment «Semester | Zeugnisübersicht» links, Kategorie-Chips rechts.
- Liste als Tabelle je Kategorie (Zeile 48 px): Fach/Modul · Prüfungen (Anzahl + Balken offene Gewichtung) · Schnitt vor Rundung (`muted`, rechts) · Zeugnisnote (Badge, rechts) · Chevron. Aufklappen zeigt die Einzelnoten inline; Erfassen/Bearbeiten im Drawer rechts statt eigener Seite.

### Berufsbildner – Übersicht (`/trainer`)
Frage der Seite: «Wen muss ich heute anschauen?»
1. **Braucht Aufmerksamkeit** (12): nur Lernende mit Status rot/gelb oder neuen Noten, je eine Zeile mit Gründen als Text. Leer: eine Zeile «Alle 3 Lernenden im Plan» – keine Karte.
2. **Meine Lernenden** (12), dichte Tabelle: Status (Punkt + Wort) · Name / Kürzel · Lehrjahr · Verlauf (Sparkline mit Band genügend–6) · Semester (+Δ) · Gesamt · Neue Noten (Zahl als Link, kein Button) · Nächste Prüfung · «⋯». Filter-Segment trägt die Zählungen: «Alle 3 · Kritisch 0 · Beobachten 0 · Neue Noten 3». KPI-Kacheln entfallen.
3. **Nächste 14 Tage** (8) als Agenda nach Tag gruppiert; **Lehrende bald** (4) nur wenn nicht leer. «Im Vergleich» wandert als sortierbare Spalte in die Tabelle.

### Cockpit Lernende (`/trainer/learners/{id}`)
- Kopf: Name, Status-Pill mit Gründen im Tooltip, Metazeile Lehrberuf · Lehrjahr · Lehrende; Aktionen: `Noten ansehen (41 neu)` primär, `Drucken`, «⋯» (CSV, Bearbeiten, Rechner).
- Tabs (URL-fähig, `?tab=`): **Übersicht** · Noten · Dokumente · Rechner · **Profil & Betreuung**.
- Übersicht: links 8 – Stand (wie Lernende) und Zeugnisnoten-Heatmap als Hauptelement; rechts 4 – Hinweise/Gründe, Ziele, geplante Prüfungen, letzte Aktivität. Leere Karten entfallen.
- Profil & Betreuung: Profil, Konto, Betreuungen, Schul-Tracks (Formulare mit Sekundärbuttons, Speichern am Formularende) – raus aus der Leseansicht (D11).

### Agenda (ersetzt `/exams`, Navigation «Agenda»)
```
Agenda                                            [Liste|Monat]   [+ Prüfung planen]
Filter: (●Prüfungen) (●Schulnetz-Termine) (○Stundenplan)
Diese Woche
  Mo 14.09.  ● Deutsch – Prüfung            BMS · 100 %          Note erfassen
  Mi 16.09.  ◇ Schulnetz: Elternabend       Termin · Aula
Nächste Woche · KW 39
  Mi 23.09.  ● Englisch                     BMS · 100 %
  Mi 23.09.  ◆ Schulnetz: M122 Prüfung      erkannt → Modul 122  [übernehmen]
```
- Quellen: eigene Prüfungen (●), Schulnetz-Prüfungen (◆, mit Link auf erkannte `Pruefung` bzw. «übernehmen»), Schulnetz-Termine (◇), Lektionen standardmässig aus. Unterscheidung über Form **und** Wort, nicht nur Farbe.
- Liste (Standard) gruppiert nach Woche, Überfälliges als eigene Gruppe oben mit «Note erfassen». Monat: 7-Spalten-Raster, pro Tag max. drei Chips + «+2», Klick öffnet Tagesdrawer. Mobil nur Liste plus Wochenleiste.
- «Prüfung planen» öffnet einen Drawer (Formular nicht mehr dauerhaft sichtbar). BB-Sicht: dieselbe Agenda über alle betreuten Lernenden, Name als Spalte.

### Admin – Tabellen (`/admin/learners`, Benutzer, Stammdaten)
- Filterleiste einzeilig über der Tabelle: Suche · Lehrberuf · Lehrjahr · Berufsbildner als Dropdown-Chips, sofort wirksam (kein «Filtern»-Button), «Weitere Filter» (BMS, Warnung, Status) als Disclosure; rechts Trefferzahl und Export.
- Zeile 44 px (Umschalter «kompakt» 36 px, analog Primer condensed/normal/spacious und Carbon-Zeilenhöhen: <https://carbondesignsystem.com/components/data-table/usage/>): Name (E-Mail als Tooltip/Sekundärzeile nur bei Suche) · Lehrberuf-Kürzel (voller Name im `title`) · Lj · Berufsbildner · Noten · Letzte Note («vor 4 Tagen») · Ø gesamt · Status. Zahlen rechtsbündig, `tabular-nums`.
- Zeilenklick öffnet das Cockpit; Aktionen «Noten», «Profil» als Icon-/Textlinks, die bei Hover und Fokus erscheinen (mobil immer sichtbar). Sticky Kopf, Sortierung sichtbar.
- **Admin-Übersicht**: «Handlungsbedarf» als eine Liste (Einrichtungslücken, Sicherung älter als 2 Tage, offene Meldungen, kritische Lernende) oben; Kennzahlen als Statuszeile; darunter Berufsbildner-Last (7) und Erfassung 12 Wochen (5). «Gesamtschnitt nach Lehrjahr» wandert in den Notenbericht.

## f) Diagramme

| Auswertung | Frage | Typ | Schwellen | Anpassung | Barrierefreiheit |
|---|---|---|---|---|---|
| Stand (Lernende, Cockpit) | Wo stehe ich zu genügend/gut/Ziel? | Bullet Graph 1–6 (Few) | Bänder knapp/genügend/gut in Grautönen aus `einstellungen`, Zielmarke | Ebene: Gesamt/Semester | Satz im `aria-label` («4.8, Ziel 5.0, genügend ab 4.0») |
| Verlauf | Werde ich besser? | Linie je Semester, **eine** Reihe hervorgehoben (Accent 2.5 px), Rest `chart-6` 1.5 px | Linie «genügend 4.0» als Haarlinie mit Label, nicht rot gestrichelt | Ebene Gesamt/Kategorie/Fach; Achse «1–6 | Ausschnitt» | Direktlabels am Linienende statt Legende (Datawrapper, <https://www.datawrapper.de/blog/line-charts>); «Als Tabelle» |
| Wo stehe ich pro Fach | Wo bin ich am schwächsten? | Punkt-/Balkenzeilen ab 1, aufsteigend sortiert | senkrechte Linie genügend; Balken neutral, nur knapp/ungenügend farbig | Semester/Lehrzeit | Wert am Balkenende; Tabelle |
| Sparkline (Tabellen, Stand) | Tendenz? | Sparkline mit grauem Band genügend–6 (Tufte) | Band statt roter Linie | – | letzter Wert als Zahl daneben, `aria-label` mit Werten |
| BB «Meine Lernenden» | Wer weicht ab? | Dumbbell (Semester ↔ Gesamt) in der Tabelle oder Spaltensortierung | genügend-Linie | Sortierung | Zahlen in Spalten |
| Zeugnisnoten-Heatmap | Wo sind Lücken? | Tabelle mit Zellen | nur knapp/ungenügend getönt, gut dezent | Semester scrollbar, erste Spalte sticky | ist bereits Tabelle; ▼ bei ungenügend |
| Rechner | Welche Note brauche ich? | Treppenlinie «Ergebnis je Note» | Ziel als beschriftete Linie, benötigte Note als annotierter Punkt | Ziel ± | Ergebnis als Satz über dem Diagramm |
| Admin Erfassung | Wird regelmässig erfasst? | 12 Säulen, Median-Linie | – | 12/26 Wochen | jede 4. KW beschriften, nicht schräg; Import-Ausreisser annotiert |
| Notenbericht Verteilung | Wie verteilen sich Zeugnisnoten? | Histogramm in 0.5-Klassen | Klassen unter genügend in `note-ungenuegend`, Rest neutral | Zeitraum, Lehrberuf, BB (bestehende Filterzeile) | Tabelle |
| Jahrgänge | Gibt es schwache Jahrgänge? | Small Multiples je Lehrberuf, Punkt je Lehrjahr (statt 4-Farben-Gruppenbalken) | genügend-Linie | Zeitraum | Tabelle |

Umsetzung in `resources/js/charts.js`: Option `hervorheben` (Index), Plugin für Direktlabels und Schwellen-Label, Gitter als Vollhaarlinie `--border` (keine gestrichelten Gitter), Legende nur ab zwei Reihen und nur, wenn keine Direktlabels passen. Neue Komponente `x-diagramm` = Titel + Canvas + `<details>` «Als Tabelle» mit denselben Daten. Grenzwerte immer aus `$grenzen` (Einstellungen), nie im JS.

## g) Komponenten-Katalog (Tailwind 4)

```blade
{{-- Buttons: pro Ansicht genau eine Primäraktion --}}
primär:    inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast
           hover:bg-accent/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:opacity-50
sekundär:  … rounded-lg border border-border-strong/60 bg-card px-3.5 text-sm font-medium text-text hover:bg-surface-2
tertiär:   … rounded-lg px-2.5 text-sm text-muted hover:bg-surface-2 hover:text-text
gefährlich:… rounded-lg px-3 text-sm text-note-ungenuegend hover:bg-note-ungenuegend/10   (solid nur im Bestätigungsdialog)
Grössen:   h-8 (Tabelle) · h-9 (Standard) · h-11 (mobil, Formular-Abschluss)

{{-- Formularfeld --}}
<label for="x" class="text-sm font-medium text-text">Gewichtung <span class="text-note-ungenuegend">*</span></label>
<input id="x" class="mt-1.5 h-10 w-full rounded-lg border border-border-strong/70 bg-input px-3 text-sm text-text
       placeholder:text-muted focus:border-accent focus:ring-2 focus:ring-ring/30" aria-describedby="x-hilfe x-fehler">
<p id="x-hilfe" class="mt-1 text-xs text-muted">…</p>  <p id="x-fehler" class="mt-1 text-xs text-note-ungenuegend">…</p>

{{-- Segmented Control (role="radiogroup", Pfeiltasten) --}}
<div class="inline-flex rounded-lg bg-surface-2 p-0.5 text-sm">
  <button role="radio" aria-checked="true" class="h-8 rounded-md px-3 text-muted aria-checked:bg-card aria-checked:text-text aria-checked:shadow-xs">Semester</button>

{{-- Tabs (Seitenebene, role="tablist") --}}
<nav class="flex gap-6 border-b border-border text-sm">
  <a aria-current="page" class="-mb-px h-10 inline-flex items-center border-b-2 border-transparent text-muted aria-[current=page]:border-accent aria-[current=page]:text-text">Übersicht</a>

{{-- Status und Note --}}
Status:    inline-flex h-6 items-center gap-1.5 rounded-md px-2 text-xs font-medium bg-surface-2 text-text  + Punkt size-1.5 rounded-full bg-note-…
Note:      inline-flex h-6 min-w-11 justify-center rounded-md px-1.5 text-sm font-semibold tabular-nums bg-note-knapp/14 text-note-knapp

{{-- Karte (E0, kein Glas) --}}
<section class="rounded-xl border border-border bg-card">
  <header class="flex h-12 items-center justify-between px-5"><h2 class="text-sm font-semibold text-text">…</h2> …</header>
  <div class="px-5 pb-5">…</div>

{{-- Tabelle --}}
<div class="overflow-x-auto rounded-xl border border-border bg-card">
<table class="w-full text-sm tabular-nums">
  <th class="sticky top-0 h-9 bg-surface-2 px-3 text-left text-2xs font-medium text-muted">  (Zahlen: text-right)
  <tr class="group border-b border-border last:border-0 hover:bg-surface-2/60">
  <td class="h-11 px-3">  Aktionen: opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 max-md:opacity-100

{{-- Leerzustand: inline, eine Zeile; ganzseitig: Icon 24 + Satz + Aktion --}}
<p class="flex items-center gap-3 px-5 py-4 text-sm text-muted">Noch keine Noten <a class="text-accent-text underline-offset-2 hover:underline">Erste Note erfassen</a></p>

{{-- Toast (G2): Farbe nur im Icon, Text in --text; role="status" bzw. "alert", Pause bei Hover --}}
<div class="fixed bottom-4 right-4 z-50 flex items-center gap-3 rounded-xl px-4 py-3 text-sm text-text glass-overlay">

{{-- Drawer (Erfassen, Bearbeiten, Planen) und Modal (nur Bestätigung): Alpine Focus-Trap, Esc, Scrim --}}
<aside class="fixed inset-y-0 right-0 z-50 w-full border-l border-border bg-card shadow-2xl sm:w-[28rem]" role="dialog" aria-modal="true">

{{-- Seitenkopf <x-seitenkopf titel meta> mit Slot aktionen --}}
<div class="flex flex-wrap items-end justify-between gap-4 pb-6">
  <div><h1 class="text-xl font-semibold tracking-[-0.01em] text-text">…</h1><p class="mt-1 text-sm text-muted">…</p></div>
  <div class="flex items-center gap-2">{{ $aktionen }}</div>
```

Navigation: G1-Leiste 56 px; aktiver Punkt `text-text` + 2-px-Unterstrich in Accent statt gefüllter Pille; inaktive `text-muted`. Befehlspalette (G2) erhält die Gruppe «Aktionen» (Note erfassen, Prüfung planen, Lernende anlegen) und «Zuletzt besucht»; Tastenkürzel werden im Menü angezeigt – Palette als Abkürzung für Kundige, nicht als einziger Weg (<https://uxpatterns.dev/patterns/advanced/command-palette>). Feedback-Button wandert ins Benutzermenü bzw. die Palette (D12).

## h) Umsetzungsplan

Jedes Paket einzeln abschliessen (Skill `notenportal-blockabschluss`: Tests, Build, Eszett-Prüfung, 390 px, ui-checker, Screenshots hell/dunkel/mobil mit `shot.mjs`).

| # | Paket | Wirkung | Dateien |
|---|---|---|---|
| 1 | **Fundament**: Tokens Gletscher (neu: surface-2, border-strong, accent-contrast, accent-text), `data-theme`, Inter Variable selbst gehostet, Typo-Skala, Glow/Gradient/globale Transition raus, Glas-Stufen G1/G2 | ganze App sofort ruhiger, Dunkel-Kontrast behoben | `resources/css/theme.css`, `resources/css/app.css`, `resources/views/layouts/app.blade.php`, `package.json`, `.claude/skills/notenportal-ui/SKILL.md` |
| 2 | **Flächen und Kopf**: Karte/Kachel/Status/Note ohne Glas, `x-seitenkopf`, ein Container, Navigation, Toast, Dropdown | Hierarchie und bündige Achse auf allen Seiten | `components/{karte,kachel,status,note,modal,dropdown}.blade.php`, neu `components/seitenkopf.blade.php`, `layouts/navigation.blade.php`, `app/Support/NotenSkala.php` |
| 3 | **Lernende**: Übersicht nach Blueprint, `x-bullet`, Sparkline mit Band, Notenliste als Tabelle mit Inline-Details, Drawer für Erfassen | grösste Nutzergruppe | `dashboards/lernender.blade.php`, `lernender/noten/*`, `components/sparkline.blade.php`, neu `components/bullet.blade.php`, `components/drawer.blade.php` |
| 4 | **Berufsbildner**: Übersicht «Braucht Aufmerksamkeit» + Tabelle, Cockpit mit Tabs | tägliche Arbeit der BB | `dashboards/berufsbildner.blade.php`, `berufsbildner/lernende/show.blade.php`, `components/heatmap.blade.php` |
| 5 | **Tabellen & Admin**: Filterleiste, 44-px-Zeilen, Zeilenaktionen, Handlungsbedarf-Liste, Entwicklerhinweis entfernen | Admin-Seiten dicht und ruhig | `admin/lernende/index.blade.php`, `admin/benutzer/*`, `dashboards/admin.blade.php`, `admin/berichte/noten.blade.php`, `admin/betrieb*`, neu `components/filterleiste.blade.php` |
| 6 | **Diagramme**: Hervorheben, Direktlabels, Schwellen-Label, Tabellenalternative, Histogramm, Small Multiples | Diagramme beantworten Fragen | `resources/js/charts.js`, `resources/js/np.js`, neu `components/diagramm.blade.php` |
| 7 | **Agenda mit Schulnetz**: Liste/Monat, Drawer «Prüfung planen», Übernahme erkannter Schulnetz-Prüfungen | neue Funktion, braucht Controller | neu `lernender/agenda.blade.php`, Controller für `CalendarEvent`, `routes/web.php`, `app/Support/Navigation.php` |
| 8 | **Themes wählbar**: Sandstein, Pflaume, Graphit, Kontrast; Wahl auf «Betrieb», Kontrast als Benutzeroption | Individualisierung, Barrierefreiheit | `theme.css`, `admin/betrieb*`, Profil-Darstellung, Einstellungen (bei Schemaänderung Skill `notenportal-migration`) |

Reihenfolge begründet: 1–2 ändern das Gesamtbild mit wenigen Dateien und schaffen die Bausteine; 3–5 bauen die Rollen auf diesen Bausteinen um; 6 profitiert von den neuen Layouts; 7 ist neue Funktion; 8 ist nach 1 nur noch Werte-Pflege, der Wert entsteht erst, wenn die Oberfläche selbst ruhig ist.
