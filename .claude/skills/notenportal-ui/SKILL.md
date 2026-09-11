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
| Tabellenkopf, Zeilen-Hover, Segment-Grund | `bg-surface-2` (Hover: `hover:bg-surface-2/60`) |
| Eingabefeld | `bg-input` |
| Text / Sekundärtext | `text-text` / `text-muted` |
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

Neue Tokens nur in `theme.css` (hell in `:root, [data-theme='gletscher']`, dunkel in `.dark, [data-theme='gletscher'].dark`, Werte als RGB-Tripel, OKLCH als Kommentar) **und** in `@theme inline` in `app.css` als `--color-…`. Kontrast vorher mit `~/tools/kontrast/kontrast.mjs` rechnen: Text ≥ 4.5:1, UI-Grenzen/Grafik ≥ 3:1.

## 3. Themes und Modus
- `<html data-theme="gletscher">` (Standard) + Klasse `.dark` für Dunkel. Weitere Themes (Paket 8) als `[data-theme='…']` / `[data-theme='…'].dark` **nach** dem Gletscher-Block in `theme.css`.
- Views kennen kein Theme: nie `dark:`-Varianten für Farben schreiben, die ein Token schon abdeckt. `dark:` nur für echte Ausnahmen.
- Chart.js liest Farben per `tokenFarbe('--chart-1')` / `notenFarbe()` (`resources/js/np.js`) – nie Farben im JS hartcodieren.

## 4. Typografie
Schrift: **Inter Variable**, selbst gehostet (`@fontsource-variable/inter`, via Vite). Keine Google-/Bunny-Fonts, kein CDN.

| Klasse | Grösse | Einsatz |
|---|---|---|
| `text-2xs` | 12 px | Tabellenkopf, Achsen, Meta |
| `text-xs` | 13 px | Hilfetext, Sekundärzeile |
| `text-sm` | 14 px | **UI-Grundschrift**, Tabellen, Buttons (`font-medium`) |
| `text-base` | 16 px | Fliesstext, Formulare mobil |
| `text-lg` | 20 px | Kartentitel gross, Drawer-Titel (`font-semibold`) |
| `text-xl` | 24 px | Seitentitel h1 (`font-semibold`, Sperrung schon im Token) |
| `text-2xl` | 30 px | Kennzahl in Statuszeile |
| `text-display` | 48 px | **eine** Heldenzahl pro Seite (600, ohne `tabular-nums`) |

- Labels in Satzschreibung: `text-sm font-medium text-text`. **Verboten:** `uppercase tracking-widest`-Labels, `font-extrabold`, `font-black`. `font-bold` nur für die Heldenzahl.
- `tabular-nums` nur, wo Zahlen untereinander stehen (Tabellen, Listen). Zahlenspalten rechtsbündig.

## 5. Flächen, Glas, Radien, Abstände

| Klasse | Stufe | Einsatz |
|---|---|---|
| `rounded-xl border border-border bg-card` | E0 | **Standard für alle Inhaltskarten** |
| `glass` (Bestand) | = E0 | feste Karte; auf `role="dialog"` automatisch E3-Schatten. Neu nicht mehr verwenden. |
| `glass-bar` (`glass-subtle` = Alias) | G1 | nur Hauptnavigation, Sticky-Toolbar |
| `glass-overlay` | G2 | Befehlspalette, Menüs, Toasts, Popover |
| `glass-scrim` | Scrim | hinter Drawer/Modal |
| `glass-lift` (Bestand) | E1 | Hover klickbarer Karten (Fläche/Schatten, keine Bewegung) |

- Glas **nie** auf Karten, Tabellen, Formularen, Diagrammen oder grossen Panels.
- Entfernt und wirkungslos (nicht mehr schreiben): `accent-glow`, `np-glow-*`, `np-text-glow-*`, `np-card-lift`, `np-btn-tactile`, Radial-Gradient auf `body`, globale `transition` auf `*`, Scale-Effekte (`active:scale-*`, `hover:scale-*`), `blur-3xl`-Deko-Orbs.
- Radien: `rounded-md` Badge · `rounded-lg` Button/Input/Segment · `rounded-xl` Karte/Tabelle · `rounded-2xl` Drawer/Modal/Palette · `rounded-full` Avatar/Punkt. `rounded-3xl` verboten (ist Alias auf 16 px).
- Abstände im 4-px-Raster: Karte `p-4`/`p-5`, zwischen Karten `gap-4`, zwischen Abschnitten `gap-8`/`gap-10`.
- Container überall `max-w-7xl mx-auto px-4 sm:px-6 lg:px-8`; Seitenkopf im selben Container. Lese-/Formularseiten `max-w-3xl` links bündig.

