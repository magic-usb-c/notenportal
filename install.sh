#!/usr/bin/env bash
# Notenportal – Installation auf Ubuntu 24.04 LTS oder neuer (erprobt auf 24.04; PHP kommt aus
# der Distribution und muss mindestens 8.3 sein). Im geklonten Repo ausführen:
#   sudo ./install.sh                                   Port 80, Datenbank «notenportal»
#   sudo ./install.sh --port 8082 --db notenportal_i2   zweite Instanz neben einer bestehenden
# Optionen: --host <IP|Name>  --ohne-firewall  --neues-admin-passwort
# Erneut ausführen = Update (Pakete, Abhängigkeiten, Build, Migrationen); .env, Webserver-Konfiguration und Konten bleiben.
set -euo pipefail

VERZ="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
NAME="$(basename "$VERZ")"
PORT=80
DB="notenportal"
HOST="$(hostname -I 2>/dev/null | awk '{print $1}')"
FIREWALL=1
ADMIN_OPTION=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --port) PORT="$2"; shift 2 ;;
        --db) DB="$2"; shift 2 ;;
        --host) HOST="$2"; shift 2 ;;
        --ohne-firewall) FIREWALL=0; shift ;;
        --neues-admin-passwort) ADMIN_OPTION="--zuruecksetzen"; shift ;;
        -h|--help) sed -n '2,7p' "$0"; exit 0 ;;
        *) echo "Unbekannte Option: $1 (siehe --help)"; exit 1 ;;
    esac
done

[[ $EUID -eq 0 ]] || { echo "Bitte mit sudo starten: sudo ./install.sh"; exit 1; }
[[ "$DB" =~ ^[A-Za-z0-9_]{1,48}$ ]] || { echo "Ungültiger Datenbankname: $DB"; exit 1; }
[[ "$PORT" =~ ^[0-9]{2,5}$ ]] || { echo "Ungültiger Port: $PORT"; exit 1; }
[[ -z "$HOST" || "$HOST" =~ ^[A-Za-z0-9.-]{1,253}$ ]] || { echo "Ungültiger Host: $HOST"; exit 1; }
[[ -n "$HOST" ]] || HOST="localhost"

BESITZER="$(stat -c %U "$VERZ")"
[[ "$BESITZER" != "root" ]] || BESITZER="${SUDO_USER:-root}"
URL="http://${HOST}$([[ "$PORT" == "80" ]] || echo ":$PORT")"

schritt() { printf '\n\033[1;34m▸ %s\033[0m\n' "$*"; }
als() { sudo -u "$BESITZER" -H bash -c "cd '$VERZ' && $*"; }

schritt "Pakete"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
# Unversionierte Metapakete statt php8.3-*: sie zeigen auf die PHP-Version der jeweiligen
# Ubuntu-Ausgabe (24.04 → 8.3, 26.04 → 8.5). Eine feste Nummer hier würde das Portal an genau
# eine Ubuntu-Ausgabe nageln – auf der nächsten gäbe es die Pakete schlicht nicht.
apt-get install -y -qq apache2 mariadb-server libapache2-mod-php php-cli php-mysql php-mbstring \
    php-xml php-curl php-zip php-intl php-gd php-bcmath unzip git curl openssl composer >/dev/null
if ! php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' 2>/dev/null; then
    echo "PHP $(php -r 'echo PHP_VERSION;' 2>/dev/null || echo '?') ist zu alt – das Notenportal braucht mindestens 8.3."
    exit 1
fi
if ! command -v node >/dev/null || (( $(node -p 'process.versions.node.split(".")[0]') < 20 )); then
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash - >/dev/null
    apt-get install -y -qq nodejs >/dev/null
fi
systemctl enable --now mariadb apache2 >/dev/null 2>&1

schritt "Datenbank"
if [[ -f "$VERZ/.env" ]]; then
    lies() { { grep -E "^$1=" "$VERZ/.env" || true; } | head -1 | cut -d= -f2- | tr -d '"'; }
    DB="$(lies DB_DATABASE)"
    [[ -n "$DB" ]] || { echo "DB_DATABASE fehlt in $VERZ/.env"; exit 1; }
    echo "  .env vorhanden – Datenbank $DB bleibt unverändert"
    mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
else
    # Least Privilege: Web-Benutzer nur Datenrechte, Migrations-Benutzer mit DDL (php artisan notenportal:migrate)
    DB_BENUTZER="${DB}_web"
    DB_PASSWORT="$(openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c 32)"
    DB_MIGRATION="${DB}_migrate"
    DB_MIGRATION_PASSWORT="$(openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c 32)"
    mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_BENUTZER'@'localhost' IDENTIFIED BY '$DB_PASSWORT';
