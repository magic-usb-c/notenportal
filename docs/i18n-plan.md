# Plan: Sprache (DE/EN), englischer Code und englisches Schema

Stand 11.09.2026. Ergänzt Abschnitt 4 des Auftrags (Routen/URLs englisch sind erledigt, `LegacyPaths` leitet 301 um).

## Umfang (gezählt)

- DB: 39 Tabellen (~24 deutsch), ~120 deutsche Spaltennamen, 56 FKs, 21 CHECKs; `lernender_id` 353× in `app/`, 188× in Tests; ~196 Raw-/`DB::`-Aufrufe.
- Code: ~100 von 155 Klassen deutsch benannt, ~30 deutsche Ordner.
- Texte: 124 Blade-Dateien (~760 Textknoten, ~280 Attribute), 93 Flash-Meldungen, 31 eigene Validierungsmeldungen, 15 Mail-Klassen, `NotificationCatalog`, ~24 JS-Strings, CSV-Köpfe.

## Entscheid

**Vor dem Go-Live (30.09., Freeze 25.09.):** nur Sprachumschaltungs-Gerüst.
- `__()` mit deutschem Text als Schlüssel. Fehlt eine Übersetzung, zeigt DE denselben Text wie heute.
- Genau eine additive Migration: `benutzer.locale` (nullable).
- Umschalter bleibt ausgeblendet (Einstellung `sprachwahl_aktiv`), bis die EN-Tests für alle Bereiche grün sind und ein Mensch die englischen Texte gegengelesen hat.

**Nach dem Go-Live:**
1. Services, Support, Controller und View-Ordner englisch umbenennen. Nur Code, die URLs bleiben.
2. Models englisch benennen, mit `#[Table(name: 'benutzer', key: 'benutzer_id')]` auf die alten Tabellen. Keine Spalten-Aliasse per Accessor, weil Raw-Queries sie umgehen.
3. DB-Rename frühestens im November, nach Rückmeldungen aus dem Pilot:
   - pro Tabellengruppe eine Migration
   - CHECKs über `information_schema` droppen und neu anlegen
   - `php artisan down` während des Laufs
   - `down()` auf der Probe-DB getestet
   - Alternative: nur neue Tabellen englisch anlegen (wie schon `calendar_*`, `notification_*`).

**Warum kein Rename vor dem Go-Live:**
- MariaDB-DDL läuft ohne Transaktion.
- Die CHECK-Namen auf Prod sind nicht vorhersagbar (`CONSTRAINT_n`); daran brach am 10.09. schon eine Migration.
- Sicherungs-ZIPs von vorher passen nicht mehr zum neuen Code.
- Rund 700 Stellen im Code müssten sich ändern.
- Für den Pilot bringt es keinen sichtbaren Nutzen.

## Technik Sprachumschaltung

- **Übersetzungsdateien:** `lang/en.json` für gemeinsame Texte, dazu pro Bereich `lang/areas/{learner,trainer,admin}/en.json`, registriert per `Lang::addJsonPath()`. So kommen sich parallele Pakete nicht in die Quere.
- **Middleware `SetLocale`** nach der Session. Reihenfolge der Quellen:
  - angemeldet: `benutzer.locale`
  - Gast: Session, dann `Accept-Language` (nur de/en)
  - zuletzt die Einstellung `sprache_standard` (Standard `de`)
  - dazu `Carbon::setLocale()`
