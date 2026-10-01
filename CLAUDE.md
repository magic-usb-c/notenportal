# Notenportal – Claude Code

Webportal für Lehrbetriebe: Lernende erfassen ihre Noten (Fachunterricht, ÜK, BMS, ABU), Berufsbildner begleiten, Admins verwalten. Pilot ab 30.09.2026 im ICT-LAB der Hamilton AG, langfristig Open Source.

**Vor jeder Arbeit `docs/auftrag/LAGE.md` lesen** – dort steht, was gilt, was nachweislich falsch ist und woran gerade gearbeitet wird. Danach `docs/auftrag/UEBERGABE.md` (Brett: was getan ist, was offen ist). Wie Claude selbst eingestellt ist (Modelle, Effort, Agents, Workflows, Hook, Werkzeuge): `docs/auftrag/SETUP-CLAUDE.md`.

Weiterführend: `docs/endspurt-plan.md` (Ideensammlung) · `docs/gui-konzept.md` (Designkonzept) · `docs/architektur.md` (Struktur, Rollen, Datenmodell) · `docs/funktionsumfang.md` · `docs/betrieb.md` (Server, Rechte, Änderungen ausserhalb des Repos)

## Umgebung
- **Eine Sitzung, Branch `main`**, kein Worktree, kein Feature-Branch. Parallelität kommt von Subagents und Workflows, nicht von zweiten Sitzungen (`docs/auftrag/NACHTLAUF.md`).
- **Cloud-Sitzung (Claude Code on the web):** Repo unter `$CLAUDE_PROJECT_DIR` (`/home/user/notenportal`). Der Hook `.claude/hooks/session-start.sh` richtet beim Start ein: MariaDB (root über Socket), Datenbanken `notenportal_test` und `notenportal_demo`, Composer, npm, Vite-Build, beständiger `APP_KEY`, Demo-Server `http://127.0.0.1:8099`. Ausserhalb des Repos überlebt nichts den Container – was bleiben soll, wird committet.
- **VM-Betrieb** (Testbetrieb seit 30.09.2026: srv-lab-dva-003, `https://172.26.14.100`, Ubuntu 26.04.1, PHP 8.5, per `install.sh`): Regeln in `docs/betrieb.md`. Pfade wie `/var/www/notenportal` gelten nur dort und im Skill `notenportal-migration`.
- Testbenutzer auf der VM (Login per E-Mail `<vorname>.<nachname>@example.local`, Admin `admin@example.local`): admin, peter (Berufsbildner), david/nando/jan/lukas/nils (Lernende). Passwort ausschliesslich über die Umgebungsvariable `NP_TEST_PW` – nie in Code, Docs, Logs oder Commits.
- Demo-Server (DB `notenportal_demo`, Konten `@demo.example`): laura.frei (Admin), michael.baumann (Berufsbildner), nina.huber/elena.fischer (Lernende). Passwort steht als Konstante `DEMO_PASSWORT` in `database/seeders/DemoSeeder.php` und gehört auch dort nirgends sonst hin – für Prüfläufe ohne Ausgabe übernehmen: `export NP_TEST_PW=$(grep -oP "DEMO_PASSWORT\s*=\s*'\K[^']+" database/seeders/DemoSeeder.php)`. Start/Reset: `tools/pruefung/demo-server.sh start|neu`.
- Browser-Werkzeuge im Repo: `tools/pruefung/` (`shot.mjs`, `klick.mjs`, `rundgang.mjs`; Skill `notenportal-pruefwerkzeuge`). Standard 1920×1080 **dunkel**; `--hell`, `--breite=2560 --hoehe=1440`. Keines nimmt ein Passwort als Argument – nur `NP_TEST_PW`, sonst stünde es in der Prozessliste.

## Harte Regeln
- UI-Texte Schweizer Hochdeutsch, **ss statt ß**. Keine erklärenden Hinweise oder Entwicklernotizen in der Oberfläche.
- **Massstab der Oberfläche: Desktop-Browser 1920×1080 bis 2560×1440 im Dunkelmodus nach Apple Human Interface Guidelines** (Skills `notenportal-ui`, `notenportal-dunkelmodus`). Hell und schmale Fenster dürfen nicht kaputtgehen, bekommen aber keine eigene Gestaltungsarbeit. Keine SF Pro, keine SF Symbols.
- Vor jeder Arbeit an Views/CSS den Skill `notenportal-ui` laden. Keine Farb-Hardcodes, keine harten Schriftgrössen.
- Betriebsspezifisches (Firmenname, Grenzwerte, Fristen) kommt aus der DB, nie aus Code oder Config.
- Berechtigung serverseitig: Lernende nur eigene Daten (ID aus der Session, nie aus dem Request), Berufsbildner nur aktiv betreute Lernende.
- Noten-Queries immer mit `whereNull('geloescht_am')`. Kein Raw-SQL ohne Binding.
- Mutierende Actions: Redirect mit `->with('success'|'error', '…')`.
- `.env` nie committen, nie lesen. `tmp-testdaten/` nie committen, nie in Fixtures oder Doku.
- Routennamen und URL-Pfade englisch (`learner.*`, `trainer.*`, `admin.*`), neuer Code englisch, Oberfläche Deutsch. Alte deutsche Pfade leitet `App\Support\LegacyPaths` per 301 weiter; neue Segmente dort ergänzen, wenn ein Pfad umbenannt wird.

