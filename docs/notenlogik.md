# Notenlogik

Stand 01.10.2026. Einzige fachliche Referenz für Durchschnitte, Rundung, Rechner, Ziele und geplante Prüfungen. Code: `app/Services/Auswertung/`.

## Begriffe

| Ebene | Bildung | Rundung (Standard) |
|---|---|---|
| Prüfung | eine Note (`noten`), Gewicht `gewichtung_prozent` (leer = 100) | – |
| **Element** | Fach × Semester (Semesterzeugnisnote) **oder** Modul (Modulnote über die aktuelle Belegung) | `kategorien.rundung_element` (0.5) |
| **Kategorie** | Mittel der Elementnoten (ungewichtet, gerundete Elementnoten) | `kategorien.rundung_schnitt` (0.1) |
| **Gesamt** | mit Notenbaum: Wurzel des Baums des Lehrberufs (siehe unten); ohne: Mittel der Kategorie-Schnitte, gewichtet mit `kategorien.gewicht_gesamt` | Baum: je Knoten; ohne: Einstellung `rundung_gesamt` (0.1) |
| Fach über die Lehrzeit | Mittel der Semesterzeugnisnoten dieses Fachs | wie Kategorie |
| Semesterschnitt | Mittel aller Elementnoten eines Semesters (Module zählen im Semester ihrer letzten Prüfung) | wie Kategorie |

Vorher wurden überall alle Prüfungen gewichtet gepoolt (ein Fach mit 8 Tests zählte 8× so viel wie ein Modul mit einer Prüfung). Das ist fachlich falsch und in allen Ansichten ersetzt.

Rundung: kaufmännisch auf den Schritt (`Rundung::auf(4.25, 0.5) = 4.5`), Schritt 0 = ungerundet. Elementnoten laufender Elemente werden ungerundet gezeigt, die gerundete Zeugnisnote daneben.

## Zuordnung Kategorie

Die Kategorie einer Note ist nicht mehr frei wählbar, sie folgt aus dem Fach bzw. Modul:
- `faecher.kategorie_id` (Pflicht). `faecher.track_typ` (BMS/ABU, nullable) ist nur noch Voraussetzung: Fach sichtbar, wenn der Lernende den Track aktiv hat. Fächer ohne Track sind berufsspezifisch und über `lehrberuf_faecher` freigegeben.
- `lehrberuf_module.kategorie_id` = Lernort des Moduls in diesem Beruf (Fachunterricht oder ÜK).

## Skala und «zählt nicht» je Fach

- `faecher.skala`: `note` (1–6) oder `stufe` (A/B/C, d = dispensiert; Sport nach Hausregel gbchur, LAGE §4.5). Stufen stehen in `noten.note_stufe` bei `note_wert` NULL (DB-CHECK: genau eines von beiden), werden angezeigt und **nie gerechnet**.
- `faecher.zaehlt = 0` (IDAF, Sport): Element bleibt sichtbar, zählt aber in keinem Kategorie-, Semester- oder Gesamtschnitt und in keiner Promotion. Ein Fächerknoten im Notenbaum, der das Fach ausdrücklich nennt, nimmt es trotzdem (Erfahrungsnote IDAF → IDPA). Im Bericht zählen solche Fächer nicht als ungenügend.
- Notenblatt und CSV-Exporte zeigen Stufen als Stufe (im Notenblatt die zuletzt erfasste je Semester und Fach), nie als Zahl.

## Notenbaum (QV und Berufsmaturität)

Gewichtete Rechnung bis zur Gesamtnote als Daten, nicht als Code (`docs/auftrag/NOTENBAUM.md`, Abnahmefälle §3 als Tests in `tests/Unit/Auswertung/NotenbaumAbnahmeTest.php`).

- Tabellen `notenbaeume` (Bezug `lehrberuf` oder `bildungsgang` mit `track_typ`), `notenbaum_knoten`, `notenbaum_knoten_faecher`, `notenbaum_positionen`. Je Lehrberuf bzw. Track ist höchstens ein Baum aktiv.
- Knotentypen: **Gruppe** (gewichtetes Mittel der zählenden Kinder), **Kategorie** (Mittel der Zeugnisnoten der zählenden Elemente einer Kategorie, wahlweise nur Module oder nur Fächer), **Fächer** (alle Semesterzeugnisnoten der genannten Fächer gemeinsam), **von Hand** (Position je Lernender: IPA, Schlussarbeit, Abschlussprüfung).
- Je Knoten: `gewicht`, `rundung` (0.1/0.5/1, leer = ungerundet; eine Gruppe rechnet mit den gerundeten Noten ihrer Kinder), `fallnote` (Note darunter → nicht bestanden), bei Gruppen `max_ungenuegend` und `max_minuspunkte` über die Kinder, `zaehlt`, `entfaellt_mit_track`.
- Fehlende Teile werden übersprungen und die Gewichte der übrigen hochgerechnet; das Ergebnis heisst dann **Prognose**, der Status bleibt «offen». Vollständig und ohne Grund → «bestanden», sonst «nicht bestanden». Ein Grund auf einer Position von Hand (z. B. IPA unter 4) ist endgültig und macht schon vorher «nicht bestanden».
- Nicht zählende Teile entscheiden nicht über das Bestehen (ihre Regeln werden nicht geprüft). Ein Teil mit Gewicht 0 und eine Gruppe ohne zählenden Teil mit Gewicht halten das Ergebnis nicht offen; zählt im ganzen Baum nichts, bleibt er «offen».
- Die Statusampel übernimmt die Gründe des Baums: endgültige (Position von Hand) rot, Gefährdungen aus Zwischenständen gelb.
- `entfaellt_mit_track`: Der Teil entfällt für Lernende, deren **zuletzt begonnener** Track dieser ist (z. B. Allgemeinbildung bei BMS); wer aus der BM ins ABU wechselt, hat danach ABU.
- Mit aktivem Lehrberuf-Baum ist die Gesamtnote in allen Ansichten die Wurzel dieses Baums («QV-Prognose» bzw. «QV-Gesamtnote»); ohne Baum rechnet der Bestand wie bisher. Bildungsgang-Bäume (BM) erscheinen zusätzlich auf der Seite «Abschluss».
- Vorlagen: `resources/vorlagen/notenbaeume/*.json` (Format `notenportal-notenbaum`, Version 1). Laden, importieren, exportieren und Gewichte anpassen unter Stammdaten → Notenbäume oder `php artisan notenportal:notenbaum liste|laden|import|export`. Die Struktur ändert man über Export → Datei bearbeiten → Import; `BaumVorlage::pruefen()` prüft jede Datei vollständig (Typen, Grenzen der Spalten). Wird ein Baum aktiv (Import oder Aktivieren), übernimmt er die Positionen des bisher aktiven Baums über den Knoten-Code (`BaumWechsel`); der bisher aktive Baum ist dabei die Wahrheit. Ein inaktiver Baum lässt sich löschen, sobald er keine Position mehr hält, die der aktive nicht hat. Fächer werden per Name im selben Track gefunden oder angelegt.
- Der Rechner simuliert Noten, nicht Positionen von Hand: eine fehlende IPA bleibt im Szenario fehlend.

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