- **Umschalter:** Einstellungen → Profil (`/settings/profile`, dort auch die Darstellung), `PUT /profile/locale`, funktioniert wie `darstellung`.
- **Datum:** Helfer `Format::date()` (de→`de_CH`, en→`en_GB`) ersetzt die harten `locale('de_CH')`.
- **Notenformat:** bleibt in beiden Sprachen `5.0`, kein Formatieren über `Intl`.
- **Mails:** `User` implementiert `HasLocalePreference`. `Notifier` baut `MailContent` und den `DigestItem`-Text in `App::withLocale($empfaenger->preferredLocale(), …)`, weil beides beim Einreihen als fertiger String entsteht.
- **Validierung:** `attributes` in `lang/en/validation.php` ergänzen, die 31 eigenen Meldungen mit `__()`.
- **JS:** Das Layout gibt `window.npI18n = @json(...)` aus; `charts.js`, `np.js` und `suche.js` holen ihre Texte über `np.t('…')`.
- **Tests** in `tests/Feature/I18n/`:
  1. Statisch: Jeder literale Schlüssel steht in einer EN-JSON, verwaiste Schlüssel und abweichende Doppel sind Fehler.
  2. Laufzeit: Jede GET-Route pro Rolle mit `locale=en` rendern; `Lang::handleMissingKeysUsing()` sammelt die fehlenden Schlüssel, erlaubt sind 0.
  3. Ratsche: Die Zahl der deutschen Textknoten ausserhalb von `__()` pro View-Ordner steht in einer Baseline und darf nur sinken; für migrierte Ordner gilt 0.

## Pakete (disjunkte Dateien)

- **B – Fundament** (opus):
  - Migration `benutzer.locale`, `SetLocale`, `bootstrap/app.php`, AppServiceProvider
  - `User`, Profil, `Support/Format`, `Navigation`, `Notifier`
  - `layouts/`, `components/`, `errors/`, `auth/`, `profile/`
  - `resources/js/*`, `lang/en.json`, `lang/*/validation.php`
  - `tests/Feature/I18n/*`
- **C – Lernende** (sonnet):
  - Views: `dashboards/lernender`, `lernender/`, `rechner/`, `dokumente/`, `import/`, `noten/`
  - die zugehörigen Controller
  - `lang/areas/learner/en.json`
- **D – Berufsbildner/Verwaltung** (sonnet):
  - Views: `dashboards/berufsbildner`, `verwaltung/`, `feedback/`, `notifications/`
  - die zugehörigen Controller
  - `lang/areas/trainer/en.json`
- **E – Admin/Mails** (sonnet):
  - Views: `dashboards/admin`, `admin/`, `mail/`
  - `Controllers/Admin`, `Notifications/Messages`, `NotificationCatalog`
  - `lang/areas/admin/en.json`
- **Abnahme für jedes Paket:**
  - Das DE-HTML ist vorher/nachher identisch (curl-Diff bzw. Screenshots).
  - Die Ratsche für die eigenen Ordner steht auf 0, der Laufzeittest ist grün.
  - Die ganze Suite ist grün.
  - Kein `{!! __() !!}` mit Benutzerdaten.

## Risiken

- **Query-Parameter umbenennen** (`ansicht`, `monat`, `sort`, `filter`): Die alten Namen müssen weiter angenommen werden, denn `LegacyPaths` übersetzt nur Pfade.
- **Queue:** `PortalMail`, `MailContent` und `User` nicht umbenennen, sonst vorher die Queue leeren (`down`, `queue:work --stop-when-empty`) und `failed_jobs` prüfen.
- **Deploy:** danach `optimize:clear` und `view:clear`; nach Klassen-Umbenennungen `dump-autoload` und ein Apache-Reload.
- **Rollback:** C/D/E ändern nur Code, ein `git revert` reicht. B enthält eine additive Spalte mit getestetem `down()`.

## Glossar DE → EN

Britisches Englisch, in allen Bereichen gleich:

| Deutsch | Englisch |
|---|---|
| Lernende/r | apprentice |
| Berufsbildner/in | trainer |
| Note | grade |
| Prüfung | exam |
| Semester | semester |
| Zeugnisnote | report grade |
| genügend | pass |
| Lehrberuf | occupation |
| Lehrbeginn / Lehrende | start / end of apprenticeship |
| ÜK (überbetrieblicher Kurs) | inter-company course (ICC) |
| Berufsfachschule | vocational school |
| Fach / Modul | subject / module |
| Schnitt / Durchschnitt | average |
| Betrieb (Einstellungsseite) | Organisation |
| Benachrichtigungen | notifications |
