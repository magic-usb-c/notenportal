# Modulkatalog aus offiziellen Quellen

Ziel: Modulnummern, Titel, Versionen, Handlungsziele, Kompetenzfeld und die
Leistungsbeurteilungsvorgabe (LBV) stehen als Stammdaten im Portal, statt sie von Hand zu tippen –
und jedes Modul verlinkt auf seine Seite im Modulbaukasten.

## Rechtslage – zuerst lesen

Die Inhalte von modulbaukasten.ch gehören **ICT-Berufsbildung Schweiz**. Die «Nutzungsbedingungen
Modulbaukasten» (`www.ict-berufsbildung.ch/infos/nutzungsbedingungen-mbk`, im Volltext gelesen am
13.09.2026) erlauben die Nutzung ausdrücklich zu dem Zweck, «dass Sie sich über die Berufsbildung im
Bereich der ICT-Berufe für Ihren eigenen Bedarf informieren können». Kopieren, Ändern, Verbreiten und
Vervielfältigen brauchen dagegen die vorherige schriftliche Zustimmung, eine Wiederverwendung für
kommerzielle Angebote ist untersagt, und übermässige Last auf der Infrastruktur ist es ebenfalls.
Daraus folgt im Portal:

- **Im Repo liegt kein Katalog.** Keine Modulliste, keine Handlungsziele, keine LBV – auch nicht in
  Seeds oder Tests (Testdaten sind erfundene Nummern und Titel). Ein öffentliches Repository wäre
  Verbreitung, und die braucht die Zustimmung.
- **Ernten für die eigene Instanz ist der erlaubte Fall**: eine Schule, ein Betrieb oder ein
  Lernender informiert sich damit über die eigene Berufsbildung. Wer Inhalte weitergeben will, klärt
  die Zustimmung selbst.
- **Verlinken ist unbedenklich** und immer möglich: ein Link ist keine Übernahme von Inhalt.
- **Die Ernte schont die Quelle.** Zwischen zwei Seiten liegt eine Pause (`--pause`, Standard
  1500 ms); sie gehört zur Zulässigkeit und wird nicht wegoptimiert.

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

Der Import ist alles-oder-nichts, legt fehlende Lehrberufe an und ordnet Module mit Lernort
(Fachunterricht/ÜK), Pflichtgrad und empfohlenem Lehrsemester zu. Wiederholte Läufe aktualisieren,
statt zu verdoppeln. Abschlüsse, die sich nur im Jahrgang der Bildungsverordnung unterscheiden,
bleiben getrennte Lehrberufe – ihre Modulpläne sind verschieden.

### Eigene Nummern und Katalognummern

`modul_nummer` ist im Portal eindeutig: eine Zeile kann nicht gleichzeitig «ÜK: Grundlagen» und
Katalogmodul 301 sein. Daran entscheidet sich, was der Import mit einem bestehenden eigenen Modul
macht:

- Wer **431** mit eigener Kurzbezeichnung erfasst hat, meint Katalogmodul 431. Der Import ergänzt
  Version und Katalogangaben und behält den eigenen Titel.
- Wer **801** für ein ABU-Modul erfunden hat, meint nicht «Grundgesetze der Farbenlehre» – der
  Katalog führt diese Nummer bei Mediamatik. Er erkennt das daran, dass der Katalog die Nummer bei
  keinem Beruf dieses Moduls führt, meldet sie und lässt sie ganz aus. Ohne diese Regel erbte jeder
  andere Beruf die fremde Bezeichnung, und der Verweis zeigte auf ein anderes Modul.

Zwei Auswege: dem eigenen Modul eine Nummer geben, die keine Katalognummer sein kann, oder mit
`--eigene-uebernehmen` einlesen, wenn es doch dieselben Module sind.

Was keine Katalognummer sein kann, ist genauer als es aussieht. Das Portal streift die führenden
Bezeichner `MODUL`, `ÜK`, `UEK`, `BK` und `M` ab – **der Buchstabe voran schützt also nicht**, `UEK301`
meint Katalogmodul 301. Was zählt, ist die Zahl: Katalognummern sind drei- oder vierstellig (alle 334
geernteten sind es). Sicher sind darum zweistellige Nummern wie `UEK01` und Präfixe ausserhalb jener
Liste wie `ABU01`.

## Was im Portal sichtbar wird

- Stammdaten → Module: Version, Kompetenzfeld und ein Verweis «Im Modulbaukasten öffnen».
- Ein Verweis entsteht **nur mit bekannter Version**. Ohne Version kein Link – eine geratene
  Adresse würde auf eine Seite zeigen, die freundlich HTTP 200 antwortet und trotzdem falsch ist.
- Eine Nummer, eine Zeile: sind zwei Versionen gleichzeitig gültig (117 V4 für die Verordnung 2021,
  V5 für 2026), führt die höchste. Der Verweis zeigt dann für den älteren Jahrgang auf die neuere
  Version. 17 der 334 geernteten Nummern sind betroffen; damit es stimmt, müsste die Zuordnung je
  Beruf die Version mitführen – notiert in `docs/audit-backlog.md`.

## Offen

- Schriftliche Zustimmung von ICT-Berufsbildung Schweiz für die Übernahme von Feldinhalten (David).
- üK-Ausbildungsprogramme auf `ict-berufsbildung.ch` sind noch nicht geprüft. Die Seite ist aus
  diesem Netz erreichbar – die früher notierte Sperre gilt nicht mehr –, und die Nutzungsbedingungen
  sind im Volltext gelesen; die üK-Unterlagen selbst noch nicht.
- Bewertungskriterien je LBV-Element (Gewichtungsspannen, geprüfte Handlungsziele) sind geerntet,
  aber noch nicht gespeichert: siehe `docs/audit-backlog.md`.
