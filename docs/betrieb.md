# Betrieb Notenportal

Server-Konfiguration und alle Änderungen ausserhalb des Repos (Datenbanken, /etc/apache2, /etc/php, ~/db-backups). Diese Datei ist die einzige Stelle, an der solche Änderungen nachvollziehbar sind.

## Server

| | |
|---|---|
| Host | srv-lab-dva-001, 172.26.14.101/24 (ens160), ICT-LAB, geschlossenes Netz |
| OS | Ubuntu 24.04 LTS |
| Webserver | Apache 2.4, mpm_prefork + mod_php 8.3, vhosts `notenportal.conf` (HTTP :80) und `notenportal-ssl.conf` (HTTPS :443, Zertifikat der eigenen Lab-CA, siehe «HTTPS») |
| PHP | 8.3 (Distro-Pakete) |
| DB | MariaDB 10.11, Datenbanken `notenportal` (Betrieb), `notenportal_test` und `notenportal_b_test` (PHPUnit, werden bei jedem Lauf neu aufgebaut; die zweite für parallele Läufe: `DB_DATABASE=notenportal_b_test php artisan test`) |
| DB-User | `np_web` (Web, .env `DB_USERNAME`): auf `notenportal` nur SELECT/INSERT/UPDATE/DELETE/LOCK TABLES/CREATE TEMPORARY TABLES/SHOW VIEW/EXECUTE; ALL auf den Test-, Demo- und Probe-Datenbanken. `np_migrate` (.env `DB_MIGRATE_USERNAME/-PASSWORD`): ALL auf `notenportal` und `notenportal_probe`, nur für `php artisan notenportal:migrate` |
| Firewall | ufw: SSH, 80, 443. Port 8082 (zweite Instanz) bewusst nicht freigegeben – Apache lauscht auf `*:8082`, von aussen durch ufw gesperrt, nur lokal (`http://127.0.0.1:8082`) erreichbar |
| Backups | täglich 02:30 `storage/app/private/sicherungen/` (ZIP, 14 Stände, Seite «Betrieb»); manuelle Dumps vor Eingriffen in `~/db-backups/` |

## Dateirechte

Apache liest als www-data über Gruppenrechte (Root-Dir 2750, others kein Zugriff). Alle Dateien brauchen Gruppe www-data + g+r, Verzeichnisse zusätzlich g+x; Setgid auf Verzeichnissen, damit neue Dateien die Gruppe erben. Symptom bei kaputten Rechten: HTTP 500 ohne Laravel-Log-Eintrag. Fix ohne sudo (ubuntu ist Mitglied von www-data):

```bash
find . -path './.git' -prune -o -type d -user ubuntu -exec chmod u+rwx,g+rxs {} \;
find . -path './.git' -prune -o -type f -user ubuntu -exec chmod u+rw,g+r {} +
find . -path './.git' -prune -o -user ubuntu ! -group www-data -exec chgrp www-data {} +
```

## Vor dem Produktivgang (in der Pilotphase bewusst offen)

Pilot im geschlossenen ICT-LAB-Netz ohne HTTPS und Härtung. Vor einem Betrieb ausserhalb des Labs:

- HTTPS: läuft seit 11.09. parallel zu HTTP mit eigener Lab-CA (siehe «HTTPS»). Offen: HTTP → HTTPS-Redirect, HSTS, `SESSION_SECURE_COOKIE=true`, sobald die Geräte der Lernenden der CA vertrauen oder ein Zertifikat der Hamilton-CA vorliegt
- `.env`: `APP_DEBUG=false`, `LOG_CHANNEL=daily`, `APP_URL` erledigt (11.09.); `APP_ENV=production` beim Go-Live
- opcache: aktiv (11.09. geprüft, `10-opcache.ini` in mod_php geladen, Distro-Standard `opcache.enable=1`); `validate_timestamps` bleibt an, weil Prod aus dem Working Copy läuft. `config:cache`/`route:cache`/`view:cache` über `php artisan optimize` (Go-Live Punkt 6)
- Kopie ausser Haus: Funktion vorhanden (11.09., Seite Betrieb → «Kopie ausser Haus», rsync in Ordner oder per SSH); Ziel muss beim Go-Live eingetragen werden. Offen: Wochenstände, wöchentlicher Restore-Test
- Least Privilege: erledigt (11.09.) – `np_web` nur DML, `np_migrate` mit DDL; Installer legt für neue Instanzen `<db>_web` (DML) und `<db>_migrate` an
- ufw auf die berechtigten Netze einschränken
- php-fpm + mpm_event statt mod_php + prefork
- Security-Header: X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy erledigt (11.09., Middleware `SicherheitsHeader`); Apache `ServerTokens Prod`/`ServerSignature Off`. Minimale CSP (`base-uri`, `object-src 'none'`, `frame-ancestors`, `form-action`) ebenfalls gesetzt; offen: `script-src`/`style-src` (Alpine braucht `unsafe-eval`, Views haben Inline-Skripte – dafür Alpine-CSP-Build und Nonces)