## 6. Bewegung
- Transitions nur pro Komponente: `transition-colors duration-100` (Hover), 150 ms (Menü, Segment), 200 ms (Akkordeon, Drawer), 300 ms (Modal). Easing `ease-out`.
- Keine Bewegung als Deko. `prefers-reduced-motion`: Überblendung statt Bewegung (ist in `app.css` für `details` und Progress gelöst).

## 7. Muster (kopieren)

```blade
{{-- Primärbutton – genau einer pro Ansicht --}}
<button class="inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary disabled:opacity-50">
{{-- Sekundär --}}
<a class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">
{{-- Tertiär --}}
<button class="inline-flex h-9 items-center rounded-lg px-2.5 text-sm text-muted hover:bg-surface-2 hover:text-text">
{{-- Gefährlich (solid nur im Bestätigungsdialog) --}}
<button class="inline-flex h-9 items-center rounded-lg px-3 text-sm text-note-ungenuegend hover:bg-note-ungenuegend/10">
{{-- Grössen: h-8 Tabelle · h-9 Standard · h-11 mobil/Formularabschluss --}}

{{-- Formularfeld --}}
<label for="x" class="text-sm font-medium text-text">Gewichtung <span class="text-note-ungenuegend">*</span></label>
<input id="x" name="x" class="mt-1.5 h-10 w-full rounded-lg border border-border-strong/70 bg-input px-3 text-sm text-text placeholder:text-muted focus:border-accent focus:ring-2 focus:ring-ring/30" aria-describedby="x-fehler">
@error('x')<p id="x-fehler" class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror

{{-- Karte --}}
<section class="rounded-xl border border-border bg-card">
  <header class="flex h-12 items-center justify-between px-5"><h2 class="text-sm font-semibold text-text">…</h2></header>
  <div class="px-5 pb-5">…</div>
</section>

{{-- Tabelle --}}
<div class="overflow-x-auto rounded-xl border border-border bg-card">
<table class="w-full text-sm tabular-nums">
  <th class="sticky top-0 h-9 bg-surface-2 px-3 text-left text-2xs font-medium text-muted">   {{-- Zahlen: text-right --}}
  <tr class="border-b border-border last:border-0 hover:bg-surface-2/60">
  <td class="h-11 px-3">

{{-- Note-Badge (Farbe nur bei knapp/ungenügend; gut höchstens Punkt, genügend in text-text) --}}
<span class="inline-flex h-6 min-w-11 justify-center rounded-md bg-note-knapp/14 px-1.5 text-sm font-semibold tabular-nums text-note-knapp">3.8</span>
{{-- ungenügend zusätzlich mit Form: ▼ oder underline decoration-2 (nie nur Farbe) --}}

{{-- Leerzustand: eine Zeile mit nächstem Schritt, leere Karten entfallen --}}
<p class="flex items-center gap-3 px-5 py-4 text-sm text-muted">Noch keine Noten <a class="text-accent-text hover:underline underline-offset-2" href="…">Erste Note erfassen</a></p>

{{-- Accordion (np-details + np-chevron für JS-Toggle) --}}
<details class="np-details rounded-xl border border-border bg-card">
  <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 hover:bg-surface-2/60">
    <span class="np-chevron transition-transform duration-200">…</span>
```

## 8. Pflicht bei jedem Feld und jeder Seite
- `<label for>` + `id`, feldgenaues `@error`, Pflichtfeld mit `*` in `text-note-ungenuegend`.
- Mutierende Formulare: Alpine `x-data="{ loading: false }"` gegen Doppelabsenden.
- Erfolg/Fehler nur über Flash-Toast (`->with('success'|'error')`), keine Inline-Banner.
- Touch-Targets ≥ 36 px (mobil 44 px), Icon-Buttons mit `aria-label`, Fokus sichtbar (`focus-visible:outline-2 outline-ring`).
- Diagrammbalken beginnen bei Note 1: Breite = (Ø − 1) / 5.
- Browser-Titel pro Seite setzen.

## 9. Tailwind 4 (CSS-first, keine tailwind.config.js)
- Eigene Klassen nur als `@utility` in `app.css`, nie ungelayertes CSS. Tokens in `@theme inline`.
- v4-Namen: `outline-hidden`, `shadow-xs`, `rounded-xs`, Important als Suffix `x!`, Opazität `bg-accent/15`.
- `space-y-*` wirkt über margin; wo versteckte Kinder stören, `flex flex-col gap-*`.
- Klassen, die nur per Alpine `:class` entstehen, in `@source inline(…)` eintragen.

## 10. Abschluss
`npm run build` (muss «built in» zeigen) · `php artisan test` · ß-Grep leer · `node ~/tools/visual/breite.mjs … --breite=390` alle «✓» · Screenshots hell/dunkel/mobil mit `~/tools/visual/shot.mjs` ansehen.
