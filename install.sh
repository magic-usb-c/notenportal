#!/usr/bin/env bash
# Notenportal – Installation auf Ubuntu 24.04 LTS oder neuer (erprobt auf 24.04 und 26.04; PHP kommt
# aus der Distribution und muss mindestens 8.3 sein). Im geklonten Repo ausführen:
#   sudo ./install.sh                                   https://notenportal, Datenbank «notenportal»
#   sudo ./install.sh --host notenportal.lab.local      eigener Name im Zertifikat und im vhost
#   sudo ./install.sh --port 8082 --db notenportal_i2   zweite Instanz neben einer bestehenden (nur HTTP)
# Optionen: --host <Name|IP>  --admin-mail <adresse>  --ohne-https  --https-port <n>
#           --ohne-firewall  --neues-admin-passwort
# Erneut ausführen = Update (Pakete, Abhängigkeiten, Build, Migrationen); .env, Webserver-Konfiguration und Konten bleiben.
set -euo pipefail

VERZ="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
NAME="$(basename "$VERZ")"
PORT=80
DB="notenportal"
HOST=""
HTTPS_PORT=443
HTTPS=-1                  # -1 = noch nicht entschieden: an, sobald die Instanz auf Port 80 läuft
FIREWALL=1
ADMIN_OPTION=""
ADMIN_MAIL=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --port) PORT="$2"; shift 2 ;;
        --db) DB="$2"; shift 2 ;;
        --host) HOST="$2"; shift 2 ;;
        --admin-mail) ADMIN_MAIL="$2"; shift 2 ;;
        --https-port) HTTPS_PORT="$2"; HTTPS=1; shift 2 ;;
        --https) HTTPS=1; shift ;;
        --ohne-https) HTTPS=0; shift ;;
        --ohne-firewall) FIREWALL=0; shift ;;
        --neues-admin-passwort) ADMIN_OPTION="--zuruecksetzen"; shift ;;
        -h|--help) sed -n '2,9p' "$0"; exit 0 ;;
        *) echo "Unbekannte Option: $1 (siehe --help)"; exit 1 ;;
    esac
done

[[ $EUID -eq 0 ]] || { echo "Bitte mit sudo starten: sudo ./install.sh"; exit 1; }
[[ "$DB" =~ ^[A-Za-z0-9_]{1,48}$ ]] || { echo "Ungültiger Datenbankname: $DB"; exit 1; }
[[ "$PORT" =~ ^[0-9]{2,5}$ ]] || { echo "Ungültiger Port: $PORT"; exit 1; }
[[ "$HTTPS_PORT" =~ ^[0-9]{2,5}$ ]] || { echo "Ungültiger HTTPS-Port: $HTTPS_PORT"; exit 1; }
[[ -z "$HOST" || "$HOST" =~ ^[A-Za-z0-9.-]{1,253}$ ]] || { echo "Ungültiger Host: $HOST"; exit 1; }
[[ -z "$ADMIN_MAIL" || "$ADMIN_MAIL" =~ ^[^[:space:]@]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$ ]] || { echo "Ungültige Admin-Adresse: $ADMIN_MAIL"; exit 1; }

# Alle IPv4-Adressen der Maschine: sie gehören als Ersatzname ins Zertifikat, damit der Aufruf über
# die IP keine zusätzliche Warnung auslöst, wenn der DNS-Name noch nicht eingetragen ist.
# Nur Adressen echter Netzwerkkarten. Docker- und Bridge-Adressen (172.17.0.1 …) gehören nicht ins
# Zertifikat: sie sind auf jedem Host dieselben und sagen über die Echtheit dieses Servers nichts aus.
mapfile -t ADRESSEN < <(ip -4 -o addr show scope global 2>/dev/null \
    | awk '$2 !~ /^(docker|br-|veth|virbr|lxcbr|lxdbr|tailscale|wg|tun|zt)/ {split($4, a, "/"); print a[1]}' \
    | awk '!gesehen[$0]++' || true)