## Zugang ICT-LAB (Pilot)

- URL für die Lernenden: `http://172.26.14.101` (vhost `notenportal.conf`, ServerName = Lab-IP, Port 80, ufw offen).
- Nur aus dem geschlossenen Lab-Netz erreichbar; kein DNS-Name, kein HTTPS in der Pilotphase.

## HTTPS (Lab-CA, kostenlos, ohne externe Stelle)

Im geschlossenen Lab gibt es keinen öffentlichen DNS-Namen, darum kein Let's Encrypt. Stattdessen eine eigene Zertifizierungsstelle auf der VM:

- Dateien: `/etc/ssl/notenportal/` – `ca.crt` (öffentlich, verteilen), `ca.key` (geheim, 600), `server.crt`/`server.key` (Apache).
- Browser vertrauen der Seite erst, wenn `ca.crt` importiert ist: Windows `certmgr.msc` → «Vertrauenswürdige Stammzertifizierungsstellen» → Importieren; macOS Schlüsselbundverwaltung → System → «Immer vertrauen»; Firefox Einstellungen → Zertifikate → Zertifizierungsstellen → Importieren. Ohne Import: Warnung «Nicht sicher», Verbindung trotzdem verschlüsselt.
- Serverzertifikat erneuern (vor 14.12.2028):
```bash
cd /etc/ssl/notenportal
sudo openssl req -newkey rsa:2048 -nodes -keyout server.key -out server.csr -subj "/CN=172.26.14.101"
sudo openssl x509 -req -in server.csr -CA ca.crt -CAkey ca.key -CAcreateserial -out server.crt -days 825 -extfile ext.cnf
sudo systemctl reload apache2
```
- Umstellung auf HTTPS-only (wenn alle Geräte der CA vertrauen): in `notenportal.conf` `Redirect permanent / https://172.26.14.101/`, `.env` `APP_URL=https://172.26.14.101` und `SESSION_SECURE_COOKIE=true`, dann `php artisan config:clear`.
- Zertifikat der Hamilton-CA statt Lab-CA: nur `SSLCertificateFile`/`SSLCertificateKeyFile` in `notenportal-ssl.conf` ersetzen.

## Go-Live-Checkliste Testbetrieb (30.09.2026)

