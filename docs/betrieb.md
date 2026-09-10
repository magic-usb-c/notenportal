# Betrieb Notenportal

Server-Konfiguration und alle Änderungen ausserhalb des Repos (Datenbanken, /etc/apache2, /etc/php, ~/db-backups). Diese Datei ist die einzige Stelle, an der solche Änderungen nachvollziehbar sind.

## Server

| | |
|---|---|
| Host | srv-lab-dva-001, 172.26.14.101/24 (ens160), ICT-LAB, geschlossenes Netz |
| OS | Ubuntu 24.04 LTS |
| Webserver | Apache 2.4, mpm_prefork + mod_php 8.3, vhost `notenportal.conf`, nur HTTP :80 |
| PHP | 8.3 (Distro-Pakete) |
| DB | MariaDB 10.11, Datenbanken `notenportal` (Betrieb), `notenportal_test` und `notenportal_b_test` (PHPUnit, werden bei jedem Lauf neu aufgebaut; die zweite für parallele Läufe: `DB_DATABASE=notenportal_b_test php artisan test`) |
| DB-User | `np_web`: ALL auf `notenportal` und `notenportal_test` |
| Firewall | ufw: SSH, 80, 443 |
| Backups | `~/db-backups/` (manuell, noch kein Cronjob) |

## Dateirechte

Apache liest als www-data über Gruppenrechte (Root-Dir 2750, others kein Zugriff). Alle Dateien brauchen Gruppe www-data + g+r, Verzeichnisse zusätzlich g+x; Setgid auf Verzeichnissen, damit neue Dateien die Gruppe erben. Symptom bei kaputten Rechten: HTTP 500 ohne Laravel-Log-Eintrag. Fix ohne sudo (ubuntu ist Mitglied von www-data):

```bash
find . -path './.git' -prune -o -type d -user ubuntu -exec chmod u+rwx,g+rxs {} \;
find . -path './.git' -prune -o -type f -user ubuntu -exec chmod u+rw,g+r {} +
find . -path './.git' -prune -o -user ubuntu ! -group www-data -exec chgrp www-data {} +
```

## Vor dem Produktivgang (in der Pilotphase bewusst offen)

Pilot im geschlossenen ICT-LAB-Netz ohne HTTPS und Härtung. Vor einem Betrieb ausserhalb des Labs:

- HTTPS mit Zertifikat (interne CA oder öffentlich), HTTP → HTTPS-Redirect, HSTS, `SESSION_SECURE_COOKIE=true`
- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, korrekte `APP_URL`, `LOG_CHANNEL=daily`
- opcache explizit aktivieren, `config:cache`/`route:cache`/`view:cache` im Deploy
- Backups per Cron (`mysqldump --single-transaction`, Grössen-/Inhaltsprüfung, 14 Tages- + 8 Wochenstände), wöchentlicher Restore-Test
- Least Privilege: `np_web` nur DML, separater `np_migrate` mit DDL für Migrationen
- ufw auf die berechtigten Netze einschränken
- php-fpm + mpm_event statt mod_php + prefork
- Security-Header (CSP, X-Frame-Options, Referrer-Policy)

## Änderungsprotokoll ausserhalb des Repos

| Datum | Änderung |
|---|---|
| 09.09.2026 | Dump `notenportal-20260909-1617.sql` ist defekt (875 Byte, nur Header). Gültige Stände: `notenportal-manuell.sql`, `notenportal-vor-claude.sql` (10.09.). |
| 10.09.2026 | Dump `~/db-backups/notenportal-20260910-0928-stufe0.sql` (48 KB) vor Beginn Stufe 0. |
| 10.09.2026 | Datenbank `notenportal_b_test` angelegt (utf8mb4_unicode_ci), `GRANT ALL ON notenportal_b_test.* TO np_web@localhost`. |
| 10.09.2026 | `notenportal.migrations`: Einträge `0001_01_01_00000{0,1,2}` (users/cache/jobs) gelöscht. Die Tabellen existierten nicht mehr, die Migrationsdateien sind entfernt. |
