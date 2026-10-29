---
name: notenportal-orchestrierung
description: "Bei Aufgaben im Notenportal: Modell und Effort wählen, Arbeit passend selbst erledigen oder delegieren, Workflows sicher steuern und Ergebnisse verifizieren. Vor autonomer Fortsetzung und substanziellen Aufgaben laden."
---

# Orchestrierung im Notenportal

## Auftrag

Du koordinierst die Arbeit im Notenportal effizient, sorgfältig und innerhalb des erteilten Auftrags. Du bist für Priorisierung, passende Aufteilung, sichere Delegation, Zusammenführung und überprüfbare Ergebnisse verantwortlich. Nutze spezialisierte Agents und Workflows, wenn sie einen klaren Vorteil bringen; delegiere nicht bloss um des Delegierens willen.

Arbeite selbst an kleinen, klar begrenzten Änderungen oder wenn kein passender Agent beziehungsweise Workflow verfügbar ist. Bei grösseren Aufgaben delegierst du Umsetzung oder unabhängige Prüfungen und bleibst für Integration und Ergebnis verantwortlich.

## Verbindliche Prioritäten

1. System-, Entwickler- und Sicherheitsvorgaben haben Vorrang vor diesem Skill.
2. Danach gelten `CLAUDE.md`, einschlägige Projektregeln und der konkrete Auftrag des Benutzers.
3. Lies vor einer autonomen Fortsetzung `docs/auftrag/LAGE.md` und `docs/auftrag/UEBERGABE.md`. Lies `CLAUDE.md` und `docs/auftrag/SETUP-CLAUDE.md`, wenn Regeln, Modellwahl oder Laufzeit relevant sind.
4. Bei widersprüchlichen Projektangaben gilt die nachweisbare aktuelle Quelle. Halte ungeklärte Punkte als Annahme oder offene Frage fest; erfinde keine Fakten.

Ein ausdrücklicher Benutzerauftrag begrenzt den Arbeitsumfang. `/weiter` erteilt zusätzlich den Auftrag, den dokumentierten Arbeitsplan fortzuführen. Ohne diese Ermächtigung beginnst du nach Erledigung des konkreten Auftrags keinen sachfremden nächsten Backlog-Punkt.

## Modell- und Effortwahl

Die Hauptsitzung koordiniert mit dem konfigurierten Hauptmodell. Verwende für Teilaufgaben das kleinste passende Modell und den passenden Effort. Die folgende Tabelle ist eine Routing-Hilfe, keine Garantie, dass ein Modell oder Werkzeug in jeder Laufzeit verfügbar ist:

| Aufgabe | Bevorzugte Zuweisung | Effort / Form |
|---|---|---|
| Suche, Inventar, Vorkommen, einfache Log-Analyse | `Explore` mit Haiku, falls verfügbar; sonst Agent `explorer` (sonnet) | low (`Explore`) · medium (`explorer`); eng begrenzter Leseauftrag |
| Schema und Datenbestand | Agent `db-inspector` (opus) | medium |
| Standard-Implementierung und Tests | Agent `explorer` nur zur Orientierung; Umsetzung durch geeigneten Sonnet-Agent oder Workflow-Stage | high |
| Code-Review | Agent `reviewer` (sonnet) | high |
| Menschliche Texte | Agent `texter` (haiku) | high |
| Schweizer Berufsbildungsfakten | Agent `recherche-schweiz` (opus) | medium |
| UI-Regelprüfung | Agent `ui-checker` (opus) | high |
| Screenshot-Sichtprüfung | Agent `bildpruefer` (sonnet) | high |
| Gegenprüfung einer Fertig-/Fehlerbehauptung | Agent `pruefer` (fable) | low |
| Architektur oder riskante Migration | zuerst passende Fachprüfung, bei Bedarf Opus; Fable nur für echte Architektur- oder Konfliktentscheidung | xhigh; Fable max nur als letzte Verifikation |
| Hartnäckiger Fehler | messen und lokalisieren, dann gezielte Reparatur; bei weiterem Konflikt eskalieren | nicht durch blindes Modell-Hochstufen ersetzen |

Die Frontmatter-Werte der tatsächlich vorhandenen Agents und `docs/auftrag/SETUP-CLAUDE.md` (Abschnitt 2.3) sind massgeblich; die Tabelle entspricht ihnen seit dem 02.10. (Commit 017131c). Erfinde keine Agenten, Modelle, Effort-Stufen oder Fähigkeiten. Falls die bevorzugte Zuweisung nicht verfügbar ist, verwende die nächste verfügbare passende Option und benenne die Abweichung nur, wenn sie für die Verifikation relevant ist.

## Arbeitsrouting

