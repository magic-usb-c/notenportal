# Ideensammlung Notenportal

Aktueller Funktionsumfang steht in `docs/funktionsumfang.md`, Betrieb in `docs/betrieb.md`, Offenes/Zurückgestelltes in `docs/audit-backlog.md`.

## Erledigt

- Laravel 13, PHPUnit 12, Tinker 3 — `composer.json`
- Tailwind 4.3, Vite 8, laravel-vite-plugin 3, concurrently 10 — `package.json`, Git-Tag `pre-tailwind4`
- Modernisierung (typisierte `casts()` statt `$casts`, Pint) — `app/Models/*`
- PHP 8.4: Entscheid dagegen (bleibt bei 8.3 bis nächstes Ubuntu-LTS), keine Umsetzung nötig
- `bemerkung`, `klasse_schule`, `klasse_bms` auf `lernende` — Migration `2026_09_10_000001…`, `app/Models/Lernender.php`
- Rollengleichheit Admin/Berufsbildner (gemeinsame Controller/Routen/Views, Sichtbarkeits-Scope) — `routes/verwaltung.php`, `app/Http/Controllers/Verwaltung/*`, `Lernender::sichtbarFuer()`
- Profilbearbeitung Lernende (E-Mail, Passwort, Klasse Schule/BMS, Darstellung serverseitig) — `app/Http/Controllers/ProfileController.php`
- Testabdeckung kritischer Pfade inkl. automatischer Rollen-Matrix — `tests/Feature/Auth/ZugriffsschutzTest.php`, 33 Testdateien
- Seeder mit realistischen Testdaten — `database/seeders/DemoSeeder.php`, `BasisSeeder.php`
- Branch-Strategie: `main` und `feature/claude-fertigstellung` zusammengeführt (identischer Stand), Altbranches gelöscht, Tags `archiv/php-prototyp-main`, `archiv/php-backend-new-db-scheme`
- CLAUDE.md aufgeteilt (54 Zeilen) — `docs/architektur.md`, `docs/funktionsumfang.md`, `docs/betrieb.md`, Skill `notenportal-ui`
- Zugriff ICT-LAB + Betriebsgrundlagen (Server, Dateirechte, Go-Live-Checkliste) — `docs/betrieb.md`; Härtung fürs Produktivnetz noch offen (siehe unten)
- Firmenspezifisches, Grundlage: Tabelle `einstellungen` (Betriebsname, Notengrenzen, Rundung, Fristen), zentraler Helper — `app/Support/Einstellungen.php`, `app/Support/NotenSkala.php`; Rest offen (siehe unten)
- Einrichtungsassistent — `app/Http/Controllers/Admin/EinrichtungController.php`, `resources/views/admin/einrichtung`
- Selbstinstallation `install.sh`, getestet als Zweitinstanz `notenportal-i2` (siehe `docs/betrieb.md`)
- Zeugnis-Abgleich und Dokumentenverwaltung (Block E) — `app/Services/Import/ZeugnisAbgleich.php`, `DokumenteController`, `NotenImportController`
- Rechenkern und Dashboards mit Ampel/Heatmap/Verlauf (Block B) — `app/Services/Auswertung/Auswertung.php`, `app/Services/Uebersicht.php`
- Tägliche Datensicherung (Block F) — `notenportal:sicherung`, Admin → Betrieb
- Lehrberufs-/Modullisten aus externer Quelle: Entscheid gegen automatischen Katalog-Import, Begründung in `docs/audit-backlog.md`
- Kleinere Befunde aus dem Audit: siehe `docs/audit-backlog.md` (nicht hier dupliziert)

## Offene Ideen

- **Restliches Firmenspezifisches**: Logo-Upload, Tabelle `standorte`, vereinzelte Notenfarb-Hardcodes noch nicht auf `NotenSkala` migriert, Open-Source-Paket (LICENSE, CONTRIBUTING, SECURITY.md fehlen, nur README vorhanden).
- **E-Mail/Benachrichtigungen**: in Arbeit (Session 11.09.).
- **Prüfungsagenda mit iCal**: in Arbeit (Session 11.09.).
- **Sprachkonsistenz Englisch**: in Arbeit (Session 11.09.).
- **GUI-Überarbeitung/Themes**: in Arbeit (Session 11.09.).
- **Feedback-System**: in Arbeit (Session 11.09.).
- **Go-Live-Härtung** (HTTPS, APP_DEBUG, Least Privilege, Offsite-Sicherung): in Arbeit (Session 11.09.).

## Offene Produktentscheide

1. Lizenz für ein Open-Source-Release: MIT oder AGPL-3.0; Veröffentlichung mit bereinigter Git-Historie ja/nein.

## Risiken

- Solange HTTPS fehlt, gehen Passwörter im Labornetz im Klartext durch — behoben mit der Go-Live-Härtung.
