# Übergabebrett

Gemeinsames Brett aller parallel laufenden Sitzungen. Beim Start lesen, nach jedem abgeschlossenen
Block ergänzen. Neueste Einträge unten.

Format: `[Kürzel] TT.MM. HH:MM  Was ist passiert / was wird gebraucht.`
Kürzel: **K** Kern und Notenlogik · **V** Verwaltung und Stammdaten · **O** Oberfläche ·
**Q** Qualität, Tests, Dokumentation.

Wer eine Änderung ausserhalb seiner Verzeichnisse braucht (`docs/auftrag/ARBEITSTEILUNG.md`),
editiert nicht selbst, sondern schreibt sie hier hinein.

Entscheide, die David treffen muss, kommen unter «Offen für David» — nicht selbst entscheiden.

---

## Verlauf

```
[—] 30.09. 14:39  Ausgangslage festgehalten: docs/auftrag/LAGE.md, NOTENBAUM.md, ARBEITSTEILUNG.md.
                  Dump notenportal-20260930-1439-vor-notenbaum.sql + Tag vor-notenbaum gesetzt.
[K] 30.09. 16:50  Block 1 Notenbaum fertig (3458e6c a6946be b12a955 90e6cf2 a3f664c, Prüfer-Fixes
                  7b229e5 a9a6274). Rechenkern je Knoten Gewicht/Rundung/Fallnote/max. ungenügend/
                  Minuspunkte/zählt/entfällt mit Track; Stufen (Sport) und «zählt nicht» je Fach;
                  Vorlagen EFZ BiVo 2020 und BM TALS1 als JSON mit Import/Export/Artisan; Seite
                  «Abschluss» für Lernende und Verwaltung; Stammdaten → Notenbäume.
                  Prüfer: 9 Befunde, alle behoben mit Test, der ohne Fix fällt (Positionen überleben
                  Reimport/Wechsel, Stufen in CSV und Notenblatt, Ampel kennt den Baum, BM-Promotion
                  per Migration 2026_10_01_000003 auch auf bestehenden DBs, Rollback bricht vor DDL ab).
                  Prod braucht: notenportal:migrate (3 Migrationen 2026_10_01_*).
[V] 30.09. 16:50  Block 2 Stammdaten nutzbar (a3f664c 2a58562). Einrichtung Schritt 4 liest Vorlagen
                  aus resources/vorlagen/stammdaten (Standard Graubünden: Italienisch statt
                  Französisch, Sport als Stufe, IDAF zählt nicht; zweite Vorlage «Schweiz ICT»), lädt
                  passende Notenbäume mit und verlinkt Fächer- und Baumpflege. Lücken 7/8 aus LAGE
                  geschlossen; 3–6 bewusst gelassen (audit-backlog).
[K] 30.09. 18:55  Nachträge Notenbaum/Stammdaten nach Prüfer: Wechsel verschiebt Positionen statt
                  sie zu löschen (0b655d1), holt sie aus allen Bäumen, zuletzt erfasste gewinnt
                  (8a0429a); Wechsel ohne Deadlock, liest Positionen sperrend, Speichern während
                  eines Wechsels bricht mit Meldung ab statt im alten Baum zu landen (5aad6ed, mit
                  zwei Prozessen gegen MariaDB nachgemessen). Einrichtung Schritt 4 ganz oder gar
                  nicht (31a8638); Vorlage meldet unbekannte Baumverweise (6f7a272). Demo hat den
                  QV-Baum (20805e1). Notenimport: 60 Zeilen 17 statt 163 Abfragen (5f0d8ab), #16 erledigt.
[Q] 30.09. 18:55  Block 4 install.sh (8e8af07 6341f34 7955478 a6c6047). Zieht aus dem Home-Verzeichnis
                  nach /var/www um, prüft den Dienststart statt «fertig» zu melden, npm vertraut der
                  System-CA, hält vor fremder DB/belegtem vhost-Namen/fremdem Port an, verliert das
                  Startpasswort bei Abbruch nicht, setzt DB_TIMEZONE samt Zeitzonentabellen.
                  Gemessen in frischen Containern (Ubuntu 24.04 mit systemd, nicht 26.04), zweite
                  Instanz daneben auf Port 8082. Ablauf: docs/betrieb.md «Neuinstallation».
[O] 30.09. 20:30  Block 3 Oberfläche nach Apple-HIG (efcfc5d 8551493 0e3f91a 9914e1f 6341f34 76c8573
                  64fbfaf 6f8e1ee 8933e00 bda1608 872e615 5effc77 4d7b0a2 b0c6197 0d19dfc 3fdf3ff
                  7c7ac01 c97ae3e 95e84ea faa42aa 708cac2 6ec839e 39b9e56 8a96715 1c358d1 0615045
                  06fd5aa e326d82). Seitenleiste nach HIG je Person umschaltbar, Topbar bleibt und legt
                  Überzähliges in «Mehr» (Priority+). Tabellen schalten Spalten nach Kartenbreite
                  (Container-Queries) statt Fenster; schmal Karten oder Werte in der Namenszelle.
                  Gemessen: alle GET-Seiten je Rolle bei 320–1280 px und mit Seitenleiste ohne
                  Querscrollen, einzige Ausnahme die Zeugnisnoten-Heatmap auf dem Telefon (audit-backlog).
                  Prüfer: 2 Befunde + Nebenbefunde, behoben mit Tests, die gegen den alten Stand fallen.
                  Achtung: Commit-Text von bda1608 ist verdreht – die Tabelle war 1625 px breit in
                  einem 1374 px breiten Container, nicht umgekehrt.
[Q] 30.09. 20:30  Block 5 Aufräumen (835b08c 0204bd7 924496a 2a0ddac ea7e431 36dc9ea). Rückmeldungen
                  12.09. alle erledigt (#15 Phase 2 bewusst nach Go-Live). i18n-Scan von 26 auf 0,
                  Test hält die Views dort. Toter Code (Note-Scopes, Leistung::toArray) entfernt.
                  Rollentrennung: jede Berufsbildner-Route mit Lernenden-/Prüfungsbezug und jedes
                  fremde Objekt der Lernenden per Sweep mit Gegenprobe und Vollständigkeitsprüfung.
                  Dabei gefunden und behoben: Abgabetermin ohne Gewichtung ergab 500 (ea7e431).
                  «Backlog A» ist nirgends definiert; ausgelegt als die offenen Punkte in
                  audit-backlog.md – erledigte mit Beleg markiert, Rest begründet gelassen.
```