(( ${#ADRESSEN[@]} )) || mapfile -t ADRESSEN < <(hostname -I 2>/dev/null | tr ' ' '\n' | grep -E '^[0-9.]+$' || true)
# Ohne --host ist der Name «notenportal»: kurz, im Lab über DNS oder hosts-Eintrag erreichbar und
# damit als Adresse für die Lernenden brauchbar. Früher stand hier die IP – die wechselt mit der VM.
[[ -n "$HOST" ]] || HOST="notenportal"
(( HTTPS >= 0 )) || { [[ "$PORT" == "80" ]] && HTTPS=1 || HTTPS=0; }

BESITZER="$(stat -c %U "$VERZ")"
[[ "$BESITZER" != "root" ]] || BESITZER="${SUDO_USER:-root}"
if (( HTTPS )); then
    URL="https://${HOST}$([[ "$HTTPS_PORT" == "443" ]] || echo ":$HTTPS_PORT")"
else
    URL="http://${HOST}$([[ "$PORT" == "80" ]] || echo ":$PORT")"
fi
SSL_VERZ="/etc/ssl/$NAME"

schritt() { printf '\n\033[1;34m▸ %s\033[0m\n' "$*"; }
als() { sudo -u "$BESITZER" -H bash -c "cd '$VERZ' && $*"; }

# Einen Schlüssel in der .env setzen oder ergänzen – ohne die Datei neu zu schreiben, damit
# Passwörter und von Hand gesetzte Werte erhalten bleiben.
setze_env() {
    local schluessel="$1" wert="$2"
    if grep -qE "^${schluessel}=" "$VERZ/.env"; then
        sed -i "s|^${schluessel}=.*|${schluessel}=${wert}|" "$VERZ/.env"
    else
        printf '%s=%s\n' "$schluessel" "$wert" >> "$VERZ/.env"
    fi
}

schritt "Voraussetzungen"
# Alles Prüfbare vor dem ersten apt-get: schlägt eine Voraussetzung erst mitten in der Installation
# fehl, bleibt ein halb eingerichteter Server zurück (Dienste laufen, Portal fehlt).
if [[ -r /etc/os-release ]]; then
    SYSTEM_ID="$(. /etc/os-release && echo "${ID:-}")"
    SYSTEM_VERSION="$(. /etc/os-release && echo "${VERSION_ID:-0}")"
    SYSTEM_NAME="$(. /etc/os-release && echo "${PRETTY_NAME:-unbekannt}")"
else
    SYSTEM_ID=""; SYSTEM_VERSION="0"; SYSTEM_NAME="unbekannt"
fi
case "$SYSTEM_ID" in
    ubuntu)
        if [[ "$(printf '%s\n24.04\n' "$SYSTEM_VERSION" | sort -V | head -1)" != "24.04" ]]; then
            echo "$SYSTEM_NAME ist zu alt – das Notenportal braucht Ubuntu 24.04 LTS oder neuer (wegen PHP 8.3)."
            exit 1
        fi
        ;;
    debian) echo "  $SYSTEM_NAME – nicht erprobt, aber die Pakete stimmen; bei Fehlern Ubuntu 24.04 LTS nehmen." ;;
    *)
        echo "Nicht unterstütztes System: $SYSTEM_NAME. Das Notenportal wird auf Ubuntu 24.04 LTS oder neuer installiert."
        exit 1
        ;;
esac
echo "  $SYSTEM_NAME"

# Apache liest die Anwendung als www-data. Liegt das Verzeichnis unter einem Home-Verzeichnis, fehlt
# dort das Durchgangsrecht (Ubuntu legt Home-Verzeichnisse als 750 an) – Apache antwortete mit 403,
# ohne dass die Ursache im Portal zu sehen wäre. Das muss vor der Installation geklärt sein.
PFAD_PRUEF="$VERZ"
while [[ "$PFAD_PRUEF" != "/" ]]; do
    if ! sudo -u www-data test -x "$PFAD_PRUEF"; then
        echo "Der Webserver-Benutzer www-data kann $PFAD_PRUEF nicht betreten – Apache würde 403 liefern."
        echo "Verschiebe das Portal an eine Stelle ausserhalb der Home-Verzeichnisse und starte dort erneut:"
        echo "  sudo mv \"$VERZ\" /var/www/notenportal && cd /var/www/notenportal && sudo ./install.sh"
        exit 1
    fi
    PFAD_PRUEF="$(dirname "$PFAD_PRUEF")"
