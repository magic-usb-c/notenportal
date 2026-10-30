# R6-12 – Leistungs-, Kontrast- und Sichtabnahme nach dem GUI-Rebuild (30.10.2026)

Abnahmebericht zu R6-12 (`docs/auftrag/GUI-R6.md` §4.2, §8, §11). Gegenstück zu `r6-leistung-baseline.md`: gleiche Tabellen, dazu die Spalten vorher (derselbe Rechner), Differenz, Grenze und erfüllt. Alle Werte stammen aus den Messdateien in `scratchpad/r6messung/` (`vergleich.md`, `vorher-*.json`, `nachher-*.json`) und aus dem Prüfergebnis der Schlussabnahme; was dort fehlt, steht als «nicht gemessen» da. Nichts ist geschätzt.

## Kurzfassung

- **Glas:** Ruhe 18.4 % → 0.6 % (Grenze ≤ 2 %), Palette 34.6 % → 15.6 % (Grenze ≤ 18 %), auf allen elf gemessenen Seiten erfüllt. Glasanteil Menü 3.1 % (Grenze ≤ 12 %, Nachmessung).
- **Bildzeiten:** Scrollen unter Glas (540 px Höhe) und Tippen in der Palette sind gegenüber derselben Maschine «vorher» durchgehend besser (Scroll p95 39–47 ms → 17 ms, lange Bilder 109–142 → 0 von 150; Tippen p95 Minimum 65–79 ms → 24–39 ms). Das Ziel Tippen ≤ 25 ms ist auf dieser Maschine nicht belegt (Median überall über 29 ms; Abschnitt 5 Punkt 5).
- **DOM-Knoten:** das alte Mass zählte abgehängte Knoten früherer Seiten mit (Abschnitt 2.8). Bereinigt: `/dashboard` 6 342 (vorher 3 628), `/grades` 9 156 (7 378), `/admin/learners` 7 186 (4 742) – alle drei über «vorher + 10 %»; vor allem `<details>`-Tabellen der Diagramme und die vorab gerenderte Suchpalette; Bildzeiten nicht schlechter.
- **Nicht erfüllt:** (b) Ruhe p95 ≤ 17.0 ms auf drei Seiten (17.4, 17.2, 17.1 ms); (c) LCP gegen die Basislinie, weil der Rechner heute langsamer ist als am 03.10. – im Vergleich mit vorher derselben Maschine erfüllt; (d) Tippen `/exams` gegen die Basislinie (34.9 gegen 32.9 ms).
- **Kontrast und Regel-Greps:** alle Modi Exit 0, 0 Pflichtverstösse, alle Greps leer.
- **Bilder, Rundgang, UI-Check:** Rundgang für alle vier Konten ohne Layoutbefund; 34 bestätigte Befunde (16 mittel, 18 niedrig), 16 verworfen. Behoben in Commit 20739bf (Abschnitt 4.3, bewusst Gelassenes in `docs/audit-backlog.md`).

## 1 Messbedingungen

| Punkt | Angabe |
|---|---|
| Datum | 30.10.2026. Messdateien 14:10–14:23 (Dateizeiten), Prüfläufe und Bilder am selben Tag |
| Werkzeug | `tools/pruefung/r6-vergleich.sh` (`leistung.mjs` und `vergleich.mjs`), Headless-Chromium mit Software-Raster, Demo-Konten nina.huber, laura.frei, michael.baumann |
| Argumente | 1920×1080: `--laeufe=3 --palette`; 1920×540: `--hoehe=540 --laeufe=3`; Dunkelmodus. Median aus 3 Läufen; Tippen zusätzlich als Minimum aus 3 (Regel aus Basislinie §5) |
| Reihenfolge | je Rolle abwechselnd vorher, nachher (Ablauf von `r6-vergleich.sh`) |
| «vorher» | zweite Instanz desselben Repos auf dem Stand vor R6 (Git-Worktree im Scratch-Ordner, `artisan serve` auf `http://127.0.0.1:8098`), gegen dieselbe Demo-Datenbank |
| «nachher» | Demo-Server `http://127.0.0.1:8099` |
| Basislinie | `r6-leistung-baseline.md` vom 03.10.2026, **Cloud-Sitzung**; vorher und nachher stammen von der **VM** vom 30.10.2026. Nur vorher gegen nachher läuft auf derselben Maschine, die Spalte Basislinie ist eine andere Maschine |
| Differenz | nachher − vorher (gleiche Maschine) |
| Ruhiges Fenster | ja: die Hauptsitzung startete `r6-vergleich.sh` erst, nachdem alle Agents, Workflows und die Testsuite beendet waren, und startete bis zum Ende der Messung nichts anderes (Sitzungsprotokoll 30.10.2026) |
| Nicht in der Messung | 2560×1440, `--menue`, `--ohne-glas`, `/trainer/learners/1/grades` (nicht in der Seitenliste des Skripts) |

**Rechner heute langsamer als am 03.10.** Derselbe alte Code («vorher») misst heute Tippen im Minimum 64.7–79.3 ms (Basislinie 31.2–40.6 ms) und LCP 332–532 ms (Basislinie 136–200 ms). Absolute Zeiten (Tippen, LCP) lassen sich darum nicht gegen die Basislinie halten; Glasanteil, Flächenzahl, Knotenzahl und Bildtakt sind rechnerunabhängig. Die Tabellen führen beide Urteile: **erfüllt** gegen die Grenze wörtlich nach §4.2 (also gegen die Basislinie), **gleiche Maschine** gegen «vorher».

**Mängel der vorher-Läufe.** Die vorher-Instanz lieferte für `/trainer` HTTP 500 (135 052 Knoten, LCP 1352 ms, Scrollweg 2406 px bei 1080 – eine Fehlerseite). Alle vorher-Zellen dieser Seite sind «nicht vergleichbar (HTTP 500)». Verdächtig ist auch vorher `/trainer/learners` mit 5 817 Knoten bei 1080 px, aber 185 340 bei 540 px; Basislinie 19 458. Die vorher-Knotenzahlen weichen mehrfach von der Basislinie ab (`/grades/calculator` 14 985 gegen 32 049, `/admin/reports/grades` 10 545 gegen 21 228), die Demodaten waren am 03.10. offenbar andere.

## 2 Bildzeiten und Fläche

### 2.1 Glasanteil Ruhe, 1920×1080 (Grenze ≤ 2 %, Ziel ≈ 0.6 %)

