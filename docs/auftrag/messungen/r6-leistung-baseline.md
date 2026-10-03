# R6 – Leistungs- und Kontrast-Baseline vor dem GUI-Rebuild (03.10.2026)

Gemessen in der Cloud-Sitzung mit `tools/pruefung/leistung.mjs` (Headless-Chromium, Software-Raster, Demo-Server, Dunkelmodus) und `tools/pruefung/kontrast.mjs`, **vor** jeder Änderung an einem `backdrop-filter`. Absolute Werte sind nicht die eines Macs; massgeblich ist der Vergleich vorher/nachher mit denselben Argumenten auf derselben Maschine. Rohdaten lagen im Scratch (`r6/leistung-baseline-*.json`) und sind hier als Tabellen festgehalten.

## 1. Seiten bei 1920×1080 (Median aus 3 Läufen, `--laeufe=3 --palette`)

| Rolle | Seite | LCP ms | Knoten | Glas (Anzahl, % Fenster) | Ruhe p95 ms | Palette: Tippen p95 / max ms | Palette: Glas % |
|---|---|---:|---:|---|---:|---|---:|
| Lernende | `/dashboard` | 164 | 11458 | 4, 18.4 % | 17 | 35.2 / 39.9 | 34.6 |
| Lernende | `/grades` | 168 | 18971 | 4, 18.4 % | 16.9 | 35.9 / 37 | 34.6 |
| Lernende | `/grades/calculator` | 176 | 32049 | 4, 18.4 % | 16.9 | 35.5 / 46.2 | 34.6 |
| Lernende | `/exams` | 136 | 9514 | 4, 18.4 % | 16.9 | 32.9 / 42.2 | 34.6 |
| Admin | `/admin` | 148 | 11948 | 4, 18.4 % | 16.9 | 40.2 / 42.3 | 34.6 |
| Admin | `/admin/reports/grades` | 188 | 21228 | 4, 18.4 % | 16.9 | 38.3 / 45.1 | 34.6 |
| Admin | `/admin/learners` | 172 | 14897 | 4, 18.4 % | 16.9 | 36.5 / 37.3 | 34.6 |
| Berufsbildner | `/trainer` | 148 | 10423 | 4, 18.4 % | 16.9 | – | – |
| Berufsbildner | `/trainer/learners` | 144 | 19458 | 4, 18.4 % | 16.9 | – | – |

Bei 1080 px Höhe scrollt mit den Demodaten keine dieser Seiten (Scrollweg 0 px, Rechner 104 px); 2560×1440 (Lernende `/dashboard`, `/grades`): LCP 192/152 ms, Glas 4 Elemente = 13.9 % des Fensters, Ruhe p95 16.9 ms.

## 2. Scrollen unter Glas bei 1920×540 (erzwungener Scrollweg, Median aus 3 Läufen)

| Rolle | Seite | Scrollweg px | Scroll p50 / p95 / max ms | lange Bilder > 33.4 ms | Layout ms (×) | Stil ms | Skript ms |
|---|---|---:|---|---:|---|---:|---:|
| Lernende | `/grades` | 0 | 16.7 / 16.9 / 24.3 | 0/150 | 0 (0) | 0 | 0.7 |
| Lernende | `/grades/calculator` | 644 | 16.6 / 19.2 / 27.4 | 0/150 | 4.4 (17) | 11.1 | 8.5 |
| Lernende | `/dashboard` | 382 | 16.7 / 20.2 / 27 | 0/150 | 0.3 (2) | 11.2 | 9.5 |
| Admin | `/admin/reports/grades` | 1658 | 16.7 / 20.3 / 23.6 | 0/150 | 0.5 (2) | 25.9 | 10.2 |
| Admin | `/admin/learners` | 464 | 16.7 / 19.2 / 23.8 | 0/150 | 0.4 (2) | 22.5 | 9.3 |

## 3. Empfindlichkeit des Instruments: dieselben Seiten mit und ohne Glas (`--ohne-glas` = `data-transparenz="reduziert"`, 1920×540, 3 Läufe)