1. Dump: `sudo mysqldump --single-transaction notenportal > ~/db-backups/notenportal-$(date +%Y%m%d-%H%M)-vor-pilot.sql`, Grösse prüfen.
2. `git pull` auf `main`, `npm ci && npm run build`, `php artisan notenportal:migrate` (Migrations-Benutzer). `composer install --no-dev` erst, wenn auf der VM nicht mehr getestet wird (entfernt PHPUnit).
3. `.env`: `APP_ENV=production` (APP_DEBUG=false, APP_URL, LOG_CHANNEL=daily sind gesetzt). Mail-Umleitung auf Seite Betrieb leeren (sonst gehen alle Mails an die Testadresse), Testmail an eine echte Adresse senden, Versandprotokoll prüfen.
4. Vorschau `php artisan notenportal:pilot-vorbereiten`, dann `php artisan notenportal:pilot-vorbereiten --ausfuehren` (Konten bleiben, Testnoten/Kommentare/Belegungen/Feedback weg, alle Konten müssen ihr Passwort neu setzen). Stand 11.09.: Vorschau auf Prod = 75 Noten, 14 Kommentare, 17 Belegungen, 9 Konten; alle Noten auf `@example.local`-Konten (Seed Feb./Jun. 2026), davon 19 bei `david.vonallmen` (5.–18.02.) – falls die behalten werden sollen, vorher über Noten → Export sichern. Generalprobe Punkte 1–7 auf i2 am 11.09. ohne Fehler (i2 läuft bereits mit `APP_ENV=production`).
5. Konten der Lernenden von Peter Scherrer prüfen/anlegen (Verwaltung → Lernende), Betreuungen und Tracks kontrollieren. Stand 11.09.: 4 aktive Betreuungen (Nando Lolli, Jan Mall, David von Allmen, «hamilton lernender» – sieht nach Testkonto aus, prüfen), alle mit Track; Betreuung Nils Dosch endete 31.01.2025. Versandprotokoll: 8 gesendet, 1 übersprungen, keine Fehler; Queue ohne offene/fehlgeschlagene Jobs.
6. `php artisan optimize` (Config-, Routen-, View-Cache). Tests laufen dank eigener Cache-Pfade trotzdem nur gegen `*_test`.
7. Dateirechte-Befehle (siehe oben) ausführen, `/login` über die Lab-IP aufrufen, mit einem Lernenden-Konto Note erfassen und Feedback senden.
8. Seite Betrieb: Ziel für «Kopie ausser Haus» eintragen, «Verbindung testen», «Jetzt kopieren»; am Folgetag prüfen, dass die Nachtsicherung kopiert wurde.
9. Alte deutsche Lesezeichen (`/noten`, `/pruefungen` …) leiten automatisch weiter – nichts zu tun.
10. Code-Freeze ab 25.09.: danach nur noch Fehlerbehebungen. Sprachumschalter bleibt ausgeblendet (`sprachwahl_aktiv` aus), Englisch-Umbenennungen von Klassen und DB erst nach dem Go-Live (`docs/i18n-plan.md`).
11. Empfehlung (Entscheid David): Prod aus einer eigenen Arbeitskopie betreiben wie i2 (`git pull --ff-only` + `php artisan optimize`), Entwicklung und Agents nur noch in `/var/www/notenportal` bzw. einem Worktree. Grund: am 11.09. (20:46, 20:55) legten halbfertige Agent-Änderungen in der Live-Kopie kurz alle Seiten lahm. Umzug betrifft Apache-DocumentRoot, `.env`, `storage/` (Dokumente, Sicherungen), Cron/Queue-Worker-Pfade; Probe auf i2 vorhanden (`install.sh`).
12. Vor der Freigabe: `php artisan notenportal:bereitschaft` (rein lesend, App\Support\Bereitschaft) – prüft APP_ENV/APP_DEBUG/APP_URL/session.secure, Mail-Umleitung, Kopie ausser Haus, letzte Sicherung, fehlgeschlagene Jobs/Mails, Kalenderabgleich-Fehler und offene Migrationen; Exit-Code 1, sobald mindestens ein Punkt «fehler» meldet (`--json` für eine maschinenlesbare Ausgabe).

## Änderungsprotokoll ausserhalb des Repos

