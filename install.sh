#!/usr/bin/env bash
# Notenportal – Installation auf Ubuntu 24.04. Im geklonten Repo ausführen:
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
apt-get install -y -qq apache2 mariadb-server libapache2-mod-php8.3 php8.3-cli php8.3-mysql php8.3-mbstring \
    php8.3-xml php8.3-curl php8.3-zip php8.3-intl php8.3-gd php8.3-bcmath unzip git curl openssl composer >/dev/null
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
    DB_BENUTZER="${DB}_web"
    DB_PASSWORT="$(openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c 32)"
    mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_BENUTZER'@'localhost' IDENTIFIED BY '$DB_PASSWORT';
ALTER USER '$DB_BENUTZER'@'localhost' IDENTIFIED BY '$DB_PASSWORT';
GRANT ALL PRIVILEGES ON \`$DB\`.* TO '$DB_BENUTZER'@'localhost';
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
SESSION_DRIVER=file
SESSION_LIFETIME=120
CACHE_STORE=file
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
als "php artisan migrate --force"
als "php artisan db:seed --force --quiet"
ZUGANG="$(als "php artisan notenportal:erstes-admin-konto $ADMIN_OPTION")"
als "php artisan optimize --quiet"

schritt "Webserver (Port $PORT)"
a2enmod -q rewrite >/dev/null
a2enmod -q php8.3 >/dev/null 2>&1 || true
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
