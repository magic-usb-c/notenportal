# Notenlogik

Stand 30.09.2026, Abschnitt «Statistik» ergänzt am 30.10.2026 (R6). Einzige fachliche Referenz für Durchschnitte, Rundung, Rechner, Ziele und geplante Prüfungen. Code: `app/Services/Auswertung/`.

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
- Nicht zählende Teile entscheiden nicht über das Bestehen (ihre Regeln werden nicht geprüft). Ein Teil mit Gewicht 0 und eine Gruppe ohne zählenden Teil mit Gewicht bewegen die Note nicht und halten das Ergebnis nicht offen – ausser sie entscheiden trotzdem übers Bestehen (eigene Mindestnote, Regel darunter, oder die Gruppe zählt ungenügende Teile bzw. Minuspunkte); dann bleibt das Ergebnis offen, bis sie erfasst sind. Zählt im ganzen Baum nichts, bleibt er «offen».
- Die Statusampel übernimmt die Gründe des Baums: endgültige (Position von Hand) und alle Gründe eines vollständig gerechneten, nicht bestandenen Baums rot, Gefährdungen aus Zwischenständen gelb.
- Neben den Gründen liefert der Rechner jede geprüfte Regel als **Bedingung** mit Stand (erfüllt, verletzt, offen) – die verletzten sind genau die Gründe. Regeln über Teile (ungenügende, Minuspunkte) sind offen, solange kein zählender Teil einen Wert hat. «Erfasst» auf der Seite «Abschluss» ist der Anteil der Gesamtnote, dessen Teile schon einen Wert haben, gewichtet wie die Rechnung (`BaumErgebnis::erfasst()`).
- `entfaellt_mit_track`: Der Teil entfällt für Lernende, deren **zuletzt begonnener** Track dieser ist (z. B. Allgemeinbildung bei BMS); wer aus der BM ins ABU wechselt, hat danach ABU.
- Mit aktivem Lehrberuf-Baum ist die Gesamtnote in allen Ansichten die Wurzel dieses Baums («QV-Prognose» bzw. «QV-Gesamtnote»); ohne Baum rechnet der Bestand wie bisher. Bildungsgang-Bäume (BM) erscheinen zusätzlich auf der Seite «Abschluss».
- Vorlagen: `resources/vorlagen/notenbaeume/*.json` (Format `notenportal-notenbaum`, Version 1). Laden, importieren, exportieren und Gewichte anpassen unter Stammdaten → Notenbäume oder `php artisan notenportal:notenbaum liste|laden|import|export`. Die Struktur ändert man über Export → Datei bearbeiten → Import; `BaumVorlage::pruefen()` prüft jede Datei vollständig (Typen, Grenzen der Spalten). Wird ein Baum aktiv (Import oder Aktivieren), holt er für jeden seiner Codes die Positionen aus allen Bäumen desselben Lehrberufs bzw. Bildungsgangs (`BaumWechsel`), nicht nur aus dem Vorgänger – kannte ein Zwischenstand einen Code nicht, liegt die Note noch im Baum davor. Verschieben statt kopieren: danach liegt eine Position nur im aktiven Baum, eine dort geleerte Note bleibt beim Zurückschalten leer. Gibt es für eine Lernende denselben Code mehrfach (Bestand), gilt die zuletzt erfasste Position (ihr `aktualisiert_am`, das beim Umziehen erhalten bleibt – nicht das des Baums, das ändert auch das Umbenennen); die überholten fallen weg. Codes, die der neue Baum nicht kennt, bleiben im alten und sperren dessen Löschen. Gleichzeitiges Arbeiten: Ein Wechsel sperrt alle Bäume des Ziels in fester Reihenfolge und liest die Positionen sperrend (neuester bestätigter Stand, nicht der Schnappschuss vom Transaktionsbeginn); Speichern im Abschluss sperrt die betroffenen Bäume geteilt und bricht mit «Der Abschluss wurde eben umgestellt …» ab, wenn einer inzwischen abgelöst ist. Ein Wechsel, der während des Speicherns kommt, wartet und nimmt den Wert mit; kollidierende Importe wiederholt `DB::transaction` (3 Versuche). Der Rollback der Notenbaum-Migrationen (000001 und 000002, die im selben Batch zuerst zurückgenommen wird) bricht vor jeder Schemaänderung ab, solange Stufen oder Positionen erfasst sind. Fächer werden per Name im selben Track gefunden oder angelegt.
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

## Statistik (Verlauf, Verteilung, Veränderung)

Die Statistikseiten (Katalog S1 bis S11 in `docs/gui-konzept.md`) rechnen nichts selbst: Durchschnitte kommen aus `Auswertung`/`Rechenkern`, Kennzahlen über Verteilungen aus `App\Services\Auswertung\Verteilung`, Verläufe und Vergleiche aus `App\Services\Auswertung\Statistik`. Controller, SQL und Views mitteln nie.

