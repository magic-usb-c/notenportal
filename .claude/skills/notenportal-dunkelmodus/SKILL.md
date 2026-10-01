---
name: notenportal-dunkelmodus
description: Dunkelmodus nach Apple Human Interface Guidelines für den Desktop-Browser (1920–2560 px). Regeln zu Farbe, Tiefe, Materialien, Typografie und Layout mit Primärquelle, dazu die Prüfliste je Seite. Laden vor jeder Gestaltungsarbeit und vor jeder Bildprüfung.
---

# Dunkelmodus nach Apple HIG

Massstab der Oberfläche: **Desktop-Browser, 1920×1080 bis 2560×1440, Dunkelmodus.** Hell und schmale
Fenster dürfen nicht brechen, bekommen aber keine eigene Gestaltungsarbeit (`CLAUDE.md`). Die
Tokens, Klassen und Muster stehen im Skill `notenportal-ui`; dieser Skill sagt, *was im Dunkeln
richtig ist* und *woran man es prüft*. Jede Regel nennt ihre Quelle. Was nicht aus der HIG stammt,
ist als Übertragung gekennzeichnet.

Quellen lesen: die HTML-Seiten von `developer.apple.com` liefern nur eine JavaScript-Hülle. Der
Inhalt derselben Seite liegt unter
`https://developer.apple.com/tutorials/data/design/human-interface-guidelines/<seite>.json`
(`primaryContentSections[].content[]`), per `curl` abrufbar. Stand der gelesenen Seiten: 01.10.2026.

## 1. Farbe im Dunkeln

