# Notenportal – Claude Code

Webportal für Lehrbetriebe: Lernende erfassen ihre Noten (Fachunterricht, ÜK, BMS, ABU), Berufsbildner begleiten, Admins verwalten. Pilot ab 30.09.2026 im ICT-LAB der Hamilton AG, langfristig Open Source.

Weiterführend: `docs/endspurt-plan.md` (Ideensammlung) · `docs/gui-konzept.md` (Designkonzept) · `docs/architektur.md` (Struktur, Rollen, Datenmodell) · `docs/funktionsumfang.md` · `docs/betrieb.md` (Server, Rechte, Änderungen ausserhalb des Repos)

## Umgebung
- VM srv-lab-dva-001, `/var/www/notenportal`, Apache 2.4 + mod_php 8.3, MariaDB 10.11, Node 22
- Laravel, Blade, Tailwind, Alpine.js, Vite
- Testbenutzer Prod (Passwort `Chur7000`, Login per E-Mail `<vorname>.<nachname>@example.local`, Admin `admin@example.local`): admin, peter (Berufsbildner), david/nando/jan/lukas/nils (Lernende)
- Demo-Server (DB `notenportal_demo`, Passwort `Demo!2026`, `@demo.example`): laura.frei (Admin), michael.baumann (Berufsbildner), nina.huber/elena.fischer (Lernende). Start: `cd public && DB_DATABASE=notenportal_demo CACHE_STORE=array php -S 127.0.0.1:8090 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`
- Zweite Instanz (Installationstest): `/var/www/notenportal-i2`, Port 8082, DB `notenportal_i2`
- Browser-Werkzeuge: `~/tools/visual` (shot.mjs, breite.mjs, import.mjs, zeugnis.mjs, erstinbetrieb.mjs)

## Harte Regeln
- UI-Texte Schweizer Hochdeutsch, **ss statt ß**. Keine erklärenden Hinweise oder Entwicklernotizen in der Oberfläche.
- Vor jeder Arbeit an Views/CSS den Skill `notenportal-ui` laden. Keine Farb-Hardcodes.
- Betriebsspezifisches (Firmenname, Grenzwerte, Fristen) kommt aus der DB, nie aus Code oder Config.
- Berechtigung serverseitig: Lernende nur eigene Daten (ID aus der Session, nie aus dem Request), Berufsbildner nur aktiv betreute Lernende.
- Noten-Queries immer mit `whereNull('geloescht_am')`. Kein Raw-SQL ohne Binding.
- Mutierende Actions: Redirect mit `->with('success'|'error', '…')`.
- `.env` nie committen.

## Datenbank
- Least Privilege: `np_web` (App) hat auf `notenportal` nur Datenrechte, `np_migrate` die Schemarechte. Migrationen auf Prod/Probe immer `php artisan notenportal:migrate` (Skill `notenportal-migration`). Auf `notenportal_test` hat `np_web` ALL.
- Schema nutzt CHECK-Constraints und Composite-FKs per `DB::statement` → nur MariaDB/MySQL, kein SQLite.
- Jede Änderung ausserhalb des Repos (DB-Rechte, Apache, PHP, Backups) in `docs/betrieb.md` protokollieren.
- Vor jedem Framework-Upgrade: `sudo mysqldump --single-transaction notenportal > ~/db-backups/…` + Git-Tag.
- Die Arbeitskopie ist Prod: neue Migrationen zuerst gegen `notenportal_probe` (Prod-Kopie) testen, dann sofort auf `notenportal` anwenden. Vor Schema-Eingriffen Dump + Tag.
- Alle Durchschnitte kommen aus `App\Services\Auswertung` (Regeln: `docs/notenlogik.md`), nie aus SQL, Controllern oder Views.

## Tests
- `php artisan test` läuft gegen `notenportal_test`. Der Guard in `tests/TestCase.php` bricht bei jeder anderen DB ab – nie entfernen.
- Factories: `User::factory()->admin()|berufsbildner()|lernender()`, Passwort `UserFactory::PASSWORT`.
- `ZugriffsschutzTest` prüft jede `role:`-Route automatisch gegen fremde Rollen.
- Einmal pro abgeschlossenem Plan-Punkt testen, dann committen.

## Git
- Arbeitsbranch `feature/claude-fertigstellung`; funktionierende Stände nach `main` mergen.
- Commit nach jedem funktionierenden Stand (Tests grün, Seite lädt): `git add -A && git commit -m "…" && git push`
- Messages Deutsch, Imperativ, eine Zeile, Präfix `Feat:` `Fix:` `GUI:` `Refactor:` `Test:` `Docs:` `Chore:`
- Subagents führen keine schreibenden Git- oder Composer-Befehle aus, auch keine Dry-Runs.

## Befehle
```bash
npm run build                 # nach CSS/JS-Änderungen Pflicht
php artisan optimize:clear
php artisan test
grep -rn "ß" resources/views/ # muss leer sein
```

## Arbeitsweise
- Subagent-Modell pro Aufgabe: haiku suchen/lesen/Schema, sonnet implementieren/Review/UI-Prüfung, opus Architektur und hartnäckige Fehler.
- `reviewer` (sonnet) einmal pro abgeschlossenem Punkt, `ui-checker` (haiku) einmal pro Rollenbereich.
- Skills: `notenportal-ui` (vor Views/CSS), `notenportal-migration` (vor jedem Prod-Migrate), `notenportal-blockabschluss` (Block fertig), `notenportal-sessionende` (vor Sessionende).
- Hook `.claude/hooks/view-pruefung.sh` meldet nach jeder View-Änderung ß und Farb-Hardcodes (Mail-Vorlagen und Notenblatt ausgenommen).
- Keine Datei zweimal lesen, laravel-lsp für Symbolsuche.
- Berichte an den User: max. 8 Zeilen pro abgeschlossenem Punkt.
- Plugins: Caveman (full) aktiv.
