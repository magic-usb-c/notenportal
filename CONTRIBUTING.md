# Mitarbeit am Notenportal

Danke fürs Mitmachen. Diese Datei beschreibt, wie eine Entwicklungsumgebung entsteht,
welche Regeln für Code und Texte gelten und was vor einem Pull Request laufen muss.

## Entwicklungsumgebung

Am schnellsten geht es wie in der Installation: `sudo ./install.sh` im geklonten Repo
richtet Pakete, Datenbank, `.env` und Webserver ein (siehe `README.md`). Wer lieber von
Hand arbeitet, braucht PHP 8.3 mit den üblichen Erweiterungen, MariaDB 10.11, Node 22 und
Composer, danach:

```bash
cp .env.example .env      # Datenbank und beide Benutzer eintragen
composer install
php artisan key:generate
php artisan notenportal:migrate
npm ci && npm run build
```

Für eine Umgebung mit Beispieldaten zusätzlich `php artisan db:seed --class=DemoSeeder`.
Beim Entwickeln liefert `composer dev` Server, Queue, Log und Vite in einem Fenster.

### Zwei Datenbankbenutzer

Das Portal trennt Rechte: der Web-Benutzer aus `DB_USERNAME` darf nur Daten lesen und
schreiben, Schemaänderungen laufen über `DB_MIGRATE_USERNAME` (siehe `config/notenportal.php`).
Deshalb heisst der Migrationsbefehl hier **`php artisan notenportal:migrate`** – ein blankes
`php artisan migrate` scheitert an fehlenden Rechten, und das ist Absicht.

### Tests

Tests laufen gegen eine eigene Datenbank, deren Name auf `_test` endet; der Guard in
`tests/TestCase.php` bricht bei jeder anderen ab, bevor `RefreshDatabase` Tabellen löscht.
Diesen Guard nie entfernen. Einmalig anlegen:

```sql
CREATE DATABASE notenportal_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON notenportal_test.* TO 'notenportal_web'@'localhost';
```

```bash
php artisan test                                        # alles
php artisan test tests/Feature/Lernender                # ein Ordner
DB_DATABASE=notenportal_b_test php artisan test         # zweite DB für parallele Läufe
```

## Regeln für Code und Texte

- **Sprache**: Die Oberfläche ist deutsch in Schweizer Hochdeutsch, also durchgehend **ss**
  statt des scharfen S (die Schweiz kennt diesen Buchstaben nicht). Neuer
  Code, Routennamen und URL-Pfade sind englisch (`learner.*`, `trainer.*`, `admin.*`).
  Keine Entwicklernotizen in der Oberfläche.
- **Übersetzungen**: Jeder deutsche Text steht in `__()`. Neue Schlüssel brauchen einen
  Eintrag in `lang/en.json`, sonst wird `tests/Feature/I18n/SchluesselTest.php` rot.
  Platzhalter (`:name`) müssen in beiden Sprachen gleich heissen.
- **Stil**: `vendor/bin/pint` formatiert, `vendor/bin/pint --test` prüft. Neue PHP-Dateien
  beginnen mit `declare(strict_types=1)`; Klassen werden importiert, nicht voll
  qualifiziert geschrieben.
- **Oberfläche**: Tailwind CSS 4 wird in `resources/css/app.css` konfiguriert, eigene
  Klassen nur als `@utility`. Der Seitenrahmen heisst `np-seite`, nicht `max-w-7xl`.
- **Rechnen mit Noten**: Die Notenlogik gehört in die Services unter `app/Services`, nie in
  einen Controller oder in eine View. Regeln und Beispiele: `docs/notenlogik.md`.
- **Migrationen**: eine Migration pro Änderung, immer mit `down()`. Vor einer
  Schemaänderung auf einer laufenden Installation gehört eine Datensicherung dazu.
- **Geheimnisse**: `.env` ist ausgeschlossen und bleibt es. Keine Passwörter, Schlüssel,
  Tokens oder echten Personendaten in Code, Tests, Dokumentation oder Commits – Testdaten
  sind erfunden.

## Commits und Pull Requests

Commit-Meldungen sind deutsch, im Imperativ, eine Zeile, mit Präfix:

```
Feat: Fix: GUI: Refactor: Test: Docs: Chore:
```

Beispiel: `Fix: Rundung der Zeugnisnote bei halben Punkten korrigieren`

Vor dem Pull Request:

```bash
vendor/bin/pint --test
php artisan test
```

Beides muss grün sein. Beschreibe im Pull Request, was sich für Lernende, Berufsbildner
oder Admins ändert, und nenne die betroffenen Seiten. Bei Änderungen an der Oberfläche
gehört ein Bild dazu, bei Änderungen an der Notenlogik ein Test, der den alten Fehler
festhält.

## Wo was steht

| Datei | Inhalt |
|---|---|
| `CLAUDE.md` | Kurzfassung aller Konventionen |
| `docs/architektur.md` | Datenbankschema und Aufbau |
| `docs/notenlogik.md` | Rechenregeln |
| `docs/funktionsumfang.md` | Funktionsumfang je Rolle |
| `docs/betrieb.md` | Betrieb, Sicherung, Änderungsprotokoll |
| `docs/audit-backlog.md` | Bewusst Offenes samt Begründung |

## Sicherheitslücken

Melde sie bitte nicht als öffentliches Issue. Ein fester Meldeweg steht noch nicht fest
(`docs/endspurt-plan.md`, offener Produktentscheid 2); bis dahin geht die Meldung an die
Betreiber des Repos.