ALTER USER '$DB_BENUTZER'@'localhost' IDENTIFIED BY '$DB_PASSWORT';
GRANT SELECT, INSERT, UPDATE, DELETE, LOCK TABLES, CREATE TEMPORARY TABLES, SHOW VIEW, EXECUTE ON \`$DB\`.* TO '$DB_BENUTZER'@'localhost';
CREATE USER IF NOT EXISTS '$DB_MIGRATION'@'localhost' IDENTIFIED BY '$DB_MIGRATION_PASSWORT';
ALTER USER '$DB_MIGRATION'@'localhost' IDENTIFIED BY '$DB_MIGRATION_PASSWORT';
GRANT ALL PRIVILEGES ON \`$DB\`.* TO '$DB_MIGRATION'@'localhost';
FLUSH PRIVILEGES;
SQL
    cat > "$VERZ/.env" <<ENV
APP_NAME=Notenportal
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=$URL
APP_TIMEZONE=Europe/Zurich
APP_LOCALE=de
APP_FALLBACK_LOCALE=de
APP_FAKER_LOCALE=de_CH
BCRYPT_ROUNDS=12
LOG_CHANNEL=daily
LOG_LEVEL=warning
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=$DB
DB_USERNAME=$DB_BENUTZER
DB_PASSWORD=$DB_PASSWORT
DB_MIGRATE_USERNAME=$DB_MIGRATION
DB_MIGRATE_PASSWORD=$DB_MIGRATION_PASSWORT
SESSION_DRIVER=file
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
MAIL_MAILER=log
ENV
    chown "$BESITZER":www-data "$VERZ/.env"
    chmod 640 "$VERZ/.env"
    echo "  Datenbank $DB und Benutzer $DB_BENUTZER angelegt"
fi

schritt "Abhängigkeiten und Oberfläche"
als "composer install --no-dev --optimize-autoloader --no-interaction --quiet"
als "npm ci --no-audit --no-fund --loglevel=error && npm run build --silent"

schritt "Anwendung"
grep -q '^APP_KEY=base64' "$VERZ/.env" || als "php artisan key:generate --force --quiet"
als "php artisan config:clear --quiet && php artisan notenportal:migrate"
als "php artisan db:seed --force --quiet"
ZUGANG="$(als "php artisan notenportal:erstes-admin-konto $ADMIN_OPTION")"
als "php artisan optimize --quiet"

schritt "Webserver (Port $PORT)"
a2enmod -q rewrite >/dev/null
# Das Apache-Modul heisst je nach Ubuntu-Ausgabe php8.3, php8.5 …: den vorhandenen Namen nehmen.
for MODUL in /etc/apache2/mods-available/php*.load; do
    [[ -e "$MODUL" ]] && a2enmod -q "$(basename "$MODUL" .load)" >/dev/null 2>&1 || true
done
# Uploads: 2 MB Standard reichen für eine Modulkatalog-Ernte oder eine lange Notenliste nicht.
for CONFD in /etc/php/*/apache2/conf.d; do
    if [[ -d "$CONFD" ]]; then
        printf 'upload_max_filesize = 16M\npost_max_size = 16M\n' > "$CONFD/99-notenportal.ini"
    fi
done
grep -qE "^\s*Listen\s+$PORT\s*$" /etc/apache2/ports.conf || echo "Listen $PORT" >> /etc/apache2/ports.conf
VHOST="/etc/apache2/sites-available/$NAME.conf"
if [[ ! -f "$VHOST" ]]; then
    cat > "$VHOST" <<CONF
<VirtualHost *:$PORT>
    ServerName $HOST
    DocumentRoot $VERZ/public
    <Directory $VERZ/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/$NAME-error.log
    CustomLog \${APACHE_LOG_DIR}/$NAME-access.log combined
</VirtualHost>
CONF
    echo "  $VHOST angelegt"
fi
a2ensite -q "$NAME" >/dev/null
if [[ "$PORT" == "80" && -e /etc/apache2/sites-enabled/000-default.conf ]]; then a2dissite -q 000-default >/dev/null; fi
apachectl -t 2>/dev/null
systemctl reload apache2 2>/dev/null || systemctl restart apache2
if (( FIREWALL )) && command -v ufw >/dev/null && ufw status | grep -q "Status: active"; then
    ufw allow "$PORT/tcp" >/dev/null
fi

schritt "Zeitplan (tägliche Sicherung)"
CRON="/etc/cron.d/${NAME//[^A-Za-z0-9_-]/_}"
echo "* * * * * www-data cd $VERZ && $(command -v php) artisan schedule:run >> /dev/null 2>&1" > "$CRON"
chmod 644 "$CRON"
echo "  $CRON"

schritt "Dateirechte"
chgrp -R www-data "$VERZ"
find "$VERZ" \( -path "$VERZ/.git" -o -path "$VERZ/node_modules" -o -path "$VERZ/vendor" \) -prune -o -type d -exec chmod u+rwx,g+rxs,o-rwx {} +
find "$VERZ" \( -path "$VERZ/.git" -o -path "$VERZ/node_modules" -o -path "$VERZ/vendor" \) -prune -o -type f -exec chmod u+rw,g+r,o-rwx {} +
chmod -R g+rX "$VERZ/vendor"
chmod -R g+w "$VERZ/storage" "$VERZ/bootstrap/cache"
if ! sudo -u www-data test -r "$VERZ/public/index.php"; then
    echo "  Achtung: www-data kann $VERZ nicht lesen – übergeordnete Verzeichnisse prüfen (z. B. chmod o+x)."
fi

STATUS="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/login" -H "Host: $HOST" || true)"
printf '\n\033[1;32m✔ Notenportal läuft: %s\033[0m  (HTTP %s)\n' "$URL" "$STATUS"
echo "$ZUGANG" | sed 's/^/  /'
if [[ "$ZUGANG" == *Startpasswort* ]]; then
    echo "  Nach dem ersten Anmelden neues Passwort setzen – danach führt die Einrichtung durch alle Schritte."
fi