**Stichtagsreihe** (`Statistik::stichtagsreihe`, S1): Wert einer Zielgrösse (Gesamt, Kategorie, Fach, Modul, Semester) an jedem Stichtag = Stand der Auswertung mit allen Leistungen, die **bis einschliesslich dieses Tages** datiert sind. Die Rechnung ist dieselbe wie sonst (`Rechenkern::auswerten`), nur auf die bis dahin bekannten Leistungen beschränkt, also mit denselben Rundungen und Gewichten.
- Stichtage sind Monatsenden ab dem ersten Periodenende ab Beginn des Zeitraums; ist das Ende des Zeitraums kein Periodenende, ist es der letzte Stichtag. Gibt es mehr als 60 Monatsenden (`MAX_STICHTAGE`), wird auf Quartalsenden vergröbert und `meta.vergroebert` ist wahr.
- Die Punkte des Diagramms sind die einzelnen erfassten Noten mit Datum (Fach, Titel, Wert) innerhalb des Zeitraums und der gewählten Zielgrösse; Annahmen ohne Wert, geplante Prüfungen und Notenbaum-Positionen sind keine Punkte.
- **Leistungen ohne Datum** (IPA, Schlussprüfung, Positionen von Hand im Notenbaum, auch noch offene) stehen nie still in der Reihe, sondern in `meta.ohneDatum` und werden als Liste unter dem Verlauf gezeigt.
- Zeiträume: Semester, Lehrjahr oder ganze Lehrzeit; die Achse ist das Datum oder das Semester.

**Verteilung** (`Verteilung`, S8 bis S11):
- **Quantile nach Typ 7** (lineare Interpolation, wie `quantile(type = 7)` in R): Position h = (n − 1) · p auf den sortierten Werten, Wert = Unterwert + Rest · (Oberwert − Unterwert). Median und Quartile q1 und q3 werden auf zwei Stellen gerundet.
- **Mindestens 3 Werte** (`MINDESTANZAHL`): bei weniger gibt es keinen Median und keine Quartile (`null`), die Oberfläche zeigt dann keine Marke. Das gilt auch für kleine Gruppen, etwa ein Lehrjahr mit zwei Lernenden, und schützt vor Aussagen über einzelne Personen.
- **Histogramm** in Klassen der Breite 0.25 von 1.0 bis 6.0 (`[von, von + Breite)`, die letzte Klasse schliesst 6.0 ein). Werte ausserhalb 1 bis 6 zählen nicht, weder in `n` noch im Median. Die Klassenzahlen summieren sich zur Zahl der gezählten Noten; gelöschte Noten (`geloescht_am`) fehlen.
- Gerechnet wird über fertige Noten. Welche Noten das sind, bestimmt die Quelle der jeweiligen Statistik (`Bericht`, `Uebersicht`) nach den Regeln oben (Stufen werden nie gerechnet, nicht zählende Fächer zählen nicht).

**Veränderung** (`Statistik::delta`, `hanteln`, S3 und S7): Delta = aktuell − Vorsemester, **auf zwei Stellen gerundet**; fehlt eine der beiden Noten, ist das Delta `null`. Eine Rundungsregel für Hanteln, Lernstand und Personenvergleich. Sortiert wird nach Delta, grösster Rückgang zuerst; Zeilen ohne Delta stehen am Ende. Die Gesamtveränderung einer Person (`Lernstand::delta`) ist die der Gesamtnote; mit Kategorienfilter wird je Person `Auswertung::semester` für beide Semester gerechnet.

**Benötigte Note** (`Statistik::benoetigt`, S5 und S6): für jede offene Position und jede kommende Prüfung die Note für «genügend» (Grenze aus der Einstellung, nie aus dem Code), gelöst wie im Zielrechner: gleiche offene Leistungen auf derselben Zielgrösse erhalten gemeinsam dieselbe Note. Ergebnis je Zeile: bestanden, benötigt (Wert), nicht erreichbar, mit Minimum und Maximum. Datierte Prüfungen stehen zuerst. Die Berufsbildner-Seiten zeigen vorerst nur die Note für «genügend»; ob sie auch die persönlichen Ziele der Lernenden sehen dürfen, ist offen (`GUI-R6.md` §10).

**Filter** (`App\Support\StatistikFilter`): Whitelist je Seite. Unbekannte Schlüssel werden ignoriert, unbekannte oder doppelte Werte fallen auf den Standard, nie ein Fehler; IDs (Kategorie, Fach, Semester, Lehrberuf) zählen nur, wenn sie zur Menge gehören, die die Person sehen darf. Die Adresse trägt nur, was vom Standard abweicht.

## Entfernt

- `modul_note_gruppen`, `noten.gruppe_id`: in der Oberfläche nie erfassbar, flache Gewichte decken die Fälle ab.
- `bewertungsregeln`: ersetzt durch Kategorie-Spalten und Einstellungen.
