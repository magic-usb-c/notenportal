---
name: notenportal-sessionende
description: Übergabepaket am Ende jeder Session – Stand, gepflegte .md-Dateien, Skills/Agents/Workflows/Werkzeuge (neu markiert), Offenes, Zurückgestelltes mit Grund, Vorschlag, fertiger Prompt. In der Cloud überlebt nur, was committet ist – das Paket gehört deshalb auch nach docs/auftrag/UEBERGABE.md. Laden, bevor die Session endet.
---

# Sessionende – Übergabepaket

Ausführen, bevor du aufhörst. Alles in EINER Antwort ausgeben, kurz und vollständig. Nichts erfinden:
jede Angabe aus einem Befehl unten.

## 1. Fakten sammeln (ein Bash-Aufruf)
```bash
git branch --show-current; git status --short | head; git fetch -q origin main; git log --oneline origin/main -1; git log --oneline -15
php artisan test 2>&1 | grep -E "Tests:"
ls docs/auftrag .claude/skills/ .claude/agents/ .claude/hooks/ .claude/workflows/ .claude/rules tools/pruefung
jq -r '.enabledPlugins | to_entries[] | select(.value) | .key' .claude/settings.json
git log --since="18 hours ago" --name-status --oneline -- .claude docs tools 2>/dev/null | grep -E '^A' | head -30
```
Neu = Datei in `git log --since` mit Status `A`, oder in dieser Session angelegt.

## 2. Doku aktualisieren (vor dem Paket)
- `docs/auftrag/UEBERGABE.md`: Eintrag im Verlauf, Abschnitt «Offen» und «Offen für David» nachführen.
- Offenes/Zurückgestelltes mit Begründung in `docs/audit-backlog.md`.
- Auto-Memory (`~/.claude/projects/…/memory`) ist in der Cloud nicht beständig – was die nächste
  Session wissen muss, steht in `LAGE.md`, `UEBERGABE.md` oder `SETUP-CLAUDE.md`.
- Committen (Skill `notenportal-blockabschluss` Schritt 8), falls etwas offen ist. Ungepushtes ist weg,
  sobald der Container endet.

## 3. Paket ausgeben – exakt diese Gliederung

```
## Übergabe Notenportal – <Datum>

**Stand**: main = <hash>, Tests <N> grün, Demo-Server <läuft/Problem>.
**Diese Session**: 3–8 Stichpunkte, je eine Zeile, mit Commit-Hash.

**Dateien (.md), die ich nutze/pflege**
- CLAUDE.md – Regeln · docs/auftrag/LAGE.md · UEBERGABE.md · SETUP-CLAUDE.md · docs/architektur.md · docs/funktionsumfang.md · docs/betrieb.md · docs/audit-backlog.md · docs/notenlogik.md · <weitere>

**Werkzeuge** (NEU markieren)
- Skills: … · Agents (Modell/Effort): … · Hooks: … · Workflows: … · Plugins: … · tools/pruefung: …

**Offen**: Liste, je eine Zeile.
**Zurückgestellt** (Grund): Liste, je eine Zeile.
**Vorschlag nächste Session**: 3–5 Punkte, priorisiert.

**Prompt für die nächste Session**
> <fertig formulierter Prompt, 10–25 Zeilen, Deutsch: /weiter mit Schwerpunkt …; Stand; Aufgaben in Reihenfolge; Abschluss über Skill notenportal-blockabschluss; am Ende Skill notenportal-sessionende>
```

## Regeln
- Keine Passwörter, Tokens oder SMTP-Zugangsdaten im Paket – auch keine Testbenutzer-Passwörter.
  Prüfwerkzeuge erhalten das Passwort ausschliesslich über `NP_TEST_PW`.
- Höchstens ~60 Zeilen ohne den Prompt.
