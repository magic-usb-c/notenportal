---
name: notenportal-ui
description: Design-System und Blade-Konventionen des Notenportals (Tokens, Tailwind-Klassen, Liquid-Glass, Notenfarben, Formular-/Button-/Badge-/Accordion-Muster). Laden vor jeder Arbeit an Blade-Views, CSS oder Alpine-Komponenten.
---

# Notenportal UI-Konventionen

## Grundsätze
- Texte Schweizer Hochdeutsch, ss statt ß. Keine erklärenden Hinweise, Entwicklernotizen oder Selbstverständliches. Ein Text bleibt nur, wenn er eine Entscheidung ermöglicht oder einen Fehler verhindert.
- Firmenname, Grenzwerte, Fristen nie hartcodieren – aus Einstellungen (DB) lesen.
- UI-UX-Pro-Max-Plugin nur für Muster und Inspiration, nie für Farben: die Tokens unten haben Vorrang.

## Tokens (resources/css/theme.css, Hell + `.dark`)
`--bg --card --text --muted --border --input --accent --ring`, jeweils mit `-rgb`-Variante für Alpha.

Tailwind-Klassen dazu, immer verwenden:
`bg-bg bg-card bg-input bg-accent text-text text-muted text-accent border-border ring-ring`

Nie hartcodieren: `bg-white`, `bg-gray-800/900`, `text-black`, `text-white` (Ausnahme: Text auf `bg-accent`, SVG, Druckansicht), `#hex` in Blade.
Alpha immer über Token: `bg-accent/5`, in CSS `rgb(var(--accent-rgb) / 0.1)` – nie `rgba()`-Mischsyntax.

## Liquid-Glass (resources/css/app.css)
| Klasse | Einsatz |
|---|---|
| `.glass` | Primäre Cards, Panels (blur 20px, saturate 160%) |
| `.glass-subtle` | Nav, Sidebars (blur 12px) |
| `.glass-btn` | Sekundäre Buttons |
| `.glass-lift` | Hover-Lift mit Tiefe |
| `.np-card-lift` | Leichter Lift ohne Glass |
| `.accent-glow` | Accent-Glow für wichtige Elemente |

Light-Mode-Werte (Border 0.12, Schatten 0.13, Deckung 0.70) liegen als `--glass-*`-Variablen in theme.css.

## Notenfarben (überall gleich)
| Note | Text | Balken |
|---|---|---|
| ≥ 5.0 | `text-green-700 dark:text-green-400` | `bg-green-500` |
| ≥ 4.0 | `text-emerald-700 dark:text-emerald-400` | `bg-emerald-500` |
| ≥ 3.5 | `text-yellow-700 dark:text-yellow-400` | `bg-yellow-500` |
| < 3.5 | `text-red-600 dark:text-red-400` | `bg-red-500` |

Hell -700 wegen WCAG AA (4.5:1 auf weisser Card), red-600 besteht knapp. Fehlertexte `text-red-600 dark:text-red-400`.
Balken beginnen bei Note 1, nicht bei 0: Breite = (Ø − 1) / 5.

## Muster

```blade
{{-- Label --}}
<label for="feld" class="text-xs uppercase tracking-widest text-muted font-medium">

{{-- Primär-Button --}}
<button class="w-full h-12 rounded-xl bg-accent text-white font-semibold
               hover:opacity-90 active:scale-[0.97] transition-all duration-150
               inline-flex items-center justify-center gap-2">

{{-- Note-Badge --}}
<span class="inline-flex items-center justify-center min-w-[3rem] px-2 py-1
             rounded-xl font-bold text-sm [FARBKLASSE]">

{{-- Accordion (np-details + np-chevron für JS-Toggle) --}}
<details class="np-details bg-card border border-border rounded-2xl shadow-sm">
  <summary class="cursor-pointer select-none px-4 py-3 flex items-center
                  justify-between list-none hover:bg-accent/5 transition-colors">
    <span class="np-chevron transition-transform duration-200">...</span>
```

- Jedes Feld: `<label for>` + `id`, feldgenaue `@error`-Ausgabe, Pflichtfeld mit `*`.
- Mutierende Formulare: Alpine `x-data="{ loading: false }"` gegen Doppelabsenden.
- Erfolg/Fehler nur über Flash-Toast (`->with('success'|'error')`), keine Inline-Banner.
- Tabellen: `overflow-x-auto`-Container, sticky thead, Zebra.
- Touch-Targets mindestens 36px, Icon-Buttons mit `aria-label`.
- Browser-Titel pro Seite setzen.
- Nach CSS/JS-Änderungen: `npm run build`.
