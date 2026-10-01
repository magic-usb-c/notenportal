---
name: notenportal-ui
description: Design-System und Blade-Konventionen des Notenportals (Tokens Theme Gletscher, Tailwind-4-Klassen, Typo-Skala, Flächen/Glas-Stufen, Notenfarben, Buttons, Formulare, Tabellen). Laden vor jeder Arbeit an Blade-Views, CSS oder Alpine-Komponenten.
---

# Notenportal UI-Regeln

Quelle: `docs/gui-konzept.md` (a–h). Tokens: `resources/css/theme.css`. Klassen/Skala: `resources/css/app.css`.
Leitsatz: ruhig, Hierarchie über Typografie und Abstand, **Farbe nur mit Bedeutung**, Glas nur für die schwebende Ebene.

## 1. Texte
- Schweizer Hochdeutsch, **ss statt ß** (`grep -rn "ß" resources/views` muss leer sein).
- Keine Hinweise, Entwicklernotizen, Selbstverständliches. Text bleibt nur, wenn er eine Entscheidung ermöglicht oder einen Fehler verhindert.
- Firmenname, Grenzwerte, Fristen nie hartcodieren – aus Einstellungen (DB). Notengrenzen immer aus `NotenSkala::grenzen()` bzw. `$grenzen`.

## 2. Farben – NUR diese Klassen

| Zweck | Klasse |
|---|---|
| Seitengrund | `bg-bg` |
| Karte, Tabelle, Formular, Dialog | `bg-card` |
| Segment-Grund, Hover klickbarer Karten, Sticky-Kopf einer Handtabelle | `bg-surface-2` (Hover: `hover:bg-surface-2/60`). Der Kopf einer `np-tabelle` hat **keine** Fläche (Linie unten, `text-muted`), ihre Zeilen streifen und hovern selbst – Tabellen immer als `np-tabelle`, nicht von Hand. |
| Eingabefeld | `bg-input` |
| Text / Sekundärtext | `text-text` / `text-muted` |
| Inaktives Bedienelement / reine Dekoration | `text-faint` / `text-ghost` – **nie für lesbaren Text** |
| Fläche ohne Karte (Fortschrittsspur, Segmentgrund, Ladeplatzhalter) | `bg-fill` / `bg-fill-2` |
| Trennlinie (dekorativ) | `border-border` |
| Eingabefeld-Rand, Sekundärbutton-Rand | `border-border-strong/70` bzw. `/60` |
| Primärfläche (genau eine Primäraktion pro Ansicht) | `bg-accent` + **`text-accent-contrast`** |
| Link, aktive Navigation, Textakzent | `text-accent-text` |
| Fokusring | `outline-ring` / `ring-ring` |
| Notenstufen | `text-note-gut` `text-note-genuegend` `text-note-knapp` `text-note-ungenuegend` (auch `bg-note-…/14`) |
| Schatten (nur hell sichtbar) | `shadow-e1` (Hover) · `shadow-e2` (Menü, Toast) · `shadow-e3` (Dialog) |

Alpha immer über den Token: `bg-accent/10`, `border-border-strong/60`; in CSS `rgb(var(--accent) / 0.1)` bzw. `rgb(var(--accent-rgb) / 0.1)`.

**Verboten** (Hook `.claude/hooks/view-pruefung.sh` meldet es):
- `bg-white`, `bg-black`, `bg-gray-*`, `bg-slate-*`, `text-black`, `text-gray-*`, `#hex`, `rgba(…)`, `style="color:…"` in Blade.
- `text-white` – auch auf Accent (im Dunkelmodus ist Accent hell → dunkle Schrift). Stattdessen `text-accent-contrast`. Ausnahmen: SVG in Mail-Vorlagen, Druckansicht.
- Tailwind-Palettenfarben (`green-*`, `emerald-*`, `yellow-*`, `red-*`, `blue-*` …) für Bedeutung. Neu nur Note-Tokens; Bestand (u. a. `NotenSkala`) wird in Paket 2 umgestellt.
- Fehlertext: `text-note-ungenuegend` (nicht `text-red-600`).
- `text-accent` für Text – immer `text-accent-text` (eigene Akzentfarbe garantiert für `--accent` nur 3:1). `text-accent` nur für Grafik (Logo-SVG) und die Füllfarbe von Checkboxen.

