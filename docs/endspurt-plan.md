# Endspurt-Plan Notenportal

Stand: 10.09.2026 · Branch `feature/claude-fertigstellung` · Grundlage: update-report.md, audit-backlog.md, CLAUDE.md, Code-, DB-, Sicherheits-, Server- und Git-Analyse per Subagents, Web-Recherche (Laravel 13, Tailwind 4, Vite 8, PHP 8.4, Berufs-/Moduldaten).

Aufwand = Claude-Code-Arbeitszeit inkl. Tests, Review-Agent und Nacharbeit, ohne Wartezeit auf Entscheide.

---

## 1. Ausgangslage – was die Analyse ergeben hat

| Befund | Bedeutung für den Plan |
|---|---|
| `main` und `feature/claude-fertigstellung` haben **keine gemeinsame Historie**. `main` = alter PHP-Prototyp (11 Commits, Jan./Feb. 2026), Feature-Branch = Laravel-Neubau ab 16.02.2026 (179 Commits). | Kein normaler Merge, kein Fast-Forward möglich → eigene Strategie (Punkt 13). |
| np_web hat inzwischen **ALL PRIVILEGES** auf `notenportal` (CLAUDE.md behauptet noch das Gegenteil). | ALTER per Migration geht → `bemerkung` ist trivial. Gleichzeitig: ein Testlauf mit gecachter Config könnte die Produktiv-DB leeren. Schutz ist Teil von Stufe 0. |
| DB-Backup vom 09.09. ist **875 Byte** (defekt). Gültig: `notenportal-manuell.sql`, `notenportal-vor-claude.sql` (je 48 KB, 10.09.). Kein Backup-Cronjob. | Vor Echtdaten zwingend automatisiertes, getestetes Backup (Punkt 15). |
| Zugriffsschutz: **keine IDOR-Lücke gefunden**. Alle Lernenden-Queries hängen an der Session, Betreuung wird mit `gueltig_von/bis` geprüft, `NoteService::normalizeForSave()` validiert Fach/Modul/Semester serverseitig. | Gut. Aber null Tests dafür → Punkt 11 sichert den Zustand ab, statt Löcher zu stopfen. |
| Tests: 25 Stück, **alle Breeze-Scaffolding** (users-Tabelle, Registrierung, E-Mail-Verifikation). `UserFactory` nutzt Felder, die es nicht gibt. | Stufe 0 ersetzt sie komplett. |
| 11 CHECK-Constraints + 2 Composite-FKs via `DB::statement` in 8 Migrationen (semester, module, betreuungen, lernender_tracks, modul_belegungen, modul_note_gruppen, bewertungsregeln, noten). | Auf MariaDB korrekt und wertvoll (Datenintegrität). Nur SQLite scheitert → bleiben, SQLite wird nicht unterstützt. |
| Breeze-Routen noch aktiv: forgot/reset-password, verify-email, confirm-password. `MAIL_MAILER=log` → Passwort-Reset per Mail funktioniert faktisch nicht. | Aufräumen in Stufe 0; Reset-Weg ist ein Produktentscheid (Frage 4). |
| Firmenspezifisch hart codiert: nur **3 Stellen** (Footer app/guest «Hamilton Bonaduz AG», Login «Hamilton Services AG» = falscher Name). `config('app.name')` wird nirgends genutzt. | Kleiner als befürchtet. Das eigentliche Problem sind die Grenzwerte. |
| Notenfarb-Logik (5.0/4.0/3.5) **~250× dupliziert**, Genügend-Grenze 4.0 7× in PHP, «30 Tage» 9×. Keine zentrale Stelle. | Voraussetzung für konfigurierbare Grenzwerte (Stufe 2). Neue Stufe-1-Views nutzen ab Tag 1 einen zentralen Helper. |
| Diagramm `x-noten-verlauf`: X-Achse nach Index statt Datum; Balken in `x-fach-modul-stats` skaliert `Ø/6` (Note 3 = halber Balken, obwohl 1 die Untergrenze ist). | Beide Darstellungen sind visuell falsch, nicht nur nichtssagend (Punkt 20). |
| Server: HTTP only, `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://localhost`, Zeitzone überall UTC, `locale=en`, opcache nicht explizit aktiv, mod_php + mpm_prefork. VM `srv-lab-dva-001`, 172.26.14.101/24, ufw erlaubt 80/443. | Punkt 15. |
| Rollen: Berufsbildner hat **kein** Noten-CRUD, keine Betreuungsverwaltung, kann Lehrberuf nicht ändern, kein Passwort-Reset für Lernende. Track-Code zwischen Admin und BB 1:1 dupliziert. Keine Policies. | Punkt 9 ist die grösste Einzelposition in Stufe 1. |
| Upgrade-Kompatibilität: `composer why-not laravel/framework ^13` blockiert nur an eigener composer.json + tinker 2. **Kein Paket blockiert.** Laravel 13 verlangt PHP ^8.3 (8.3.6 reicht). laravel-vite-plugin 3 verlangt zwingend Vite 8. concurrently 10 verlangt Node ≥ 22 (vorhanden). | Upgrades sind machbar ohne Improvisation. |

---

## 2. Zeitplan

Annahme: Start Testbetrieb **Mi 30.09.2026** (Frage 1). Feature-Freeze **Fr 25.09.**

| Woche | Inhalt |
|---|---|
| KW 37 (10.–13.09.) | Stufe 0 komplett · CLAUDE.md aufteilen (14) · Branch-Zusammenführung vorbereiten (13) |
| KW 38 (14.–18.09.) | Laravel 13 + PHPUnit 12 + Tinker 3 (1) · Seeder (12) · Tailwind 4/Vite 8 (2, 3) mit Timebox · Modernisierung (4) · main-Merge + CI (13) · ICT-LAB-Grundlagen (15) |
| KW 39 (21.–25.09.) | Rollengleichheit (9) · Feedback-Modul (8) · Passwortwechsel · Profil (10) · bemerkung (7) · Backlog (6) · Sicherheitstests (11) · UI-Texte · Diagramm-Korrektur |
| KW 40 (28.–30.09.) | Betrieb scharf schalten (HTTPS, Backups, Prod-Env) · Echtdaten/Konten anlegen · Abnahme mit Peter · Go-Live |
| ab Oktober | Nachbesserung aus Feedback + Stufe 2 |

Stufe 0 + 1: rund **80–105 h**. Knapp, aber machbar. Grösste Risiken: Rollengleichheit (9) und Tailwind-Verifikation (2). Für beide gibt es unten ein klares Abbruchkriterium.

---

## 3. Stufe 0 – Testsuite (Voraussetzung für alles)

