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
```

---

## Offen für David

- **Seitenleiste statt Topbar?** Apple-HIG-Umbau der Navigation ist vorgeschlagen, aber nicht
  entschieden. Nicht eigenmächtig umbauen.
- **Kantonale BMV**: ob sie an die BMV 2025 angepasst ist, liess sich nicht klären
  (`LAGE.md` 4.8). Betrifft die BM-Vorlage.
- **INBE-Modulverteilung**: Lektionentafel und Modulverteilungs-PDF widersprechen sich.
- **Lizenz und Meldeweg für Sicherheitslücken** sind weiterhin offen.
- **Prod-Testpasswort aus Commit `171ed72`** liegt in der Git-Historie und muss rotiert werden.
