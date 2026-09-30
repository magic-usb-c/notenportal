# Auftrag: Notenbaum

Ersetzt die flache Kategorie-Gewichtung durch eine Struktur, die die offiziellen Rechenwege
abbilden kann. Vorrang vor allen anderen Aufträgen. Fachliche Grundlage und Quellen:
`docs/auftrag/LAGE.md`, Abschnitte 2 und 4.

---

## 1. Warum

Heute (`app/Services/Auswertung/Rechenkern.php:66-100`): Kategorie-Schnitt = ungewichtetes Mittel
aller Elementnoten, Gesamtnote = mit `kategorien.gewicht_gesamt` gewichtetes Mittel der Kategorien.

Die Bildungsverordnung verlangt aber unter anderem, dass die Erfahrungsnote Informatikkompetenzen
aus **80 % Schulmodulen und 20 % ÜK-Modulen** besteht. Das ist eine Gewichtung *innerhalb* dessen,
was heute eine Ebene ist — sie lässt sich nicht ausdrücken. Dazu kommen Fallnoten, nicht zählende
Noten, von Hand erfasste Positionen und eine zweite, völlig andere Struktur für die
Berufsmaturität.

Ein einziger Umbau erschlägt die Lücken 1 bis 5 aus `LAGE.md` Abschnitt 3.

---

## 2. Was gebaut wird

**Ein gewichteter Baum je Lehrberuf beziehungsweise Bildungsgang.** Knoten tragen Gewicht,
Rundung und optional eine Bestehensnorm. Blätter sind entweder abgeleitet (Fach- oder Modulnoten
aus der Datenbank) oder von Hand erfasst (IPA, Schlussarbeit, Schlussprüfung).

Der bestehende Kategorie-Begriff bleibt als Blatt-Typ erhalten, damit kein Bestand verloren geht.

Anforderungen an das Modell:

1. Beliebige Tiefe, Geschwister mit relativen Gewichten.
2. Rundung je Knoten: auf ganze/halbe Note, auf eine Dezimalstelle, oder gar nicht.
3. **Fallnote** je Knoten: ein Mindestwert, dessen Unterschreitung das Ganze nicht bestehen lässt.
4. **Nicht zählende Noten**: erfasst und angezeigt, aber nicht in der Rechnung (Sport, IDAF).
5. **Bewertungsskalen**: neben 1–6 auch Stufen wie A/B/C und «dispensiert».
6. Von Hand erfasste Positionen ohne zugrunde liegende Einzelnoten.
7. Ein Baum ist **Daten, keine Konstanten** — anlegbar, änderbar, als Vorlage importier- und
   exportierbar, gleicher Weg wie der Modulkatalog.

---

## 3. Verbindliche Abnahmefälle

Diese Fälle sind die Spezifikation. Wer das Schema anders schneidet als unten angedeutet, ist frei —
aber diese Tests müssen grün sein, mit genau diesen Zahlen.

### 3.1 Informatiker/in EFZ — QV-Gesamtnote

Baum laut `LAGE.md` Abschnitt 2.

**Fall A — regulär**

| Position | Wert |
|---|---|
| IPA | 5,0 |
| Allgemeinbildung: Erfahrungsnote / Schlussarbeit / Schlussprüfung | 4,5 / 5,0 / 4,0 |
| Erweiterte Grundkompetenzen (Mittel der 8 Semesterzeugnisnoten) | 4,5 |
| Informatikkompetenzen: Schulmodule / ÜK-Module | 5,0 / 4,0 |

Erwartet:
- Allgemeinbildung = (4,5 + 5,0 + 4,0) / 3 = 4,5
- Informatikkompetenzen = 5,0 × 0,8 + 4,0 × 0,2 = **4,8**
- Gesamtnote = 5,0 × 0,40 + 4,5 × 0,20 + 4,5 × 0,10 + 4,8 × 0,30 = 2,0 + 0,9 + 0,45 + 1,44 = **4,79 → 4,8**
- Bestanden: ja (IPA 5,0 ≥ 4 · Informatikkompetenzen 4,8 ≥ 4 · Gesamt 4,8 ≥ 4)

**Fall B — Fallnote IPA**
IPA 3,5, alles andere wie Fall A. Gesamtnote rechnerisch 4,2 — **nicht bestanden**, weil die
praktische Arbeit unter 4 liegt. Die Begründung muss ausgewiesen werden, nicht nur das Ergebnis.

**Fall C — Fallnote Informatikkompetenzen**
Schulmodule 3,5, ÜK 4,5 → 3,5 × 0,8 + 4,5 × 0,2 = **3,7** → nicht bestanden, obwohl die
Gesamtnote ≥ 4 sein kann.