**Ziel:** `php artisan test` grün gegen `notenportal_test` (MariaDB), echte Tests für das Rollenmodell. Erst danach Framework-Upgrades. Ab hier gilt die Commit-Regel aus CLAUDE.md.

| # | Schritt | Aufwand |
|---|---|---|
| 0.1 | Frischer Dump `notenportal` nach `~/db-backups/` (Grösse + Inhalt prüfen). Defekten 875-B-Dump in betrieb.md dokumentieren. | 0.5 h |
| 0.2 | `phpunit.xml`: `DB_CONNECTION=mariadb`, `DB_DATABASE=notenportal_test`, alle DB-Werte mit `force="true"`. Zusätzlich `APP_CONFIG_CACHE=bootstrap/cache/config.testing.php`, damit ein `php artisan optimize` nie die Prod-Config in Tests schiebt. **Guard** in `tests/TestCase.php`: bricht ab, wenn der Datenbankname nicht auf `_test` endet. Grund: np_web hat jetzt DROP auf der Produktiv-DB; `RefreshDatabase` würde sie ohne Guard leeren. | 1 h |
| 0.3 | Breeze entfernen: 7 Feature-Testdateien + Example-Tests, Routen/Controller/Views für Registrierung, E-Mail-Verifikation, Passwort-Bestätigung. Forgot/Reset-Password bleibt vorerst nur, wenn Frage 4 «per Mail» ergibt. `laravel/breeze` aus require-dev. Ungenutzte `users`-Tabelle per Migration droppen (vorher bestätigen, dass nichts darauf zugreift). | 1.5 h |
| 0.4 | Factories: `User` (benutzer, passwort_hash, States `admin()`, `berufsbildner()`, `lernender()`), `Lernender`, `Berufsbildner`, `Betreuung`, `Lehrberuf`, `Semester`, `Kategorie`, `Fach`, `Modul`, `ModulBelegung`, `Note`. Rollen-Seeder für Tests. | 2 h |
| 0.5 | Tests Auth + Rollen: Login per E-Mail, falsches Passwort, Rate-Limit (5 Versuche), inaktiver Benutzer gesperrt (auch bei laufender Session), Weiterleitung je Rolle aufs richtige Dashboard, Logout invalidiert Session, Rollen-Middleware-Matrix (jede Rolle × /admin, /berufsbildner, /noten). | 2 h |
| 0.6 | Migrationen: alle 23 gegen leere `notenportal_test` laufen lassen, `migrate:fresh` zweimal hintereinander (Idempotenz), Schema-Vergleich Test-DB ↔ Prod-DB (`mysqldump --no-data` diff). Constraints bleiben als `DB::statement`; SQLite wird im README als nicht unterstützt geführt. | 1 h |

**Summe Stufe 0: ~8 h.** Commit pro Schritt, Reviewer-Agent am Schluss.

---

## 4. Stufe 1 – Modernisierung und Testbetrieb

### Reihenfolge und Begründung

Upgrades **vor** den neuen Features: Neue Views (Feedback, Profil, Rollengleichheit) entstehen dann direkt auf Laravel 13 / Tailwind 4 und müssen nicht nachmigriert werden. Die visuelle Verifikation von Tailwind 4 ist ohne aktive Nutzer einfacher und stört das Feedback der Testpersonen nicht.

### 1 · Laravel 13 (+ PHPUnit 12, Tinker 3) — 3–4 h

- composer.json: `php ^8.3`, `laravel/framework ^13.0`, `laravel/tinker ^3.0`, `phpunit/phpunit ^12.0`, `laravel/sail` entfernen (kein Docker im Einsatz), `laravel/breeze` bereits in 0.3 entfernt. Pail, Pint, Collision (8.9.5 unterstützt ^13.20), Mockery, Faker: kompatibel, verifiziert per Dry-Run.
- Upgrade-Guide-Punkte mit Relevanz hier: CSRF-Middleware heisst neu `PreventRequestForgery` (+ Sec-Fetch-Site-Prüfung → Login, AJAX-PATCH der Notiz und Feedback-POST testen), `cache.serializable_classes`, Session-Serialisierung `json` (invalidiert Sessions einmalig, vor Go-Live unkritisch), `DELETE … JOIN` wird vollständig kompiliert (Soft-Delete-Pfade prüfen).
- PHPUnit 12: nur Attribute (`#[Test]`, `#[DataProvider]`) – die neuen Tests aus Stufe 0 werden direkt so geschrieben.
- Ablauf: Test grün → Upgrade → Test grün → Smoke-Test aller Rollen im Browser → Commit.
- Laravel 12 bekommt nur noch Security-Fixes bis 24.02.2027; Laravel 13 bis 17.03.2028.

### 2 + 3 · Tailwind 4.3, Vite 8, laravel-vite-plugin 3, concurrently 10 — 10–14 h (Timebox)

Die drei Frontend-Upgrades hängen zusammen (laravel-vite-plugin 3 verlangt Vite 8, `@tailwindcss/vite` 4.3 läuft mit Vite 8) und werden als eine Serie gemacht, je ein Commit:

1. **Aufräumen:** `@tailwindcss/vite` 4.1.18 ist aktuell installiert, aber ungenutzt (vite.config.js lädt es nicht, PostCSS-Pfad aktiv). Kein Rückbau nötig, es wird im nächsten Schritt zum aktiven Plugin.
2. **Vite 8 + laravel-vite-plugin 3 + concurrently 10** (noch mit Tailwind 3 via PostCSS) → Build + Smoke-Test.
3. **Baseline aufnehmen** (siehe Prüfplan unten) – noch auf Tailwind 3.
4. **`npx @tailwindcss/upgrade`** → `tailwind.config.js` wird CSS-first (`@theme`), PostCSS + autoprefixer fallen weg, `@tailwindcss/vite` wird aktiv, `@tailwindcss/forms` per `@plugin`.
5. **Nacharbeit** laut Prüfplan, erneute Aufnahme, Diff = 0 oder jede Abweichung begründet.

**Abbruchkriterium:** Ist die Verifikation bis Fr 18.09. nicht sauber, bleibt der Tailwind-Schritt auf einem Seitenzweig und wird erster Punkt von Stufe 2. Vite 8 / laravel-vite-plugin 3 / concurrently 10 bleiben trotzdem drin (unabhängig von Tailwind 4).

#### Prüfplan Design-System

Werkzeug: Playwright + Chromium headless auf der VM (heute kein Browser installiert; Installation der System-Libs per apt → betrieb.md). Ein Skript fährt ~25 Seiten × 3 Rollen × Hell/Dunkel × 2 Viewports (390 px, 1280 px) gegen eine eigene Demo-DB `notenportal_demo` (deterministischer Seeder aus Punkt 12, damit Screenshots vergleichbar sind). Pro Lauf drei Artefakte:

