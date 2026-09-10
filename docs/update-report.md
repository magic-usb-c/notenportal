# Update- und Setup-Report - 09.09.2026 16:17

## 0. Sicherung

- Offene Aenderungen committed
- Rollback-Tag: pre-update-20260909-1617
-    d1d7359..133eb0e  feature/claude-fertigstellung -> feature/claude-fertigstellung
-  * [new tag]         pre-update-20260909-1617 -> pre-update-20260909-1617
- **DB-Dump FEHLGESCHLAGEN** - vor dem Upgrade von Hand sichern!

## 1. Versionen VORHER

```
OS          : Ubuntu 24.04.4 LTS
Kernel      : 6.8.0-139-generic
PHP         : 8.3.6
Composer    : 2.9.5
Node        : v22.23.2
npm         : 10.9.8
MariaDB     : 10.11.14-MariaDB
Apache      : Apache/2.4.58
Claude Code : 2.1.266 (Claude Code)
git         : 2.43.0
gh          : 2.45.0
Laravel     : Laravel Framework 12.51.0
Tailwind    : "tailwindcss": "^3.1.0"
```

### Veraltete Composer-Pakete

```
laravel/breeze       2.3.8   ! 2.4.2   Minimal Laravel authentication scaffolding with Bla...
laravel/framework    12.51.0 ~ 13.31.0 The Laravel Framework.
laravel/pail         1.2.6   ! 1.2.7   Easily delve into your Laravel application's log fi...
laravel/pint         1.27.1  ! 1.31.1  An opinionated code formatter for PHP.
laravel/sail         1.53.0  ! 1.67.0  Docker files for running a basic Laravel application.
laravel/tinker       2.11.1  ~ 3.0.2   Powerful REPL for the Laravel framework.
mockery/mockery      1.6.12  ! 1.6.15  Mockery is a simple yet flexible PHP mock object fr...
nunomaduro/collision 8.8.3   ! 8.9.5   Cli error handling for console/command-line PHP app...
phpunit/phpunit      11.5.53 ~ 12.5.35 The PHP Unit Testing framework.
```

### Veraltete npm-Pakete

```
Package              Current  Wanted  Latest  Location                          Depended by
@tailwindcss/vite     4.1.18   4.3.3   4.3.3  node_modules/@tailwindcss/vite    notenportal
alpinejs              3.15.8  3.17.2  3.17.2  node_modules/alpinejs             notenportal
autoprefixer         10.4.24  10.5.5  10.5.5  node_modules/autoprefixer         notenportal
axios                 1.13.5  1.20.0  1.20.0  node_modules/axios                notenportal
concurrently           9.2.1   9.2.4  10.0.5  node_modules/concurrently         notenportal
laravel-vite-plugin    2.1.0   2.1.0   3.2.0  node_modules/laravel-vite-plugin  notenportal
postcss                8.5.6  8.5.28  8.5.28  node_modules/postcss              notenportal
tailwindcss           3.4.19  3.4.19   4.3.3  node_modules/tailwindcss          notenportal
vite                   7.3.1   7.3.6   8.2.2  node_modules/vite                 notenportal
(keine oder Fehler)
```

## 2. Baseline-Testlauf (VOR den Updates)

```
  ────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\ProfileTest > correct password must…  QueryException   
  SQLSTATE[42000]: Syntax error or access violation: 1142 DROP command denied to user 'np_web'@'localhost' for table `notenportal`.`benutzer` (Connection: mysql, Host: 127.0.0.1, Port: 3306, Database: notenportal, SQL: drop table `notenportal`.`benutzer`, `notenportal`.`benutzer_rollen`, `notenportal`.`berufsbildner`, `notenportal`.`betreuungen`, `notenportal`.`bewertungsregeln`, `notenportal`.`faecher`, `notenportal`.`kategorien`, `notenportal`.`lehrberufe`, `notenportal`.`lehrberuf_faecher`, `notenportal`.`lehrberuf_module`, `notenportal`.`lernende`, `notenportal`.`lernender_tracks`, `notenportal`.`migrations`, `notenportal`.`module`, `notenportal`.`modul_belegungen`, `notenportal`.`modul_note_gruppen`, `notenportal`.`noten`, `notenportal`.`noten_gesehen`, `notenportal`.`noten_kommentare`, `notenportal`.`rollen`, `notenportal`.`semester`)

  at vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
    834▕             $exceptionType = $this->isUniqueConstraintError($e)
    835▕                 ? UniqueConstraintViolationException::class
    836▕                 : QueryException::class;
    837▕ 
  ➜ 838▕             throw new $exceptionType(
    839▕                 $this->getNameWithReadWriteType(),
    840▕                 $query,
    841▕                 $this->prepareBindings($bindings),
    842▕                 $e,



  Tests:    24 failed, 1 passed (2 assertions)
  Duration: 1.05s

```

## 3. Systemupdates

-   xdg-utils zutty
- Verwenden Sie »sudo apt autoremove«, um sie zu entfernen.
- The following upgrades have been deferred due to phasing:
-   base-files motd-news-config
- 0 aktualisiert, 0 neu installiert, 0 zu entfernen und 2 nicht aktualisiert.
- Systempakete aktualisiert
- Node  zu alt fuer @tailwindcss/upgrade, installiere Node 22
- Node jetzt: v22.23.2
- Use composer self-update --rollback to return to version 2.9.5

## 4. Projekt-Abhaengigkeiten (innerhalb bestehender Constraints)