| Rolle | Seite | Basislinie (Flächen, %) | vorher (Flächen, %) | nachher (Flächen, %) | Differenz | Grenze | erfüllt |
|---|---|---|---|---|---:|---|---|
| nina.huber | `/dashboard` | 4, 18.4 | 4, 18.4 | 2, 0.6 | -17.8 | ≤ 2 | ja |
| nina.huber | `/grades` | 4, 18.4 | 4, 18.4 | 2, 0.6 | -17.8 | ≤ 2 | ja |
| nina.huber | `/grades/calculator` | 4, 18.4 | 4, 18.4 | 2, 0.6 | -17.8 | ≤ 2 | ja |
| nina.huber | `/exams` | 4, 18.4 | 4, 18.4 | 2, 0.6 | -17.8 | ≤ 2 | ja |
| nina.huber | `/qualification` | – | 4, 18.4 | 2, 0.6 | -17.8 | ≤ 2 | ja |
| laura.frei | `/admin` | 4, 18.4 | 4, 18.4 | 2, 0.6 | -17.8 | ≤ 2 | ja |
| laura.frei | `/admin/reports/grades` | 4, 18.4 | 4, 18.4 | 2, 0.6 | -17.8 | ≤ 2 | ja |
| laura.frei | `/admin/learners` | 4, 18.4 | 4, 18.4 | 2, 0.6 | -17.8 | ≤ 2 | ja |
| michael.baumann | `/trainer` | 4, 18.4 | nicht vergleichbar (HTTP 500) | 2, 0.6 | – | ≤ 2 | ja |
| michael.baumann | `/trainer/learners` | 4, 18.4 | 4, 18.4 | 2, 0.6 | -17.8 | ≤ 2 | ja |
| michael.baumann | `/trainer/learners/1` | 4, 18.4 | 4, 18.4 | 2, 0.6 | -17.8 | ≤ 2 | ja |

Bei 1920×540: Basislinie 4 Flächen, 23.2 %; nachher 2 Flächen, 1.2 % auf allen elf Seiten (vorher 4, 23.2 %). Die zwei verbliebenen Flächen sind die Glaskapseln (`np-glas-gruppe`). Leiste und Symbolleiste haben keinen Weichzeichner mehr.

### 2.2 Glasanteil Palette offen, 1920×1080 (Grenze ≤ 18 %)

| Rolle | Seite | Basislinie (Flächen, %) | vorher (Flächen, %) | nachher (Flächen, %) | Differenz | Grenze | erfüllt |
|---|---|---|---|---|---:|---|---|
| nina.huber | `/dashboard` | 5, 34.6 | 5, 34.6 | 1, 15.6 | -19.0 | ≤ 18 | ja |
| nina.huber | `/grades` | 5, 34.6 | 5, 34.6 | 1, 15.6 | -19.0 | ≤ 18 | ja |
| nina.huber | `/grades/calculator` | 5, 34.6 | 5, 34.6 | 1, 15.6 | -19.0 | ≤ 18 | ja |
| nina.huber | `/exams` | 5, 34.6 | 5, 34.6 | 1, 15.6 | -19.0 | ≤ 18 | ja |
| nina.huber | `/qualification` | nicht angegeben | 5, 34.6 | 1, 15.6 | -19.0 | ≤ 18 | ja |
| laura.frei | `/admin` | 5, 34.6 | 5, 34.6 | 1, 15.6 | -19.0 | ≤ 18 | ja |
| laura.frei | `/admin/reports/grades` | 5, 34.6 | 5, 34.6 | 1, 15.6 | -19.0 | ≤ 18 | ja |
| laura.frei | `/admin/learners` | 5, 34.6 | 5, 34.6 | 1, 15.6 | -19.0 | ≤ 18 | ja |
| michael.baumann | `/trainer` | nicht angegeben | nicht gemessen (HTTP 500) | 1, 15.6 | – | ≤ 18 | ja |
| michael.baumann | `/trainer/learners` | nicht angegeben | 5, 34.6 | 1, 15.6 | -19.0 | ≤ 18 | ja |
| michael.baumann | `/trainer/learners/1` | nicht angegeben | 5, 34.6 | 1, 15.6 | -19.0 | ≤ 18 | ja |

Nur das Overlay der Palette (`glass-overlay`) ist noch Glas (1 Fläche, 15.6 %).

### 2.3 Glasanteil Menü offen, 1920×1080 `--menue=<Auslöser>` (Grenze ≤ 12 %)

| Seite | Basislinie | vorher | nachher | Differenz | Grenze | erfüllt |
|---|---|---|---|---|---|---|
| `/admin` (Benutzermenü, `button[aria-label^="Konto"]`) | 5 Flächen, 20.9 % (Hover p95 23.9 ms) | nicht gemessen | 3 Flächen, 3.1 % (Nachmessung unter Last; Hover nicht gewertet) | – | ≤ 12 % | ja |

Der Lauf mit `--menue` ist in dieser Abnahme nicht gemacht worden.

### 2.4 Tippen p95 in der Palette, 1920×1080 `--laeufe=3 --palette` (Grenze ≤ Basislinie, Ziel ≤ 25 ms)

Angewandte Regel nach Basislinie §5: Tippen wird als Minimum aus 3 Läufen verglichen, weil es stark streut (T = 10.2 ms). Basislinie je Zeile: §1, bei Berufsbildnern §5; bei `/dashboard` und `/admin/reports/grades` das Minimum aus §5. In Klammern die drei Einzelläufe.

