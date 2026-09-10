# Funktionsumfang

Rechenregeln (Zeugnisnoten, Rundung, Gewichtung, Promotion, Rechner): `docs/notenlogik.md`.

## Lernender
- Dashboard: Gesamt-, Semester- und Kategorienoten mit Verlauf, Ziele mit benötigter Durchschnittsnote, «Zu tun» (überfällige Prüfungen, neue Kommentare, fehlende Module), nächste Prüfungen, Verlauf- und Balkendiagramm (Kategorie/Fach/Semester umschaltbar), letzte Noten, Lehrzeit-Fortschritt
- Noten je Semester: Kategorie → Fach/Modul mit Zeugnisnote, Schnitt vor Rundung, Modulfortschritt (offene Gewichtung), Promotionsstatus; Zeugnisübersicht über die Lehrzeit als Heatmap
- Erfassen/Bearbeiten: ein Formular, Bezug Fach/Modul mit Vorauswahl, Semester aus dem Datum, Live-Auswirkung auf Fach/Modul, Kategorie, Semester und Gesamt; Viertelnoten
- Prüfungen planen (Fach/Modul, Datum, Gewicht); überfällige erscheinen als Aufgabe, «Note erfassen» übernimmt die Prüfung
- Ziele (Gesamt, Kategorie, Fach, Modul) mit benötigter Note
- Notenrechner vorwärts (Was-wäre-wenn mit geplanten/angenommenen Noten, Vergleich vorher/nachher) und rückwärts (benötigte Note für ein Ziel, Kurve)
- Notenblatt zum Drucken/PDF (Zeugnisnoten je Semester, Prüfungen, Promotion, Unterschriften), CSV-Export
- Kommentare lesen, Titel inline bearbeiten, Deep-Link `?_open=<note_id>`
- Modul wiederholen: aktuelle Belegung schliessen, ab der ersten neuen Note zählt nur der neue Versuch; zurücknehmbar, solange keine neue Note erfasst ist
- Notenimport aus Excel, ODS, CSV oder PDF: Spalten werden erkannt (mit oder ohne Kopfzeile), Fach/Modul zugeordnet (Modulnummer, Name, Kürzel, ähnlichster Name zur Prüfung), Dubletten markiert; Vorschau mit korrigierbaren Zeilen, dann Übernahme über dieselben Regeln wie beim Erfassen; CSV-Vorlage
- Dokumente: Zeugnisse, Notenlisten und Sonstiges hochladen (Drag & Drop, bis 10 MB), nach Art und Semester ablegen, im Browser öffnen oder mit sprechendem Namen herunterladen
- Zeugnis-Abgleich: Zeugnis-PDF gegen die berechneten Zeugnisnoten prüfen (stimmt / abweichend / fehlt), fehlende als Note «Zeugnis» übernehmen

## Berufsbildner
- Dashboard: betreute Lernende nach Ampel sortiert (rot/gelb/grün mit Gründen: Promotion gefährdet, Semesterschnitt, ungenügende Zeugnisnoten, Einbrüche, Rückgang, fehlende Noten, Inaktivität), Brennpunkte, Vergleich, Prüfungen der nächsten 14 Tage, Lehrende bald
- Lernenden-Cockpit: Stand, Verlauf, Heatmap, Ziele, Prüfungen, Rechner für den Lernenden
- Noten lesen, korrigieren («geändert von»), kommentieren, als gesehen markieren; Notenblatt, CSV
- Dokumente der betreuten Lernenden einsehen, hochladen, löschen; Zeugnis-Abgleich (Übernahme und Notenimport nur für Admin)
- Lernende anlegen, Betreuung, Tracks

## Admin
- Dashboard: Betriebskennzahlen, Einrichtungslücken (laufende Lehren ohne Betreuung, Lehrberuf, Track, Module), Last pro Berufsbildner, Aktivität 12 Wochen, Jahrgänge je Lehrberuf und Lehrjahr, kritische Lernende, Lehrende bald
- Alles wie Berufsbildner für alle Lernenden, zusätzlich Noten anlegen und löschen
- Erstinbetriebnahme: `sudo ./install.sh` im geklonten Repo (Pakete, Datenbank, .env, Build, Apache, Rechte, erstes Admin-Konto mit Startpasswort), danach alles im Browser: erzwungener Passwortwechsel und geführte Einrichtung (Betrieb und Notengrenzen, Kategorien, Semester-Generator mit Vorschau, Lehrberufe und BMS/ABU-Fächer mit Vorauswahl, Module als Liste je Lernort, Berufsbildner/Admins und Lernende in Zeilen mit Startpasswörtern zum Drucken)
- Betrieb: Name, Notengrenzen mit Live-Skala, Rundung Gesamtschnitt, Fristen; Sicherungen (täglich automatisch, «Jetzt sichern», Herunterladen als ZIP mit Datenbank und Dokumenten, Löschen, Warnung bei Fehler oder Sicherung älter als zwei Tage)
- Benutzer (Rollen Admin/Berufsbildner und Benutzername änderbar), Betreuungen; Stammdaten: Lehrberufe (Module zeilenweise mit Lernort, Pflicht, empfohlenem Semester, aktiv), Fächer (Kategorie, Track optional), Kategorien (Rundung, Gewicht im Gesamtschnitt, Promotionsregeln), Semester (leere löschbar)
- Notenbericht: Zeitraum (Semester/ganze Lehrzeit), Lehrberuf, Berufsbildner; Status, Kategorien, tiefste Fächer/Module, Verteilung der Zeugnisnoten, sortierbar, CSV, druckbar

## Global
- Suche/Befehlspalette (Ctrl+K): Lernende, Konten (Admin), Seiten
- Hell/Dunkel serverseitig gespeichert, Flash-Toast, Fehlerseiten 403/404/500
- Liquid-Glass-System, Chart.js-Diagramme mit Theme-Tokens
- Grenzwerte (gut/genügend/kritisch), Rundung Gesamtschnitt und Fristen aus `einstellungen`
- Deaktivierte Benutzer sofort ausgesperrt; CSV-Exporte mit Formel-Injection-Schutz