`text-faint`/`text-ghost` und `bg-fill`/`bg-fill-2` sind Apples abgestufte Label- bzw.
Füllebenen (Tertiary/Quaternary), aus `--text` per Deckung abgeleitet, damit sie in
jedem Theme passen. Sie liegen bewusst unter 4.5:1 – WCAG 1.4.3 nimmt inaktive
Bedienelemente aus. Platzhalter und Sekundärtext bleiben bei `text-muted`.

Neue Tokens nur in `theme.css` (hell in `:root, [data-theme='gletscher']`, dunkel in `.dark, [data-theme='gletscher'].dark`, Werte als RGB-Tripel, OKLCH als Kommentar) **und** in `@theme inline` in `app.css` als `--color-…`. Kontrast vorher mit `~/tools/kontrast/kontrast.mjs` rechnen: Text ≥ 4.5:1, UI-Grenzen/Grafik ≥ 3:1. `~/tools/kontrast/alpha.mjs` prüft zusätzlich alle 24 Theme-Blöcke auf einmal und meldet mit Exit-Code 1, wenn `text`/`muted` ihre Schwelle reissen.

## 3. Themes und Modus
- `<html data-theme="gletscher">` (Standard) + Klasse `.dark` für Dunkel. Weitere Themes (Paket 8) als `[data-theme='…']` / `[data-theme='…'].dark` **nach** dem Gletscher-Block in `theme.css`.
- Views kennen kein Theme: nie `dark:`-Varianten für Farben schreiben, die ein Token schon abdeckt. `dark:` nur für echte Ausnahmen.
- Chart.js liest Farben per `tokenFarbe('--chart-1')` / `notenFarbe()` (`resources/js/np.js`) – nie Farben im JS hartcodieren.

## 4. Typografie
Schrift: **Inter Variable**, selbst gehostet (`@fontsource-variable/inter`, via Vite). Keine Google-/Bunny-Fonts, kein CDN, kein SF Pro.

Das Portal ist eine **Desktop-Anwendung** (1920–2560 px). Die Skala folgt deshalb den **macOS-Textstilen**
der Apple Human Interface Guidelines (Typography → Specifications → macOS built-in text styles):
Body 13 pt ist die Grundschrift, 10 pt das Minimum. Tokens in `resources/css/app.css` (`@theme`), in rem,
damit die persönliche Schriftgrösse alles mitskaliert. Kein eigenes Tracking (Inter bringt es mit).

| Klasse | Grösse/Zeile | macOS-Stil | Einsatz |
|---|---|---|---|
| `text-3xs` | 10/13 | Footnote, Caption | Zähler-Badge, Kalenderzelle, Skalenmarken |
| `text-2xs` | 11/14 | Subheadline | Tabellenkopf, Achsen, Marken |
| `text-xs` | 12/16 | Callout | Hilfetext, Sekundärzeile, Metazeile |
| `text-sm` | 13/18 | Body / Headline | **Grundschrift** aller Bedienelemente, Tabellen, Formulare; Gruppenüberschriften über Listen `font-semibold` |
| `text-base` | 15/20 | Title 3 | Kartentitel (`x-karte`, `x-diagramm`) `font-semibold`, Drawer-/Dialogtitel |
| `text-lg` | 17/22 | Title 2 | Abschnittstitel auf Leseseiten |
| `text-xl` | 22/26 | Title 1 | Kennzahl in Kacheln |
| `text-2xl` | 26/32 | Large Title | **Seitentitel h1** (nur über `<x-seitenkopf>`, `font-bold`) |
| `text-3xl` | 36/40 | – | grosse Ziffer: Noteneingabe, Gesamtschnitt im Cockpit |
| `text-display` | 48 px | – | **eine** Heldenzahl pro Seite |

- Labels in Satzschreibung: `text-sm text-text`, ohne Sternchen (Pflicht ist der Normalfall; freiwillige Felder tragen den Platzhalter «Optional»).
  **Verboten:** `uppercase tracking-widest`-Labels, `font-extrabold`, `font-black`. `font-bold` nur für h1 (Large Title) und die Heldenzahl.
