# Systemdoku für OneNote

Ein OneNote-Abschnitt nach der Firmenvorlage «System - [TEMPLATE] Servicename», als HTML.
`index.html` im Browser öffnen – dort steht die Reihenfolge der Seiten und der Weg nach OneNote.

## Einfügen

Seite im Browser öffnen, `Ctrl+A`, `Ctrl+C`, in OneNote neue Seite, `Ctrl+V`. Tabellen, Bilder und
Formatierung kommen mit. Vor dem Kopieren einmal ganz nach unten scrollen, damit alle Bilder
geladen sind. Unterseiten in OneNote über Rechtsklick → «Zu Unterseite tiefer stufen».

## Aufbau

| Datei | OneNote-Seite |
|---|---|
| `index.html` | – (nur Wegleitung, nicht einfügen) |
| `01-notenportal.html` | Notenportal (Hauptseite) |
| `02-installation-server.html` … `07-backup-restore.html` | Unterseiten |
| `08-howto.html` | Howto |
| `09-…` bis `11-…` | Unterseiten von Howto |
| `12-kontakte-support.html`, `13-weiterfuehrende-dokumente.html` | Unterseiten |

Zwei Stellen sind absichtlich leer: die Kontakttabellen und die PRTG-Sensornamen.

## Bilder auffrischen

Alle Screenshots stammen aus der Demoinstanz (`notenportal_demo`, erfundene Daten) und sind
1366 Pixel breit, helles Thema, deutsch.

```bash
NP_DOKU_PW=<passwort-der-demokonten> node tools/systemdoku-bilder.mjs http://127.0.0.1:8090
```

Das Skript schreibt nach `docs/systemdoku/bilder/`. Es darf **nur** gegen eine Demoinstanz laufen –
die Bilder landen in der Dokumentation und dürfen keine echten Personen zeigen. Liegt `playwright`
ausserhalb des Projekts (hier: `~/tools/visual`), das Skript von dort aus aufrufen.