| Rolle | Seite | Basislinie | vorher Min (Läufe) | nachher Min (Läufe) | nachher Median | Differenz Min (nachher − vorher) | Grenze | erfüllt (Basislinie) | gleiche Maschine | Abstand Min − 25 ms |
|---|---|---|---|---|---:|---:|---|---|---|---:|
| nina.huber | `/dashboard` | 33.8 (Min §5; Median §1: 35.2) | 73 (76.3; 73.3; 73) | 33.8 (33.8; 41.8; 38.6) | 38.6 | -39.2 | ≤ Basislinie | ja | ja | +8.8 |
| nina.huber | `/grades` | 35.9 | 75.1 (78.1; 75.1; 76.6) | 31.9 (42.6; 31.9; 40) | 40 | -43.2 | ≤ Basislinie | ja | ja | +6.9 |
| nina.huber | `/grades/calculator` | 35.5 | 78.5 (79; 83.9; 78.5) | 32.3 (39.3; 32.3; 32.5) | 32.5 | -46.2 | ≤ Basislinie | ja | ja | +7.3 |
| nina.huber | `/exams` | 32.9 | 70.8 (72.8; 74.5; 70.8) | 34.9 (40.3; 34.9; 39.9) | 39.9 | -35.9 | ≤ Basislinie | nein | ja | +9.9 |
| nina.huber | `/qualification` | – | 67.1 (76.2; 79.4; 67.1) | 32.1 (32.1; 38.7; 41.6) | 38.7 | -35.0 | ≤ Basislinie | – | ja | +7.1 |
| laura.frei | `/admin` | 40.2 | 68.1 (83.6; 68.1; 104) | 28.9 (47.1; 28.9; 29.2) | 29.2 | -39.2 | ≤ Basislinie | ja | ja | +3.9 |
| laura.frei | `/admin/reports/grades` | 40.6 (Min §5; Median §1: 38.3) | 78.5 (81; 78.5; 81) | 38.6 (46.3; 38.6; 42.8) | 42.8 | -39.9 | ≤ Basislinie | ja | ja | +13.6 |
| laura.frei | `/admin/learners` | 36.5 | 68.7 (84.3; 100.3; 68.7) | 27.2 (32.3; 37.5; 27.2) | 32.3 | -41.5 | ≤ Basislinie | ja | ja | +2.2 |
| michael.baumann | `/trainer` | 33.0 (§5) | nicht vergleichbar (HTTP 500) | 27.3 (38.5; 42.8; 27.3) | 38.5 | – | ≤ Basislinie | ja | nicht vergleichbar (HTTP 500) | +2.3 |
| michael.baumann | `/trainer/learners` | 31.2 (§5) | 64.7 (76.4; 65.2; 64.7) | 29.9 (40; 29.9; 40.9) | 40 | -34.8 | ≤ Basislinie | ja | ja | +4.9 |
| michael.baumann | `/trainer/learners/1` | 35.4 (§5) | 79.3 (79.3; 81; 81.7) | 24.2 (24.2; 43.3; 50.9) | 43.3 | -55.1 | ≤ Basislinie | ja | ja | -0.8 |

Nach der Regel aus Basislinie §5 (Minimum aus 3, weil Tippen stark streut) überschreitet nur `/exams` die Basislinie (34.9 gegen 32.9 ms), `/dashboard` liegt genau darauf. Wörtlich nach §4.2 (Median) überschreiten 6 von 9 vergleichbaren Seiten die Basislinie (z. B. `/dashboard` 38.6 gegen 35.2, `/trainer/learners/1` 43.3 gegen 35.4) – die Basislinie stammt aber von einer 2.3- bis 2.8-mal schnelleren Maschine (Abschnitt 1), das Urteil gegen sie ist darum für beide Lesarten nicht tragfähig. Gegen vorher auf derselben Maschine ist jede Zeile besser, im Minimum wie im Median (−35 bis −55 ms). Das **Ziel ≤ 25 ms** ist auf dieser Maschine nirgends belegt: der Median liegt auf jeder Seite über 29 ms; einzig ein Einzellauf auf `/trainer/learners/1` (24.2 ms, Median 43.3) liegt darunter. Ein einzelner Lauf ist oft 10 ms und mehr schlechter als das Minimum.

### 2.5 Scroll p95 unter Glas, 1920×540 `--hoehe=540 --laeufe=3` (Grenze ≤ Basislinie + T mit T = 1.1 ms, lange Bilder 0 von 150)

Basislinie: §2, bei Berufsbildnern §5 (der Plan führt dort die früheren, unter Last gemessenen Werte 17.6 / 19.6 / 19.8). Wo kein Basiswert in §4.2 steht, gilt nur der Vergleich mit vorher.

| Rolle | Seite | Basislinie p95 | vorher p95 (Scrollweg) | nachher p95 (Scrollweg) | Differenz | lange Bilder vorher → nachher (je Median, von 150) | Grenze | erfüllt (Basislinie) | gleiche Maschine |
|---|---|---|---|---|---:|---|---|---|---|
| nina.huber | `/dashboard` | 20.2 | 40.6 (382 px) | 17.2 (413 px) | -23.4 | 138 → 0 | ≤ Basislinie + 1.1, 0/150 | ja | ja |
| nina.huber | `/grades` | 16.9 | 17 (0 px) | 16.9 (539 px) | -0.1 | 1 → 0 | ≤ Basislinie + 1.1, 0/150 | ja | ja |
| nina.huber | `/grades/calculator` | 19.2 | 46.8 (644 px) | 16.9 (701 px) | -29.9 | 125 → 0 | ≤ Basislinie + 1.1, 0/150 | ja | ja |
| nina.huber | `/exams` | – | 40.4 (72 px) | 16.9 (323 px) | -23.5 | 137 → 0 | ≤ Basislinie + 1.1, 0/150 | – | ja |
| nina.huber | `/qualification` | – | 39.4 (40 px) | 17.1 (225 px) | -22.3 | 109 → 0 | ≤ Basislinie + 1.1, 0/150 | – | ja |
| laura.frei | `/admin` | – | 45.8 (188 px) | 17.2 (191 px) | -28.6 | 142 → 0 | ≤ Basislinie + 1.1, 0/150 | – | ja |
| laura.frei | `/admin/reports/grades` | 20.3 | 40.6 (1658 px) | 16.9 (1741 px) | -23.7 | 139 → 0 | ≤ Basislinie + 1.1, 0/150 | ja | ja |
| laura.frei | `/admin/learners` | 19.2 | 40.2 (608 px) | 16.9 (691 px) | -23.3 | 128 → 0 | ≤ Basislinie + 1.1, 0/150 | ja | ja |
| michael.baumann | `/trainer` | 18.3 (§5; Plan: 17.6) | nicht vergleichbar (HTTP 500) | 17 (451 px) | – | – → 0 | ≤ Basislinie + 1.1, 0/150 | ja | nicht vergleichbar (HTTP 500) |
| michael.baumann | `/trainer/learners` | – | 43.9 (12 px) | 17 (165 px) | -26.9 | 139 → 0 | ≤ Basislinie + 1.1, 0/150 | – | ja |
| michael.baumann | `/trainer/learners/1` | 20.3 (§5; Plan: 19.6) | 43.1 (1118 px) | 16.9 (1275 px) | -26.2 | 137 → 0 | ≤ Basislinie + 1.1, 0/150 | ja | ja |
| michael.baumann | `/trainer/learners/1/grades` | 18.5 (§5; Plan: 19.8) | nicht gemessen | nicht gemessen | – | – | ≤ Basislinie + 1.1, 0/150 | nicht gemessen | nicht gemessen |