- 
- 81 packages you are using are looking for funding.
- Use the `composer fund` command to find out more!
- > @php artisan vendor:publish --tag=laravel-assets --ansi --force
- 
-   [37;44m INFO [39;49m No publishable resources for tag [1m[laravel-assets][22m.  
- 
- No security vulnerability advisories found.
- 
- 38 packages are looking for funding
-   run `npm fund` for details
- 
- found 0 vulnerabilities
-   run `npm fund` for details
- 
- found 0 vulnerabilities
- Caches geleert

## 5. Versionen NACHHER

```
PHP         : 8.3.6
Composer    : 2.10.3
Node        : v22.23.2
npm         : 10.9.8
MariaDB     : 10.11.14-MariaDB
Apache      : Apache/2.4.58
Laravel     : Laravel Framework 12.69.2
Tailwind    : "tailwindcss": "^3.1.0"
```

### Noch offene Major-Upgrades - Aufgabe fuer Claude Code

```
laravel/framework 12.69.2 ~ 13.31.0 The Laravel Framework.
laravel/tinker    2.11.1  ~ 3.0.2   Powerful REPL for the Laravel framework.
phpunit/phpunit   11.5.56 ~ 12.5.35 The PHP Unit Testing framework.
```

## 6. Testlauf NACH den Updates

```
  ────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\ProfileTest > correct password must…  QueryException   
  could not find driver (Connection: sqlite, Database: :memory:, SQL: select exists (select 1 from "main".sqlite_master where name = 'migrations' and type = 'table') as "exists")

  at vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
    834▕             $exceptionType = $this->isUniqueConstraintError($e)
    835▕                 ? UniqueConstraintViolationException::class
    836▕                 : QueryException::class;
    837▕ 
  ➜ 838▕             throw new $exceptionType(
    839▕                 $this->getNameWithReadWriteType(),
    840▕                 $query,
    841▕                 $this->prepareBindings($bindings),
    842▕                 $e,



  Tests:    24 failed, 1 passed (2 assertions)
  Duration: 0.69s

```
- computing gzip size...
- public/build/manifest.json              0.33 kB │ gzip:  0.17 kB
- public/build/assets/app-nM8MdhCh.css   54.59 kB │ gzip:  9.77 kB
- public/build/assets/app-e0wfXoiD.js   106.75 kB │ gzip: 38.61 kB
- ✓ built in 1.99s

## 7. Claude-Code-Konfiguration

- Settings geschrieben
- Skill mariadb-query-optimization installiert
- 4 Subagents angelegt
- CLAUDE.md ergaenzt

## 8. Plugins

- Adding marketplace…✔ Marketplace 'laravel' already on disk — declared in user settings
- Adding marketplace…✔ Marketplace 'caveman' already on disk — declared in user settings
- Installing plugin "laravel@laravel"...✔ Successfully installed plugin: laravel@laravel (scope: user)
- Installing plugin "laravel-lsp@laravel"...✔ Successfully installed plugin: laravel-lsp@laravel (scope: user)

### Plugin-Status

```
Installed plugins:

  ❯ caveman@caveman
    Version: 655b7d9c5431
    Scope: project
    Status: ✔ enabled

  ❯ context7@claude-plugins-official
    Version: 517b2fcd1b60
    Scope: project
    Status: ✘ disabled

  ❯ frontend-design@claude-plugins-official
    Version: 517b2fcd1b60
    Scope: project
    Status: ✔ enabled

  ❯ laravel-lsp@laravel
    Version: 1.0.0
    Scope: project
    Status: ✔ enabled

  ❯ laravel-lsp@laravel
    Version: 1.0.0
    Scope: user
    Status: ✔ enabled

  ❯ laravel@laravel
    Version: 1.0.0
    Scope: project
    Status: ✔ enabled

  ❯ laravel@laravel
    Version: 1.0.0
    Scope: user
    Status: ✔ enabled

  ❯ security-guidance@claude-plugins-official
    Version: 2.0.7
    Scope: project
    Status: ✘ disabled

  ❯ tailwind-v4-shadcn@claude-skills
    Version: 3.3.1
    Scope: project
    Status: ✘ disabled

  ❯ ui-ux-pro-max@ui-ux-pro-max-skill
    Version: 2.5.0
    Scope: user
    Status: ✘ disabled

```

## 9. GitHub-Pruefung

```
github.com
  ✓ Logged in to github.com account magic-usb-c (/home/ubuntu/.config/gh/hosts.yml)
  - Active account: true
  - Git operations protocol: https
  - Token: gho_************************************
  - Token scopes: 'gist', 'read:org', 'repo', 'workflow'

Remote:
origin	https://github.com/magic-usb-c/notenportal.git (fetch)
origin	https://github.com/magic-usb-c/notenportal.git (push)

Repo:
{"defaultBranchRef":{"name":"main"},"isPrivate":true,"name":"notenportal","viewerPermission":"ADMIN"}

Branches remote:
refs/heads/feature/backend-noten-crud
refs/heads/feature/claude-fertigstellung
refs/heads/laravel-rebuild
refs/heads/main
refs/heads/php-backend-new-db-scheme

Schreibtest (dry-run):

Offene PRs:
```

## 10. Zusammenfassung

- Rollback Code: git reset --hard pre-update-20260909-1617
- Rollback DB:   mysql -unp_web -p notenportal < ~/db-backups/notenportal-20260909-1617.sql
- Dieser Report: /var/www/notenportal/docs/update-report.md
