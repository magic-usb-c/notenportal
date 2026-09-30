---
description: Lage einlesen und selbstständig am nächsten offenen Punkt weiterarbeiten
---

Arbeite selbstständig am Notenportal weiter. Keine Rückfragen an David, solange eine vertretbare
Annahme möglich ist — Annahme treffen, im Bericht nennen, weitermachen.

Schwerpunkt für diesen Lauf, falls angegeben: $ARGUMENTS
Ohne Angabe nimmst du den nächsten offenen Punkt aus `docs/auftrag/LAGE.md` Abschnitt 7.

## Zuerst lesen

1. `docs/auftrag/LAGE.md` — was gilt, was falsch ist, welche Quellen schon geprüft sind
2. `docs/auftrag/UEBERGABE.md` — was andere Sitzungen gerade tun und was für dich daliegt
3. `docs/auftrag/ARBEITSTEILUNG.md` — welche Verzeichnisse dir gehören
4. `CLAUDE.md` — harte Regeln
5. Den eigenen Auftrag, meist `docs/auftrag/NOTENBAUM.md`

Prüf dabei, ob du wirklich in einem Worktree stehst und nicht in `/var/www/notenportal`. Wenn doch:
sofort aufhören und es melden — diese Arbeitskopie ist Produktion.

## Dann arbeiten

- Vor jeder Behauptung über eine Ursache: **messen, nicht raten.** Bau ein Instrument, wenn keines
  da ist. In dieser Codebasis hat Raten schon zwei Tage gekostet.
- Bevor eine Zahl aus der Schweizer Berufsbildung in Code oder Stammdaten landet: Agent
  `recherche-schweiz`. `LAGE.md` Abschnitt 4 hat den bereits belegten Bestand.
- Vor jedem Schema-Eingriff: Skill `notenportal-migration` (Dump und Tag), erst gegen
  `notenportal_probe` inklusive Rollback.
- Vor Arbeit an Views oder CSS: Skill `notenportal-ui`.
- Nach jedem lauffähigen Stand committen: Tests grün, Seite lädt. Push nur auf den eigenen
  `claude/*`-Branch, nie auf `main`.
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