Zu lesen: Der Scrollweg ist nachher oft länger als vorher (z. B. `/grades` 539 px gegen 0 px); der Test ist also nicht leichter geworden. Vorher bei `/grades` ist ein Ruhewert (Scrollweg 0). Einzelläufe mit langen Bildern: nina.huber `/dashboard` Lauf 3 (1 langes Bild, p95 25.9 ms), michael.baumann `/trainer/learners/1` Lauf 2 (1 langes Bild, p95 20.8 ms); beide Mediane liegen bei 0. Bei 1920×1080 (nicht in §4.2) schneiden zwei Befunde der Basislinie nachher so ab: `/trainer/learners/1` 17.6 ms bei 735 px (Basislinie §5: 29.6 ms, 3 lange Bilder; der in der Basislinie an R6-04/R6-09 übergebene Befund), `/admin/reports/grades` 18.1 ms bei 1201 px (Basislinie §5: 33.0–34.1 ms, 5–9 lange Bilder).

### 2.6 Ruhe p95 und Animationen, 1920×1080 (Grenze ≤ 17.0 ms, 0 Animationen)

| Rolle | Seite | Basislinie | vorher p95 (Läufe) | nachher p95 (Läufe) | Differenz | Animationen vorher → nachher | Grenze | erfüllt | gleiche Maschine (nachher ≤ vorher) |
|---|---|---|---|---|---:|---|---|---|---|
| nina.huber | `/dashboard` | 17.0 | 16.9 (16.9; 16.9; 16.9) | 16.9 (16.9; 17; 16.9) | 0 | 0 → 0 | ≤ 17.0 ms, 0 Animationen | ja | ja |
| nina.huber | `/grades` | 16.9 | 17 (17.2; 17; 16.9) | 17.2 (16.9; 17.2; 17.2) | +0.2 | 0 → 0 | ≤ 17.0 ms, 0 Animationen | nein | nein |
| nina.huber | `/grades/calculator` | 16.9 | 16.9 (16.9; 16.9; 17.1) | 17 (17; 16.9; 17) | +0.1 | 0 → 0 | ≤ 17.0 ms, 0 Animationen | ja | nein |
| nina.huber | `/exams` | 16.9 | 16.9 (16.8; 16.9; 16.9) | 16.9 (16.9; 17; 16.9) | 0 | 0 → 0 | ≤ 17.0 ms, 0 Animationen | ja | ja |
| nina.huber | `/qualification` | – | 17 (19.4; 17; 16.9) | 16.9 (16.9; 17.2; 16.9) | -0.1 | 0 → 0 | ≤ 17.0 ms, 0 Animationen | ja | ja |
| laura.frei | `/admin` | 16.9 | 17.3 (17.3; 17.8; 16.9) | 16.9 (16.9; 16.9; 17) | -0.4 | 0 → 0 | ≤ 17.0 ms, 0 Animationen | ja | ja |
| laura.frei | `/admin/reports/grades` | 16.9 | 17 (16.9; 17; 17) | 17.4 (17.6; 17.4; 16.9) | +0.4 | 0 → 0 | ≤ 17.0 ms, 0 Animationen | nein | nein |
| laura.frei | `/admin/learners` | 16.9 | 17.5 (17; 17.5; 19.4) | 17 (17.5; 16.9; 17) | -0.5 | 0 → 0 | ≤ 17.0 ms, 0 Animationen | ja | ja |
| michael.baumann | `/trainer` | 16.9 | nicht vergleichbar (HTTP 500) | 16.9 (17; 16.9; 16.9) | – | – → 0 | ≤ 17.0 ms, 0 Animationen | ja | – |
| michael.baumann | `/trainer/learners` | 16.9 | 16.9 (20; 16.9; 16.9) | 17.1 (17; 17.1; 20.9) | +0.2 | 0 → 0 | ≤ 17.0 ms, 0 Animationen | nein | nein |
| michael.baumann | `/trainer/learners/1` | 16.9 | 17.5 (19.4; 17.5; 16.9) | 16.9 (17; 16.9; 16.9) | -0.6 | 0 → 0 | ≤ 17.0 ms, 0 Animationen | ja | ja |

Drei Seiten überschreiten 17.0 ms: `/admin/reports/grades` 17.4, `/grades` 17.2, `/trainer/learners` 17.1 ms. Die Überschreitung liegt bei 0.1 bis 0.4 ms; die Basislinie gibt T für Ruhe mit 0.3 ms an, und vorher derselben Maschine liegt in einzelnen Läufen und Medianen bis 17.8 ms (`/admin` 17.3, `/admin/learners` 17.5, `/trainer/learners/1` 17.5). Die Grenze in §4.2 ist ohne T geschrieben, deshalb steht hier «nein». Animationen in Ruhe: 0 auf allen Seiten.

### 2.7 LCP, 1920×1080 (Grenze ≤ Basislinie + 10 %)

LCP streut stark; der erste Lauf einer Seite ist oft ein Ausreisser (z. B. 684, 736, 700 ms). Verglichen wird der Median.

| Rolle | Seite | Basislinie | vorher (Läufe) | nachher (Läufe) | Differenz | Grenze | erfüllt (Basislinie) | gleiche Maschine (≤ vorher + 10 %) |
|---|---|---:|---|---|---:|---|---|---|
| nina.huber | `/dashboard` | 164 | 440 (680; 440; 436) | 368 (736; 368; 368) | -72 | ≤ 180 ms | nein | ja |
| nina.huber | `/grades` | 168 | 448 (448; 316; 516) | 356 (356; 340; 604) | -92 | ≤ 185 ms | nein | ja |
| nina.huber | `/grades/calculator` | 176 | 424 (392; 432; 424) | 456 (460; 456; 436) | +32 | ≤ 194 ms | nein | ja |
| nina.huber | `/exams` | 136 | 380 (372; 380; 396) | 368 (368; 308; 384) | -12 | ≤ 150 ms | nein | ja |
| nina.huber | `/qualification` | – | 288 (272; 288; 328) | 268 (276; 256; 268) | -20 | – | – | ja |
| laura.frei | `/admin` | 148 | 356 (292; 356; 384) | 308 (684; 304; 308) | -48 | ≤ 163 ms | nein | ja |
| laura.frei | `/admin/reports/grades` | 188 | 424 (424; 456; 396) | 368 (368; 376; 316) | -56 | ≤ 207 ms | nein | ja |
| laura.frei | `/admin/learners` | 172 | 408 (420; 392; 408) | 336 (384; 336; 336) | -72 | ≤ 189 ms | nein | ja |
| michael.baumann | `/trainer` | 148 (§5: 128) | nicht vergleichbar (HTTP 500) | 344 (368; 344; 272) | – | ≤ 163 ms | nein | – |
| michael.baumann | `/trainer/learners` | 144 (§5: 116) | 332 (488; 332; 332) | 256 (328; 256; 244) | -76 | ≤ 158 ms | nein | ja |
| michael.baumann | `/trainer/learners/1` | 200 (§5) | 532 (784; 464; 532) | 408 (424; 396; 408) | -124 | ≤ 220 ms | nein | ja |