**Fall D — Gewichtung wirkt wirklich**
Schulmodule 6,0 / ÜK 1,0 → 6,0 × 0,8 + 1,0 × 0,2 = **5,0**. Ein ungewichtetes Mittel ergäbe 3,5.
Dieser Test ist der eigentliche Beweis, dass der Fehler behoben ist.

### 3.2 Berufsmaturität TALS1

- Abschlussnote Fach **mit** Prüfung = ½ Prüfungsnote + ½ Erfahrungsnote, gerundet auf ganze/halbe.
  Prüfung 4,5 / Erfahrung 5,2 → 4,85 → **5,0**.
- Abschlussnote Fach **ohne** Prüfung (Ergänzungsbereich) = Erfahrungsnote, gerundet.
- Erfahrungsnote = Mittel aller Semesterzeugnisnoten auf **eine Dezimalstelle**.
- IDAF/IDPA = ½ IDPA + ½ Erfahrungsnote IDAF.
- **IDAF zählt nicht zur Promotion** (BMV Art. 16 Abs. 3) — erfasst, angezeigt, nicht gerechnet.
- Promotion bestanden nur, wenn **alle drei**: Schnitt ≥ 4,0 **und** Minuspunkte ≤ 2,0 **und**
  höchstens zwei Noten unter 4. Prüffall: Noten 3,5 / 3,5 / 5,0 / 5,0 / 5,0 / 5,0 → Schnitt 4,5,
  Minuspunkte 1,0, zwei Ungenügende → bestanden. Mit einer dritten 3,5 statt einer 5,0 → Schnitt
  4,25, Minuspunkte 1,5 — beide Grenzen eingehalten, trotzdem **nicht bestanden**, weil drei Noten
  unter 4 liegen. Genau diese dritte Bedingung fehlt heute.

### 3.3 Sport

- Skala A/B/C plus «d» für dispensiert.
- Erfassbar, im Zeugnis sichtbar.
- Geht in **keine** Rechnung ein: nicht in Semesterschnitt, nicht in Promotion, nicht ins QV.
- Prüffall: Ein Lernender mit Sport «C» und sonst lauter 5,0 hat Schnitt 5,0 und ist promoviert.

### 3.4 Bestand

- Bestehende Noten, Kategorien und Zeugnisse bleiben erhalten und rechnen nach der Migration
  identisch weiter, solange kein Baum hinterlegt ist. Ein Lehrberuf ohne Baum verhält sich exakt
  wie heute.
- `php artisan test` bleibt vollständig grün.
- Die Migration läuft zuerst gegen `notenportal_probe`, inklusive Rollback, dann gegen
  `notenportal` (Skill `notenportal-migration`).

---

## 4. Reihenfolge

Jeder Schritt lässt das Portal lauffähig und wird einzeln committet.

1. **Bewertungsskalen und «zählt nicht».** DB-CHECK auf `noten.note_wert` lockern, Skala-Begriff
   einführen, Sport A/B/C und IDAF abbilden. Kleinster, sicherster Teil. Danach 3.3 grün.
2. **Baum-Schema und Rechenkern.** Rekursive Auswertung mit Gewicht, Rundung, Fallnote. Danach
   3.1 und 3.2 grün.
3. **Oberfläche.** Baum je Lehrberuf ansehen und bearbeiten; Zeugnis- und Cockpit-Ansichten zeigen
   die Zwischenstufen und bei Nichtbestehen die Begründung.
4. **Vorlagen.** Baum als JSON importieren und exportieren, analog `notenportal:modulkatalog`.
   Mitgeliefert: «Informatiker/in EFZ (BiVo 2020)» und «BM TALS1 (BMV 2025)» — als Vorlagendateien,
   **nicht** als Konstanten im Code.
5. **Ausrichtungen.** `track_typ` vom Enum zu echten Bildungsgängen mit Dauer und Fächerliste
   (Lücke 3 und 5). Danach lassen sich TALS1 und ARTE1 nebeneinander führen.
6. **Unterrichtsbereich und Lektionen** auf `faecher` (Lücke 4 und 6) — nur noch Darstellung.

---

## 5. Regeln

- Alle Durchschnitte kommen aus `App\Services\Auswertung`, nie aus SQL, Controllern oder Views.
- Rundung nur an den Stellen, die die Verordnung nennt — Zwischenergebnisse nicht vorzeitig runden.
  Wo gerundet wird, gehört der Artikel als Kommentar daneben.
- Jede Bestehensentscheidung liefert eine Begründung mit, nicht nur ein Ja/Nein.
- `docs/notenlogik.md` wird mitgeführt; es ist die fachliche Referenz und darf dem Code nie
  widersprechen.
- Was bewusst liegen bleibt, nach `docs/audit-backlog.md` mit Begründung.