done

# composer und npm laufen als $BESITZER, nicht als root (sonst gehörten vendor/ und node_modules/
# der Installation root). Wurde das Repo mit «sudo git clone» geholt, gehört alles root und die
# beiden Schritte scheitern mitten im Lauf mit einem unverständlichen Schreibfehler.
if ! sudo -u "$BESITZER" test -w "$VERZ"; then
    echo "$BESITZER darf in $VERZ nicht schreiben – composer und npm würden abbrechen."
    echo "Eigentum übertragen und erneut starten:"
    echo "  sudo chown -R $BESITZER:$BESITZER \"$VERZ\" && sudo ./install.sh"
    exit 1
fi

schritt "Pakete"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
# Unversionierte Metapakete statt php8.3-*: sie zeigen auf die PHP-Version der jeweiligen
# Ubuntu-Ausgabe. Eine feste Nummer hier würde das Portal an genau eine Ubuntu-Ausgabe nageln –
# auf der nächsten gäbe es die Pakete schlicht nicht.
apt-get install -y -qq apache2 mariadb-server libapache2-mod-php php-cli php-mysql php-mbstring \
    php-xml php-curl php-zip php-intl php-gd php-bcmath unzip git curl openssl >/dev/null
if ! php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' 2>/dev/null; then
    echo "PHP $(php -r 'echo PHP_VERSION;' 2>/dev/null || echo '?') ist zu alt – das Notenportal braucht mindestens 8.3."
    exit 1
fi
# Composer liegt in Ubuntu im Bestandteil «universe». Ist der auf dem Abbild nicht aktiviert, würde
# ein gemeinsamer apt-Aufruf die ganze Installation abbrechen – darum getrennt, mit dem offiziellen
# Installer als Rückfall (Prüfsumme gegen die veröffentlichte Signatur).
if ! command -v composer >/dev/null; then
    apt-get install -y -qq composer >/dev/null 2>&1 || true
fi
if ! command -v composer >/dev/null; then
    echo "  composer fehlt im System – hole ihn von getcomposer.org"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    ERWARTET="$(curl -fsSL https://composer.github.io/installer.sig)"
    TATSAECHLICH="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
    if [[ "$ERWARTET" != "$TATSAECHLICH" ]]; then
        rm -f /tmp/composer-setup.php
        echo "Die Prüfsumme des Composer-Installers stimmt nicht – Abbruch."
        exit 1
    fi
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
    rm -f /tmp/composer-setup.php
fi
# Node: erst die Distribution fragen, erst dann NodeSource. Ubuntu 26.04 liefert Node 22 selbst mit;
# NodeSource kennt eine frische Ubuntu-Ausgabe oft noch nicht und bricht dann ab. Die Reihenfolge
# nicht tauschen: das NodeSource-Paket verdrängt das Distributionspaket «npm», nicht umgekehrt.
node_ok() {
    command -v node >/dev/null && command -v npm >/dev/null &&
        (( $(node -p 'process.versions.node.split(".")[0]' 2>/dev/null || echo 0) >= 20 ))
}
if ! node_ok; then
    KANDIDAT="$(apt-cache policy nodejs 2>/dev/null | awk '/Candidate:/{print $2}')"
    KANDIDAT="${KANDIDAT%%.*}"
    if [[ "$KANDIDAT" =~ ^[0-9]+$ ]] && (( KANDIDAT >= 20 )); then
        apt-get install -y -qq nodejs npm >/dev/null
    else
        curl -fsSL https://deb.nodesource.com/setup_22.x | bash - >/dev/null
        apt-get install -y -qq nodejs >/dev/null
    fi
fi
if ! node_ok; then
    echo "Node.js 20 oder neuer liess sich nicht installieren – ohne Node kein Oberflächen-Build."
    echo "Von Hand nachholen und erneut starten: https://github.com/nodesource/distributions"
    exit 1
fi
systemctl enable --now mariadb apache2 >/dev/null 2>&1

schritt "Datenbank"
FRISCH=0
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
    FRISCH=1
fi