- `tabular-nums` nur, wo Zahlen untereinander stehen (Tabellen, Listen). Zahlenspalten rechtsbündig.
- Schriftgrössen nie hart setzen (`text-[15px]`, `style="font-size:…"`) – nur die Klassen oben. Tailwinds übrige Stufen
  (`text-4xl` …) sind per `--text-*: initial` abgeschaltet und erzeugen kein CSS; der View-Hook meldet sie.
  Wer eine Stufe vermisst, ergänzt sie als Token, nicht in der View.

## 5. Flächen, Materialien, Radien, Abstände

**Nur Desktop.** Keine Breakpoint-Varianten (`sm:`, `md:`, `lg:`, `xl:`) schreiben – ab 1280 px gelten sie
ohnehin alle; der Desktopwert ist die Basisklasse. Einzige Ausnahme: die Hauptnavigation
(`layouts/navigation.blade.php`, `max-xl:` für die eingeklappte Leiste unter 1280 px).

| Klasse (`app.css`) | Einsatz |
|---|---|
| `np-karte` | **Standard für alle Inhaltskarten** (Karte, Formular, Tabelle): `bg-card`, `rounded-xl`, Schatten E1 statt Rahmen |
| `np-karte-klickbar` | ganze Karte als Link (Hover hellt auf, keine Bewegung) |
| `np-gruppe` | gruppierte Liste in einer Karte (Haarlinie zwischen Zeilen); Zeilen über `<x-einstellung>` |
| `np-tabelle` | macOS-Tabelle: Kopf ohne Fläche, Zeilen im Wechsel hinterlegt, Hover gerundet. Mehrere Tabellen untereinander: `table-fixed` + `<colgroup>` mit festen Breiten, damit die Spalten bündig stehen. Spalten ohne einen einzigen Wert entfallen. |
| `np-marke` | Badge/Zähler (Pille, `text-2xs` 600); Bedeutung über `bg-note-…/14 text-note-…` |
| `np-feld` · `np-feld-klein` | Eingabefeld/Auswahl 36 px bzw. 28 px (Filterleiste) |
| `glass-bar` / `np-symbolleiste` | nur Hauptnavigation und Symbolleiste |
| `glass-overlay` | Befehlspalette, Menüs, Toasts, Popover |
| `glass-scrim` | hinter Drawer/Modal |

- Glas **nie** auf Karten, Tabellen, Formularen, Diagrammen oder grossen Panels.
- Entfernt (nicht mehr schreiben): `accent-glow`, `np-glow-*`, `np-card-lift`, `np-btn-tactile`, `glass-lift`, `glass-subtle`, Scale-Effekte, Deko-Orbs, mobile Kartenansichten von Tabellen.
- Radien: `rounded-md` Badge-Ecke · `rounded-lg` Feld/Segment · `rounded-xl` Karte · `rounded-2xl` Drawer/Modal/Palette · `rounded-full` Knopf, Marke, Avatar, Punkt.
- Abstände im 4-px-Raster: Karte `p-5`, zwischen Karten `gap-4`, zwischen Abschnitten `gap-8`.
- **Trefferfläche:** jedes Bedienelement mindestens 24x24 px (WCAG 2.5.8); Textlinks als Aktion `inline-flex min-h-6 items-center`.
- **Seitengerüst:** Inhalt `<div class="py-6"><div class="mx-auto np-seite px-8">` (`np-seite` füllt das Fenster, Deckel 2048 px). Lese-/Formularseiten innen `np-spalte` (48rem, mittig) + `schmal` am Seitenkopf – nie `max-w-3xl` für eine ganze Seite. Übersichten mit Haupt- und Nebenspalte: `grid grid-cols-12 items-start gap-4`, `col-span-8` / `col-span-4`.
- **Seitenkopf** nur über `<x-seitenkopf titel untertitel zaehler zurueck schmal>` im Slot `header`. Aktionen im Slot `aktionen` – sie wandern in die Symbolleiste oben rechts (höchstens **eine** Primäraktion), der Zurück-Knopf (`zurueck`) vorne. Metadaten als `untertitel` (eine Zeile, mit ` · ` getrennt), nicht als eigene Karte.
- Datensätze bearbeiten im **Drawer** (`<x-drawer>`, schwebend rechts), Bestätigungen über `data-bestaetigen` (HIG-Dialog, nie `window.confirm`).
- Toast nur über `<x-toast>` (Layout rendert Flash automatisch), Menüs über `<x-dropdown>` (G2). Modal und Drawer liegen auf `z-[70]`.
- Klickbare Tabellenzeilen: `data-href` auf `<tr>` (ganze Zeile klickbar, Tastatur über den Link in der ersten Zelle).
- Zahlenfelder ohne Pfeile (Spinner sind global ausgeblendet); Gewichtungen über `<x-gewicht-feld>`.
- Lernstand nur über `<x-status status>`: rot «Kritisch», gelb «Beobachten», grün «Im Plan», grau «Offen» und «Abgeschlossen» (Lehrende vorbei – keine Warnungen mehr).
- Notenfarben kommen aus `NotenSkala::text()/badge()` (gut/genügend neutral, knapp/ungenügend farbig, ungenügend zusätzlich unterstrichen); volle Stufenfarbe nur `NotenSkala::farbe()`, Punkte `NotenSkala::punkt()`.