Gegen die Basislinie überall «nein», aber nur wegen des langsameren Rechners (vorher ist heute 2.3- bis 2.8-mal so hoch wie die Basislinie). Gegen vorher ist LCP auf allen vergleichbaren Seiten gleich oder besser (−12 bis −124 ms); die einzige Verschlechterung ist `/grades/calculator` (+32 ms, +7.5 %, innerhalb 10 %). Für `/trainer` fehlt der Vergleichswert (HTTP 500), `/qualification` hat keinen Basiswert.

### 2.8 DOM-Knoten, 1920×1080 (Grenze ≤ Basislinie + 10 %, Zeilen in §4.2: `/dashboard`, `/grades`, `/admin/learners`)

| Rolle | Seite | Basislinie | vorher | nachher | Differenz (nachher − vorher) | nachher gegen Basislinie | nachher gegen vorher | Grenze (§4.2) | erfüllt |
|---|---|---:|---:|---:|---:|---:|---:|---|---|
| nina.huber | `/dashboard` | 11458 | 11574 | 19813 | +8239 | +73 % | +71 % | ≤ 12604 | nein |
| nina.huber | `/grades` | 18971 | 23140 | 26511 | +3371 | +40 % | +15 % | ≤ 20868 | nein |
| nina.huber | `/grades/calculator` | 32049 | 14985 | 20925 | +5940 | -35 % | +40 % | keine Zeile in §4.2 | – |
| nina.huber | `/exams` | 9514 | 18210 | 16143 | -2067 | +70 % | -11 % | keine Zeile in §4.2 | – |
| nina.huber | `/qualification` | – | 10002 | 15453 | +5451 | – | +54 % | keine Zeile in §4.2 | – |
| laura.frei | `/admin` | 11948 | 12100 | 17104 | +5004 | +43 % | +41 % | keine Zeile in §4.2 | – |
| laura.frei | `/admin/reports/grades` | 21228 | 10545 | 19757 | +9212 | -7 % | +87 % | keine Zeile in §4.2 | – |
| laura.frei | `/admin/learners` | 14897 | 14524 | 19942 | +5418 | +34 % | +37 % | ≤ 16387 | nein |
| michael.baumann | `/trainer` | 10423 (§5: 10565) | nicht vergleichbar (HTTP 500) | 14823 | – | +42 % | – | keine Zeile in §4.2 | – |
| michael.baumann | `/trainer/learners` | 19458 (§5: 20026) | 5817 | 23733 | +17916 | +22 % | +308 % | keine Zeile in §4.2 | – |
| michael.baumann | `/trainer/learners/1` | (§5: 30335) | 14651 | 20427 | +5776 | – | +39 % | keine Zeile in §4.2 | – |

**Das Mass war fehlerhaft (Nachtrag 30.10.2026).** `leistung.mjs` las `Nodes` aus `Performance.getMetrics`; die Zahl enthält auch die abgehängten Knoten früherer Seiten derselben Registerkarte, bis der Speicher bereinigt ist. Deshalb wuchs sie je Lauf um die ganze Vorseite (nachher `/dashboard` 13 253 → 19 813 → 26 370, vorher 7 684 → 11 574 → 15 464), und der «Median» war Lauf 2. Die Basislinie vom 03.10. entstand mit demselben Mass; ihre Knotenzahlen und damit die Grenzen in §4.2 hängen von der Seitenfolge ab. Seit dem Nachtrag ruft `leistung.mjs` vor dem Zählen `HeapProfiler.collectGarbage` auf. Nachmessung (3 Läufe, je Lauf gleich, Demo-DB frisch, beide Instanzen wie oben; lief, während ein Workflow arbeitete – für Knotenzahlen ohne Belang, Bildzeiten dieses Laufs sind nicht gewertet):

| Seite | vorher | nachher | Differenz | Grenze §4.2 (altes Mass) | vorher + 10 % (bereinigt) | erfüllt (bereinigt) |
|---|---:|---:|---:|---:|---:|---|
| `/dashboard` | 3 628 | 6 342 | +2 714 (+75 %) | ≤ 12 604 | ≤ 3 991 | nein |
| `/grades` | 7 378 | 9 156 | +1 778 (+24 %) | ≤ 20 868 | ≤ 8 116 | nein |
| `/admin/learners` | 4 742 | 7 186 | +2 444 (+52 %) | ≤ 16 387 | ≤ 5 216 | nein |

Die Grenze aus §4.2 ist mit dem alten Mass entstanden und hier nicht anwendbar. Mit demselben, bereinigten Mass verfehlen alle drei Seiten «vorher + 10 %» (GUI-R6.md §4.2, Nachtrag). Herkunft, gezählt am 30.10.2026 mit einem TreeWalker über das lebende Dokument (Elemente, Text, Kommentare; die Zahlen liegen unter `Nodes`, weil CDP auch interne Knoten zählt):

| Seite | vorher | nachher | Zuwachs | davon `<details>` | davon Suchpalette | Rest |
|---|---:|---:|---:|---:|---:|---:|
| `/dashboard` (nina.huber) | 1 931 | 3 651 | +1 720 | +1 284 | +364 | +72 |
| `/grades` (nina.huber) | 3 582 | 4 521 | +939 | +88 | +364 | +487 |
| `/admin/learners` (laura.frei) | 2 602 | 4 109 | +1 507 | +203 | +520 | +784 (davon SVG +286) |

Auf dem Dashboard sind es vor allem die Tabellenfassungen der Diagramme in `<details>` (Planentscheid «Tabelle in details»), auf jeder Seite die vorab gerenderten Einträge der Suchpalette (177 → 697 Knoten bei laura.frei); der Rest sind Ansagen, Symbole und Tabellenzeilen. Die Bildzeiten (Scroll, Ruhe, Tippen) sind gegen vorher auf derselben Maschine trotzdem nicht schlechter geworden. Künftig: Knoten nur mit dem bereinigten Mass und gegen «vorher» auf derselben Datenbank vergleichen.

## 3 Kontrast und Regel-Greps (Prüflauf 30.10.2026)

