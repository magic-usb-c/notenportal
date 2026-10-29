---
name: notenportal-agentauftrag
description: Vorlage für Aufträge an Subagents im Notenportal – Modell und Effort, eigene Test-DB, verbotene Befehle, Dateigrenzen zwischen parallelen Agents, Pflicht-Abschlussprüfungen, Rückmeldeformat. Laden, bevor ein Agent für Code im Notenportal gestartet wird.
---

# Agent-Auftrag schreiben

## Modell und Effort
Tabelle im Skill `notenportal-orchestrierung`. Kurz: haiku low suchen · sonnet high umsetzen/reviewen ·
opus xhigh entwerfen · fable max nur als letzte Instanz. Die benannten Agents (`pruefer` fable low,
`ui-checker` opus high, `bildpruefer` sonnet high …) bringen Modell und Effort im Frontmatter mit; bei
`general-purpose` immer beides setzen, sonst erbt der Agent Fable xhigh.

## Test-DB je Agent
`notenportal_test` gehört der Hauptsession. Agents nehmen `notenportal_b_test`, `_c_test`, `_d_test`,
`_e_test` – nie zwei Agents auf derselben. Anlegen, falls sie fehlt:
`mysql --socket=/run/mysqld/mysqld.sock -u root -e "CREATE DATABASE IF NOT EXISTS notenportal_b_test"`.
Der Guard in `tests/TestCase.php` verlangt ein `*_test`-Suffix. Die Datenbank trennt nur die Tabellen:
`Storage::fake('local')` nutzt in jedem Prozess dasselbe Verzeichnis `storage/framework/testing/disks/local`,
darum läuft **nur eine ganze Suite zur Zeit** (sonst fallen `SicherungTest`/`SicherungKopieTest`);
Agents prüfen einzelne Tests mit `--filter`, die ganze Suite läuft in der Hauptsession.

## Pflichtblock (wörtlich in jeden Auftrag)
```
Du arbeitest im Repository-Wurzelverzeichnis des Notenportals (Cloud: $CLAUDE_PROJECT_DIR). Branch main.
ZUERST lesen: .claude/skills/notenportal-ui/SKILL.md und .claude/skills/notenportal-dunkelmodus/SKILL.md (bei Views/CSS) sowie die im Auftrag genannten Dateien.
HARTE REGELN: keine schreibenden git-Befehle (add/commit/stash/checkout/reset/restore), kein composer, kein npm install (auch kein dry-run), keine Subagents.
Nie tmp-testdaten/ oder .env anfassen, keine echten Namen/Noten in Fixtures oder Doku. Passwörter nur über Umgebungsvariablen (NP_TEST_PW), nie als Argument, nie in Ausgabe.
Massstab der Oberfläche: Desktop 1920×1080 bis 2560×1440 im Dunkelmodus nach Apple HIG; hell und schmal dürfen nicht brechen, bekommen aber keine eigene Arbeit.
Tests nur: DB_DATABASE=<eigene>_test php artisan test (bzw. php vendor/bin/phpunit --filter …); prüfen, dass diese DB wirklich benutzt wird.
Neue Routen zuerst registrieren und prüfen, erst dann in Layouts/Komponenten referenzieren; im gemeinsamen Head/Layout zusätzlich @if(Route::has('…')). Alpine-Komponenten aus resources/js: erst bauen (npm run build), dann in Views verwenden.
UI: Schweizer Hochdeutsch (ss statt ß), Texte mit __() + Englisch in lang/en.json bzw. lang/areas/*/en.json. Keine Hinweistexte, keine Entwicklernotizen in der Oberfläche.
Formulare mit benannten Submit-Knöpfen (name/value): nie synchron im @submit sperren (:disabled="loading" + loading = true) – ein gesperrter Knopf schickt seinen Wert nicht mit. Stattdessen setTimeout(() => loading = true).
Abschluss: vendor/bin/pint auf geänderte PHP-Dateien; Tests der eigenen DB grün; grep -rn "ß" resources/views lang leer; .claude/hooks/view-pruefung.sh auf geänderte Views (echo '{"tool_input":{"file_path":"<absoluter pfad>"}}' | bash .claude/hooks/view-pruefung.sh); npm run build nur wenn CSS/JS/neue Tailwind-Klassen (melden). Arbeitet parallel ein anderer Agent an resources/js oder resources/css, NICHT bauen, sondern nur Klassen verwenden, die schon im Build sind: grep -c "\.size-10[{:,]" public/build/assets/*.css.
Sichtbare Änderung: NP_TEST_PW=… node tools/pruefung/shot.mjs <email@demo.example> "/pfad" --dir=<ordner> und das Bild mit Read ansehen; Befund im Bericht nennen.
Rückmeldung (max 12 Zeilen): geänderte/neue Dateien, Testzahl, was du gesehen hast, bewusst Weggelassenes (auch in docs/audit-backlog.md).
```

## Parallele Agents
- Dateigrenzen im Auftrag nennen («NICHT anfassen: …»). Geteilte Dateien (`navigation.blade.php`,
  `lang/en.json`, `routes/*.php`, `app.css`) nur minimal und direkt vor dem Edit neu lesen.
- Mehrere Agents an denselben Dateien → Workflow mit `isolation: 'worktree'` je Agent, Zusammenführung
  in der Hauptsession.
- Neue Routen: `ZugriffsschutzTest`-Routenliste und `EnglischeSeitenTest` ergänzen lassen. Neue Seiten
  mit Listen: `AbfragenAnzahlTest`.

## Nach der Rückmeldung (Hauptsession)
1. Nichts glauben: `git status`, Diff lesen, Suite selbst laufen lassen, Screenshot selbst ansehen.
2. Schema geändert? Cloud: `DB_DATABASE=notenportal_demo php artisan migrate`; VM: Skill `notenportal-migration`.
3. Skill `notenportal-blockabschluss` (explizit stagen, fremde Hunks trennen).
4. Abbruch am Nutzungslimit: Arbeitskopie prüfen (`php -l`, Views kompilieren, Rollen-Seiten 200),
   dann Agent per `SendMessage` fortsetzen statt neu starten.