schritt "Abhängigkeiten und Oberfläche"
# Auf einer neuen Ubuntu-Ausgabe ist die häufigste Abbruchursache eine PHP-Version, die
# composer.lock noch nicht kennt. Die Meldung von composer allein führt in die Irre.
if ! als "composer install --no-dev --optimize-autoloader --no-interaction --quiet"; then
    echo
    echo "composer install ist fehlgeschlagen. Auf einem frischen System ist meist PHP die Ursache:"
    echo "  installiert ist PHP $(php -r 'echo PHP_VERSION;' 2>/dev/null || echo '?'), verlangt wird mindestens 8.3."
    echo "  Genaue Liste: cd $VERZ && composer check-platform-reqs"
    echo "  Nicht mit --ignore-platform-reqs übergehen – dann fehlen zur Laufzeit Erweiterungen."
    exit 1
fi
als "npm ci --no-audit --no-fund --loglevel=error && npm run build --silent"

schritt "Anwendung"
grep -q '^APP_KEY=base64' "$VERZ/.env" || als "php artisan key:generate --force --quiet"
als "php artisan config:clear --quiet && php artisan notenportal:migrate"
als "php artisan db:seed --force --quiet"
[[ -z "$ADMIN_MAIL" ]] || ADMIN_OPTION="$ADMIN_OPTION --email=$ADMIN_MAIL"
ZUGANG="$(als "php artisan notenportal:erstes-admin-konto $ADMIN_OPTION")"
als "php artisan optimize --quiet"

ALIASE=()
merke_alias() { local n="$1"; [[ -n "$n" && "$n" != "$HOST" ]] || return 0
                local a; for a in "${ALIASE[@]}"; do [[ "$a" != "$n" ]] || return 0; done; ALIASE+=("$n"); }
[[ "$HOST" != *.* ]] || merke_alias "${HOST%%.*}"
merke_alias "$(hostname -s 2>/dev/null || true)"
merke_alias "$(hostname -f 2>/dev/null || true)"
for A in "${ADRESSEN[@]}"; do merke_alias "$A"; done