| Prüfung | Basislinie 03.10. | nachher | erfüllt |
|---|---|---|---|
| `node tools/pruefung/kontrast.mjs` | 3628 Paare, 0 Pflichtverstösse, 11 Hinweise unter Schwelle | Exit 0; 24 Theme-Blöcke, 14 Akzentblöcke, 4262 Paare, 0 Pflichtverstösse, 13 Hinweise unter Schwelle (keine Pflichtverstösse) | ja |
| `kontrast.mjs --minimum` | 24 Zeilen, Overlay dunkel surface-2/0.92 | Exit 0, 0 Pflichtverstösse; Overlay dunkel surface-2/0.94, hell card/0.92; die 24 Minima-Zeilen sind im Prüfergebnis nicht wiedergegeben | ja |
| `kontrast.mjs --glanz=0.07` | ohne Wirkung (kein `--glas-licht`) | Exit 0, 4262 Paare, 0 Pflichtverstösse | ja |
| `kontrast.mjs --stufen` | alle 12 dunklen Themes ok, `grund-hoch` fehlte noch | Exit 0, alle 12 ok; bg < grund-hoch < card < surface-2 streng aufsteigend (Gletscher 0.0080 < 0.0123 < 0.0175 < 0.0329) | ja |
| `grep -rn "motion-reduce:" resources/views` | – | leer | ja |
| `grep -nE "rgb\((255 255 255\|0 0 0) ?/" resources/css/app.css` | – | leer | ja |
| `grep -nE "systemGray\|system[A-Z]\|apple\.com\|#0066cc" resources/css/theme.css` | – | leer | ja |
| `grep -rnE "glass-bar\|glass-seitenleiste\|glass-btn\|np-btn-primary\|np-fade-in\|data-autohide" resources/` | – | leer | ja |
| `grep -n ":is([^)]*::" resources/css/app.css` | Fehler vorhanden (Nebenbefund §3) | leer | ja |
| `grep -rn view-transition-name resources/` | – | im Code nur `np-leiste-auswahl` (`navigation.blade.php:51`, `:75`) und `np-tab-auswahl` (`:119`, `:142`); `app.css:313`, `:841` sind Kommentare | ja |
| `grep -rn "ß" resources/views/ lang/` | – | leer | ja |

Die Zahl der Hinweise unter Schwelle ist von 11 auf 13 gestiegen; sie zählen nicht als Pflichtverstoss. Die Paarzahl steigt von 3628 über 4166 (nach R6-00) auf 4262.

## 4 Bilder, Rundgang, UI-Check

### 4.1 Bilder (Dunkel 1920, 2560, reduzierte Bewegung, reduzierte Transparenz, mehr Kontrast, Hell als Stichprobe)

| Rolle | Bilder | Ergebnis |
|---|---|---|
| nina.huber (Lernende) | 26 + 2 Ausschnitte, 5 Seiten, 6 Zustände | Flächenstufen, Kontrast, Glas korrekt; unter reduzierter Transparenz alles deckend (Seitenleiste `rgb(32,36,44)`, Overlay `rgb(46,51,61)`, kein `backdrop-filter`); 5 Befunde, 3 bestätigt |
| livia.gerber (Lernende ohne Noten) | 21, 4 Seiten, 5 Zustände + Hell | Layout, Leerzustände in Ordnung; 3 Mängel, 1 bestätigt |
| michael.baumann (Berufsbildner) | 21, 4 Seiten, 5 Zustände + Hell (nur `/trainer`) | keine hohen oder mittleren Mängel; 2 niedrige bestätigt |
| laura.frei (Admin) | 21, 4 Seiten, 5 Zustände + Hell (nur `/admin`) | kein Überlauf, kein Platzhalter; 5 Befunde, 3 bestätigt (davon 1 mittel) |

Alle Aufrufe lieferten HTTP 200 ohne Überlauf- oder JS-Meldung; die Zustandszeilen stimmten mit den Flags überein. Kein ß, kein abgeschnittener oder überlappender Text, kein Entwicklertext. Bilder unter `scratchpad/r6pruefung/bilder/<rolle>/`.

### 4.2 Rundgang (`rundgang.mjs <konto> --shots`, Dunkel 1920×1080)

| Konto | Seiten | Exit | Layoutbefunde |
|---|---:|---:|---|
| nina.huber | 19 | 0 | keine |
| livia.gerber | 18 | 0 | keine |
| michael.baumann | 21 | 0 | keine |
| laura.frei | 57 | 0 | keine |

Befundarten (UEBERLAUF, KLEIN, ABGESCHNITTEN, ELLIPSE, RAND, VORFAHR-CLIP, UEBERLAPPUNG, STATUS, LADEN, JS) über alle vier Ausgaben: 0 Treffer. Nur ein Lauf bei 1920 px, nicht bei 2560. Bilder unter `scratchpad/r6pruefung/rundgang/<konto>/`.

### 4.3 Bestätigte Befunde (34; behoben in Commit 20739bf)

Bildbefunde (9):

| ID | Schwere | Datei:Zeile | Titel · Fix |
|---|---|---|---|
| ADMIN-1 | mittel | `resources/views/admin/berichte/noten.blade.php:130` | Karte «Verteilung der Zeugnisnoten» kürzer als die rechte Spalte, leere Fläche darunter · `items-start` entfernen |
| LERNENDE-2 | niedrig | `resources/js/charts.js:600` | Notenverlauf: Zeitachse wechselt zwischen Ladungen · nach `document.fonts.ready` neu bauen |
| LERNENDE-3 | niedrig | `resources/views/lernender/noten/index.blade.php:213` | Prüfungsanzahl linksbündig · `text-right` ergänzen |
| LERNENDE-4 | niedrig | `resources/views/components/zeitleiste.blade.php:63` | Agenda leer: tote Tabellen-Aufklappung · Tabellen-Slot in `@if($zeilen !== [])` |
| OHNE-NOTEN-2 | niedrig | `resources/views/abschluss/index.blade.php:129` | Leerwert-Strich als dicker Balken, schief zur Beschriftung · immer `<x-note variante="hero">` rendern |
| BERUFSBILDNER-1 | niedrig | `resources/views/verwaltung/lernende/index.blade.php:64` | Filterleiste und Anzahl laufen über die Kartenbreite hinaus · Klasse an `x-filterleiste` durchreichen |
| BERUFSBILDNER-2 | niedrig | `resources/views/verwaltung/noten/index.blade.php:55` | Inhaltsbreite springt zwischen den Reitern Übersicht und Noten · `max-w-[100rem]` streichen |
| ADMIN-3 | niedrig | `resources/views/components/punktstreifen.blade.php:49` | Legende ohne Linienzeichen, Medianlinie geht unter · Zeichen in der Legende, Median auf `fill-text` |
| ADMIN-5 | niedrig | `resources/views/admin/berichte/noten.blade.php:380` | Statustext bricht mit Einzelwort «Note» um · `text-pretty` |

UI-Check Lernende (7):

