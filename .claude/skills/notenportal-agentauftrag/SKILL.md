---
name: notenportal-agentauftrag
description: Vorlage für Aufträge an Subagents im Notenportal – Modellwahl, eigene Test-DB, verbotene Befehle, Dateigrenzen zwischen parallelen Agents, Pflicht-Abschlussprüfungen, Rückmeldeformat. Laden, bevor ein Agent für Code im Notenportal gestartet wird.
---

# Agent-Auftrag schreiben

## Modell
- Suchen/Lesen/Inventar: `haiku` (Explore-Agent).
- Umsetzen, Tests schreiben, Review: `sonnet`.
- Architektur/Konzept, heikle Migration, Sicherheitsfragen: `opus`.

## Test-DB je Agent
`notenportal_test` = Hauptsession. Agents: `notenportal_b_test`, `_c_test`, `_d_test`, `_e_test` – nie zwei Agents auf derselben.
`notenportal_probe` nur für Restore-/Migrationsproben (Skill `notenportal-migration`).

## Pflichtblock (wörtlich in jeden Auftrag)
```
Du arbeitest in /var/www/notenportal (Prod läuft direkt aus dieser Arbeitskopie – Seiten müssen jederzeit funktionieren).
ZUERST lesen: .claude/skills/notenportal-ui/SKILL.md (bei Views/CSS) und die im Auftrag genannten Dateien.
HARTE REGELN: keine schreibenden git-Befehle (add/commit/stash/checkout/reset/restore), kein composer (auch kein dry-run), keine Subagents.
Nie tmp-testdaten/ oder .env anfassen, keine echten Namen/Noten in Fixtures oder Doku.
Keine Migration auf Prod: nur `DB_DATABASE=<eigene>_test`. Neue Spalten im Code mit Schema::hasColumn-Guard (statisch gecacht), bis die Hauptsession migriert hat.
Tests nur: `DB_DATABASE=<eigene>_test php artisan test` (prüfen, dass diese DB wirklich benutzt wird).
Prod-Schutz: Code, der auf jeder Seite läuft (Provider, Layouts, Navigation, Darstellung), erst lauffähig ablegen – nach jedem solchen Edit `php -l` und `curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1/login` = 200. `php artisan view:cache` nie als ubuntu (Apache kann fremde kompilierte Views nicht touchen → 500) – nur `sudo -u www-data php artisan view:cache`; Tests kompilieren separat in storage/framework/views-testing. Neue Routen zuerst registrieren und prüfen, erst dann in Layouts/Komponenten referenzieren; im gemeinsamen Head/Layout zusätzlich `@if(Route::has('…'))` (21:38-Vorfall: `route('manifest')` vor der Route → alle Seiten 500). Genauso Alpine-Komponenten aus resources/js: erst bauen (`npm run build`), dann in Views verwenden – sonst Alpine-Fehler live auf Prod (22:40-Vorfall Notenrechner).
UI: Schweizer Hochdeutsch (ss statt ß), Texte mit __() + Englisch in lang/en.json bzw. lang/areas/*/en.json.
Abschluss: vendor/bin/pint auf geänderte PHP-Dateien; volle Suite grün; `grep -rn "ß" resources/views lang` leer;
.claude/hooks/view-pruefung.sh auf geänderte Views; `npm run build` nur wenn CSS/JS/neue Tailwind-Klassen (melden).
Rückmeldung (max 12 Zeilen): geänderte/neue Dateien, Testzahl, bewusst Weggelassenes (auch in docs/audit-backlog.md).
```

## Parallele Agents
- Dateigrenzen im Auftrag nennen («NICHT anfassen: …»). Geteilte Dateien (navigation.blade.php, lang/en.json, routes/*.php) nur minimal und direkt vor dem Edit neu lesen.
- Neue Routen: `ZugriffsschutzTest`-Routenliste und `EnglischeSeitenTest` ergänzen lassen. Neue Seiten mit Listen: `AbfragenAnzahlTest`.

## Nach der Rückmeldung (Hauptsession)
1. Nichts glauben: `git status`, Diff lesen, Suite selbst laufen lassen.
2. Migration? → Skill `notenportal-migration`.
3. Skill `notenportal-blockabschluss` (explizit stagen, fremde Hunks trennen).
4. Abbruch am Nutzungslimit: Arbeitskopie prüfen (php -l, Views kompilieren, Rollen-Seiten 200), dann Agent per SendMessage fortsetzen statt neu starten.