| Seite | Zustand | Glas | Scroll p95 / max ms | Palette: Tippen p95 / max ms | Schliessen p95 ms |
|---|---|---|---|---|---:|
| `/grades` | mit Glas | 4 (23.2 %) | 16.9 / 24.9 | 27.6 / 32.8 | 23.8 |
| `/dashboard` | mit Glas | 4 (23.2 %) | 19.3 / 27.8 | 29.4 / 35.3 | 25.1 |
| `/grades` | ohne Glas | 2 (1.2 %) | 16.9 / 17.8 | 16.9 / 17.3 | 17.3 |
| `/dashboard` | ohne Glas | 2 (1.2 %) | 17 / 19 | 17.1 / 18.2 | 17.2 |

Lesart: 16.7–16.9 ms ist der 60-Hz-Takt (Boden). Mit Glas kostet das Tippen in der Befehlspalette (Overlay mit `blur(28px)` über 45 % des Fensters) p95 ≈ 28–29 ms, also fast zwei Bildperioden; ohne Glas liegt es auf dem Takt. Scrollen unter Leiste und Kapseln kostet p95 ≈ +2 ms und max ≈ +9 ms. Das Instrument sieht Glas also über die Bilddauern (rAF); die DevTools-Trace-Summen (`--trace`, Raster/Commit) sind nur Zusatzinformation, weil Headless ohne Drosselung mehr Bilder zeichnet, sobald es nicht mehr auf den Rasterer wartet.

Nebenbefund: vor dem Lauf behielten unter `data-transparenz="reduziert"` zwei Glaskapseln (`np-glas-gruppe::before`) ihren Weichzeichner, weil `.np-glas-gruppe::before` innerhalb von `:is()` ungültig ist und still verworfen wird (Selectors 4: Pseudo-Elemente sind in `:is()` nicht erlaubt). Behoben (eigene Regel), danach Glas 0 (0 %), Tippen p95 17 ms.

## 4. Kontrast: kleinste Glas-Deckung je Theme (`node tools/pruefung/kontrast.mjs --minimum`)

Spalte 1: Deckung von `--card`, bei der `text` **und** `muted` ihre Schwelle (4.5:1, Kontrast-Theme 7:1) über **jeder** Token-Unterlage halten (Grund, Karte, Akzent, sechs Diagramm- und vier Notenfarben). Spalte 2: nur über Grund/`surface-2`. Spalte 3: mit Scrollkante, d. h. die Unterlage ist vorher mit `--bg` zu 0.72 abgeblendet (zweiter Stopp des Verlaufs in `np-symbolleiste::before`).

```

Kleinste Glas-Deckung (card über jeder Token-Unterlage), bei der text UND muted ihre Schwelle halten:
  gletscher/hell       0.91   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.73
  gletscher/dunkel     0.85   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.26
  sandstein/hell       0.83   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.45
  sandstein/dunkel     0.81   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.21
  pflaume/hell         0.83   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.43
  pflaume/dunkel       0.79   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.10
  graphit/hell         0.85   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.51
  graphit/dunkel       0.84   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.37
  wald/hell            0.81   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.40
  wald/dunkel          0.80   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.15
  abendrot/hell        0.79   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.35
  abendrot/dunkel      0.81   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.19
  papier/hell          0.84   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.51
  papier/dunkel        0.83   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.30
  mitternacht/hell     0.80   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.35
  mitternacht/dunkel   0.72   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.00
  fjord/hell           0.81   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.40
  fjord/dunkel         0.80   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.15
  bernstein/hell       0.83   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.44
  bernstein/dunkel     0.83   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.26
  schiefer/hell        0.80   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.37
  schiefer/dunkel      0.80   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.19
  kontrast/hell        0.82   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.37
  kontrast/dunkel      0.78   nur über Grund/surface-2: 0.00   mit Scrollkante (bg 0.72): 0.15

Materialien aus app.css: np-glas hell card/0.8 dunkel card/0.78 · glass-bar hell card/0.8 dunkel card/0.8 · glass-seitenleiste hell card/0.8 dunkel card/0.8 · glass-overlay hell card/0.86 dunkel surface-2/0.92 · np-glas-gruppe hell card/0.78 dunkel card/0.74
24 Theme-Blöcke, 14 Akzentblöcke, 3628 Paare geprüft, 0 Pflichtverstösse, 11 Hinweise unter Schwelle.
```

