---
description: Lage einlesen und selbstständig am nächsten offenen Punkt weiterarbeiten
---

Arbeite selbstständig am Notenportal weiter. Keine Rückfragen an David, solange eine vertretbare
Annahme möglich ist — Annahme treffen, im Bericht nennen, weitermachen. Hör nicht von selbst auf:
nach jedem abgeschlossenen Punkt kommt ohne Rückfrage der nächste.

Schwerpunkt für diesen Lauf, falls angegeben: $ARGUMENTS
Ohne Angabe nimmst du den obersten offenen Punkt aus `docs/auftrag/UEBERGABE.md` («Offen»), danach
`docs/audit-backlog.md`.

## Zuerst lesen

1. `docs/auftrag/LAGE.md` — was gilt, was falsch ist, welche Quellen schon geprüft sind
2. `docs/auftrag/UEBERGABE.md` — was bereits getan wurde und was offen ist
3. `CLAUDE.md` — harte Regeln, Massstab Desktop/Dunkelmodus
4. Skill `notenportal-orchestrierung` — welches Modell und welcher Effort für welche Teilaufgabe
5. Den Auftrag zum Punkt: `docs/auftrag/GUI-APPLE.md` (Oberfläche) oder `docs/auftrag/NOTENBAUM.md` (Rechnung)

## Dann arbeiten

- Vor jeder Behauptung über eine Ursache: **messen, nicht raten.** Bau ein Instrument, wenn keines
  da ist. In dieser Codebasis hat Raten schon zwei Tage gekostet.
- Substanzielle Aufgaben als Workflow: Befunde parallel sammeln, gegnerisch verifizieren, dann
  umsetzen (Skill `notenportal-orchestrierung`, Vorlagen in `.claude/workflows/`).
- Bevor eine Zahl aus der Schweizer Berufsbildung in Code oder Stammdaten landet: Agent
  `recherche-schweiz`. `LAGE.md` Abschnitt 4 hat den bereits belegten Bestand.
- Vor Arbeit an Views oder CSS: Skills `notenportal-ui` und `notenportal-dunkelmodus`. Jede
  sichtbare Änderung mit `tools/pruefung/shot.mjs` (dunkel, 1920) ansehen, nicht nur bauen.
- Schema-Eingriff auf einer VM: Skill `notenportal-migration`. In der Cloud reicht
  `DB_DATABASE=notenportal_demo php artisan migrate`.
- Nach jedem lauffähigen Stand committen und auf `main` pushen: Tests grün, Seite lädt.
- Bevor du einen Block als erledigt meldest: Agent `pruefer` darauf ansetzen. Dessen Befunde
  abarbeiten, bevor du weitergehst.
- Nach jedem Block: Skill `notenportal-blockabschluss`, Eintrag in `docs/auftrag/UEBERGABE.md`.

## Wenn du an eine Grenze stösst

Entscheide selbst und dokumentiere die Annahme. Nur was David wirklich entscheiden muss — Produkt-
oder Rechtsfragen, Dinge mit Aussenwirkung — kommt unter «Offen für David» ins Übergabebrett.
Dort weiterarbeiten, wo du auch ohne die Antwort vorankommst; nicht stehenbleiben und warten.

Was du bewusst liegen lässt, kommt mit Begründung nach `docs/audit-backlog.md`.

## Berichten

Höchstens acht Zeilen, Deutsch, keine Füllwörter, keine Rückschau auf das, was du gerade getan
hast. Was ist jetzt anders, was ist bewiesen, was ist als Nächstes dran.

Dann ohne Rückfrage den nächsten Punkt nehmen.