| ID | Schwere | Datei:Zeile | Titel · Fix |
|---|---|---|---|
| UI-lernende-1 | mittel | `resources/views/abschluss/index.blade.php:190` | Baum-Tabelle ohne caption und Zeilenkopf · `caption` sr-only, `td` zu `th scope="row"` (Z. 213, 225) |
| UI-lernende-2 | mittel | `resources/views/lernender/noten/index.blade.php:174` | Tabelle je Kategorie ohne caption · `caption` sr-only mit `$g->name` |
| UI-lernende-3 | mittel | `resources/views/rechner/index.blade.php:263` | Tabellen Auswirkung und Promotion ohne caption und Zeilenkopf · caption, `th scope="row"` (Z. 281, 315) |
| UI-lernende-4 | mittel | `resources/views/components/verlauf.blade.php:41` | Verlauf-Tabelle: Zahlenspalten links, nur die letzte rechtsbündig · `rechts`-Indizes im Paket, `statistik.js` anpassen |
| UI-lernende-5 | mittel | `resources/views/rechner/index.blade.php:18` | Rechner-Kurve: Zusammenfassung fehlt bei Spielraum null · Satz mit echtem Wert, Text in `lang/en.json` |
| UI-lernende-6 | mittel | `resources/views/lernender/noten/partials/note.blade.php:38` | Bedienelemente innerhalb von `<summary>` · aus der summary nehmen, als Geschwister anordnen |
| UI-lernende-10 | niedrig | `resources/views/rechner/index.blade.php:167` | Textfarbe als Fläche statt Füll-Token · `even:bg-text/3` zu `even:bg-fill-2`, Z. 72 `hover:bg-fill` |

UI-Check Berufsbildner (9):

| ID | Schwere | Datei:Zeile | Titel · Fix |
|---|---|---|---|
| UI-berufsbildner-1 | mittel | `resources/views/components/punktstreifen.blade.php:60` | Punkte nicht per Tastatur erreichbar, Stufe nur über Farbe · `tabindex`, Pfeiltasten, Ansage mit Stufe |
| UI-berufsbildner-3 | mittel | `resources/views/components/heatmap.blade.php:10` | Heatmap ohne caption, Zahlen zentriert, ▼ wird vorgelesen · caption, `aria-hidden`, `text-right` |
| UI-berufsbildner-4 | mittel | `resources/views/verwaltung/noten/create.blade.php:12` | Note erfassen ohne Fächer: Hinweis in Notenfarbe, kein Weiterweg · `x-leer` mit Knopf zum Profil |
| UI-berufsbildner-5 | mittel | `resources/views/verwaltung/noten/index.blade.php:81` | Lernenden-Wechsel lädt schon beim Blättern mit den Pfeiltasten · `x-dropdown` oder nur bei Enter/blur navigieren |
| UI-berufsbildner-6 | niedrig | `resources/views/verwaltung/lernende/show.blade.php:8` | Hauptaktion nicht einheitlich markiert · in den Slot `hauptaktion` (auch `dashboards/berufsbildner.blade.php:45`, `verwaltung/lernende/index.blade.php:47`) |
| UI-berufsbildner-8 | niedrig | `resources/views/dashboards/berufsbildner.blade.php:327` | «in 1 Tagen» / «in 0 Tagen» · `Format::wann($l->lehrende)` |
| UI-berufsbildner-9 | niedrig | `resources/views/dashboards/berufsbildner.blade.php:15` | Akzentfarbe auf Symbol der Kachel «Alle» ohne Bedeutung · `bg-fill text-muted` |
| UI-berufsbildner-10 | niedrig | `resources/views/dashboards/berufsbildner.blade.php:128` | Stufen-Chips verlieren beim Hover ihren Ton · Hover im Ton (`/22`) |
| UI-berufsbildner-11 | niedrig | `resources/views/verwaltung/pruefungen/index.blade.php:69` | Leerzustand Prüfungstermine führt ohne Filter ins Leere · Knopf nur bei Filter, sonst Kalender-Abo/Abgabetermin |

UI-Check Admin (9):

| ID | Schwere | Datei:Zeile | Titel · Fix |
|---|---|---|---|
| UI-admin-1 | mittel | `resources/views/admin/berichte/noten.blade.php:352` | Lernende-Tabelle ohne caption · `caption` sr-only «Lernende» |
| UI-admin-2 | mittel | `app/Support/NotenSkala.php:29` | Stufe «knapp» nur über Farbe erkennbar · Form (gepunktet unterstrichen) in TEXT/BADGE, Docblock, Skill und Konzept nachführen |
| UI-admin-3 | mittel | `resources/views/dashboards/admin.blade.php:129` | Hartcodierter Grenzwert 20 · Betonung bei `$bb->neu > 0` oder Einstellungsschlüssel |
| UI-admin-4 | mittel | `resources/views/admin/stammdaten/faecher/index.blade.php:22` | Fast alle Admin-Tabellen ohne caption · caption mit vorhandenem Titeltext (auch `module/index:67`, `lehrberufe/show:47/149`, `einrichtung/_zugaenge:8`) |
| UI-admin-5 | mittel | `resources/views/admin/einrichtung/_zugaenge.blade.php:11` | Zugänge-Tabelle: `th` ohne scope · `scope="col"` an Z. 11–14 |
| UI-admin-7 | niedrig | `resources/views/admin/stammdaten/lehrberufe/show.blade.php:109` | Grund für gesperrtes «Entfernen» nur im title · `sr-only`-Text (auch Z. 110, 186) |
| UI-admin-8 | niedrig | `resources/views/admin/berichte/noten.blade.php:396` | Leerzustände im Notenbericht: Weiterweg fehlt oder sinnlos · Link nur bei Filter, sonst «Lernende erfassen» (auch Z. 121–124) |
| UI-admin-10 | niedrig | `resources/views/dashboards/admin.blade.php:28` | Browser-Titel «Betrieb» widerspricht h1/Navigation · Titel auf «Übersicht» |
| UI-admin-11 | niedrig | `resources/views/admin/berichte/noten.blade.php:92` | Kategorie-Filter: Leeroption heisst nur «Kategorie» · «Alle Kategorien», Eintrag in `lang/en.json` |

Zählung: 16 mittel (ADMIN-1; UI-lernende-1 bis 6; UI-berufsbildner-1, 3, 4, 5; UI-admin-1 bis 5), 18 niedrig.

### 4.4 Verworfene Befunde (16)