---

## Offen für David

- **Standard der Navigation**: Seitenleiste nach HIG ist gebaut und je Person umschaltbar
  (Einstellungen → Darstellung), die Topbar bleibt. Standard ist «Oben»; ob neue Konten mit der
  Seitenleiste starten sollen, entscheidest du.
- **Gewichte nach BM-Dispens von der Allgemeinbildung**: Quellen widersprechen sich (hochrechnen
  57,14/42,86 vs. 50/50). Nicht voreingestellt; im Baum lässt sich «Entfällt mit BMS» je Knoten setzen.
- **Rundung der Allgemeinbildung und der IPA im QV**: nur aus Sekundärquellen, Primärquelle
  (Bildungsverordnung/Bildungsplan) war hinter dem Proxy nicht erreichbar.
- **Mehrere BM-Ausrichtungen**: ein BM-Baum hängt am Track BMS; zwei Ausrichtungen gleichzeitig
  (TALS und WD) gehen erst mit Bildungsgängen als eigene Tabelle (LAGE Lücke 3).
- **Kantonale BMV**: ob sie an die BMV 2025 angepasst ist, liess sich nicht klären
  (`LAGE.md` 4.8). Betrifft die BM-Vorlage.
- **INBE-Modulverteilung**: Lektionentafel und Modulverteilungs-PDF widersprechen sich.
- **Lizenz und Meldeweg für Sicherheitslücken** sind weiterhin offen.
- **Prod-Testpasswort aus Commit `171ed72`** liegt in der Git-Historie und muss rotiert werden.