- **Screenshots** mit Pixel-Diff.
- **Style-Probe (JSON):** `getComputedStyle` für definierte Selektoren (`.glass`, `.glass-subtle`, `.glass-btn`, `.glass-lift`, `.np-card-lift`, `.accent-glow`, Note-Badges aller 4 Stufen, `border-border`, `bg-accent/5`, `ring-ring`, Inputs, Buttons) → Farben nach sRGB normalisiert und mit ΔE < 1 verglichen. Robuster als Pixel, weil v4 intern mit `color-mix()`/OKLCH rechnet.
- **Kontrast:** axe-core auf jeder Seite, Hell und Dunkel.

| Punkt | Was v4 ändert / der Upgrader nicht sauber macht | Prüfung | Wiederherstellung |
|---|---|---|---|
| Default-Border-Farbe | v3: gray-200, v4: `currentColor`. 19 Dateien nutzen `border` ohne Farbklasse. Der Upgrader fügt einen Kompatibilitätsblock mit gray-200 ein – falsch für uns. | Style-Probe `border-*-color` auf allen Elementen mit Border; Screenshot-Diff Hell/Dunkel | Base-Regel `*, ::before, ::after { border-color: rgb(var(--border-rgb)); }` in `@layer base`: entspricht der Design-Absicht (Border = Token) statt gray-200. |
| Dark-Mode | `darkMode: 'class'` wird zu `@custom-variant dark (…)`. Muss exakt `&:where(.dark, .dark *)` sein, sonst greifen die 296 `dark:`-Varianten in 53 Dateien nicht. | Alle Seiten im Dunkel-Durchlauf; Toggle-Test inkl. localStorage und ohne Flackern beim Laden | Variante von Hand setzen und gegen die `.dark`-Klasse auf `<html>` testen. |
| Tokens `rgb(var(--x) / <alpha-value>)` | `<alpha-value>` existiert in v4 nicht mehr. Opazitäts-Modifier (`bg-accent/5`) laufen über `color-mix()`. | Style-Probe aller `/nn`-Varianten; Vergleich mit v3-Werten | `@theme inline { --color-accent: rgb(var(--accent-rgb)); … }` für alle 8 Tokens. `inline`, damit der Dark-Mode-Wechsel der Variablen weiter wirkt. |
| Glass-Effekte | Klassen liegen in `@layer components`. v4 nutzt native Cascade-Layers: CSS **ausserhalb** eines Layers schlägt jetzt jede Utility. Varianten wie `hover:glass-lift` brauchen `@utility`. | Style-Probe `backdrop-filter`, `background-color`, `box-shadow`, `border-color` je Glass-Klasse Hell/Dunkel | Glass-Klassen als `@utility` definieren, ungelayertes CSS in app.css in Layer verschieben. |
| WCAG-AA-Farben | **Die v4-Palette ist neu in OKLCH definiert**, green/emerald/yellow-700 und red-600 haben andere Werte als in v3. red-600 bestand schon in v3 nur knapp. | axe-core-Kontrast auf allen Badge-Varianten; gezielte Messung red-600 auf `--card` hell | Wenn < 4.5:1: die vier Notenfarben in `@theme` auf die geprüften v3-Werte fixieren (oder red-700). |
| Light-Mode-Alphas | Glass-Border 0.12, Schatten 0.13, Deckung 0.70 liegen als Variablen in theme.css → vom Upgrader nicht betroffen, aber Schatten-Utilities heissen um (`shadow-sm` → `shadow-xs`). | Style-Probe `box-shadow`; Screenshot hell | Werte bleiben in theme.css; nur Klassennamen prüfen. |
| Ring | `ring` = 1 px statt 3 px, Farbe `currentColor`. 176 Stellen nutzen `ring-2 ring-ring` (unkritisch); nackte `ring` werden vom Upgrader zu `ring-3`. | Fokus-Durchlauf per Tastatur, Screenshot fokussierter Inputs | – |
| Cursor | Buttons haben in v4 `cursor: default`. 93 `<button>`, keiner mit `cursor-pointer`. | Style-Probe `cursor` | Base-Regel `button:not(:disabled), [role="button"] { cursor: pointer; }`. |
| Placeholder | v4: 50 % der Textfarbe statt gray-400 | Screenshot leerer Formulare | Base-Regel mit `--muted`. |
| `space-*`, `divide-*` | Selektor geändert (93 + 25 Stellen); wirkt anders bei versteckten/inline Kindern | Screenshot-Diff | Betroffene Stellen auf `gap-*` umstellen (ohnehin moderner). |
| Hover auf Touch | `hover:` greift nur noch bei `(hover: hover)` | Mobile-Viewport-Durchlauf | Gewollt, keine Aktion. |
| Druckansicht | Eigene Farben/Inline-CSS | PDF-Druck aller 3 Rollen | – |

Browser-Minimum v4: Chrome 111, Safari 16.4, Firefox 128. Für die Geräte im ICT-LAB ist das unkritisch, im Plan aber festgehalten, weil es für Open Source in die Systemanforderungen gehört.

### 4 · Modernisierung aller Dateien — 4–6 h

Nach beiden Upgrades, damit der Code aussieht wie auf den neuen Versionen geschrieben:

- Rector mit `driftingly/rector-laravel` (Laravel-13-Set) + PHP-8.3-Set als temporäres Werkzeug; danach Pint.
- `protected $casts` → `casts()`-Methode (13 Models), typisierte Konstanten, `readonly` wo passend, Constructor Property Promotion in Services.
- config/*.php gegen das Laravel-13-Skeleton abgleichen, Ungenutztes entfernen (Registrierungs-/Verifikations-Reste).
- `phpunit.xml` auf PHPUnit-12-Schema, `vite.config.js`, `package.json`-Scripts, `composer.json`-Scripts (`dev` mit concurrently 10).
- `postcss.config.js` und `tailwind.config.js` entfallen (CSS-first).
- Zeitzone: Speicherung bleibt UTC, Anzeige zentral `Europe/Zurich` über einen Formatter; `locale` `de_CH`. (Prüfungsdaten sind `DATE` und nicht betroffen; betroffen sind erstellt_am, gesehen_am, Kommentarzeiten.)
- Zentraler Noten-Helper (`App\Support\NotenSkala` + Blade-Komponente `<x-note>`): neue Views nutzen ihn ab sofort. Die ~250 Alt-Stellen werden in Stufe 2 (16) migriert.

### 5 · PHP 8.4 – Bewertung (keine Umsetzung)

**Empfehlung: Nicht jetzt, und nicht über das PPA.**

- Nutzen: Laravel 13 verlangt nur 8.3. Property Hooks, asymmetrische Sichtbarkeit, Lazy Objects: nichts davon braucht das Projekt.
- Support: PHP 8.3 upstream Security bis 31.12.2027; das Ubuntu-Paket (main) bekommt Canonical-Backports im 24.04-Zyklus (bis 2029, aus der LTS-Politik abgeleitet).
- Kosten PPA-Weg: `ppa:ondrej/php` (Drittanbieter, Einzelmaintainer), alle Extensions neu, sinnvollerweise gleichzeitig Wechsel mod_php + prefork → php-fpm + mpm_event + proxy_fcgi. Rund 3–4 h plus Betriebsrisiko. `bcmath` fehlt heute, müsste mit.
- **Entscheidendes Argument für Open Source:** Das Installationsskript (18) soll auf einem frischen Ubuntu mit Distro-Paketen laufen. Eine PPA-Abhängigkeit macht jede Fremdinstallation fragiler.
- Besserer Weg: neuere PHP-Version über die Distro-Pakete des nächsten Ubuntu-LTS (26.04; welche PHP-Version es mitbringt, vor dem Wechsel prüfen). Der Wechsel auf php-fpm + mpm_event lohnt sich unabhängig davon und kann bereits auf 8.3 erfolgen (Stufe 2, Betrieb).

### 6 · Offene Punkte aus audit-backlog.md — 2–3 h

| Punkt | Entscheid | Begründung |
|---|---|---|
| [mittel] markAlleGesehen ignoriert aktiven Filter | **Vor Testbetrieb** | Kernablauf von Peter. Mit Filter «alle gesehen» markiert unsichtbare Noten → neue Noten gehen unter. Echter Funktionsfehler. |
| [niedrig] BenutzerController::update speichert vor 2. Validierung | **Vor Testbetrieb** | Datenintegrität: halb gespeicherte Benutzer. Ein `validate()` + `DB::transaction`. Wird in Punkt 9 ohnehin angefasst. |
| [niedrig] BB-Model ohne SoftDeletes | **Mit Punkt 9** | Datei wird im Refactor angefasst, kostet Minuten. |
| [niedrig] Admin-Formular-Labels weichen ab | **Mit Punkt 9** | Die gemeinsamen Verwaltungs-Views bekommen ohnehin den Label-Standard; der Rest der Admin-Formulare wartet. |
| [niedrig] Lichtkanten-Insets hell wirkungslos | **Mit Tailwind 4** | Dieselben CSS-Zeilen werden in der Glass-Verifikation angefasst. Fällt Tailwind 4 aus der Timebox, wartet der Punkt. |
| [niedrig] Glow-Alphas themen-unabhängig | **Mit Tailwind 4** | Wie oben. |

### 7 · `bemerkung` auf `lernende` — 1–2 h

Migration `text NULL` (geht jetzt, np_web hat ALTER). Bearbeitbar durch BB und Admin in der gemeinsamen Verwaltungsansicht (Punkt 9). Standard: **intern**, für den Lernenden nicht sichtbar (Frage 5). Hinweis: Nach nDSG haben Lernende ein Auskunftsrecht auch auf interne Notizen; das betrifft den Inhalt, nicht die Technik.

### 8 · Feedback-Modul — 6–8 h

- Tabelle `feedback`: `feedback_id`, `benutzer_id` (FK), `kategorie` (feedback/idee/bug), `text` (max. 5000), `route_name`, `url` (Pfad + Query), `user_agent`, `viewport`, `status` (offen/in_arbeit/erledigt), `admin_notiz`, `erstellt_am`, `aktualisiert_am`, `erledigt_am`.
- Schwebender Button im App-Layout für **alle Rollen** (Peter testet ebenfalls mit), Alpine-Dialog, Senden per `fetch` ohne Reload, Toast. Route, URL und Viewport werden automatisch mitgeschickt, der Nutzer sieht nur Kategorie + Text. Rate-Limit `throttle:10,1`.
- «Meine Meldungen»: Jede Person sieht ihre eigenen Meldungen samt Status. Das hält die Testgruppe bei der Stange, ohne Mailversand.
- Admin: Liste mit Filter Status/Kategorie/Rolle, Statuswechsel inline, Notiz, Zähler offener Meldungen auf dem Admin-Dashboard. CSV-Export für die Auswertung.
- Tests: Lernender sieht keine fremden Meldungen, kann keinen Status ändern; Admin-Routen 403 für andere Rollen.

### 9 · Rollengleichheit — 14–18 h

**Ja, dieselben Controller und Views sind möglich, und zwar sauber:**

- Gemeinsame Controller unter `App\Http\Controllers\Verwaltung\` (Lernende, Noten des Lernenden, Tracks, Betreuung, Modulbelegungen, Konto-Aktionen wie Passwort zurücksetzen/deaktivieren).
- Routen werden **zweimal registriert** über eine gemeinsame Routen-Datei `routes/verwaltung.php`, eingebunden in die Gruppen `prefix('admin')->name('admin.')->middleware('role:Admin')` und `prefix('berufsbildner')->name('berufsbildner.')->middleware('role:Berufsbildner')`. URLs bleiben `/admin/lernende/…` bzw. `/berufsbildner/lernende/…`.
- Views unter `resources/views/verwaltung/` erzeugen Links über einen Helper, der den Namenspräfix der aktuellen Route übernimmt (`verwaltung_route('lernende.show', $l)` → `admin.lernende.show` oder `berufsbildner.lernende.show`). Keine hartcodierten `route('admin.…')` mehr.
- Sichtbarkeit zentral: `LernenderPolicy` + Scope `Lernender::sichtbarFuer($user)`: Admin sieht alle, BB je nach Frage 2 alle oder nur aktiv betreute. Heute ist der Betreuungs-Check in mehreren Controllern einzeln nachgebaut (Lernende, Noten, Kommentare, Export); danach gibt es genau eine Stelle, die getestet wird.
- Admin-exklusiv bleibt: Verwaltung von Admins und Berufsbildnern, Stammdaten, Berichte, Feedback-Verwaltung.
- Noten-Änderungen durch BB/Admin werden bereits in `erfasst_von`/`aktualisiert_von` protokolliert. Die Notenansicht zeigt künftig «geändert von …», damit Lernende Fremdänderungen sehen.
- Alte Admin/BB-Lernende-Controller und -Views werden gelöscht, nicht parallel gehalten.

**Abbruchkriterium:** Das Minimum für Go-Live ist, dass Peter seine Lernenden anlegen, vollständig bearbeiten (inkl. Lehrberuf, bemerkung, Tracks), deaktivieren und deren Passwort zurücksetzen kann. Noten-CRUD durch BB kann notfalls in die erste Oktoberwoche rutschen, weil Lernende ihre Noten selbst erfassen.

### 10 · Profilbearbeitung Lernende — 3–5 h

| Feld | Lernender darf ändern? | Begründung |
|---|---|---|
| Passwort | **Ja** (mit aktuellem Passwort) | Selbstverständlich. |
| E-Mail | **Ja**, mit Passwortbestätigung | Kontaktadresse, auf die BB per mailto schreibt. Sie ist aber auch der Login → Bestätigung gegen Tippfehler und Kontoübernahme. |
| Klasse Berufsfachschule (neu, z. B. «INF24b») | **Ja** | Nur Info für BB, keine Logik daran. Die Lernenden kennen sie besser als der Betrieb. |
| Klasse BMS (neu, nur bei aktivem BMS-Track) | **Ja** | Wie oben. |
| Darstellung (Hell/Dunkel/System) serverseitig | **Ja** | Heute nur localStorage → geht auf jedem Laborrechner verloren. |
| Vorname, Nachname | Nein | Stehen auf der Druckansicht mit Unterschriftenzeile und in Berichten; müssen den offiziellen Namen tragen. Korrektur via BB. |
| Benutzername | Nein | Technische Identität. |
| Rolle, Aktiv-Status | Nein | Berechtigungen. |
| Lehrberuf, Lehrbeginn, Lehrende | Nein | Bestimmen erlaubte Fächer/Module und die Semesterzuordnung → Notenlogik. |
| BMS-/ABU-Tracks | Nein | Bestimmen, welche Kategorien erfasst werden dürfen → Notenlogik. |
| Modulbelegungen | Nein | Zuordnung Note ↔ Modul, Gewichtungsgruppen. |
| Betreuung | Nein | Zuordnung, steuert, wer die Noten sieht. |
| bemerkung | Nein (und standardmässig unsichtbar) | Interne Notiz. |

Neue Spalten `klasse_schule`, `klasse_bms`, `darstellung` per Migration. Test: Profil-Update ignoriert eingeschleuste Felder (`aktiv`, `lehrberuf_id`, `rolle`).

### 11 · Testabdeckung kritische Pfade — 6–8 h

Kein bekanntes Loch, deshalb sichert jeder Test einen heute korrekten Pfad gegen Regressionen ab:

- **Automatische Routen-Matrix:** Test iteriert über `Route::getRoutes()`; jede Route mit `role:Admin` bzw. `role:Berufsbildner` muss für Lernende 403 liefern. Neue Routen sind damit automatisch abgedeckt.
- Lernender A gegen Daten von B: `edit/update/destroy` mit fremder `note_id` (404), Deep-Link `?_open=<fremde note_id>` (keine fremden Inhalte im HTML), Notiz-PATCH, `gesehen`, Kommentar anlegen/löschen, Export und Druck (fremde Titel dürfen nicht vorkommen), Rechner.
- Formular-Manipulation: `store` mit eingeschleuster `lernender_id` (landet bei A), fremde `modul_belegung_id` (422), Fach ausserhalb des eigenen Lehrberufs (422), BMS-Note ohne BMS-Track (422), manipulierte `semester_id`, `gewichtung_prozent` ausserhalb 5–100, `note_wert` ausserhalb 1–6.
- Berufsbildner: nicht betreute Lernende (Liste, Detail, Noten, Druck, Export, Export-alle, Kommentar, gesehen) – jeweils mit abgelaufener, zukünftiger und fremder Betreuung. Kein Zugriff auf Admin-/BB-Konten.
- Soft-Delete: gelöschte Noten erscheinen nirgends (Liste, Export, Druck, Durchschnitt, Berichte).
- Feedback, Profil, Passwortwechsel (siehe dort).

### 12 · Seeder mit realistischen Testdaten — 3–4 h

`DemoSeeder` (nie im Standard-`DatabaseSeeder`, nie gegen die Produktiv-DB; Guard wie in 0.2): 1 Admin, 3 BB, ~14 Lernende. Lehrberufe Informatiker/in EFZ Applikationsentwicklung und Plattformentwicklung, Entwickler/in digitales Business EFZ, ICT-Fachmann/-frau EFZ. Lehrbeginn 2022–2025 (1.–4. Lehrjahr), je Beruf mit und ohne BMS (ohne BMS → ABU), Modulbelegungen nach Lehrberufsplan. Notenverläufe mit erkennbaren Mustern: stetig besser, Einbruch in einem Fach, stabil gut, knapp ungenügend. Kommentare, gesehen-Markierungen, eine abgelaufene Betreuung. Fester Faker-Seed → deterministisch (Voraussetzung für den Screenshot-Vergleich). Neutrale Firmen- und Personennamen, kein Hamilton.

### 13 · Branch-Strategie — 2–3 h

| Branch | Befund | Vorgehen |
|---|---|---|
| `main` | Alter PHP-Prototyp, keine gemeinsame Historie mit dem Laravel-Stand | Tag `archiv/php-prototyp`, dann Zusammenführung (unten) |
| `feature/backend-noten-crud` | Vollständig in `main` gemergt (PR #1) | Vom Archiv-Tag abgedeckt → löschen |
| `php-backend-new-db-scheme` | 15 Commits auf `main`, PHP-Prototyp mit neuem Schema, nirgends gemergt | Tag `archiv/php-backend-new-db-scheme` → löschen |
| `laravel-rebuild` | Vollständig im Feature-Branch enthalten (Spitze ist Vorfahre) | löschen |

**Zusammenführung ohne Force-Push:** Auf dem Feature-Branch `git merge -s ours --allow-unrelated-histories origin/main` (übernimmt die alte Historie, Dateistand bleibt Laravel). Danach PR `feature/claude-fertigstellung → main`; das ist dann ein normaler Merge. Die 179 Commits bleiben erhalten, die Prototyp-Historie auch, `main` wird nie überschrieben (der Force-Push auf main ist in `.claude/settings.json` ohnehin gesperrt).

**Zeitpunkt:** direkt nach Stufe 0 + Laravel 13 (KW 38). Danach: Branch-Schutz auf `main`, GitHub Actions (PHPUnit mit MariaDB-Service-Container + `npm run build`), kurzlebige Branches pro Punkt → PR → `main`. Go-Live = Tag `v0.9.0`; der Server läuft ab dann auf `main`.

### 14 · CLAUDE.md aufteilen — 1–2 h (früh, KW 37)

Früh, weil jede weitere Session davon profitiert. CLAUDE.md hat heute 349 Zeilen; Ziel < 100:

- **Bleibt:** Projekt in 3 Zeilen, Umgebung, harte Regeln (Branch, Commit-Regel, `ss` statt `ß`, keine Farb-Hardcodes, Soft-Delete-Filter, Berechtigungsprüfung über Policy/Scope, Test-Guard), wichtigste Befehle, Subagent-/Modellwahl, Token-Disziplin.
- **→ `.claude/skills/notenportal-ui/SKILL.md`:** Design-Tokens, Tailwind-Klassen, Glass-System, Notenfarb-Logik, Blade-Snippets (Label, Button, Badge, Accordion).
- **→ `docs/architektur.md`:** Datenmodell, Verzeichnisstruktur, Rollenmodell. **→ `docs/funktionsumfang.md`:** «Was bereits implementiert ist». **→ `docs/betrieb.md`:** Dateirechte, Server, Backups.
- **Korrigieren:** DB-Rechte-Abschnitt (veraltet), «php artisan test schlägt fehl → ignorieren» (ab Stufe 0 falsch), bemerkung-Hinweis, Testbenutzer.
- `.claude/agents/` (heute untracked) wird committet.

### 15 · Zugriff ICT-LAB + Betrieb — 4–6 h

Gehört in Stufe 1, weil ab Go-Live echte Noten von Lernenden auf dem Server liegen:

- vhost mit ServerName (Frage 3), **HTTPS** (sonst gehen Passwörter im Klartext durchs Labornetz), HTTP → HTTPS-Redirect, HSTS, `SESSION_SECURE_COOKIE=true`.
- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `LOG_CHANNEL=daily` mit Aufbewahrung, `APP_LOCALE=de_CH`.
- opcache explizit aktiv, `config:cache`/`route:cache`/`view:cache` im Deploy-Ablauf (gefahrlos dank Test-Guard aus 0.2).
- **Backups:** Cron täglich `mysqldump --single-transaction` mit Grössen- und Inhaltsprüfung (der 875-B-Fehler darf nicht mehr unbemerkt passieren), 14 Tages- + 8 Wochenstände, wöchentlicher Restore-Test in `notenportal_restore`.
- Least Privilege: np_web zurück auf DML; separater DB-User `np_migrate` mit DDL nur für Deploy/Migrationen.
- Deploy-Skript `deploy.sh` (pull main, composer install --no-dev, npm ci && build, migrate --force, caches, Rechte).
- ufw auf das Labornetz einschränken, sobald es bekannt ist.
- Alles in `docs/betrieb.md`.

### Aus Stufe 2 vorgezogen

| Punkt | Aufwand | Warum jetzt |
|---|---|---|
| **Erzwungener Passwortwechsel** (aus 17) | 2–3 h | Beim Go-Live legt Peter echte Konten mit Startpasswörtern an. Ohne Pflichtwechsel bleiben die bekannten Passwörter bestehen. Wird in 17 für das Standard-Admin-Konto wiederverwendet. |
| **Firmenname korrigieren + Tabelle `einstellungen`** (aus 16) | 1–2 h | Login zeigt heute den falschen Namen «Hamilton Services AG». Die drei Stellen lesen ab sofort `betrieb_name` aus der DB (Wert «Hamilton AG»). Die Admin-Oberfläche folgt in 16/17; die Tabelle jetzt anzulegen erspart einen Umbau. |
| **Irreführende Diagramme korrigieren** (aus 20) | 2–3 h | Zeitachse nach Datum statt Index, Balken ab Note 1 statt 0, Referenzlinie 4.0. Die Testgruppe soll keine falschen Aussagen sehen; das ausführliche Statistik-Konzept bleibt in Stufe 2. |
| **UI-Texte bereinigen** (durchgehend) | 3–4 h | Siehe Abschnitt 6. |

**Nach Stufe 2 verschoben:** nichts fix. Bedingt: Tailwind 4 (Timebox-Kriterium) und BB-Noten-CRUD (Abbruchkriterium Punkt 9).

---

## 5. Stufe 2 – Produktreife und Open Source

Reihenfolge nach Nutzen: Zuerst was die Testgruppe spürt (20, 19), dann der Weg zur Fremdinstallation (16 → 17 → 18), dann Komfort (21).

### 20 · Statistiken, die etwas aussagen — 12–16 h

Grundregeln: Y-Achse immer die volle Notenskala 1–6 (nie abgeschnitten, damit Schwankungen nicht dramatisiert werden), Referenzlinie 4.0, Balken beginnen bei 1, Zeitachse proportional zum Datum bzw. zum Semester, gleiche Skala über alle kleinen Diagramme, Farben aus der Notenskala. Server-seitig gerendertes SVG (keine Chart-Bibliothek, funktioniert im Druck).

| Darstellung | Beantwortet die Frage | Für |
|---|---|---|
| **Kennzahlen-Zeile:** gewichteter Ø aktuelles Semester · Δ zum Vorsemester (Pfeil + Wert) · Anzahl ungenügende Noten · Tiefstnote | «Wie stehe ich gerade, und besser oder schlechter als letztes Semester?» | L, BB |
| **Semesterverlauf pro Kategorie** (Linie, x = Semester, Punkt = gewichteter Ø, Linie 4.0) | «Wie entwickle ich mich über die ganze Lehrzeit in Schule, ÜK, BMS?» | L, BB |
| **Heatmap Fach/Modul × Semester** (Zelle = Ø, Farbe aus der Notenskala, leer = keine Note) | «Welches Fach kippt in welchem Semester? Wo fehlen Noten?» | L, BB |
| **Veränderungsliste** (Fächer sortiert nach Δ zum Vorsemester, grösste Verschlechterung oben) | «Wo muss ich zuerst hinschauen?» | L, BB |
| **Trend je Fach** (Steigung der Regressionsgeraden über die letzten 3 Semester, Noten/Semester, als Pfeil ↗ → ↘ mit Schwelle ±0.1) | «Ist das ein Ausreisser oder eine Richtung?» | L, BB |
| **Klassenübersicht BB** (Zeile pro Lernendem: Sparkline Semester-Ø, Δ, Trend, Anzahl < 4.0, letzte Erfassung) | «Wer braucht jetzt Aufmerksamkeit?» – ersetzt Teile der «Zu tun»-Liste | BB |
| **Promotionsprüfung** (optional, falls Regeln hinterlegt): erfüllt / gefährdet nach den Regeln aus der vorhandenen, leeren Tabelle `bewertungsregeln` | «Bin ich promotionsgefährdet?» | L, BB |

Entfällt: das heutige Index-Liniendiagramm der letzten 20 Einzelnoten. Einzelnoten verschiedener Fächer auf einer Linie verbunden zeigen keinen Verlauf.

### 19 · Zeugnis-Upload — 6–8 h

**Empfehlung: Ablage als Datei mit Abgleich, kein Auslesen.**

- Zeugnisse der Berufsfachschulen, BMS und ÜK-Anbieter haben je eigene Layouts, oft gescannt. OCR müsste pro Schule angelernt werden und bleibt fehleranfällig; die Noten müsste trotzdem jemand kontrollieren.
- Echter Nutzen ohne OCR: Der BB sieht das offizielle Zeugnis direkt neben den selbst erfassten Noten desselben Semesters und markiert «mit Zeugnis abgeglichen». Das schliesst die Vertrauenslücke der Selbsterfassung, das eigentliche Problem des Portals.
- Technik: PDF/JPG/PNG bis 10 MB, private Disk (`storage/app/private/zeugnisse/{lernender}/{uuid}`), Auslieferung nur über Controller mit Policy, Tabelle `zeugnisse` (lernender, semester, typ Schule/BMS/ÜK, Originalname, MIME, Grösse, SHA-256, hochgeladen_am, abgeglichen_von/am). php.ini-Limits anheben (heute 2 MB).
- Später optional: Noten aus einem Zeugnis manuell in einer Tabelle nacherfassen, mit Zeugnis daneben.

### 16 · Alles Firmenspezifische raus — 12–16 h

- `einstellungen` (aus Stufe 1) wird vollständig: Betriebsname, Kurzname, Logo (Upload, public-Disk), Kontakt (Adresse, Telefon, E-Mail Berufsbildung), Notengrenzen (gut 5.0 / genügend 4.0 / kritisch 3.5), Rundung, Inaktivitätsfrist (heute 9× «30 Tage»), Lehrende-Vorwarnfrist. Gecacht, Admin-Oberfläche «Betrieb».
- Standorte: neue Tabelle, optional pro Lernendem/BB (Filter in Berichten).
- Migration der ~250 Notenfarb-Stellen auf `<x-note>`/`NotenSkala`, die Grenzen kommen aus der DB. Tests für die Grenzfälle (3.49/3.5/3.99/4.0/4.99/5.0).
- Systematische Suche erneut (Hamilton, Bonaduz, Personennamen, Domains) über Code, Seeds, Tests, Docs, Git-Historie. Wo der Name als Testdatum bleibt: «Hamilton AG».
- Open-Source-Paket: LICENSE (Frage 7), README (Installation, Systemanforderungen inkl. Browser-Minimum), CONTRIBUTING, SECURITY.md. Die Git-Historie enthält Namen und Testkonten → öffentliches Repo mit bereinigter Historie (Frage 7).

### 17 · Einrichtungswizard — 12–16 h

- Basis-Seeder legt `admin` mit Pflicht-Passwortwechsel an (Mechanismus aus Stufe 1), `einstellungen.einrichtung_abgeschlossen = false`.
- Middleware: Solange nicht abgeschlossen, landet jeder Admin im Wizard; alle anderen Rollen existieren noch nicht.
- Schritte: 1 Betrieb (Name, Adresse, Kontakt) · 2 Logo · 3 Lehrberufe auswählen (aus mitgelieferter Liste bzw. Import 21) · 4 Semesterdaten (Vorschlag Feb./Aug. automatisch generiert, anpassbar) · 5 Notengrenzwerte (Standard vorausgefüllt) · 6 Erste Berufsbildner (mit Startpasswort + Pflichtwechsel) · Zusammenfassung.
- Jeder Schritt speichert sofort (Abbruch jederzeit, Fortsetzung beim nächsten Login).
- Nach Abschluss liefern die Wizard-Routen 404; alle Werte bleiben unter Admin → Betrieb/Stammdaten änderbar. Die Formulare werden als Komponenten geteilt, nicht doppelt gebaut.

### 18 · Selbstinstallation — 12–16 h

- `install.sh`, `set -Eeuo pipefail`, `trap ERR` gibt aus: Schritt, Befehl, Ursache, Behebung. Jeder Schritt prüft zuerst, ob er schon erledigt ist (idempotent).
- Ablauf: Ubuntu-Version prüfen (24.04/26.04) → apt: apache2, mariadb-server, php8.3 + Extensions (Distro, kein PPA), composer, Node 22 → DB + DB-User mit Zufallspasswort (np_web DML, np_migrate DDL) → `.env` aus `.env.example` → `key:generate` → `migrate --force` → `BasisSeeder` → `npm ci && npm run build` → vhost aus Template, `a2enmod rewrite headers`, optional HTTPS → Dateirechte (Schema aus CLAUDE.md) → Abschlussmeldung mit URL und Admin-Startpasswort.
- Parameter: `--dir`, `--db-name`, `--server-name`, `--port`, `--no-apache-reload`. Bestehende vhosts/DBs werden nie überschrieben, Abbruch bei Namenskollision.
- `BasisSeeder`: nur Rollen, Kategorien (SCHULE/ÜK/BMS/ABU), Standardgrenzwerte, Standard-Admin mit Pflichtwechsel. Keine Betriebsdaten.
- Test: zweite Instanz `/var/www/notenportal-i2`, DB `notenportal_i2`, eigener vhost (Port 8082), zweimal hintereinander ausführen (Idempotenz), danach Wizard durchklicken. Die bestehende Instanz wird vorher/nachher per Checksumme (vhost, .env, DB-Dump) verglichen. Einschränkung: Auf dieser VM sind alle Pakete schon installiert, der Installationspfad der Abhängigkeiten wird dort nur als «schon vorhanden» durchlaufen. Echter Frischtest zusätzlich in einem Ubuntu-24.04-Container (LXD), falls auf der VM einsetzbar.

### 21 · Lehrberufs- und Modullisten aus externer Quelle — 6–10 h

Recherche-Ergebnis (10.09.2026):

| Quelle | Schnittstelle | Nutzbar für |
|---|---|---|
| SBFI Berufsverzeichnis (BECC) | **Keine** API, kein Export, nur HTML (verifiziert) | Nachschlagen |
| SDBB Profession Service (berufsberatung.ch) | **Ja**: REST-API ohne Authentifizierung, Swagger-Doku, zusätzlich Excel-Download (verifiziert). Berufsbezeichnungen, SBFI-Nr., ÜK-Tage, Fächerspiegel. **Keine Module.** Lizenz nicht explizit dokumentiert. | Berufs-Stammdaten |
| ICT-Berufsbildung Modulbaukasten | **Keine** API gefunden, Webseite als JS-App, Downloads nur PDF. Nutzungsbedingungen (seit 01.07.2025): alle Rechte vorbehalten (nur Auszug gesehen, Volltext war nicht erreichbar). | Nur manuell |
| opendata.swiss | Nur Statistiken (Lernendenzahlen), keine Stammdaten | – |

Zu den Berufen: «Betriebsinformatiker/in EFZ» wird laut ICT-Berufsbildung eingestellt (letzte Lehreintritte 2023). In der DB heisst der Eintrag `INBE`; gemeint ist vermutlich die alte Fachrichtung Betriebsinformatik (Frage 1). Die Revision ICT-Fachmann/-frau EFZ (neue Module ab 2026) ist nur aus Suchauszügen bekannt und muss vor einer Übernahme direkt geprüft werden.

**Vorschlag:** Kein Scraping. Berufe optional per SDBB-API aktualisieren (Knopf «Berufsdaten abgleichen», Diff-Vorschau). Module per **Importdatei**, die ein Admin einmal pro Jahr einspielt (CSV, UTF-8, `;`-getrennt, Excel-tauglich):

```
beruf_kuerzel;beruf_bezeichnung;fachrichtung;sbfi_nr;modul_nummer;modul_titel;lernort;pflicht;empf_semester;gueltig_ab;gueltig_bis;quelle
XYZ;Beispielberuf EFZ;Beispielfachrichtung;<sbfi_nr>;<nr>;<Modultitel>;SCHULE;1;2;2025-08-01;;<quelle_url>
```

(Platzhalter, keine echten Werte. SBFI-Nr. und Moduldaten kommen ausschliesslich aus der geprüften Importdatei.) Import mit Vorschau: neu / geändert / entfällt. Module mit Noten werden nie gelöscht, nur auf `gueltig_bis` gesetzt. Eine Vorlage für die drei relevanten Berufe liegt dem Repo bei, nachdem sie gegen Modulbaukasten/Bildungsplan geprüft ist. Vor jeder automatisierten Nutzung des Modulbaukastens: Anfrage bei ICT-Berufsbildung Schweiz.

### Weitere Stufe-2-Kandidaten (aus der Analyse)

php-fpm + mpm_event statt mod_php (2–3 h) · Passwort-Reset per Mail, falls SMTP verfügbar (2 h) · Benachrichtigungen an BB bei neuen Noten (4–6 h) · CSP-Header (2 h) · Wechsel auf Ubuntu 26.04 / PHP 8.4+.

---

## 6. Durchgehend: Texte

Gefundene Kandidaten (~20): Login «Accounts werden durch den Admin erstellt» und «Hamilton Services AG»; «Hinweis: … Note(n) ohne Gewichtung werden mit 100 % gerechnet»; «Semester wird automatisch anhand Prüfungsdatum gesetzt» (Admin create/edit); drei erklärende Absätze im Rechner; «(Shortcut: N)»; «Leer = automatisch» (Kategorien, Semester); «Die Betreuung wird automatisch dir zugewiesen».

Regel für die Bereinigung: Ein Text bleibt nur, wenn er eine Entscheidung ermöglicht oder einen Fehler verhindert, die der Nutzer ohne ihn nicht treffen bzw. vermeiden könnte. Beispiel: «Achtung: Durchschnitt unter 4.0» bleibt als Statusanzeige (Farbe + Kurzlabel), ohne erklärenden Satz. Tastaturkürzel wandern in einen Hilfe-Dialog (`?`), statt in Buttons zu stehen. Erklärungen zu automatischen Abläufen entfallen; die Oberfläche zeigt stattdessen das Ergebnis (z. B. das ermittelte Semester live im Formular, das gibt es bereits). Kein `ß` im Code gefunden. Umsetzung mit dem ui-checker pro Rolle.

---

## 7. Risiken

| Risiko | Gegenmassnahme |
|---|---|
| Test löscht Produktiv-DB (np_web hat DROP) | Guard in TestCase + `APP_CONFIG_CACHE` für Tests + Least-Privilege (15) |
| Tailwind 4 verändert Design unbemerkt | Style-Probe + Screenshot-Diff + axe; Timebox mit Abbruchkriterium |
| Rollengleichheit sprengt den Zeitrahmen | Minimum für Go-Live definiert (Punkt 9), alte Controller erst nach grünem Test entfernen |
| Keine funktionierenden Backups | Backup vor jedem Schritt (0.1), Cron + Restore-Test vor Go-Live |
| Klartext-Passwörter im Labornetz | HTTPS vor Go-Live (Frage 3) |
| Entscheide kommen spät | Fragen unten; Standardannahmen sind jeweils markiert, damit die Arbeit weiterläuft |

---

## 8. Offene Produktentscheide

1. **Testbetrieb:** Startdatum (Annahme 30.09.), Anzahl Lernende, welche Lehrberufe? Startet er mit leerer DB (Testdaten weg) oder werden die bestehenden Konten übernommen? Was ist `INBE` genau?
2. **Rollengleichheit:** Sieht und verwaltet ein Berufsbildner **alle** Lernenden des Betriebs oder nur die selbst betreuten? Darf er Noten ändern und löschen? (Annahme: nur betreute; Noten ja, mit sichtbarem «geändert von».)
3. **ICT-LAB:** DNS-Name für den Server? HTTPS-Zertifikat von der internen CA (Hamilton-IT) möglich, oder selbstsigniert? Welches Netz/welche Geräte? Ist der Betrieb mit echten Lernendendaten auf dieser Labor-VM mit IT/Datenschutz abgesprochen?
4. **Passwort vergessen:** Gibt es einen SMTP-Relay für Mails, oder setzt nur BB/Admin zurück? (Annahme: nur BB/Admin, Mail-Reset-Routen fliegen raus.)
5. **bemerkung:** Nur intern (BB/Admin), oder für den Lernenden sichtbar? (Annahme: intern.)
6. **Profil:** Liste in Punkt 10 so in Ordnung, inkl. neuer Felder Klasse Berufsfachschule/BMS?
7. **Open Source:** Lizenz MIT (einfach, Firmen dürfen alles) oder AGPL-3.0 (Verbesserungen müssen zurückfliessen)? Veröffentlichung als neues Repo mit bereinigter Historie?
8. **Branches:** Einverstanden, dass `laravel-rebuild`, `php-backend-new-db-scheme` und `feature/backend-noten-crud` nach Archivierung als Tags gelöscht werden und `main` per `-s ours`-Merge den Laravel-Stand bekommt?