| Datum | Änderung |
|---|---|
| 09.09.2026 | Dump `notenportal-20260909-1617.sql` ist defekt (875 Byte, nur Header). Gültige Stände: `notenportal-manuell.sql`, `notenportal-vor-claude.sql` (10.09.). |
| 10.09.2026 | Dump `~/db-backups/notenportal-20260910-0928-stufe0.sql` (48 KB) vor Beginn Stufe 0. |
| 10.09.2026 | Datenbank `notenportal_b_test` angelegt (utf8mb4_unicode_ci), `GRANT ALL ON notenportal_b_test.* TO np_web@localhost`. |
| 10.09.2026 | `notenportal.migrations`: Einträge `0001_01_01_00000{0,1,2}` (users/cache/jobs) gelöscht. Die Tabellen existierten nicht mehr, die Migrationsdateien sind entfernt. |
| 10.09.2026 | Dump `notenportal-20260910-0945-vor-migration.sql`, danach Migrationen 2026_09_10_000001–000003 (lernende: bemerkung, klasse_schule, klasse_bms; benutzer: passwort_wechsel_noetig, darstellung; Tabelle einstellungen). `einstellungen.betrieb_name = 'Hamilton AG'` gesetzt. |
| 10.09.2026 | Dump `notenportal-20260910-0947-pre-laravel13.sql` + Git-Tag `pre-laravel13` vor Framework-Upgrade. |
| 10.09.2026 | Datenbank `notenportal_c_test` angelegt, `GRANT ALL` für np_web (dritte Test-DB für parallele Agent-Läufe). |
| 10.09.2026 | Dump `notenportal-20260910-0953-vor-notenwert.sql`, Migration 2026_09_10_000005: `noten.note_wert` decimal(3,1) → decimal(4,2). Im selben Lauf Migration 2026_09_10_000004 (Tabelle `feedback`). |
| 10.09.2026 | Datenbank `notenportal_demo` angelegt, `GRANT ALL` für np_web, migriert und mit `DemoSeeder` befüllt (Grundlage für visuelle Vergleiche). |
| 10.09.2026 | Playwright + Chromium (inkl. System-Libs per `npx playwright install --with-deps`) in `~/tools/visual/` (ausserhalb des Repos) für Screenshot-/Style-/Kontrast-Vergleiche: `capture.mjs`, `compare.mjs`, `config.json`. |
| 10.09.2026 | Zeitzonen-Tabellen in MariaDB geladen (`mysql_tzinfo_to_sql /usr/share/zoneinfo | sudo mysql mysql`). `.env`: `APP_TIMEZONE=Europe/Zurich`, `DB_TIMEZONE=Europe/Zurich` (Sitzungszeitzone der DB-Verbindung). Bestehende Zeitstempel wurden in UTC gespeichert und werden nicht umgerechnet (Testdaten). |
| 10.09.2026 | `.env`: `DB_CONNECTION=mysql` → `mariadb` (gleicher Treiber wie Tests, Sitzungszeitzone greift). |
| 10.09.2026 | Dump `notenportal-20260910-1353-vor-notenlogik.sql` + Git-Tag `pre-notenlogik`. Migration 000006 (Notenlogik) brach auf `notenportal` ab: das Prod-Schema stammt aus dem ursprünglichen SQL-Skript, CHECK-Constraints heissen dort `CONSTRAINT_1…4` statt `chk_noten_*`. Halbzustand gesichert (`…-halbmigriert.sql`), `notenportal` aus dem 13:53-Dump neu aufgebaut (seither nur migrationsbedingte Änderungen), Migration robust gemacht (IF EXISTS, CHECKs per information_schema) und erneut ausgeführt. |
| 10.09.2026 | Datenbank `notenportal_probe` angelegt, `GRANT ALL` für np_web: Kopie des Prod-Dumps, um Migrationen vor dem Prod-Lauf zu testen (`sudo mysql notenportal_probe < dump`, dann `DB_DATABASE=notenportal_probe php artisan migrate --force`). |
| 10.09.2026 | Migration 000007: Einstellungen `frist_inaktiv_tage` (30), `frist_lehrende_tage` (60). |
| 11.09.2026 | `.env` Prod (Sicherung vorher `~/db-backups/env-prod-*.bak`, 600): `MAIL_MAILER=smtp`, `MAIL_SCHEME=smtps`, `MAIL_HOST=smtp.migadu.com`, `MAIL_PORT=465`, `MAIL_USERNAME`/`MAIL_FROM_ADDRESS=notenportal@vonall.men`, `MAIL_PASSWORD` (nur in .env), `QUEUE_CONNECTION=database`, `APP_URL=http://172.26.14.101` (Links in Mails). Testmail an dl.vonallmen@gmail.com angekommen. |
| 11.09.2026 | `/etc/cron.d/notenportal` (Prod): `* * * * * www-data cd /var/www/notenportal && php artisan schedule:run` – gleicher Eintrag wie install.sh. install.sh selbst nicht auf Prod ausgeführt: `composer install --no-dev` würde PHPUnit aus der Arbeitskopie entfernen. Damit laufen Sicherung 02:30, Mail-Queue (jede Minute) und Benachrichtigungen. |
| 11.09.2026 | Datenbank `notenportal_d_test` angelegt, `GRANT ALL` für np_web (vierte Test-DB für parallele Agents). |
| 11.09.2026 | Dump `notenportal-20260910-2349-vor-mail.sql` + Tag `vor-mail`. Migration `2026_09_11_000001_notifications` (jobs, job_batches, failed_jobs, password_reset_tokens, notification_policies/_preferences, mail_log, notification_digest_items, notification_marks). Erster Lauf brach ab (FK `bigint` auf `benutzer.benutzer_id` = `int unsigned`), vier Queue-Tabellen und zwei leere notification-Tabellen blieben; die leeren entfernt, Migration korrigiert (`unsignedInteger`), Probe → Prod erfolgreich. |
| 11.09.2026 | Dump `notenportal-…-vor-cache.sql` + Tag `vor-cache`, Migration `2026_09_11_000002_cache_table` (Probe → Prod), `.env` Prod `CACHE_STORE=database`: File-Cache-Verzeichnisse von www-data (0755) waren für CLI-Prozesse nicht beschreibbar, Einstellungen blieben veraltet. Einstellung `mail_redirect_to = dl.vonallmen@gmail.com` (alle Mails an Gmail bis Go-Live, Seite Betrieb). |
| 11.09.2026 | Dump `notenportal-…-vor-agenda.sql` + Tag `vor-agenda`, Migration `2026_09_11_000003_agenda` (pruefungen: Uhrzeit, Dauer, Art, Hilfsmittel, Stoff, Notizen, Raum, Lehrperson, note_id, Quelle/extern_uid, abgesagt_am; dokumente.pruefung_id + Art «pruefung»; benutzer.kalender_token; Tabellen calendar_feeds, calendar_events) Probe (inkl. Rollback) → Prod. Composer: `sabre/vobject` ^4.5 (iCal lesen/schreiben). Scheduler: `calendar:sync` stündlich (:17). |
| 11.09.2026 | `.env` Prod: `APP_DEBUG=false`, `LOG_CHANNEL=daily`, `LOG_LEVEL=info`. Least Privilege: DB-Benutzer `np_migrate` angelegt (Passwort nur in .env), `np_web` auf `notenportal` auf Datenrechte beschränkt (inkl. Rest-Grant auf `migrations`), Dump als np_web und alle Rollen-Seiten geprüft. Migrationen auf Prod nur noch mit `php artisan notenportal:migrate`. |
| 11.09.2026 | HTTPS: eigene CA `/etc/ssl/notenportal/ca.crt` (10 Jahre, Schlüssel 600), Serverzertifikat für IP 172.26.14.101 + srv-lab-dva-001 (825 Tage, bis 14.12.2028), `a2enmod ssl`, vhost `notenportal-ssl.conf` (TLS 1.2/1.3) aktiviert, Apache reload. HTTP :80 bleibt ohne Umleitung. |
| 11.09.2026 | Dump `notenportal-20260911-0026-vor-invite-tokens.sql` + Tag `vor-invite-tokens`, Migration `2026_09_11_000004_password_invite_tokens` (eigene Tabelle für Einladungs-Links, 7 Tage; «Passwort vergessen» bleibt 60 Minuten in password_reset_tokens) erst auf `notenportal_probe` inkl. Rollback, dann `notenportal`. |
| 11.09.2026 | Dump `notenportal-20260911-0343-vor-feedback-kontrast.sql` + Tag `vor-feedback-kontrast`, Migrationen `2026_09_11_000006_feedback_kontext_und_screenshot` (feedback: rolle, browser, js_fehler, screenshot_*; Kategorien fehler/idee/frage/lob) und `2026_09_11_000007_benutzer_kontrast` (benutzer.kontrast) gezielt per `notenportal:migrate --path=…` erst auf `notenportal_probe` (inkl. Rollback-Test), dann `notenportal`. 000005 (Kalender-Feed) bewusst noch offen. |
| 11.09.2026 | Nachtsicherung 02:30 scheiterte («Permission denied» auf `storage/app/private/lernende`): Ordner, die CLI-Prozesse als ubuntu anlegten, hatten Laravels Standard 0700. Rechte korrigiert (Befehle «Dateirechte», zusätzlich g+w in `storage`), `config/filesystems.php` Disk `local` mit `permissions` 0770/0660, Sicherung als www-data erfolgreich (`notenportal-20260911-054515.zip`). |
| 11.09.2026 | Dump `notenportal-20260911-0347-vor-feed-import.sql` + Tag `vor-feed-import`, Migration `2026_09_11_000005_calendar_feed_import_exams` (calendar_feeds.import_exams) per `--path` erst auf `notenportal_probe` inkl. Rollback, dann `notenportal`. |
| 11.09.2026 | Dump `notenportal-20260911-0719-vor-locale.sql` + Tag `vor-locale`, Migration `2026_09_11_000008_benutzer_locale` (`benutzer.locale` varchar(5) nullable, persönliche Sprache; Einstellungen `sprache_standard`/`sprachwahl_aktiv` ohne Schema) erst auf `notenportal_probe` (inkl. Rollback), dann `notenportal`. Sprachwahl bleibt aus. |
| 11.09.2026 | Generalprobe Go-Live-Checkliste Punkte 1–7 auf der zweiten Instanz (i2): Dump `~/db-backups/notenportal_i2-20260911-0930-vor-generalprobe.sql` (59 KB), `npm ci` + Build, `notenportal:migrate` (nichts offen), `notenportal:pilot-vorbereiten --ausfuehren` (3 Konten → Passwortwechsel), `optimize`, Dateirechte, `/login` 200, keine Fehler im Log. Prod unverändert. |
| 11.09.2026 | Restore-Test: Nachtsicherungs-Fehler 02:30 lag vor Fix 52781f5; Probelauf `notenportal:sicherung` als www-data (wie Cron) ok (`notenportal-20260911-113535.zip`). `datenbank.sql` daraus in `notenportal_probe` eingespielt: 38 Tabellen, Zeilenzahlen identisch mit Prod (ausser `cache_locks`); Dokumente im ZIP 2 = 2 auf Disk. |
| 11.09.2026 | `/etc/apache2/conf-available/security.conf`: `ServerTokens OS` → `Prod`, `ServerSignature On` → `Off` (Kopie `security.conf.vor-servertokens`), `apache2ctl configtest` ok, `systemctl reload apache2`. Header zeigt nur noch `Server: Apache`. Gilt für Prod und i2. |
| 11.09.2026 | Dump `notenportal-20260911-1731-vor-praeferenzen.sql` + Tag `vor-praeferenzen`, Migration `2026_09_11_000009_benutzer_praeferenzen` (`benutzer.praeferenzen` JSON nullable: persönliches Theme, Akzentfarbe, Schriftgrösse, Bewegung) erst auf `notenportal_probe` (inkl. Rollback), dann `notenportal`. |
| 11.09.2026 | Dump `notenportal-20260911-1934-vor-aktivitaeten.sql` + Tag `vor-aktivitaeten` (kurz vor der Migration nochmals `notenportal-20260911-1951-vor-aktivitaeten-b.sql`), Migration `2026_09_12_000010_aktivitaeten` (Aktivitätsprotokoll, neue Tabelle `aktivitaeten`) erst auf `notenportal_probe` (up/rollback/up, zweimal, inkl. `hasTable`-Guard), dann `notenportal`. `optimize:clear` als www-data, `/login` 200. |
| 11.09.2026 | `apt install php8.3-pcov` (Coverage für `php artisan test --coverage`), sofort `phpdismod -s apache2 pcov` + `systemctl reload apache2`: pcov nur in der CLI, Prod/i2 (mod_php) ohne. Rückbau: `apt remove php8.3-pcov`. |
| 12.09.2026 | Dump `notenportal-20260911-2158-vor-feedback-stimmen.sql` + Tag `vor-feedback-stimmen`, Migration `2026_09_12_000011_feedback_stimmen_und_duplikate` (neue Tabelle `feedback_stimmen`, Spalte `feedback.duplikat_von` mit FK, Index `idx_feedback_route_status`) erst auf `notenportal_probe` (up/rollback/up), dann `notenportal`. `/login` 200. |
| 11.09.2026 | `storage/framework/views` an `www-data:www-data` (g+w): von ubuntu kompilierte Views liessen Apache mit «touch(): Utime failed» scheitern (500). Tests kompilieren seither nach `storage/framework/views-testing` (phpunit.xml); `view:cache` nur noch `sudo -u www-data`. |
| 12.09.2026 | Dump `~/db-backups/notenportal-20260912-0236-modulstatus.sql` (98 KB) + Git-Tag `modulstatus`, danach Migration 2026_09_12_000013: `pruefungen.art` enum(`pruefung`,`abgabe`), Default `pruefung`. Auf `notenportal_probe` mit Rollback und erneutem Hochfahren geprüft. |