if (( HTTPS )); then
    schritt "Zertifikat (eigene Lab-Zertifizierungsstelle)"
    # Im geschlossenen Lab gibt es keinen öffentlichen DNS-Namen und darum kein Let's Encrypt.
    # Eine eigene CA auf der Maschine ist der einzige Weg zu einem Zertifikat, dem die Geräte der
    # Lernenden nach einem einmaligen Import wirklich vertrauen.
    mkdir -p "$SSL_VERZ"
    chmod 755 "$SSL_VERZ"
    if [[ ! -s "$SSL_VERZ/ca.key" ]]; then
        openssl req -x509 -newkey rsa:4096 -sha256 -days 3650 -nodes \
            -keyout "$SSL_VERZ/ca.key" -out "$SSL_VERZ/ca.crt" \
            -subj "/C=CH/O=Notenportal Lab/CN=Notenportal Lab-CA" \
            -addext "basicConstraints=critical,CA:TRUE,pathlen:0" \
            -addext "keyUsage=critical,keyCertSign,cRLSign" 2>/dev/null
        chmod 600 "$SSL_VERZ/ca.key"
        chmod 644 "$SSL_VERZ/ca.crt"
        echo "  Lab-CA neu angelegt, gültig 10 Jahre"
    else
        echo "  Lab-CA vorhanden (bleibt – ein Wechsel würde alle importierten Zertifikate entwerten)"
    fi

    # Alle Namen und Adressen, unter denen das Portal aufgerufen wird, gehören ins Zertifikat.
    # Fehlt einer, warnt der Browser genau bei diesem Aufruf – auch wenn die CA importiert ist.
    SAN="DNS:$HOST"
    for A in "${ALIASE[@]}"; do
        if [[ "$A" =~ ^[0-9.]+$ ]]; then SAN="$SAN,IP:$A"; else SAN="$SAN,DNS:$A"; fi
    done
    SAN="$SAN,DNS:localhost,IP:127.0.0.1"

    NEU=0
    [[ -s "$SSL_VERZ/server.crt" && -s "$SSL_VERZ/server.key" ]] || NEU=1
    [[ -f "$SSL_VERZ/san.txt" && "$(cat "$SSL_VERZ/san.txt" 2>/dev/null)" == "$SAN" ]] || NEU=1
    if [[ -s "$SSL_VERZ/server.crt" ]] && \
       ! openssl x509 -in "$SSL_VERZ/server.crt" -checkend 2592000 -noout >/dev/null 2>&1; then
        NEU=1
        echo "  Serverzertifikat läuft in weniger als 30 Tagen ab – wird erneuert"
    fi
    if (( NEU )); then
        printf 'basicConstraints=CA:FALSE\nkeyUsage=critical,digitalSignature,keyEncipherment\nextendedKeyUsage=serverAuth\nsubjectAltName=%s\n' \
            "$SAN" > "$SSL_VERZ/ext.cnf"
        openssl req -newkey rsa:2048 -nodes -keyout "$SSL_VERZ/server.key" \
            -out "$SSL_VERZ/server.csr" -subj "/CN=$HOST" 2>/dev/null
        # 825 Tage: mehr akzeptieren Safari und Chrome bei eigenen Zertifikaten nicht.
        openssl x509 -req -in "$SSL_VERZ/server.csr" -CA "$SSL_VERZ/ca.crt" -CAkey "$SSL_VERZ/ca.key" \
            -CAcreateserial -out "$SSL_VERZ/server.crt" -days 825 -sha256 \
            -extfile "$SSL_VERZ/ext.cnf" 2>/dev/null
        echo "$SAN" > "$SSL_VERZ/san.txt"
        chmod 600 "$SSL_VERZ/server.key"
        chmod 644 "$SSL_VERZ/server.crt"
    fi
    echo "  Zertifikat für $HOST, gültig bis $(openssl x509 -in "$SSL_VERZ/server.crt" -noout -enddate | cut -d= -f2)"
    echo "  Namen im Zertifikat: ${SAN//,/, }"

    # Die CA muss herunterladbar sein, bevor jemand HTTPS vertraut – darum als normale Datei im
    # DocumentRoot und über HTTP erreichbar. Nur ein öffentliches Zertifikat, kein Schlüssel.
    install -m 644 "$SSL_VERZ/ca.crt" "$VERZ/public/lab-ca.crt"

    # Der Server muss seinen eigenen Namen auflösen, sonst schlägt die Selbstprüfung am Ende fehl
    # und die Anwendung erreicht sich selbst nicht (Kalenderabgleich, Sicherungsprüfung).
    if ! grep -qE "[[:space:]]${HOST}([[:space:]]|\$)" /etc/hosts; then
        printf '127.0.0.1\t%s %s\n' "$HOST" "${HOST%%.*}" >> /etc/hosts
        echo "  /etc/hosts: $HOST zeigt lokal auf 127.0.0.1"
    fi

    setze_env APP_URL "$URL"
    setze_env SESSION_SECURE_COOKIE true
    # Der Config-Cache entstand ein paar Zeilen weiter oben mit den alten Werten. Ohne diesen
    # Durchlauf zeigten Links in E-Mails weiter auf http:// und die Sitzung blieb unsicher.
    als "php artisan config:clear --quiet && php artisan optimize --quiet"
fi

schritt "Webserver (HTTP $PORT$( ((HTTPS)) && echo " → HTTPS $HTTPS_PORT" || true))"
a2enmod -q rewrite headers >/dev/null
if (( HTTPS )); then a2enmod -q ssl >/dev/null; fi
# Das Apache-Modul heisst je nach Ubuntu-Ausgabe php8.3, php8.5 …: den vorhandenen Namen nehmen.
for MODUL in /etc/apache2/mods-available/php*.load; do
    [[ -e "$MODUL" ]] && a2enmod -q "$(basename "$MODUL" .load)" >/dev/null 2>&1 || true