Quelle: [Dark Mode](https://developer.apple.com/design/human-interface-guidelines/dark-mode) und
[Color](https://developer.apple.com/design/human-interface-guidelines/color).

- **Dunklere Hintergründe, hellere Vordergründe – keine Inversion.** Die Dunkelpalette ist keine
  Umkehrung der Hellpalette; viele Farben werden invertiert, manche nicht. Darum eigene Dunkelwerte
  je Token in `theme.css`, nie «hell umgedreht».
- **Nur adaptive, semantische Farben.** Hartcodierte Werte oder Farben, die sich nicht anpassen,
  sind verboten. Im Portal: ausschliesslich die Token-Klassen aus `notenportal-ui` §2.
- **Kontrast mindestens 4.5:1; bei eigenen Vorder-/Hintergrundfarben 7:1 anstreben, besonders bei
  kleinem Text.** Betrifft `text-text` und `text-muted` auf `bg-bg`, `bg-card`, `bg-surface-2`,
  `bg-input` und auf den Materialien. `text-faint`/`text-ghost` sind bewusst darunter und darum nie
  für lesbaren Text.
- **Weisse Bildhintergründe abmildern.** Ein Bild mit weissem Grund leuchtet im Dunkeln; leicht
  abdunkeln oder den Hintergrund entfernen. Betrifft Logos, Diagramm-Exporte, Mail-Vorschauen.
- **Labelfarben in vier Stufen** (primary, secondary, tertiary, quaternary) passen sich automatisch
  an. Im Portal: `text-text`, `text-muted`, `text-faint`, `text-ghost` – in dieser Reihenfolge und
  nur in dieser Bedeutung.
- **Semantik nicht umdeuten.** Keine Trennlinienfarbe als Text, keine Sekundärtextfarbe als
  Hintergrund. Im Portal: `border-border` nur für Linien, `text-muted` nie als Fläche.
- **Eine Farbe, eine Bedeutung.** Dieselbe Farbe für «interaktiv» und für dekorativen Text
  verwirrt. Im Portal: `text-accent-text` nur für Links und aktive Navigation, Notenfarben nur für
  Notenstufen.
- **Nie nur Farbe.** Status zusätzlich über Form oder Text (Notenstufe «ungenügend» unterstrichen
  oder mit ▼, Fehler mit Text, Fokus mit Ring).
- **Akzent sparsam.** Farbe auf dem *Hintergrund* genau einer Primäraktion hebt sie hervor; nicht
  mehrere Bedienelemente mit farbigem Hintergrund, keine farbigen Symbole und Texte auf Materialien
  ohne Grund. Im Portal: ein `bg-accent`-Knopf pro Ansicht, Rest `glass-btn` oder tertiär.
- **Systemfarbwerte nicht hartcodieren** – sie schwanken von Version zu Version. Darum keine
  Apple-RGB-Werte in `theme.css` abschreiben; eigene Töne mit geprüftem Kontrast.
- **macOS, Desktop-Tinting:** Transparenz in eigenen Komponenten nur, wenn sie eine sichtbare
  Fläche haben *und* in einem neutralen Zustand sind; nie in farbigen Zuständen. Übertragung auf das
  Portal: leichte Transparenz nur in `glass-bar`/`glass-overlay`, nie auf `bg-accent` oder
  Notenfarben.

## 2. Tiefe und Ebenen

Quelle: [Dark Mode – iOS, iPadOS](https://developer.apple.com/design/human-interface-guidelines/dark-mode#iOS-iPadOS)
und [Materials](https://developer.apple.com/design/human-interface-guidelines/materials).

- **Base und Elevated.** Im Dunkeln entsteht Tiefe über *zwei Hintergrundsätze*: der Grund ist
  dunkler (tritt zurück), die vordere Ebene heller (tritt vor). Wenn etwas in den Vordergrund kommt
  (Popover, Sheet), wechselt der Hintergrund von base zu elevated. Übertragung: `bg-bg` (base) <
  `bg-card` < `bg-surface-2`; Menüs, Toasts, Dialoge heller als die Karte plus Haarlinie
  `border-border`. Schatten tragen im Dunkeln fast nichts – `shadow-e1…e3` sind im Hellen die
  Tiefe, im Dunkeln ist es die Helligkeitsstufe.
- **Materialien nur für die funktionale Ebene.** Liquid Glass bildet eine eigene Schicht für
  Bedienelemente und Navigation (Tab-Leisten, Seitenleisten, Toolbars) *über* dem Inhalt und gehört
  nicht in die Inhaltsebene. Im Portal: `glass-bar` nur Hauptnavigation und Sticky-Toolbar,
  `glass-overlay` nur Menüs, Paletten, Toasts, Popover; Karten, Tabellen, Formulare, Diagramme ohne
  Glas.
- **Glas sparsam, reguläre Variante bei Text.** Die reguläre Variante blurrt und hellt den
  Hintergrund für Lesbarkeit auf und ist für textreiche Elemente (Seitenleiste, Popover) gedacht;
  die klare Variante nur über Medien. Grössere Elemente wie Seitenleisten sind opaker. Übertragung:
  die Seitenleiste darf opaker sein als ein Popover; nie «klares» Glas über Tabellen.
- **Dickere Materialien = mehr Kontrast für feine Schrift**, dünnere bewahren den Kontext.
  Übertragung: Wo Text auf `glass-overlay` unter 4.5:1 fällt, Deckkraft des Materials erhöhen,
  nicht die Schrift aufhellen.
- **Vibrante Farben auf Materialien.** Auf Material gehören die system-vibranten Label-/Fill-Farben;
  quaternary nie auf dünnem Material. Übertragung: auf `glass-*` nur `text-text`/`text-muted`, nie
  `text-faint`/`text-ghost`.
- **Scroll-Kante statt Vollfarbe** unter Bedienelementen
  ([Layout](https://developer.apple.com/design/human-interface-guidelines/layout#Visual-hierarchy)):
  Inhalt darf unter Navigation und Toolbars durchlaufen, abgesetzt durch den Scroll-Edge-Effekt. Im
  Portal: `glass-bar` erfüllt das; eigene Scrollbereiche mit `np-scroll-edge`.

## 3. Typografie

Quelle: [Typography](https://developer.apple.com/design/human-interface-guidelines/typography)
(Abschnitte Ensuring legibility, Conveying hierarchy, Specifications macOS).

- **macOS: Standardgrösse 13 pt, Minimum 10 pt.** Im Portal (`resources/css/app.css`): `text-2xs`
  (11 px) ist die kleinste Stufe für Lesbares (Nebentext, Legenden), `text-3xs` (10 px) nur für
  Fussnoten und Beschriftungen in Diagrammen und Heatmaps – nie für Zahlen, die jemand vergleicht.
- **Keine leichten Gewichte.** Regular, Medium, Semibold, Bold; kein Ultralight, Thin, Light. Im
  Portal zusätzlich: kein `font-extrabold`/`font-black` (`notenportal-ui` §4).
- **Hierarchie über Gewicht, Grösse und Farbe**, nicht über Rahmen. Wenige Schriften: eine
  (Inter Variable).
- **macOS-Textstile** (Gewicht · Grösse/Zeile · betont): Large Title Regular 26/32 Bold · Title 1
  Regular 22/26 Bold · Title 2 Regular 17/22 Bold · Title 3 Regular 15/20 Semibold · Headline Bold
  13/16 Heavy · Body Regular 13/16 Semibold · Callout Regular 12/15 Semibold · Subheadline Regular
  11/14 Semibold · Footnote Regular 10/13 Semibold · Caption 1 Regular 10/13 Medium · Caption 2
  Medium 10/13 Semibold. Die Skala des Portals (`notenportal-ui` §4) ist davon abgeleitet und etwas
  grösser, weil ein Browserfenster weiter weg steht als ein Mac-Fenster; neue Stufen nur als Token.
- **Tracking aus Apples macOS-Tabelle** (1/1000 em): 12 pt 0 · 13 −6 · 15 −16 · 17 −26 · 20 −23 ·
  22 −12 · 28 +14. Steht in den Tokens, nie in der View.
- **SF Pro und SF Symbols sind tabu** (`docs/auftrag/GUI-APPLE.md`): Apples Font-Lizenz erlaubt
  die Schriften nur für Mockups von Software auf Apple-Plattformen, nicht für Webinhalte
  (`https://developer.apple.com/fonts/`).

## 4. Layout auf dem grossen Bildschirm

Quelle: [Layout](https://developer.apple.com/design/human-interface-guidelines/layout).

- **Wichtigstes oben und am Anfang der Leserichtung.** Seitenkopf mit Titel und einer Primäraktion,
  die Heldenzahl zuerst.
- **Ausrichten und einrücken**, um Hierarchie zu zeigen; **gruppieren** über Negativraum, Container
  oder Trennlinien – nicht alles ist eine Karte.
- **Progressive Disclosure:** Disclosure-Dreiecke, Menüs, verschachtelte Ansichten statt alles auf
  einmal; im Portal `np-details`, `<x-dropdown>`, Drawer.
- **Bedienelemente vom Inhalt unterscheiden** über die Material-Ebene, nicht über eine zweite
  Vollfarbe.
- **macOS: nichts Wichtiges an den unteren Fensterrand.** Fenster werden oft unter die
  Bildschirmkante geschoben. Keine Sticky-Fusszeile mit Primäraktion; Formularabschluss gehört
  unter das Formular, nicht fixiert.
- **Anpassung an die Fenstergrösse:** Zwischen 1920 und 2560 px muss das Layout ruhig bleiben
  (`np-seite` deckelt bei 2048 px, Lesespalten `max-w-3xl`); Raster gewinnen Spalten, Tabellen
  werden nicht breiter als ihre Spalten brauchen.

## 5. Prüfliste je Seite (dunkel, 1920 und 2560 px)

Screenshots mit `node tools/pruefung/shot.mjs <email> "/pfad" --breite=1920` und `--breite=2560`,
mit `Read` ansehen; Rundgang `node tools/pruefung/rundgang.mjs <email>` muss Exit 0 liefern.
Bildurteil durch den Agent `bildpruefer`.

1. **Ebenen unterscheidbar?** Grund, Karte, Tabellenkopf/Hover und Overlay sind vier erkennbar
   verschiedene Helligkeiten; Haarlinie `border-border` sichtbar, aber nicht hart.
2. **Kein reines Schwarz, kein reines Weiss** als Fläche oder Text.
3. **Text liest sich:** `text-text` und `text-muted` ≥ 4.5:1 auf jeder Fläche, auf der sie stehen;
   kein `text-faint` für Inhalt.
4. **Glas nur Navigation und Overlays.** Karten, Tabellen, Formulare, Diagramme sind matt.
5. **Ein Akzent pro Ansicht**, Links in `text-accent-text`, Notenfarben nur bei knapp/ungenügend
   und immer mit Form.
6. **Vier Zustände** (leer, lädt, Fehler, sehr viele Daten) sehen im Dunkeln gut aus.
7. **Fokusring sichtbar** (`outline-ring`, ≥ 3:1 gegen die Fläche), Tastaturweg durch Menüs und
   Dialoge, Escape schliesst.
8. **Bilder und Logos** ohne weisse Kästen; Diagrammfarben aus Tokens (`tokenFarbe()`).
9. **Nichts Wichtiges unten fixiert**, keine Querscrollleiste, kein abgeschnittener Text.
10. **Hell einmal stichprobenartig** (`--hell`): darf nicht brechen, wird nicht gestaltet.