### 10.09.2026 – Zweite Instanz für den Installationstest
- Zweck: `install.sh` auf dieser VM wie auf einem frischen Server durchspielen, ohne die laufende Instanz anzufassen.
- `/var/www/notenportal-i2` (Klon von `feature/claude-fertigstellung`, Besitzer ubuntu:www-data, 2750), installiert mit `sudo ./install.sh --port 8082 --db notenportal_i2 --ohne-firewall`.
- Vom Installer angelegt: Datenbank `notenportal_i2`, DB-Benutzer `notenportal_i2_web`@localhost (nur diese DB), `/etc/apache2/sites-available/notenportal-i2.conf` (aktiviert), Zeile `Listen 8082` in `/etc/apache2/ports.conf`. Apache wurde nur neu geladen (reload), `notenportal.conf` und die Datenbank `notenportal` blieben unverändert. Port 8082 ist in ufw nicht freigegeben – nur lokal erreichbar.
- Browser-Durchlauf (Playwright, `~/tools/visual/erstinbetrieb.mjs`): Login mit Startpasswort → Passwortwechsel → alle Schritte der Einrichtung → Abschluss → Login als angelegter Berufsbildner und Lernende mit Passwortwechsel. Ohne Fehler.
- Entfernen: `sudo a2dissite notenportal-i2 && sudo sed -i '/^Listen 8082$/d' /etc/apache2/ports.conf && sudo systemctl reload apache2 && sudo mysql -e "DROP DATABASE notenportal_i2; DROP USER 'notenportal_i2_web'@'localhost';" && sudo rm -rf /var/www/notenportal-i2 /etc/apache2/sites-available/notenportal-i2.conf`

