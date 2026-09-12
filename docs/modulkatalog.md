# Modulkatalog aus offiziellen Quellen

Ziel: Modulnummern, Titel, Versionen, Handlungsziele, Kompetenzfeld und die
Leistungsbeurteilungsvorgabe (LBV) stehen als Stammdaten im Portal, statt sie von Hand zu tippen –
und jedes Modul verlinkt auf seine Seite im Modulbaukasten.

## Rechtslage – zuerst lesen

Die Inhalte von modulbaukasten.ch gehören **ICT-Berufsbildung Schweiz**; die Seite verweist auf die
«Nutzungsbedingungen Modulbaukasten» (`www.ict-berufsbildung.ch/infos/nutzungsbedingungen-mbk`).
Nach dem, was dort laut Suchtreffern steht, braucht das Übernehmen von Inhalten eine schriftliche
Zustimmung. Deshalb gilt im Portal:

- **Im Repo liegt kein Katalog.** Keine Modulliste, keine Handlungsziele, keine LBV – auch nicht in
  Seeds oder Tests (Testdaten sind erfundene Nummern und Titel).
- **Verlinken ist unbedenklich** und immer möglich: ein Link ist keine Übernahme von Inhalt.
- **Wer Inhalte importiert, tut das auf der eigenen Instanz** und klärt die Zustimmung selbst.

## Wege zu den Daten – was geprüft ist

| Weg | Ergebnis |
|---|---|
| Tiefenlink je Modul `modulbaukasten.ch/module/{Nummer}/{Version}/de-DE` | funktioniert, braucht die Version |
| Modulidentifikation als PDF `…/Module/{Nummer}_{Version}_DE.pdf` | funktioniert für neuere Module; ältere liegen unter `{Nummer}_{Version}_{Titel}.pdf`, manche gar nicht |
| Öffentliche API | **gibt es nicht.** Die Seite ist eine Angular-Anwendung und bezieht ihre Daten über ein Zugangstoken eines fremden Systems. Das Portal verwendet dieses Token nicht |
| Gesamtübersicht-PDF | unbrauchbar: eine Layout-Seite ohne Struktur, Stand «Version 2.0» |
| Bildungsplan-PDF je Beruf (becc.admin.ch) | Existenz und Adresse gesichert, Modultabellen darin sind nicht zuverlässig maschinenlesbar |
| SBFI-Berufsverzeichnis `becc.admin.ch/becc/public/bvz/beruf/showAllActive?offset=0&max=60` | 909 Bildungsangebote mit Berufsnummer und Stufe, blätternd; kein CSV, keine API |

Zwei Fallen, teuer gelernt: eine **fehlende Seite antwortet mit HTTP 200 und HTML** – wer eine Datei
holt, prüft den Inhaltstyp, nie den Status. Und **mehrere Versionen eines Moduls sind gleichzeitig
gültig** (z. B. 117 V4 und V5), je nach Bildungsverordnung des Abschlusses.

## Ablauf

Der Index – welches Modul zu welchem Abschluss, in welchem Lehrjahr, Pflicht oder Wahlpflicht –
steht erst in der gerenderten Seite. Ein PHP-Abruf erhält nur das leere Grundgerüst. Darum zwei
Schritte: ernten, dann einlesen.

```bash
# 1. Ernten (einmal pro Aktualisierung, braucht Node und Chromium; nicht auf dem Portalserver nötig)
npm i -D playwright && npx playwright install --with-deps chromium
node tools/modulkatalog-ernte.mjs --ziel=/tmp/katalog.json                      # nur Listen, schnell
node tools/modulkatalog-ernte.mjs --ziel=/tmp/katalog.json --details            # mit Handlungszielen und LBV, langsam
node tools/modulkatalog-ernte.mjs --ziel=/tmp/katalog.json --details \
     --abschluss="Entwickler/in digitales Business EFZ"                         # nur ein Beruf

# 2. Einlesen: zeigt zuerst nur, was passieren würde
php artisan notenportal:modulkatalog /tmp/katalog.json
php artisan notenportal:modulkatalog /tmp/katalog.json --anwenden
```

Der Import ist alles-oder-nichts, legt fehlende Lehrberufe an, ordnet Module mit Lernort
(Fachunterricht/ÜK), Pflichtgrad und empfohlenem Lehrsemester zu und lässt eigene Module (ABU, BMS,
schuleigene Nummern) unangetastet. Wiederholte Läufe aktualisieren, statt zu verdoppeln.

## Was im Portal sichtbar wird

- Stammdaten → Module: Version, Kompetenzfeld und ein Verweis «Im Modulbaukasten öffnen».
- Ein Verweis entsteht **nur mit bekannter Version**. Ohne Version kein Link – eine geratene
  Adresse würde auf eine Seite zeigen, die freundlich HTTP 200 antwortet und trotzdem falsch ist.

## Offen

- Schriftliche Zustimmung von ICT-Berufsbildung Schweiz für die Übernahme von Feldinhalten (David).
- `ict-berufsbildung.ch` war aus diesem Netz nicht erreichbar (Verbindung abgewiesen, DNS löst auf);
  üK-Ausbildungsprogramme und die Nutzungsbedingungen im Volltext konnten nicht geprüft werden.
- Bewertungskriterien je LBV-Element (Gewichtungsspannen, geprüfte Handlungsziele) sind geerntet,
  aber noch nicht gespeichert: siehe `docs/audit-backlog.md`.
