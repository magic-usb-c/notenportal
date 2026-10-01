---
name: notenportal-orchestrierung
description: Welches Modell und welcher Effort für welche Teilaufgabe, wann Subagent, Workflow oder selbst, wie neue Agents/Skills/Workflows angelegt werden. Laden, bevor eine Aufgabe verteilt wird, die mehr als eine Datei oder mehr als einen Schritt umfasst.
---

# Orchestrierung im Notenportal

Die Hauptsitzung läuft als **Fable 5.1, Effort xhigh, Ultracode an, Advisor Fable** (`.claude/settings.json`).
Sie ist Leiterin, nicht Arbeiterin: sie zerlegt, verteilt, verifiziert, committet. Sie schreibt selbst
nur Kleinigkeiten (eine Zeile, eine Datei) oder das, was nach zwei Agent-Runden immer noch falsch ist.

## 1. Modell und Effort je Aufgabe

| Aufgabe | Modell | Effort | Form |
|---|---|---|---|
| Dateien finden, Inventar, Vorkommen zählen, Routen auflisten | haiku (`Explore`) · sonnet (`explorer`) | low | `Explore`-Agent mit `model: haiku`, oder `explorer` |
| Schema/DB nachsehen | sonnet | medium | `db-inspector` |
| Umsetzen (Controller, Views, Tests), Texte, Doku | sonnet | high | Subagent oder Workflow-Stage |
| Code-Review eines Diffs | sonnet | high | `reviewer` |
| UI-Regelprüfung je Rollenbereich | opus | xhigh | `ui-checker` |
| Sichtprüfung von Screenshots (Dunkelmodus, HIG) | opus | xhigh | `bildpruefer` |
| Behauptung «fertig/behoben/grün» widerlegen | opus | xhigh | `pruefer` |
| Architektur, Datenmodell, Migration mit Risiko | opus | xhigh | Subagent, dann `pruefer` |
| Hartnäckiger Fehler nach zwei erfolglosen Runden | opus → fable | xhigh → max | Subagent, Instrument bauen, messen |
| Letzte Verifikation, wenn opus-Prüfer sich widersprechen | fable | max | Workflow-Stage mit Mehrheitsvotum |
| Fakten Schweizer Berufsbildung | sonnet | high | `recherche-schweiz` |

Vokabular (geprüft an der Claude-Code-Dokumentation, Stand 01.10.2026): `model: haiku|sonnet|opus|fable|inherit`,
`effort: low|medium|high|xhigh|max` – im Agent-Frontmatter, in `agent(prompt, {model, effort})` eines
Workflows und im Skill-Frontmatter. Ohne Angabe erbt ein Agent Modell und Effort der Hauptsitzung
(= Fable xhigh): das ist für mechanische Arbeit Verschwendung, deshalb immer setzen.

## 2. Selbst, Subagent oder Workflow

- **Selbst:** Antwort auf eine Frage, Edit an einer Datei, Commit, Bericht.
- **Subagent (`Agent`-Werkzeug):** eine abgegrenzte Aufgabe mit klarem Ergebnis – Suche, Review,
  Umsetzung an genannten Dateien. Mehrere unabhängige Agents immer in einer Nachricht starten.
- **Workflow (`Workflow`-Werkzeug, Ultracode):** jede Aufgabe mit mehreren Dimensionen, Rollen,
  Seiten oder Befunden. Muster: Befunde parallel sammeln (`pipeline`), **jeden Befund gegnerisch
  verifizieren** (2–3 Prüfer mit verschiedenen Blickwinkeln, Mehrheit zählt), erst dann umsetzen.
  Vorlagen: `.claude/workflows/notenportal-audit.js` (Code und Views),
  `.claude/workflows/notenportal-dunkel-rundgang.js` (Screenshots je Rolle im Dunkelmodus).
  Ein bewährter Lauf wird gespeichert (`/workflows`, Taste `s`, Ziel «Projekt») und committet.
- In der Cloud gilt `CLAUDE_CODE_MAX_SUBAGENT_SPAWN_DEPTH=1`: Subagents starten keine Subagents.
  Workflows laufen aus der Hauptsitzung heraus, das ist deshalb kein Hindernis.

## 3. Regeln für jeden Auftrag

- Vorlage und Pflichtblock: Skill `notenportal-agentauftrag`.
- Subagents schreiben nie Git, Composer oder npm (auch keine Dry-Runs). Commit nur in der Hauptsitzung.
- Parallel umsetzende Agents bekommen disjunkte Dateien; sonst `isolation: worktree` im Workflow.
- Jede Behauptung eines Agents («Tests grün», «sieht gut aus») wird nachgemessen: Suite selbst
  laufen lassen, Screenshot selbst ansehen, `pruefer` ansetzen.
- Advisor (`advisor`-Werkzeug): vor dem ersten substanziellen Schritt einer Aufgabe und vor der
  Fertigmeldung. Vorher den Stand committen.

## 4. Neue Agents, Skills, Workflows anlegen

Die Hauptsitzung legt sie selbst an, wenn eine Aufgabe wiederkehrt, und committet sie.

- **Agent:** `.claude/agents/<name>.md` mit Frontmatter `name, description, tools, model, effort`,
  optional `disallowedTools, maxTurns, skills, isolation: worktree, background: true`. Beschreibung so
  schreiben, dass klar ist, *wann* er zu nehmen ist. Deutsch, max. ~40 Zeilen Anweisung.
- **Skill:** `.claude/skills/<name>/SKILL.md` mit `name, description`, optional `model, effort,
  allowed-tools, paths`. Nur Wissen, das sonst jedes Mal neu erarbeitet würde. Nach dem Anlegen
  `/reload-skills`.
- **Regel mit Pfadbezug:** `.claude/rules/<thema>.md` mit Frontmatter `paths:` – wird automatisch
  geladen, sobald eine passende Datei bearbeitet wird (`.claude/rules/oberflaeche.md`).
- **Workflow:** `.claude/workflows/<name>.js`, Kopf `export const meta = { name, description, phases }`
  als reines Literal; Skript nutzt `agent()`, `pipeline()`, `parallel()`, `phase()`, `log()`;
  JSON-Schema für strukturierte Ergebnisse. Kein `Date.now()`, kein Dateisystem. Aufruf
  `Workflow({name})` oder `/run`.
- **Befehl:** `.claude/commands/<name>.md` – ein Prompt mit `$ARGUMENTS`, als `/<name>` aufrufbar.

## 5. Was nicht automatisiert wird

Entscheide mit Aussenwirkung (Produkt, Recht, Lizenz, Passwortrotation) kommen unter «Offen für
David» in `docs/auftrag/UEBERGABE.md`. Alles andere wird entschieden, dokumentiert, gemacht.