### 10.09.2026 – Block E: Dokumente und Importe
- Dump vorher: `~/db-backups/notenportal-20260910-1539-vor-block-e.sql`, Git-Tag `vor-block-e`.
- Composer: `phpoffice/phpspreadsheet` ^5.9 (Excel/ODS/CSV lesen), `smalot/pdfparser` ^2.12 (Text aus PDF).
- Migration `2026_09_10_000008_dokumente` zuerst auf `notenportal_probe`, dann auf `notenportal` angewendet; Login danach 200.
- Dateien liegen unter `storage/app/private/lernende/{id}/dokumente/{jahr}/` (Disk `local`, `serve` aus) – gehören ins Backup zusätzlich zur Datenbank.
- Upload-Grenze im Repo gesetzt (`public/.htaccess`: `upload_max_filesize 12M`, `post_max_size 16M`); die globale php.ini bleibt unverändert.

## Datensicherung (Block F, 10.09.2026)

- Täglich 02:30 über den Laravel-Scheduler: `notenportal:sicherung` erstellt `storage/app/private/sicherungen/notenportal-JJJJMMTT-HHMMSS.zip` (`datenbank.sql` aus `mariadb-dump --single-transaction`, `dateien/lernende/…`, `LIESMICH.txt`), die 14 neusten bleiben. Status, «Jetzt sichern», Herunterladen und Löschen auf Admin → Betrieb.
- Zeitplan: `install.sh` schreibt `/etc/cron.d/<verzeichnisname>` (`* * * * * www-data … php artisan schedule:run`). **Prod** (`/var/www/notenportal`): Eintrag `/etc/cron.d/notenportal` besteht seit 10.09.2026 (Sicherung 02:30, Queue, Tageszusammenfassung, Kalenderabgleich).
- Wiederherstellen (Notfall, im Terminal):

```bash
unzip notenportal-JJJJMMTT-HHMMSS.zip -d /tmp/wiederherstellung
sudo mysqldump --single-transaction notenportal > ~/db-backups/notenportal-vor-wiederherstellung.sql
sudo mysql notenportal < /tmp/wiederherstellung/datenbank.sql
rsync -a /tmp/wiederherstellung/dateien/lernende/ /var/www/notenportal/storage/app/private/lernende/
sudo chgrp -R www-data storage/app/private && sudo chmod -R g+rwX storage/app/private
php artisan optimize:clear
```