done
# Uploads: 2 MB Standard reichen für eine Modulkatalog-Ernte oder eine lange Notenliste nicht.
# memory_limit: die Kommandozeile läuft unter Ubuntu ohne Limit, Apache mit 128M. Ein Import oder
# eine Sicherung stösst dort an – und ein Speicherabbruch erscheint im Laravel-Log nur als
# «A facade root has not been set», also als Folgefehler, der die Ursache verdeckt.
for CONFD in /etc/php/*/apache2/conf.d; do
    if [[ -d "$CONFD" ]]; then
        printf 'upload_max_filesize = 16M\npost_max_size = 16M\nmemory_limit = 256M\n' > "$CONFD/99-notenportal.ini"
    fi
done
grep -qE "^\s*Listen\s+$PORT\s*$" /etc/apache2/ports.conf || echo "Listen $PORT" >> /etc/apache2/ports.conf
if (( HTTPS )) && [[ "$HTTPS_PORT" != "443" ]]; then
    grep -qE "^\s*Listen\s+$HTTPS_PORT\s*$" /etc/apache2/ports.conf || echo "Listen $HTTPS_PORT" >> /etc/apache2/ports.conf
fi

# Marke, damit ein erneuter Lauf die eigene Konfiguration aktualisieren darf, eine von Hand
# angepasste aber nicht überschreibt. Wer die Zeile entfernt, behält seine Fassung.
MARKE="# von install.sh erzeugt – wer diese Zeile entfernt, behält seine eigene Fassung"
schreibe_vhost() {
    local datei="$1" inhalt
    inhalt="$(cat)"
    if [[ -f "$datei" ]] && ! grep -qF "$MARKE" "$datei"; then
        echo "  $(basename "$datei") ist von Hand angepasst – bleibt unverändert"
        return 0
    fi
    if [[ -f "$datei" ]] && [[ "$(cat "$datei")" == "$inhalt" ]]; then
        echo "  $(basename "$datei") unverändert"
        return 0
    fi
    printf '%s\n' "$inhalt" > "$datei"
    echo "  $(basename "$datei") geschrieben"
}

ALIAS_ZEILE=""
[[ ${#ALIASE[@]} -eq 0 ]] || ALIAS_ZEILE="    ServerAlias ${ALIASE[*]}"
VERZEICHNIS_BLOCK="    <Directory $VERZ/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>"
HTTPS_SUFFIX=""
[[ "$HTTPS_PORT" == "443" ]] || HTTPS_SUFFIX=":$HTTPS_PORT"
HTTP_SUFFIX=""
[[ "$PORT" == "80" ]] || HTTP_SUFFIX=":$PORT"

if (( HTTPS )); then
    # HTTP leitet auf HTTPS um – mit einer Ausnahme: das CA-Zertifikat. Wer es noch nicht importiert
    # hat, würde es sonst nur über eine Zertifikatswarnung laden können, also genau über den Weg,
    # den der Import beheben soll.
    schreibe_vhost "/etc/apache2/sites-available/$NAME.conf" <<CONF
<VirtualHost *:$PORT>
    $MARKE
    ServerName $HOST
$ALIAS_ZEILE
    DocumentRoot $VERZ/public
$VERZEICHNIS_BLOCK
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/lab-ca\.crt\$
    RewriteRule ^ https://$HOST$HTTPS_SUFFIX%{REQUEST_URI} [R=301,L]
    ErrorLog \${APACHE_LOG_DIR}/$NAME-error.log
    CustomLog \${APACHE_LOG_DIR}/$NAME-access.log combined
</VirtualHost>
CONF
    schreibe_vhost "/etc/apache2/sites-available/$NAME-ssl.conf" <<CONF
<VirtualHost *:$HTTPS_PORT>
    $MARKE
    ServerName $HOST
$ALIAS_ZEILE
    DocumentRoot $VERZ/public
$VERZEICHNIS_BLOCK
    SSLEngine on
    SSLCertificateFile $SSL_VERZ/server.crt
    SSLCertificateKeyFile $SSL_VERZ/server.key
    SSLProtocol -all +TLSv1.2 +TLSv1.3
    SSLHonorCipherOrder off
    SSLSessionTickets off
    ErrorLog \${APACHE_LOG_DIR}/$NAME-ssl-error.log
    CustomLog \${APACHE_LOG_DIR}/$NAME-ssl-access.log combined
</VirtualHost>
CONF
    a2ensite -q "$NAME-ssl" >/dev/null
    [[ ! -e /etc/apache2/sites-enabled/default-ssl.conf ]] || a2dissite -q default-ssl >/dev/null
else
    schreibe_vhost "/etc/apache2/sites-available/$NAME.conf" <<CONF
<VirtualHost *:$PORT>
    $MARKE
    ServerName $HOST
$ALIAS_ZEILE
    DocumentRoot $VERZ/public
$VERZEICHNIS_BLOCK
    ErrorLog \${APACHE_LOG_DIR}/$NAME-error.log
    CustomLog \${APACHE_LOG_DIR}/$NAME-access.log combined
</VirtualHost>
CONF
fi
a2ensite -q "$NAME" >/dev/null
if [[ "$PORT" == "80" && -e /etc/apache2/sites-enabled/000-default.conf ]]; then a2dissite -q 000-default >/dev/null; fi
if ! APACHE_PRUEFUNG="$(apachectl -t 2>&1)"; then
    echo "$APACHE_PRUEFUNG"
    echo "Die Apache-Konfiguration ist fehlerhaft – nichts neu geladen, der alte Stand läuft weiter."
    exit 1
fi
systemctl reload apache2 2>/dev/null || systemctl restart apache2
if (( FIREWALL )) && command -v ufw >/dev/null && ufw status | grep -q "Status: active"; then
    ufw allow "$PORT/tcp" >/dev/null
    if (( HTTPS )); then ufw allow "$HTTPS_PORT/tcp" >/dev/null; fi
fi

schritt "Zeitplan (tägliche Sicherung)"
CRON="/etc/cron.d/${NAME//[^A-Za-z0-9_-]/_}"
echo "* * * * * www-data cd $VERZ && $(command -v php) artisan schedule:run >> /dev/null 2>&1" > "$CRON"
chmod 644 "$CRON"
echo "  $CRON"

if (( FRISCH )); then
    schritt "Erste Sicherung"
    # Einmal jetzt statt erst in der Nacht: damit ist bewiesen, dass der Sicherungsweg funktioniert,
    # und die Bereitschaftsprüfung meldet nicht am ersten Tag «keine Sicherung in den letzten 2 Tagen».
    if als "php artisan notenportal:sicherung" >/dev/null 2>&1; then
        echo "  storage/app/private/sicherungen/ – täglich 02:30 automatisch, 14 Stände"
    else
        echo "  Die erste Sicherung ist fehlgeschlagen – auf der Seite Betrieb «Jetzt sichern» versuchen."
    fi
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

schritt "Selbstprüfung"
if (( HTTPS )); then
    # Über --resolve und --cacert statt gegen 127.0.0.1: so wird geprüft, was der Browser prüft –
    # richtiger Name im Zertifikat und Vertrauenskette zur Lab-CA. Ein blosses -k würde beides
    # überspringen und die häufigste Fehlkonfiguration (fehlender Name im Zertifikat) durchlassen.
    STATUS="$(curl -s -o /dev/null -w '%{http_code}' --cacert "$SSL_VERZ/ca.crt" \
        --resolve "$HOST:$HTTPS_PORT:127.0.0.1" "https://$HOST$HTTPS_SUFFIX/login" || true)"
    if [[ "$STATUS" == "200" ]]; then
        echo "  HTTPS: Zertifikat gültig für $HOST, Anmeldeseite erreichbar"
    else
        echo "  HTTPS antwortet mit ${STATUS:-keiner Antwort}"
        curl -sS -o /dev/null --cacert "$SSL_VERZ/ca.crt" --resolve "$HOST:$HTTPS_PORT:127.0.0.1" \
            "https://$HOST$HTTPS_SUFFIX/login" 2>&1 | sed 's/^/    /' || true
    fi
    UMLEITUNG="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/login" -H "Host: $HOST" || true)"
    [[ "$UMLEITUNG" == "301" ]] && echo "  HTTP leitet um (301 → $URL)" \
        || echo "  Achtung: HTTP antwortet mit $UMLEITUNG statt 301 – Umleitung prüfen"
    CA_STATUS="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/lab-ca.crt" -H "Host: $HOST" || true)"
    [[ "$CA_STATUS" == "200" ]] && echo "  CA-Zertifikat zum Herunterladen: $URL/lab-ca.crt (auch über http://$HOST$HTTP_SUFFIX/lab-ca.crt)" \
        || echo "  Achtung: /lab-ca.crt antwortet mit $CA_STATUS – die Lernenden können die CA nicht laden"
else
    STATUS="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/login" -H "Host: $HOST" || true)"
fi
if [[ "$STATUS" != "200" ]]; then
    # Erfolg nur melden, wenn die Anmeldeseite wirklich kommt: eine grüne Zeile mit einem 403 oder 500
    # daneben wird überlesen, und der Anwender sucht den Fehler später an der falschen Stelle.
    printf '\n\033[1;31m✖ Das Portal antwortet nicht wie erwartet: HTTP %s statt 200\033[0m\n' "${STATUS:-keine Antwort}"
    echo "  Adresse:      $URL"
    case "$STATUS" in
        403) echo "  403 heisst meist: www-data darf das Verzeichnis nicht lesen – Portal nach /var/www verschieben." ;;
        500)
            # Ein PHP-Fataler landet im Apache-Protokoll, nicht im Laravel-Log. Im Laravel-Log steht
            # dann nur die Folgemeldung «A facade root has not been set» – sie verdeckt die Ursache.
            echo "  Die eigentliche Ursache steht im Apache-Protokoll:"
            for LOGDATEI in "/var/log/apache2/$NAME-ssl-error.log" "/var/log/apache2/$NAME-error.log"; do
                if [[ -s "$LOGDATEI" ]]; then
                    echo "  --- $LOGDATEI"
                    tail -n 8 "$LOGDATEI" | sed 's/^/  /'
                    break
                fi
            done
            echo "  Danach erst: tail -30 $VERZ/storage/logs/laravel-*.log"
            ;;
        000|"") echo "  Keine Antwort: läuft Apache? systemctl status apache2 – und hört er auf Port $PORT?" ;;
        *) echo "  Fehlerprotokoll: tail -30 /var/log/apache2/$NAME-error.log" ;;
    esac
    echo "$ZUGANG" | sed 's/^/  /'
    exit 1
fi
printf '\n\033[1;32m✔ Notenportal läuft: %s\033[0m  (HTTP %s)\n' "$URL" "$STATUS"
echo "$ZUGANG" | sed 's/^/  /'
if [[ "$ZUGANG" == *Startpasswort* ]]; then
    echo "  Nach dem ersten Anmelden neues Passwort setzen – danach führt die Einrichtung durch alle Schritte."
fi

if (( HTTPS )); then
    # Der Installer kann den Namen im Lab nicht selbst auflösbar machen: der DNS-Eintrag liegt beim
    # Netzbetrieb. Darum genau sagen, was fehlt – sonst endet es bei jedem Lernenden im hosts-File.
    cat <<ENDE

  Damit die Lernenden $URL erreichen, fehlen noch zwei Handgriffe:

  1. DNS-Eintrag im Lab (einmal, durch den Netzbetrieb):
     A-Record   $HOST  →  ${ADRESSEN[0]:-<IP dieses Servers>}
     Der Eintrag muss auf dem DNS-Server stehen, den der VPN-Zugang an die Clients verteilt.
     Zur Probe von einem verbundenen Client: nslookup ${HOST%%.*}

  2. Lab-CA auf jedem Gerät einmal importieren, sonst warnt der Browser:
     Herunterladen: http://$HOST$HTTP_SUFFIX/lab-ca.crt
     Windows  Doppelklick → Zertifikat installieren → Lokaler Computer →
              «Vertrauenswürdige Stammzertifizierungsstellen»
     macOS    Doppelklick → Schlüsselbund «System» → Zertifikat öffnen → «Immer vertrauen»
     Firefox  Einstellungen → Zertifikate → Zertifizierungsstellen → Importieren
     Die Verbindung ist auch ohne Import verschlüsselt – nur eben mit Warnung.

  Ohne DNS-Eintrag hilft ersatzweise ein Eintrag in der hosts-Datei des Clients:
     ${ADRESSEN[0]:-<IP dieses Servers>}  ${HOST%%.*}
ENDE
fi
