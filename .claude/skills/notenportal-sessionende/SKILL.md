---
name: notenportal-sessionende
description: Übergabepaket am Ende jeder Session für den Chat, in dem der User die Prompts schreibt – Stand, gepflegte .md-Dateien, Skills/Agents/Plugins/Werkzeuge (neu markiert), Offenes, Zurückgestelltes mit Grund, Vorschlag, fertiger Prompt. Laden, bevor die Session endet.
---

# Sessionende – Übergabepaket

Ausführen, bevor du aufhörst. Alles in EINER Antwort ausgeben, kurz und vollständig. Nichts erfinden: jede Angabe aus einem Befehl unten.

## 1. Fakten sammeln (ein Bash-Aufruf)
```bash
cd /var/www/notenportal
git branch --show-current; git status --short | head; git log --oneline origin/main -1; git log --oneline -15
php artisan test 2>&1 | grep -E "Tests:"
ls docs/ .claude/skills/ .claude/agents/ .claude/hooks/ .claude/workflows/ ~/tools/visual/*.mjs
jq -r '.enabledPlugins | to_entries[] | select(.value) | .key' .claude/settings.json
git log --since="18 hours ago" --name-status --oneline -- .claude docs ~/tools 2>/dev/null | grep -E '^A' | head -30
```
Neu = Datei in `git log --since` mit Status `A`, oder in dieser Session angelegt.

## 2. Doku aktualisieren (vor dem Paket)
- `~/.claude/projects/-var-www-notenportal/memory/endspurt-testbetrieb.md`: Stand, Blöcke, Fallen. Indexzeile in MEMORY.md mitziehen.
- Offenes/Zurückgestelltes mit Begründung in `docs/audit-backlog.md`.
- Committen (Skill `notenportal-blockabschluss` Schritt 8), falls etwas offen ist.

## 3. Paket ausgeben – exakt diese Gliederung

```
## Übergabe Notenportal – <Datum>

**Stand**: Branch …, main = <hash>, Tests <N> grün, Prod <läuft/Problem>, i2 <Stand>.
**Diese Session**: 3–8 Stichpunkte, je eine Zeile, mit Commit-Hash.

**Dateien (.md), die ich nutze/pflege**
- CLAUDE.md – Regeln · docs/architektur.md · docs/funktionsumfang.md · docs/betrieb.md · docs/audit-backlog.md · docs/notenlogik.md · docs/endspurt-plan.md (Ideensammlung) · <weitere>
- Memory: ~/.claude/projects/-var-www-notenportal/memory/*.md

**Werkzeuge** (NEU markieren)
- Skills: … · Agents (Modell): … · Hooks: … · Workflows: … · Plugins: … · ~/tools/visual: …

**Offen**: Liste, je eine Zeile.
**Zurückgestellt** (Grund): Liste, je eine Zeile.
**Vorschlag nächste Session**: 3–5 Punkte, priorisiert.

**Prompt für die nächste Session**
> <fertig formulierter Prompt, 10–25 Zeilen, Deutsch: Lies CLAUDE.md und docs/…; Stand; Aufgaben in Reihenfolge; Abschluss über Skill notenportal-blockabschluss; am Ende Skill notenportal-sessionende>
```

## Regeln
- Keine Passwörter, Tokens oder SMTP-Zugangsdaten im Paket (Testbenutzer-Passwörter Chur7000 / Demo!2026 sind erlaubt).
- Höchstens ~60 Zeilen ohne den Prompt.