## 6. Bewegung
- Transitions nur pro Komponente: `transition-colors duration-100` (Hover), 150 ms (Menü, Segment), 200 ms (Akkordeon, Drawer), 300 ms (Modal). Easing `ease-out`.
- Keine Bewegung als Deko. `prefers-reduced-motion`: Überblendung statt Bewegung (ist in `app.css` für `details` und Progress gelöst).

## 7. Muster (kopieren)

```blade
{{-- Knöpfe (32 px, Pille). Genau eine Primäraktion pro Ansicht, in der Symbolleiste. --}}
<button class="np-knopf np-knopf-primaer">Speichern</button>
<a class="np-knopf np-knopf-sekundaer">Bearbeiten</a>
<button class="np-knopf np-knopf-schlicht">Abbrechen</button>
<button class="np-knopf np-knopf-gefahr">Löschen</button>            {{-- voll rot nur im Bestätigungsdialog: np-knopf-gefahr-voll --}}
<button class="np-knopf np-knopf-symbol" aria-label="…"><x-symbol name="pencil-square" /></button>
<button class="np-knopf np-knopf-sekundaer np-knopf-rund" aria-label="…">…</button>   {{-- Symbol mit Fläche, kreisrund (Menü «…», Stepper) --}}
{{-- Grössen: np-knopf-klein (28 px, in Tabellen) · Standard 32 px · np-knopf-gross (40 px, Anmeldung) --}}

{{-- Formular = gruppierte Liste (macOS-Systemeinstellungen), mittig in np-spalte --}}
<form method="POST" class="flex np-spalte flex-col gap-8" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
  <section>
    <h2 class="mb-2 px-1 text-sm font-semibold text-text">Fach</h2>
    <div class="np-karte np-gruppe">
      <x-einstellung :label="__('Name')" fuer="name" name="name">
        <input id="name" name="name" required class="np-feld w-72" @error('name') aria-invalid="true" aria-describedby="name-fehler" @enderror>
      </x-einstellung>
      {{-- mit Hinweis: beide ids in EINEM aria-describedby --}}
      <x-einstellung :label="__('Kürzel')" fuer="kuerzel" name="kuerzel" :hinweis="__('…')">
        <input id="kuerzel" name="kuerzel" class="np-feld w-32" placeholder="{{ __('Optional') }}"
               aria-describedby="kuerzel-hinweis @error('kuerzel') kuerzel-fehler @enderror" @error('kuerzel') aria-invalid="true" @enderror>
      </x-einstellung>
      <x-einstellung :label="__('Skala')" name="skala"><x-segment-auswahl name="skala" :wert="…" :optionen="[…]" /></x-einstellung>
      <x-einstellung :label="__('Aktiv')" fuer="aktiv" name="aktiv">
        <input type="hidden" name="aktiv" value="0"><input type="checkbox" role="switch" id="aktiv" name="aktiv" value="1" class="np-schalter">
      </x-einstellung>
      <x-einstellung :label="__('Beschreibung')" fuer="beschreibung" name="beschreibung" gestapelt>
        <textarea id="beschreibung" name="beschreibung" rows="4" class="np-feld w-full"></textarea>
      </x-einstellung>
    </div>
    <p class="mt-2 px-1 text-xs text-muted">Fussnote, nur wenn sie eine Entscheidung ermöglicht.</p>
  </section>
  <x-formular-aktionen :abbrechen="route('…')">{{ __('Fach anlegen') }}</x-formular-aktionen>
</form>
{{-- Feldbreiten: Text w-72/w-80 · kurz w-32/w-40 · Zahl w-24 text-right tabular-nums · Datum w-44 · Auswahl w-56.
     Löschen: eigene <section class="mt-10 np-spalte"> mit np-gruppe-Zeile und np-knopf-gefahr. --}}

{{-- Karte --}}
<section class="np-karte p-5">
  <h2 class="text-sm font-semibold text-text">…</h2>
  <div class="mt-3">…</div>
</section>

{{-- Tabelle --}}
<section class="np-karte">
  <div class="px-2 pb-2"><table class="np-tabelle table-fixed text-sm">
    <colgroup><col class="w-32"><col><col class="w-28"></colgroup>
    <thead><tr><th scope="col">Datum</th><th scope="col">Fach</th><th scope="col" class="text-right">Note</th></tr></thead>
    <tbody><tr data-href="…"><td>…</td>…</tr></tbody>
  </table></div>
</section>

{{-- Note-Badge (Farbe nur bei knapp/ungenügend) --}}
<x-note :wert="$note" />

{{-- Leerzustand --}}
<x-leer titel="Noch keine Noten" text="…" />
```

