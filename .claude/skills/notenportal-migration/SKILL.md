---
name: notenportal-migration
description: Neue Datenbank-Migration sicher auf Prod bringen – Dump und Git-Tag, zuerst gegen notenportal_probe (Prod-Kopie), dann sofort gegen notenportal, Eintrag in docs/betrieb.md. Laden vor JEDEM `php artisan migrate` ausserhalb der Tests.
---

# Migration auf Prod bringen

Die Arbeitskopie /var/www/notenportal IST Prod. Neuer Code mit neuen Spalten bricht Prod, bis die Migration dort gelaufen ist.
Führe die Schritte **genau in dieser Reihenfolge** aus. Bricht ein Schritt ab: STOPP, nicht weiter, Fehler lösen.

## Voraussetzung
- Migration liegt in `database/migrations/` und `php artisan test` ist grün.
- MariaDB-DDL ist NICHT transaktional: halb gelaufene Migration = Restore aus Dump. Deshalb:
  - Constraints/Indizes nur mit `IF EXISTS` droppen oder Namen per `information_schema` suchen. Nie Namen annehmen (Prod heisst teils `CONSTRAINT_1…4`).
  - CHECK-Constraints und Composite-FKs per `DB::statement`.
  - `down()` implementieren.

## Schritt 1 – Kurzname festlegen
Wähle einen Kurznamen ohne Leerzeichen, z. B. `vor-mail`. Unten `KURZ` ersetzen.

## Schritt 2 – Dump + Tag
```bash
cd /var/www/notenportal
STAND=$(date +%Y%m%d-%H%M)
sudo mysqldump --single-transaction notenportal > ~/db-backups/notenportal-$STAND-KURZ.sql
ls -la ~/db-backups/notenportal-$STAND-KURZ.sql        # muss > 40 KB sein, sonst STOPP
git tag -f KURZ && git push -f origin KURZ
```

## Schritt 3 – Probe-DB neu aus dem Dump
```bash
sudo mysql -e "DROP DATABASE IF EXISTS notenportal_probe; CREATE DATABASE notenportal_probe CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON notenportal_probe.* TO np_web@localhost;"
sudo mysql notenportal_probe < ~/db-backups/notenportal-$STAND-KURZ.sql
DB_DATABASE=notenportal_probe php artisan migrate --force
```
Fehler? → Migration korrigieren, Schritt 3 wiederholen. Prod ist unberührt.

Optional Rückweg testen: `DB_DATABASE=notenportal_probe php artisan migrate:rollback --step=1 --force && DB_DATABASE=notenportal_probe php artisan migrate --force`

## Schritt 4 – Sofort Prod
```bash
php artisan migrate --force
php artisan optimize:clear
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1/login     # muss 200 sein
```
Kein 200 → `tail -50 storage/logs/laravel*.log`, sofort beheben.

## Schritt 5 – Zweite Instanz (falls vorhanden)
Die zweite Instanz bekommt die Migration mit dem Skill `notenportal-blockabschluss` (install.sh migriert). Nichts tun.

## Schritt 6 – Protokoll
In `docs/betrieb.md`, Tabelle «Änderungsprotokoll ausserhalb des Repos», eine Zeile anhängen:
```
| TT.MM.JJJJ | Dump `notenportal-STAND-KURZ.sql` + Tag `KURZ`, Migration `<dateiname>` (<was sie tut>) erst auf `notenportal_probe`, dann `notenportal`. |
```

## Notfall: Prod halb migriert
```bash
sudo mysqldump --single-transaction notenportal > ~/db-backups/notenportal-$(date +%Y%m%d-%H%M)-halbmigriert.sql
sudo mysql -e "DROP DATABASE notenportal; CREATE DATABASE notenportal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql notenportal < ~/db-backups/notenportal-$STAND-KURZ.sql
```
Danach in betrieb.md protokollieren.
