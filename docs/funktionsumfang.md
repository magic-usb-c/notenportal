# Funktionsumfang

## Lernender
- Noten CRUD mit Validierung, Soft-Delete; Viertelnoten (step 0.05)
- Accordion nach Fach/Modul, gewichtete Durchschnitte, Kategorie-Auswertung (Fachunterricht/ÜK/BMS/ABU)
- Semester-Navigation (←/→, schwebende Pill), Semester-Übersicht mit Balken
- CSV-Export, Druckansicht mit Unterschriftenzeile
- Noten-Rechner (Alpine, live), Live-Ø-Vorschau beim Erfassen mit Delta
- Notiz inline bearbeiten (PATCH, ohne Reload), Gewichtungs-Schnellwahl 25/50/100 %
- Tastatur: N = neu, Esc = Accordions schliessen, J/K = Navigation
- Deep-Link `?_open=<note_id>` öffnet Semester und Accordion

## Berufsbildner
- Noten der betreuten Lernenden lesen, kommentieren (Ctrl/Cmd+Enter), als gesehen markieren, Neu-Badge, «Alle gesehen»
- Lernende anlegen (Betreuung wird zugewiesen), Profil mit Lehrdaten inline, Tracks BMS/ABU
- CSV-Export aller Noten betreuter Lernender
- «Zu tun»: inaktiv 30 Tage, Ø < 4.0, Lehrende < 30 Tage

## Admin
- Benutzerverwaltung aller Rollen, Passwort-Generator, Rollen als Card-Radios
- Schnellsuche Ctrl+K, Quick-Actions auf dem Dashboard
- Betreuungen (neue beendet offene automatisch), Tracks
- Stammdaten: Lehrberufe, Module, Fächer, Semester (Überlappungsprüfung), Kategorien
- Berichte mit Kategorie-Auswertung, Histogramm, BB-Filter, sortierbaren Spalten

## Global
- Dark Mode (localStorage), Flash-Toast, Page-Progress-Bar, Fehlerseiten 403/404/500
- Liquid-Glass-System, Accent-Orbs, Count-up-Animation
- Deaktivierte Benutzer werden sofort ausgesperrt
- CSV-Exporte mit Formel-Injection-Schutz und Schweizer Datumsformat