## 8. Pflicht bei jedem Feld und jeder Seite
- `<label for>` + `id` (über `<x-einstellung fuer>`), feldgenaues `@error` mit `aria-invalid` und `aria-describedby="{feld}-fehler"`; Hinweis-id `{feld}-hinweis` im selben Attribut. Keine Sternchen.
- Mutierende Formulare: Alpine `x-data="{ loading: false }"` gegen Doppelabsenden.
- Erfolg/Fehler nur über Flash-Toast (`->with('success'|'error')`), keine Inline-Banner.
- Trefferfläche ≥ 24 px (WCAG 2.5.8; `np-knopf-klein` erfüllt das), Icon-Buttons mit `aria-label`, Fokus sichtbar (`focus-visible:outline-2 outline-ring`).
- Diagrammbalken beginnen bei Note 1: Breite = (Ø − 1) / 5.
- Browser-Titel pro Seite setzen.

## 9. Tailwind 4 (CSS-first, keine tailwind.config.js)
- Eigene Klassen nur als `@utility` in `app.css`, nie ungelayertes CSS. Tokens in `@theme inline`.
- v4-Namen: `outline-hidden`, `shadow-xs`, `rounded-xs`, Important als Suffix `x!`, Opazität `bg-accent/15`.
- `space-y-*` wirkt über margin; wo versteckte Kinder stören, `flex flex-col gap-*`.
- Klassen, die nur per Alpine `:class` entstehen, in `@source inline(…)` eintragen.

## 10. Abschluss
`npm run build` (muss «built in» zeigen) · `php artisan test` · ß-Grep leer · Hook ohne Meldung ·
`node tools/pruefung/rundgang.mjs <email>` je betroffener Rolle mit Exit 0 (keine JS-Fehler, kein Überlauf) ·
Screenshots **dunkel, 1920 und 2560 px** mit `node tools/pruefung/shot.mjs <email> "/pfad" --breite=1920|2560`
erzeugen und jedes Bild mit `Read` ansehen · hell (`--hell`) nur einmal stichprobenartig: darf nicht brechen,
bekommt keine Gestaltungsarbeit. Werkzeuge und Massstab: Skill `notenportal-pruefwerkzeuge`,
Dunkelmodus-Regeln: Skill `notenportal-dunkelmodus`.
Passwort bei allen Werkzeugen nur über `NP_TEST_PW`, nie als Argument.
