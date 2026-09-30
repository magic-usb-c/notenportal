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
```

---

## Offen für David

- **Seitenleiste statt Topbar?** Apple-HIG-Umbau der Navigation ist vorgeschlagen, aber nicht
  entschieden. Nicht eigenmächtig umbauen.
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
