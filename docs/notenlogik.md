# Notenlogik

Stand 10.09.2026. Einzige fachliche Referenz für Durchschnitte, Rundung, Rechner, Ziele und geplante Prüfungen. Code: `app/Services/Auswertung/`.

## Begriffe

| Ebene | Bildung | Rundung (Standard) |
|---|---|---|
| Prüfung | eine Note (`noten`), Gewicht `gewichtung_prozent` (leer = 100) | – |
| **Element** | Fach × Semester (Semesterzeugnisnote) **oder** Modul (Modulnote über die aktuelle Belegung) | `kategorien.rundung_element` (0.5) |
| **Kategorie** | Mittel der Elementnoten (ungewichtet, gerundete Elementnoten) | `kategorien.rundung_schnitt` (0.1) |
| **Gesamt** | Mittel der Kategorie-Schnitte, gewichtet mit `kategorien.gewicht_gesamt` | Einstellung `rundung_gesamt` (0.1) |
| Fach über die Lehrzeit | Mittel der Semesterzeugnisnoten dieses Fachs | wie Kategorie |
| Semesterschnitt | Mittel aller Elementnoten eines Semesters (Module zählen im Semester ihrer letzten Prüfung) | wie Kategorie |

Vorher wurden überall alle Prüfungen gewichtet gepoolt (ein Fach mit 8 Tests zählte 8× so viel wie ein Modul mit einer Prüfung). Das ist fachlich falsch und in allen Ansichten ersetzt.

Rundung: kaufmännisch auf den Schritt (`Rundung::auf(4.25, 0.5) = 4.5`), Schritt 0 = ungerundet. Elementnoten laufender Elemente werden ungerundet gezeigt, die gerundete Zeugnisnote daneben.

## Zuordnung Kategorie

Die Kategorie einer Note ist nicht mehr frei wählbar, sie folgt aus dem Fach bzw. Modul:
- `faecher.kategorie_id` (Pflicht). `faecher.track_typ` (BMS/ABU, nullable) ist nur noch Voraussetzung: Fach sichtbar, wenn der Lernende den Track aktiv hat. Fächer ohne Track sind berufsspezifisch und über `lehrberuf_faecher` freigegeben.
- `lehrberuf_module.kategorie_id` = Lernort des Moduls in diesem Beruf (Fachunterricht oder ÜK).

## Modulabschluss und offene Gewichtung

`module.ziel_gewicht_summe_default` (Standard 100). Offene Gewichtung = Ziel − Summe erfasster Gewichte. Ein Modul ist abgeschlossen, wenn die Summe das Ziel erreicht. Der Rechner nimmt offene Gewichtung automatisch als unbekannte Prüfung auf.

Wiederholung: Eine Belegung mit `end_datum` ist abgeschlossen; neue Noten erzeugen eine neue Belegung. Gezählt wird immer die jüngste Belegung.

## Promotion (je Kategorie, pro Semester)

Spalten `kategorien.promotion_min_schnitt`, `promotion_max_ungenuegend`, `promotion_max_minuspunkte` (alle leer = keine Regel). Minuspunkte = Σ (Genügend-Grenze − Elementnote) über ungenügende Elemente. Standard BMS: 4.0 / 2 / 2.0.

## Grenzwerte (Einstellungen)

`note_gut` 5.0, `note_genuegend` 4.0, `note_kritisch` 3.5, `rundung_gesamt` 0.1. Farbstufen über `App\Support\NotenSkala` und `<x-note>`.

## Rechner

Szenario = echte Noten + geplante Prüfungen + Was-wäre-wenn-Noten. Ziel = Ebene (gesamt, kategorie, semester, kategorie×semester, fach, fach×semester, modul) + Zielwert. Unbekannte Prüfungen erhalten alle denselben Wert x; gesucht ist das kleinste x (Schritt 0.05) mit gerundetem Zielwert ≥ Ziel. Ergebnis: benötigt / schon erreicht (auch mit 1.0) / nicht erreichbar (mit Maximum bei 6.0). Rechnung serverseitig (eine Engine, getestet), die Oberfläche fragt per JSON.

## Geplante Prüfungen, Ziele

- `pruefungen`: Lernender plant Prüfung (Fach oder Modul, Datum, Gewicht, Titel). «Note eintragen» wandelt sie in eine Note um und löscht die Planung. Vergangene Planungen ohne Note erscheinen als «Note fehlt».
- `ziele`: Zielwert je Ebene (gesamt, kategorie, fach, modul). Dashboard zeigt Abstand und benötigte Note in den geplanten Prüfungen.

## Entfernt

- `modul_note_gruppen`, `noten.gruppe_id`: in der Oberfläche nie erfassbar, flache Gewichte decken die Fälle ab.
- `bewertungsregeln`: ersetzt durch Kategorie-Spalten und Einstellungen.