Folgerungen für den Entwurf (R6):
- Heutige Materialien liegen bei 0.74–0.92. Ohne Scrollkante bräuchte Text auf Glas über einem Akzentknopf oder einer Diagrammfarbe 0.72–0.91 – «mehr Transparenz» bei statischer Textfarbe ist dort nicht möglich.
- Mit Scrollkante (Inhalt blendet Richtung Grund ab, bevor er die Schrift erreicht) halten dunkle Themes schon ab 0.00–0.37, helle ab 0.35–0.73. Transparenz wird also über die Kante gekauft, nicht über die Deckung.
- Die schwebende Seitenleiste liegt auf `position: fixed` neben der Hauptspalte (`np-hauptspalte` rückt per `padding-left` aus); unter ihr liegt nur der Seitengrund. Dort ist jede Deckung lesbar – ein eigener, bewusst gestalteter Hintergrund hinter der Leiste (ruhiger Verlauf aus `--bg`-nahen Tönen) gibt dem Glas erst etwas zu brechen und muss als Unterlage mitgeprüft werden.
- `ThemeKontrastTest` prüft die Materialien seit 03.10.2026 mit (Deckungen direkt aus `app.css`).

## 5. Berufsbildner (michael.baumann, nachgetragen 03.10.2026, Median aus 3 Läufen)

Gemessen, während zwei Workflow-Agents auf derselben Maschine liefen (CPU-Last); R6-00 misst mit denselben Argumenten nach. Rohdaten lagen im Scratch (`r6/leistung-baseline-trainer-*.json`).

Scrollen unter Glas bei 1920×540 (`--hoehe=540 --laeufe=3`):

| Seite | Scrollweg px | Scroll p50 / p95 / max ms | lange Bilder > 33.4 ms | Layout ms (×) | Stil ms | Skript ms |
|---|---:|---|---:|---|---:|---:|
| `/trainer` | 234 | 16.7 / 17.6 / 23.6 | 0/150 | 0.4 (2) | 14.2 | 8.2 |
| `/trainer/learners` | 0 | 16.7 / 16.9 / 25.3 | 0/150 | 0 (0) | 0 | 0.7 |
| `/trainer/learners/1` | 1028 | 16.7 / 19.6 / 24 | 0/150 | 0.4 (2) | 30.7 | 8.5 |
| `/trainer/learners/1/grades` | 1568 | 16.7 / 19.8 / 28.7 | 0/150 | 0.4 (2) | 5 | 8.6 |

Palette bei 1920×1080 (`--laeufe=3 --palette`):

| Seite | LCP ms | Knoten | Ruhe p95 ms | Palette öffnet ms | Tippen p95 / max ms | Schliessen p95 ms |
|---|---:|---:|---:|---:|---|---:|
| `/trainer` | 156 | 10565 | 16.9 | 26.3 | 31.5 / 39.2 | 36.4 |
| `/trainer/learners` | 140 | 20026 | 16.9 | 32.6 | 31.2 / 34.6 | 28.4 |
| `/trainer/learners/1` | 212 | 30335 | 17 | 31.9 | 33.7 / 44.5 | 38.7 |

Auffällig: `/trainer/learners/1` scrollt bei 1080 px Höhe 488 px und zeigte dabei p95 29 ms mit 4 langen Bildern – bei 540 px Höhe derselbe Weg mit 19.6 ms. Vermutlich die parallele CPU-Last; R6-00 klärt das durch Wiederholung, bevor der Wert als Grenze gilt.
