# Notenportal – Claude Code

Webportal für Lehrbetriebe: Lernende erfassen ihre Noten (Fachunterricht, ÜK, BMS, ABU), Berufsbildner begleiten, Admins verwalten. Pilot ab 30.09.2026 im ICT-LAB der Hamilton AG, langfristig Open Source.

Weiterführend: `docs/endspurt-plan.md` (Plan) · `docs/architektur.md` (Struktur, Rollen, Datenmodell) · `docs/funktionsumfang.md` · `docs/betrieb.md` (Server, Rechte, Änderungen ausserhalb des Repos)

## Umgebung
- VM srv-lab-dva-001, `/var/www/notenportal`, Apache 2.4 + mod_php 8.3, MariaDB 10.11, Node 22
- Laravel, Blade, Tailwind, Alpine.js, Vite
- Testbenutzer (Passwort `Chur7000`, Login per E-Mail): admin, peter (Berufsbildner), david/nando/jan/lukas/nils (Lernende)

## Harte Regeln
- UI-Texte Schweizer Hochdeutsch, **ss statt ß**. Keine erklärenden Hinweise oder Entwicklernotizen in der Oberfläche.
- Vor jeder Arbeit an Views/CSS den Skill `notenportal-ui` laden. Keine Farb-Hardcodes.
- Betriebsspezifisches (Firmenname, Grenzwerte, Fristen) kommt aus der DB, nie aus Code oder Config.
- Berechtigung serverseitig: Lernende nur eigene Daten (ID aus der Session, nie aus dem Request), Berufsbildner nur aktiv betreute Lernende.
- Noten-Queries immer mit `whereNull('geloescht_am')`. Kein Raw-SQL ohne Binding.
- Mutierende Actions: Redirect mit `->with('success'|'error', '…')`.
- `.env` nie committen.

## Datenbank
- `np_web` hat ALL auf `notenportal` und `notenportal_test`: Migrationen inkl. ALTER funktionieren.
- Schema nutzt CHECK-Constraints und Composite-FKs per `DB::statement` → nur MariaDB/MySQL, kein SQLite.
- Jede Änderung ausserhalb des Repos (DB-Rechte, Apache, PHP, Backups) in `docs/betrieb.md` protokollieren.
- Vor jedem Framework-Upgrade: `sudo mysqldump --single-transaction notenportal > ~/db-backups/…` + Git-Tag.

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
- `reviewer` einmal pro abgeschlossenem Punkt, `ui-checker` einmal pro Rollenbereich.
- Keine Datei zweimal lesen, laravel-lsp für Symbolsuche.
- Berichte an den User: max. 8 Zeilen pro abgeschlossenem Punkt.
- Plugins: Caveman (full) aktiv.