| ID | Titel | Grund (eine Zeile) |
|---|---|---|
| LERNENDE-1 | Mehr Kontrast: sekundäre Knöpfe ohne Kontur | Die 1-px-Kante gilt nach Plan nur für Materialien (GUI-R6.md §4, `notenportal-ui` Z. 111), Knöpfe sind keine Materialien |
| LERNENDE-5 | Abschluss: Eingabefelder ragen über die Zahlenspalte | Gemessen: Zahlen bündig bis 1 px; nur der Feldrahmen ragt 8 px, gewollt (`abschluss/index.blade.php:235`, `-mr-2`) |
| OHNE-NOTEN-1 | Mehr Kontrast: nur Kapseln und Seitenleiste mit Rahmen | Planentscheid: Kante nur für Materialien, Karten bleiben Inhaltsebene (G1) |
| OHNE-NOTEN-3 | Noteneingabefelder brechen die Zahlenkante | Gleiche Messung wie LERNENDE-5: Abweichung ≤ 1 px, Absicht aus R5-Fix (Commit 58a6860) |
| ADMIN-2 | Streudiagramm: Punkte verdecken sich | Widerlegt: Stapelung in `noten.blade.php:240-259`, gemessen 6.6 px Abstand bei Radius 5.9 px |
| ADMIN-4 | Übersicht: Unterkanten der Spalten um 3 px versetzt | Gewollt: `items-start` nach `notenportal-ui` SKILL.md:118 |
| UI-lernende-7 | Notenfarbe knapp für Hinweise ohne Notenbezug | Keine Regel beschränkt `note-knapp` auf Notenstufen; durchgehend Warnung = knapp |
| UI-lernende-8 | Abschluss: Leerzustand ohne Weiterweg | Keine Sackgasse, ständige Seitenleiste; `x-leer` verlangt keine Aktion |
| UI-lernende-9 | Erklärender Hinweistext im Prüfungsformular | Statusaussage, die einen Fehler verhindert, kein Entwicklerhinweis (`_form.blade.php:42`) |
| UI-lernende-11 | Primäraktion ohne `data-hauptaktion` | Wirkt nur in `np-glas-gruppe`; beide Seiten haben genau eine Akzent-Primäraktion rechts |
| UI-lernende-12 | Fortschrittsbalken in Akzentfarbe | Anzeige ohne Bedienung, die Regel gilt nur für Bedienelemente |
| UI-berufsbildner-2 | Hantel: Stufe nur als Farbe | Liste der knappen und ungenügenden Zeilen mit Symbol, Wert und Stufenname direkt darunter (S7, G19) |
| UI-berufsbildner-7 | Textfarbe muted als Fläche in Grafiken | Nicht real: Statuspunkt wie `status.blade.php:7-8`, Hantellinien sind Diagrammmarken; Fix würde verschlechtern |
| UI-admin-6 | Gefilterte Leerzustände ohne Weiterweg | Widerlegt: `x-filterleiste` zeigt «Zurücksetzen» (`filterleiste.blade.php:47-49`) |
| UI-admin-9 | Leere Berufsbildner-Karte ohne Weiterweg | Widerlegt: Kartenkopf-Link «Alle ›» (`admin.blade.php:97`, `karte.blade.php:20`) |
| UI-admin-12 | Frist «zwei Tage» für veraltete Sicherung hartcodiert | Folgt aus dem täglichen Cron (`routes/console.php:12`), keine Betriebseinstellung |

## 5 Offen

1. **DOM-Knoten:** Messfehler im Werkzeug behoben (abgehängte Knoten früherer Seiten, Abschnitt 2.8). Mit dem bereinigten Mass liegen alle drei Seiten über «vorher + 10 %» (+24 bis +75 %). Gezählt (Abschnitt 2.8): Dashboard vor allem `<details>`-Tabellen, überall die vorab gerenderte Suchpalette. Tippen in der Palette ist trotzdem schneller als vorher. Offen: Grenze für die Tabellenfassungen anpassen oder Tabellen erst beim Öffnen erzeugen – Entscheid David.
2. **Ruhe p95** 17.4 / 17.2 / 17.1 ms auf `/admin/reports/grades`, `/grades`, `/trainer/learners` gegen die Grenze 17.0 ms. Streuung auf dieser Maschine gleichauf mit vorher; Wiederholung im belegt ruhigen Fenster würde klären, ob es Messrauschen ist.
3. **Glasanteil Menü:** nachgemessen am 30.10.2026 (`leistung.mjs laura.frei@demo.example /admin --menue='button[aria-label^="Konto"]'`): 3 Flächen, **3.1 %** (Basislinie 20.9 %, Grenze ≤ 12 %) – erfüllt. Gemessen, während ein Workflow lief: Hover p95 18.7 ms gilt darum nur unter Last, der Glasanteil ist lastunabhängig. Weiter nicht gemessen: 2560×1440 und `/trainer/learners/1/grades` (Scroll).
4. **Absolute Zeiten (Tippen, LCP)** lassen sich auf diesem Rechner nicht gegen die Cloud-Basislinie halten. Für ein sauberes Urteil entweder dieselbe Umgebung wie am 03.10. verwenden oder die Grenze als «≤ vorher» festschreiben. Tippen `/exams` überschreitet die Basislinie auch im Minimum.
5. **Ziel Tippen ≤ 25 ms** (Bedingung für R6-13 Lichtbrechung, GUI-R6.md §6 Z. 210): auf der VM nicht bewertbar – das Ziel ist an der Cloud-Maschine der Basislinie festgemacht, die VM misst denselben alten Code 2.3- bis 2.8-mal langsamer. Im Median liegt keine Seite unter 29 ms (Einzellauf `/trainer/learners/1` 24.2 ms, Median 43.3 – kein Beleg). Entscheid 30.10.2026: R6-13 wird an seinem eigenen, relativen Kriterium aus §11 abgenommen (Tippen und Scroll p95 mit `data-brechung` ≤ diese Messung + T, auf derselben Maschine); fällt es durch, bleibt `data-brechung` aus.
6. **Messung wiederholen:** vorher-Instanz lieferte für `/trainer` HTTP 500; vorher-Knotenzahlen von `/trainer/learners` sind verdächtig. Ein Lauf mit gesunder vorher-Instanz und dokumentiertem ruhigem Fenster schliesst die Lücken in den Spalten «vorher».
7. **34 bestätigte Befunde** (16 mittel, 18 niedrig): behoben in Commit 20739bf (30.10.2026), ganze Suite grün, Bilder der geänderten Seiten angesehen. Bewusst gelassen und begründet: Abschnitt «R6-12 Sichtbefunde behoben» in `docs/audit-backlog.md`. Offen: erneuter Audit und Rundgang über alle Rollen.
8. **Nicht Teil dieser Abnahme:** `php artisan test` (ganze Suite) und `pruefer` – liegen bei der Hauptsitzung; ebenso der Commit.