## Datenbank
- VM: Least Privilege – `np_web` (App) hat auf `notenportal` nur Datenrechte, `np_migrate` die Schemarechte. Migrationen dort immer `php artisan notenportal:migrate` (Skill `notenportal-migration`). Cloud: root über Socket, `DB_*` kommen aus dem Hook; `DB_DATABASE` wird bewusst nicht global gesetzt (`phpunit.xml` → `notenportal_test`, Demo-Server → `notenportal_demo`), für andere Artisan-Aufrufe `DB_DATABASE=notenportal_demo php artisan …`.
- Schema nutzt CHECK-Constraints und Composite-FKs per `DB::statement` → nur MariaDB/MySQL, kein SQLite.
- Jede Änderung ausserhalb des Repos (DB-Rechte, Apache, PHP, Backups) in `docs/betrieb.md` protokollieren.
- Vor jedem Schema-Eingriff auf einer VM: Dump + Git-Tag. In der Cloud sind alle Datenbanken Wegwerfdaten.
- Alle Durchschnitte kommen aus `App\Services\Auswertung` (Regeln: `docs/notenlogik.md`), nie aus SQL, Controllern oder Views.

## Tests
- `php artisan test` läuft gegen `notenportal_test`. Der Guard in `tests/TestCase.php` bricht bei jeder anderen DB ab – nie entfernen. Einzelne Tests: `php vendor/bin/phpunit --filter 'Name'`.
- Factories: `User::factory()->admin()|berufsbildner()|lernender()`, Passwort `UserFactory::PASSWORT`.
- `ZugriffsschutzTest` prüft jede `role:`-Route automatisch gegen fremde Rollen.
- Einmal pro abgeschlossenem Plan-Punkt testen, dann committen.

## Git
- Arbeitsbranch `main`. Commit nach jedem funktionierenden Stand (Tests grün, Seite lädt), dann `git push origin HEAD:main`. Dateien explizit stagen, nie `git add -A`.
- Messages Deutsch, Imperativ, eine Zeile, Präfix `Feat:` `Fix:` `GUI:` `Refactor:` `Test:` `Docs:` `Chore:`. Kein Force-Push, keine Modellnamen in Commits.
- Subagents führen keine schreibenden Git-, Composer- oder npm-Befehle aus, auch keine Dry-Runs.

## Befehle
```bash
npm run build                                  # nach CSS/JS-Änderungen Pflicht, muss «built in» zeigen
grep -rn "ß" resources/views/ lang/            # muss leer sein
vendor/bin/pint --dirty                        # geänderte PHP-Dateien
php artisan test                               # ganze Suite
tools/pruefung/demo-server.sh status|start|neu # Demo-Instanz
node tools/pruefung/shot.mjs <email> "/pfad,/pfad2" --dir=<ordner>     # Screenshots dunkel 1920
node tools/pruefung/rundgang.mjs <email>                                # alle Seiten einer Rolle, Layoutbefunde
```

## Arbeitsweise und Orchestrierung
- Hauptsitzung: Fable 5.1, Effort xhigh, Ultracode, Advisor Fable (`.claude/settings.json`). Sie plant, verteilt, prüft und committet; sie schreibt selbst nur, was ein Subagent nicht sauber schreiben kann.
- Modellwahl je Aufgabe (Skill `notenportal-orchestrierung`): haiku low/medium für mechanisches Suchen und Inventar · sonnet high für Umsetzung, Tests, Review, Texte · opus xhigh für `pruefer`, Architektur, Dunkelmodus-Sichtprüfung, hartnäckige Fehler · fable max nur für die letzte Verifikation, wenn opus widersprüchlich bleibt.
- Jede substanzielle Aufgabe läuft als Workflow (`.claude/workflows/`): Befunde parallel sammeln, gegnerisch verifizieren, erst dann umsetzen. Fertige Workflows werden ins Repo gespeichert.
- `reviewer` einmal pro abgeschlossenem Punkt, `ui-checker` einmal pro Rollenbereich, `pruefer` vor jeder Fertigmeldung.
- Skills: `notenportal-ui` (vor Views/CSS), `notenportal-dunkelmodus` (Dunkelmodus nach HIG), `notenportal-orchestrierung` (Agents/Workflows), `notenportal-agentauftrag` (vor jedem Code-Agent), `notenportal-pruefwerkzeuge` (Browser-Prüfung), `notenportal-blockabschluss` (Block fertig), `notenportal-sessionende` (vor Sessionende), `notenportal-migration` (nur VM).
- Hook `.claude/hooks/view-pruefung.sh` meldet nach jeder View-Änderung ß, Farb-Hardcodes, harte Schriftgrössen (Mail-Vorlagen und Notenblatt ausgenommen).
- Keine Datei zweimal lesen, laravel-lsp für Symbolsuche. Nicht raten: messen, Quelle nennen, Lücke als Lücke markieren.
- Berichte an David: max. 8 Zeilen pro abgeschlossenem Punkt, Deutsch, keine Rückfragen, solange eine vertretbare Annahme möglich ist.