1. **Auftrag schärfen:** Bestimme gewünschtes Ergebnis, betroffene Oberfläche und nötige Abnahmekriterien. Bei einem kleinen Auftrag arbeite direkt. Bei einem grösseren Auftrag identifiziere zunächst den kleinsten entscheidenden Codepfad und einen gezielten Check.
2. **Workflow wählen:** Aufgaben mit Änderungen an mehreren Dateien laufen als Workflow. Nutze einen vorhandenen Workflow, wenn er zur Aufgabe passt; neue Workflow-Infrastruktur nur anlegen, wenn sie wiederverwendbar und tatsächlich nötig ist.
3. **Teilaufgaben schneiden:** Jede Delegation erhält ein eindeutiges Ziel, konkrete Dateigrenzen, nötigen Kontext, verbotene Aktionen, Prüfauftrag und ein kurzes Rückmeldeformat. Lies vor Code-Delegation `.claude/skills/notenportal-agentauftrag/SKILL.md` und verwende dessen Pflichtblock.
4. **Parallelisieren mit Grenzen:** Parallel laufen nur unabhängige Aufgaben. Teile gemeinsame Dateien nicht gleichzeitig mehreren Agents zu; falls nötig, nutze die im Projekt vorgesehene Isolation. In der Cloud keine verschachtelten Subagents starten. Subagents führen keine schreibenden Git-, Composer- oder npm-Befehle aus.
5. **Workflow-Aufrufe absichern:** Setze bei jedem `agent(prompt, { model, effort })` sowohl `model` als auch `effort` ausdrücklich. Prüfe, dass jeder Aufruf im Workflow diese Werte erhält; sonst kann er das teure Hauptmodell erben. Passe Parallelität und Bündelgrösse an Laufzeit, Rate-Limits und unabhängige Arbeit an.
6. **Ergebnisse integrieren:** Behandle Agent-Berichte als Behauptungen, nicht als Beweis. Prüfe Änderungen und Diff selbst, führe die passende fokussierte Validierung aus und löse widersprüchliche Befunde durch zusätzliche Evidenz statt Mehrheitsentscheid.

## Sicherheit und Änderungsgrenzen

- Lies und respektiere den bestehenden Arbeitsbaum. Überschreibe, verwerfe oder stage keine fremden Änderungen; trenne eigene und fremde Hunks.
- Niemals `git reset --hard`, `git clean`, `git checkout` oder `git restore` verwenden, um Fehler oder nicht bestandene Tests zu kaschieren. Keine fremden Änderungen löschen. Ein Fehlschlag ist kein Grund für einen destruktiven Rollback.
- `.env` und `tmp-testdaten/` nicht lesen, ändern, ausgeben oder in Fixtures/Dokumentation übernehmen. Geheimnisse und Passwörter nicht in Argumente, Logs, Screenshots oder Berichte schreiben; Testpasswörter ausschliesslich über die vorgesehene Umgebungsvariable verwenden.
- Tests nur gegen die vorgesehene eigene Testdatenbank ausführen. Datenbank- und Migrationsregeln aus `CLAUDE.md` sowie `notenportal-migration` strikt einhalten; niemals produktive oder fremde Datenbanken für Tests verwenden.
- Keine Aussenwirkung, zusätzliche Produktentscheidung oder Ausweitung des Auftrags ohne Autorisierung. Wenn eine Entscheidung tatsächlich nur der Benutzer treffen kann, dokumentiere sie unter «Offen» und arbeite an unabhängigen Teilen weiter.
- Commit und Push nach `notenportal-blockabschluss` nur, wenn Projektvorgaben, Sitzungsberechtigungen und höherrangige Anweisungen es erlauben. Explizit betroffene Dateien stagen, niemals pauschal `git add -A`; niemals force-pushen. Keine dieser Regeln rechtfertigt das Umgehen einer Berechtigungssperre.

## Fehler- und Eskalationsprotokoll

1. Lies die vollständige Fehlermeldung und identifiziere den fehlgeschlagenen Schritt. Unterscheide Produktfehler, Testfehler, Umgebungsproblem und nicht reproduzierbaren Befund.
2. Führe den kleinsten Check aus, der die vermutete Ursache bestätigen oder widerlegen kann. Bei unklarer Ursache delegiere eine eng begrenzte Diagnose an `explorer` oder den passenden Fach-Agent.
3. Repariere nur die betroffene Ursache und wiederhole denselben fokussierten Check. Ändere nicht mehrere Hypothesen gleichzeitig.
4. Nach zwei erfolglosen, substanziell unterschiedlichen Reparaturversuchen: stoppe spekulative Änderungen, sichere den aktuellen Arbeitsstand, dokumentiere Belege und verbleibendes Risiko. Verwende keinen automatischen Reset. Eskaliere an den passend stärkeren Fach-Agent oder melde einen echten Blocker; setze unabhängige, autorisierte Arbeit fort.
5. Dokumentiere bewusst zurückgestellte Punkte mit Grund in `docs/audit-backlog.md`, wenn sie zum Auftrag gehören. Überspringe keinen offenen Pflichtcheck und behaupte keinen Erfolg ohne Beleg.

## Validierung und Abschluss

- Nach der ersten inhaltlichen Änderung folgt als nächster Schritt ein fokussierter ausführbarer Check, sofern verfügbar. Bei einem Fehlschlag repariere dieselbe Scheibe und wiederhole diesen Check, bevor du den Umfang erweiterst.
- Wähle weitere Prüfungen nach Risiko und Projektvorgaben: passende Tests, Formatierung, Build, Text-/Hook-Prüfungen und bei sichtbaren Änderungen die vorgeschriebenen Screenshots beziehungsweise Rundgänge.
- Vor Abschluss eines implementierten Blocks lade und befolge `.claude/skills/notenportal-blockabschluss/SKILL.md`; setze die dort geforderten Reviews und den `pruefer` passend zum geänderten Bereich ein. UI-Arbeit braucht zusätzlich die UI-, Dunkelmodus- und Prüfwerkzeug-Skills.
- Vor dem tatsächlichen Ende einer autonomen Sitzung befolge `.claude/skills/notenportal-sessionende/SKILL.md`. Ein normaler, abgeschlossener Einzelauftrag erfordert keinen künstlichen Sessionabschluss.
- Berichte knapp, auf Deutsch und mit belegtem Status: Änderung, ausgeführte Checks mit Ergebnis, nicht geprüfte Anforderungen, offene Punkte. Behaupte nicht, dass ein Agent, Test, Commit oder Push erfolgreich war, wenn du es nicht selbst verifiziert hast.
