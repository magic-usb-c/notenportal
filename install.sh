#!/usr/bin/env bash
# Notenportal – Installation auf Ubuntu 24.04 LTS oder neuer (erprobt auf 24.04 und 26.04; PHP kommt
# aus der Distribution und muss mindestens 8.3 sein). Im geklonten Repo ausführen:
#   sudo ./install.sh                                   https://notenportal, Datenbank «notenportal»
#   sudo ./install.sh --host notenportal.lab.local      eigener Name im Zertifikat und im vhost
#   sudo ./install.sh --port 8082 --db notenportal_i2   zweite Instanz neben einer bestehenden (nur HTTP)
# Optionen: --host <Name|IP>  --admin-mail <adresse>  --ohne-https  --https-port <n>
#           --ohne-firewall  --neues-admin-passwort
#   sudo ./install.sh --pruefen                          nur nachsehen, warum das Portal mit 500 antwortet
#   sudo ./install.sh --entfernen                       alles wieder abräumen (mit Sicherung und Rückfrage)
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
ENTFERNEN=0
PRUEFEN=0
RUECKFRAGE=1
ARGUMENTE=("$@")          # für den Neustart nach dem Umzug aus dem Home-Verzeichnis

while [[ $# -gt 0 ]]; do
    case "$1" in
        --entfernen) ENTFERNEN=1; shift ;;
        --pruefen) PRUEFEN=1; shift ;;
        --ohne-rueckfrage) RUECKFRAGE=0; shift ;;
        --port) PORT="$2"; shift 2 ;;
        --db) DB="$2"; shift 2 ;;
        --host) HOST="$2"; shift 2 ;;
        --admin-mail) ADMIN_MAIL="$2"; shift 2 ;;
        --https-port) HTTPS_PORT="$2"; HTTPS=1; shift 2 ;;
        --https) HTTPS=1; shift ;;
        --ohne-https) HTTPS=0; shift ;;
        --ohne-firewall) FIREWALL=0; shift ;;
        --neues-admin-passwort) ADMIN_OPTION="--zuruecksetzen"; shift ;;
        -h|--help) sed -n '2,11p' "$0"; exit 0 ;;
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
# umask 002: alles, was artisan und composer anlegen (Zwischenspeicher, Protokolle, übersetzte
# Vorlagen), bleibt für die Gruppe beschreibbar. Zusammen mit dem setgid-Bit auf den Verzeichnissen
# gehört es damit der Gruppe www-data – sonst legt der Installer Dateien an, die der Webserver
# später nicht mehr überschreiben kann.
als() { sudo -u "$BESITZER" -H bash -c "umask 002; cd '$VERZ' && $*"; }

# Eine Sonde, die die Anwendung wie eine Webanfrage startet, dabei aber Laravels Fehlerseite durch
# eine ersetzt, die die Ausnahme im Klartext ausgibt. Ohne das ersetzt Laravel eine Ausnahme durch
# seine eigene Fehlerseite, scheitert dabei ein zweites Mal und protokolliert nur noch die
# Folgemeldung «A facade root has not been set» – die Ursache bleibt unsichtbar.
# Gelingt der Start, kommt die Anmeldeseite; misslingt er, kommt Klasse, Meldung, Datei und Zeile.
schreibe_sonde() {
    cat > "$1" <<'SONDENENDE'
<?php
$wurzel = getenv('NP_WURZEL') ?: dirname(__DIR__);
require $wurzel . '/vendor/autoload.php';
$app = require_once $wurzel . '/bootstrap/app.php';
$app->singleton(Illuminate\Contracts\Debug\ExceptionHandler::class, function () {
    return new class implements Illuminate\Contracts\Debug\ExceptionHandler {
        public function report(Throwable $e): void {}
        public function shouldReport(Throwable $e): bool { return false; }
        public function renderForConsole($output, Throwable $e): void {}
        public function render($request, Throwable $e)
        {
            $text = "SONDE-FEHLER\n";
            for ($f = $e; $f !== null; $f = $f->getPrevious()) {
                $text .= get_class($f) . ': ' . $f->getMessage() . "\n";
                $text .= '  in ' . $f->getFile() . ':' . $f->getLine() . "\n";
                foreach (array_slice($f->getTrace(), 0, 8) as $s) {
                    $text .= '    ' . ($s['file'] ?? '?') . ':' . ($s['line'] ?? '?')
                          . '  ' . ($s['class'] ?? '') . ($s['type'] ?? '') . ($s['function'] ?? '') . "\n";
                }
                $text .= "\n";
            }
            return new Symfony\Component\HttpFoundation\Response(
                $text, 500, ['Content-Type' => 'text/plain; charset=utf-8']
            );
        }
    };
});
$app->handleRequest(Illuminate\Http\Request::createFromBase(
    Symfony\Component\HttpFoundation\Request::create('/login', 'GET', [], $_COOKIE ?? [], [], $_SERVER)
));
SONDENENDE
    chmod 644 "$1"
}

# Rechte so setzen, dass $BESITZER arbeiten und www-data lesen und in storage/ schreiben kann.
# Wird zweimal aufgerufen: einmal vor den artisan-Läufen, damit deren Dateien richtig entstehen,
# und einmal am Schluss für alles, was danach noch dazugekommen ist.
setze_rechte() {
    chgrp -R www-data "$VERZ"
    find "$VERZ" \( -path "$VERZ/.git" -o -path "$VERZ/node_modules" -o -path "$VERZ/vendor" \) -prune \
        -o -type d -exec chmod u+rwx,g+rxs,o-rwx {} +
    find "$VERZ" \( -path "$VERZ/.git" -o -path "$VERZ/node_modules" -o -path "$VERZ/vendor" \) -prune \
        -o -type f -exec chmod u+rw,g+r,o-rwx {} +
    [[ ! -d "$VERZ/vendor" ]] || chmod -R g+rX "$VERZ/vendor"
    chmod -R g+w "$VERZ/storage" "$VERZ/bootstrap/cache"
}

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

# Alles, was der Installer am System hinterlässt, wieder abräumen – damit eine misslungene
# Installation sauber von vorne beginnen kann, statt dass Reste die nächste Runde vergiften.
# Die Datenbank wird vorher gesichert: ein Abräumen aus Versehen darf keine Noten kosten.
if (( ENTFERNEN )); then
    lies_env() { [[ -f "$VERZ/.env" ]] || return 0
                 { grep -E "^$1=" "$VERZ/.env" || true; } | head -1 | cut -d= -f2- | tr -d '"'; }
    E_DB="$(lies_env DB_DATABASE)";           E_DB="${E_DB:-$DB}"
    E_WEB="$(lies_env DB_USERNAME)";          E_WEB="${E_WEB:-${E_DB}_web}"
    E_MIG="$(lies_env DB_MIGRATE_USERNAME)";  E_MIG="${E_MIG:-${E_DB}_migrate}"
    E_URL="$(lies_env APP_URL)"
    E_HOST="${E_URL#*://}"; E_HOST="${E_HOST%%[:/]*}"; E_HOST="${E_HOST:-$HOST}"

    schritt "Entfernen – das verschwindet"
    echo "  Datenbank        $E_DB (wird vorher gesichert)"
    echo "  Datenbankkonten  $E_WEB, $E_MIG"
    echo "  Apache-Sites     $NAME.conf, $NAME-ssl.conf"
    echo "  Zertifikate      $SSL_VERZ (inklusive Lab-CA – importierte Kopien werden wertlos)"
    echo "  Zeitplan         /etc/cron.d/${NAME//[^A-Za-z0-9_-]/_}"
    echo "  hosts-Eintrag    $E_HOST"
    echo "  Im Verzeichnis   .env, vendor/, node_modules/, public/build, storage/logs, Zwischenspeicher"
    echo "  Nicht angetastet: $VERZ selbst, Apache, MariaDB, PHP, die Pakete."
    if (( RUECKFRAGE )); then
        printf '\n  Zum Bestätigen «entfernen» eingeben: '
        read -r ANTWORT
        [[ "$ANTWORT" == "entfernen" ]] || { echo "  Abgebrochen – es wurde nichts verändert."; exit 1; }
    fi

    schritt "Sicherung vor dem Entfernen"
    if mysql -N -e "SHOW DATABASES LIKE '$E_DB';" 2>/dev/null | grep -q .; then
        SICHERUNG="/root/${NAME}-entfernt-$(date +%Y%m%d-%H%M%S).sql.gz"
        if mysqldump --single-transaction --routines --events "$E_DB" 2>/dev/null | gzip > "$SICHERUNG"; then
            chmod 600 "$SICHERUNG"
            echo "  $SICHERUNG ($(du -h "$SICHERUNG" | cut -f1))"
        else
            rm -f "$SICHERUNG"
            echo "  Die Sicherung ist fehlgeschlagen – Abbruch, damit nichts verloren geht."
            exit 1
        fi
    else
        echo "  Keine Datenbank $E_DB vorhanden – nichts zu sichern"
    fi

    schritt "Entfernen"
    a2dissite -q "$NAME-ssl" >/dev/null 2>&1 || true
    a2dissite -q "$NAME" >/dev/null 2>&1 || true
    rm -f "/etc/apache2/sites-available/$NAME.conf" "/etc/apache2/sites-available/$NAME-ssl.conf"
    # Bleibt gar keine Site übrig, die Standardsite wieder anschalten, damit Apache startet. Läuft auf
    # dem Server noch eine zweite Instanz, bleibt alles, wie es ist – sonst bekäme die auf einmal einen
    # zweiten vhost auf demselben Port.
    if [[ -z "$(ls -A /etc/apache2/sites-enabled 2>/dev/null)" ]]; then
        a2ensite -q 000-default >/dev/null 2>&1 || true
    fi
    # 99-notenportal.ini bleibt: die Datei gilt für ganz PHP und damit auch für eine zweite Instanz.
    # Sie hebt nur Upload- und Speichergrenzen an – stehen zu lassen schadet nichts, wegnehmen schon.
    rm -f "/etc/cron.d/${NAME//[^A-Za-z0-9_-]/_}"
    rm -rf "$SSL_VERZ"
    [[ -z "$E_HOST" ]] || sed -i "/^127\.0\.0\.1[[:space:]].*[[:space:]]\?${E_HOST//./\\.}\([[:space:]]\|$\)/d" /etc/hosts
    mysql <<SQL 2>/dev/null || true
DROP DATABASE IF EXISTS \`$E_DB\`;
DROP USER IF EXISTS '$E_WEB'@'localhost';
DROP USER IF EXISTS '$E_MIG'@'localhost';
FLUSH PRIVILEGES;
SQL
    rm -f "$VERZ/.env" "$VERZ/public/lab-ca.crt"
    rm -rf "$VERZ/vendor" "$VERZ/node_modules" "$VERZ/public/build"
    rm -f "$VERZ"/bootstrap/cache/*.php
    rm -rf "$VERZ"/storage/logs/* "$VERZ"/storage/framework/views/* \
           "$VERZ"/storage/framework/sessions/* "$VERZ"/storage/framework/cache/data/*
    if apachectl -t >/dev/null 2>&1; then systemctl reload apache2 >/dev/null 2>&1 || true; fi
    printf '\n\033[1;32m✔ Abgeräumt.\033[0m Neu aufsetzen mit: sudo ./install.sh\n'
    echo "  Die Sicherung der Datenbank liegt unter /root/ – erst löschen, wenn sie nicht mehr gebraucht wird."
    echo "  Soll auch das Verzeichnis weg: cd .. && sudo rm -rf \"$VERZ\""
    exit 0
fi

# Nur nachsehen, nichts verändern: startet die Anwendung einmal auf der Kommandozeile und einmal
# durch Apache und zeigt die Ausnahme im Klartext. Dauert Sekunden – im Gegensatz zu einer
# vollständigen Neuinstallation, die an einem Fehler im Webserver ohnehin nichts ändert.
if (( PRUEFEN )); then
    [[ -f "$VERZ/.env" && -d "$VERZ/vendor" ]] || { echo "Keine Installation in $VERZ – erst sudo ./install.sh"; exit 1; }
    P_URL="$({ grep -E '^APP_URL=' "$VERZ/.env" || true; } | head -1 | cut -d= -f2- | tr -d '"')"
    P_HOST="${P_URL#*://}"; P_HOST="${P_HOST%%/*}"
    P_PORT="${P_HOST##*:}"; [[ "$P_PORT" != "$P_HOST" ]] || P_PORT=443
    P_HOST="${P_HOST%%:*}"

    schritt "1. Kommandozeile (als www-data, mit der php.ini von Apache)"
    APACHE_INI="$(dirname "$(ls -d /etc/php/*/apache2/conf.d 2>/dev/null | tail -n 1)" 2>/dev/null || true)"
    P_DATEI="$(mktemp /tmp/notenportal-pruefung-XXXXXX.php)"
    schreibe_sonde "$P_DATEI"
    P_CLI="$(sudo -u www-data env "NP_WURZEL=$VERZ" php ${APACHE_INI:+-c "$APACHE_INI"} \
        -d display_errors=1 -d error_reporting=-1 "$P_DATEI" 2>&1 || true)"
    rm -f "$P_DATEI"
    if [[ "$P_CLI" == *"<!DOCTYPE"* && "$P_CLI" != *"SONDE-FEHLER"* ]]; then
        echo "  Die Anwendung startet – die Anmeldeseite wird gerendert."
    else
        echo "$P_CLI" | grep -av 'facade root' | head -n 20 | sed 's/^/  /'
    fi

    schritt "2. Durch Apache hindurch"
    SONDE="np-diagnose-$(openssl rand -hex 8).php"
    trap 'rm -f "$VERZ/public/$SONDE"' EXIT
    schreibe_sonde "$VERZ/public/$SONDE"
    chown "$BESITZER":www-data "$VERZ/public/$SONDE" 2>/dev/null || true
    P_WEB="$(curl -sk --max-time 30 --resolve "$P_HOST:$P_PORT:127.0.0.1" \
        "https://$P_HOST:$P_PORT/$SONDE" 2>&1 || true)"
    rm -f "$VERZ/public/$SONDE"; trap - EXIT
    if [[ "$P_WEB" == *"<!DOCTYPE"* && "$P_WEB" != *"SONDE-FEHLER"* ]]; then
        echo "  Die Anwendung startet auch durch Apache – der 500 kommt von woanders."
    elif [[ -z "$P_WEB" ]]; then
        echo "  Keine Antwort von https://$P_HOST:$P_PORT – Apache prüfen."
    else
        echo "$P_WEB" | grep -av 'facade root' | head -n 24 | sed 's/^/  /'
    fi

    schritt "3. Umgebung"
    echo "  PHP Kommandozeile : $(php -r 'echo PHP_VERSION;')"
    echo "  PHP unter Apache  : $(php ${APACHE_INI:+-c "$APACHE_INI"} -r 'echo PHP_VERSION;' 2>/dev/null || echo '?')"
    echo "  Apache-PHP-Modul  : $(apachectl -M 2>/dev/null | grep -o 'php[0-9_]*module' | head -1 || echo '?')"
    echo "  opcache (Apache)  : $(php ${APACHE_INI:+-c "$APACHE_INI"} -r 'echo ini_get("opcache.enable") ? "an" : "aus";' 2>/dev/null || echo '?')"
    echo "  PCRE-JIT (Apache) : $(php ${APACHE_INI:+-c "$APACHE_INI"} -r 'echo ini_get("pcre.jit") ? "an" : "aus";' 2>/dev/null || echo '?')"
    # PCRE-JIT braucht beschreibbaren und ausführbaren Speicher. Verbietet die Härtung des Dienstes
    # das, scheitert jedes preg_match – aber nur im Apache-Prozess, nie auf der Kommandozeile.
    echo "  Apache-Härtung    : MemoryDenyWriteExecute=$(systemctl show apache2 -p MemoryDenyWriteExecute --value 2>/dev/null || echo '?')"
    exit 0
fi

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
# ohne dass die Ursache im Portal zu sehen wäre. Der übliche Weg «git clone im Home, sudo ./install.sh»
# soll trotzdem in einem Zug durchlaufen: das Portal zieht nach /var/www um und startet dort neu.
# Das Durchgangsrecht am Home-Verzeichnis zu öffnen wäre die schlechtere Wahl (fremde Dateien lesbar).
www_data_kommt_durch() {
    local pfad="$1"
    while [[ "$pfad" != "/" ]]; do
        sudo -u www-data test -x "$pfad" || return 1
        pfad="$(dirname "$pfad")"
    done
}
if ! www_data_kommt_durch "$VERZ"; then
    ZIEL="/var/www/$NAME"
    if [[ -e "$ZIEL" ]]; then
        echo "Der Webserver-Benutzer www-data kann $VERZ nicht betreten – Apache würde 403 liefern."
        echo "Unter $ZIEL liegt schon etwas. Ist es dieses Portal, dort aktualisieren und installieren:"
        echo "  cd $ZIEL && git pull && sudo ./install.sh"
        echo "Soll es eine zweite Instanz daneben werden: unter anderem Namen nach /var/www verschieben und"
        echo "dort mit eigener Datenbank und eigenem Port starten, z. B."
        echo "  sudo ./install.sh --db ${DB}_2 --port 8082"
        exit 1
    fi
    echo "  $VERZ liegt im Home-Verzeichnis – Apache darf dort nicht lesen."
    echo "  Das Portal zieht nach $ZIEL um und die Installation läuft dort weiter."
    mkdir -p /var/www
    mv "$VERZ" "$ZIEL"
    # Über bash statt direkt: ein Klon aus einem ZIP oder einer Kopie hat oft kein Ausführungsbit.
    # cd vorher: beim Umzug über eine Dateisystemgrenze ist das alte Arbeitsverzeichnis danach weg.
    cd "$ZIEL"
    NP_UMGEZOGEN_VON="$VERZ" exec bash "$ZIEL/install.sh" "${ARGUMENTE[@]}"
fi

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
    php-xml php-curl php-zip php-intl php-gd php-bcmath unzip git curl openssl iproute2 >/dev/null
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
# Startet ein Dienst nicht, schlug die Installation früher erst beim ersten mysql-Aufruf fehl – mit einer
# Socket-Meldung, die auf die falsche Spur führt. Die häufigste Ursache ist ein belegter Port.
# Nur Meldungen ab dem eigenen Startversuch zeigen: ältere Zeilen im Journal führen sonst auf eine
# Ursache, die gar nicht mehr besteht (etwa ein längst freier Port, während der Dienst maskiert ist).
dienst_klemmt() {
    local dienst="$1" seit="$2" ports="$3"
    echo "Der Dienst $dienst startet nicht."
    if [[ "$(systemctl is-enabled "$dienst" 2>/dev/null || true)" == masked ]]; then
        echo "Er ist maskiert und darf darum nicht starten. Freigeben mit: sudo systemctl unmask $dienst"
        return
    fi
    echo "Seine Meldungen seit dem Startversuch:"
    journalctl -u "$dienst" --since "$seit" -n 12 --no-pager 2>/dev/null | sed 's/^/    /' || true
    echo "Häufigste Ursache: Port $ports ist belegt – nachsehen mit: sudo ss -ltnp | grep -E ':(${ports// oder /|}) '"
}
# Die Ports, auf denen Apache lauschen will, stehen in ports.conf (80, mit mod_ssl auch 443,
# nach einer früheren Installation mit --port auch dieser).
apache_ports() {
    local p
    p="$(awk '$1 == "Listen" {n = split($2, t, ":"); print t[n]}' /etc/apache2/ports.conf 2>/dev/null | sort -un | paste -sd' ')"
    p="${p:-$PORT}"
    echo "${p// / oder }"
}
DIENST_START="$(date '+%Y-%m-%d %H:%M:%S')"
systemctl enable --now mariadb apache2 2>&1 | grep -v -e '^Synchronizing' -e '^Executing' -e 'Created symlink' | sed 's/^/  /' || true
for DIENST in mariadb apache2; do
    systemctl is-active --quiet "$DIENST" && continue
    case "$DIENST" in
        mariadb) dienst_klemmt mariadb "$DIENST_START" 3306 ;;
        apache2) dienst_klemmt apache2 "$DIENST_START" "$(apache_ports)" ;;
    esac
    exit 1
done

# Ein fremdes Programm auf dem HTTPS-Port (oder auf --port) fiel früher erst beim Neustart von Apache
# ganz am Schluss auf: Apache blieb danach aus, und das Startpasswort war bereits vergeben, aber nie
# gezeigt. Jetzt wird vorab geprüft, bevor etwas angelegt ist. Apache selbst darf den Port halten.
port_fremd_belegt() {
    local zeile
    zeile="$(ss -ltnpH "sport = :$1" 2>/dev/null || true)"
    [[ -n "$zeile" && "$zeile" != *'"apache2"'* ]]
}
for P in "$PORT" $( (( ! HTTPS )) || echo "$HTTPS_PORT"); do
    port_fremd_belegt "$P" || continue
    echo "Port $P ist von einem anderen Programm belegt – Apache könnte danach nicht mehr starten:"
    ss -ltnpH "sport = :$P" 2>/dev/null | sed 's/^/    /' || true
    echo "Das Programm beenden oder das Portal auf einen anderen Port legen (--port, --https-port)."
    exit 1
done

# Derselbe Name auf demselben Port in einem fremden vhost: Apache nimmt den alphabetisch ersten,
# eine neue Instanz «notenportal-b» würde der bestehenden «notenportal» still den Namen wegnehmen.
# Nur bei der ersten Installation dieser Instanz prüfen – ein Update ändert an den Namen nichts.
if [[ ! -e "/etc/apache2/sites-available/$NAME.conf" ]]; then
    EIGENE_PORTS="$PORT"; (( ! HTTPS )) || EIGENE_PORTS="$PORT $HTTPS_PORT"
    for KONF in /etc/apache2/sites-enabled/*.conf; do
        [[ -e "$KONF" ]] || continue
        if awk -v h="$HOST" -v ports=" $EIGENE_PORTS " '
            /<VirtualHost/ { port = $0; sub(/.*:/, "", port); sub(/>.*/, "", port) }
            tolower($1) == "servername" || tolower($1) == "serveralias" {
                for (i = 2; i <= NF; i++) if (tolower($i) == tolower(h) && index(ports, " " port " ")) treffer = 1
            }
            END { exit !treffer }' "$KONF"; then
            echo "Der Name $HOST ist auf Port ${EIGENE_PORTS// / bzw. } schon vergeben: $KONF"
            echo "Eine zweite Instanz braucht einen eigenen Port oder Namen, z. B."
            echo "  sudo ./install.sh --db ${DB}_2 --port 8082        (nur HTTP, lokal)"
            echo "  sudo ./install.sh --db ${DB}_2 --host np2.lab.local"
            exit 1
        fi
    done
fi

schritt "Datenbank"
FRISCH=0
if [[ -f "$VERZ/.env" ]]; then
    lies() { { grep -E "^$1=" "$VERZ/.env" || true; } | head -1 | cut -d= -f2- | tr -d '"'; }
    DB="$(lies DB_DATABASE)"
    [[ -n "$DB" ]] || { echo "DB_DATABASE fehlt in $VERZ/.env"; exit 1; }
    echo "  .env vorhanden – Datenbank $DB bleibt unverändert"
    mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
else
    # Ohne .env, aber mit gefüllter Datenbank: das ist fast immer ein zweiter Klon neben einer laufenden
    # Instanz. Weiterzumachen hiesse, deren Datenbank-Passwörter neu zu setzen – sie antwortete danach
    # mit 500, während dieser Lauf grün endet. Darum hier anhalten, bevor irgendetwas geändert ist.
    TABELLEN="$(mysql -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB'" 2>/dev/null || echo 0)"
    if (( TABELLEN > 0 )); then
        echo "Die Datenbank $DB gibt es schon ($TABELLEN Tabellen), aber $VERZ hat keine .env."
        echo "Gehört sie zu einer anderen Instanz, würde diese Installation deren Zugang überschreiben."
        echo "  Zweite Instanz daneben:  sudo ./install.sh --db ${DB}_2 --port 8082"
        echo "  Ist es die Datenbank dieses Portals: die .env aus der Sicherung zurücklegen und erneut starten."
        exit 1
    fi
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
# npm vertraut nur seinen eingebauten Zertifizierungsstellen, nicht dem Systemspeicher. Hinter einem
# Firmen-Proxy, der TLS aufbricht, scheitert npm ci sonst mit SELF_SIGNED_CERT_IN_CHAIN, obwohl apt
# und composer (die dem System vertrauen) durchkommen.
SYSTEM_CA=/etc/ssl/certs/ca-certificates.crt
NPM_UMGEBUNG=""
[[ -r "$SYSTEM_CA" ]] && NPM_UMGEBUNG="NODE_EXTRA_CA_CERTS=$SYSTEM_CA "
if ! als "${NPM_UMGEBUNG}npm ci --no-audit --no-fund --loglevel=error && npm run build --silent"; then
    echo
    echo "npm ci oder der Build der Oberfläche ist fehlgeschlagen. Häufige Ursachen:"
    echo "  - kein Zugang zu registry.npmjs.org (Proxy: in /etc/environment eintragen, dann neu anmelden)"
    echo "  - eine Firmen-Zertifizierungsstelle fehlt im Systemspeicher:"
    echo "      sudo cp firma-ca.crt /usr/local/share/ca-certificates/ && sudo update-ca-certificates"
    echo "  - zu wenig Arbeitsspeicher für den Build (mindestens 1 GB frei)"
    echo "  Details: das letzte Protokoll unter ~$BESITZER/.npm/_logs/"
    exit 1
fi

# Vor den artisan-Läufen, nicht erst danach: sonst entstehen Protokoll, Sitzungen und übersetzte
# Vorlagen unter der Gruppe von $BESITZER, und www-data scheitert später beim ersten Schreibversuch.
setze_rechte

schritt "Anwendung"
grep -q '^APP_KEY=base64' "$VERZ/.env" || als "php artisan key:generate --force --quiet"
als "php artisan config:clear --quiet && php artisan notenportal:migrate"
als "php artisan db:seed --force --quiet"
[[ -z "$ADMIN_MAIL" ]] || ADMIN_OPTION="$ADMIN_OPTION --email=$ADMIN_MAIL"
ZUGANG="$(als "php artisan notenportal:erstes-admin-konto $ADMIN_OPTION")"
# Ab hier existiert das Admin-Konto. Bricht ein späterer Schritt ab (etwa Apache wegen eines belegten
# Ports 443), darf das Startpasswort nicht mit untergehen: es wird nirgends gespeichert und ein
# zweiter Lauf meldet nur noch «Admin-Konto vorhanden».
ZUGANG_GEZEIGT=0
zeige_zugang() { echo "$ZUGANG" | sed 's/^/  /'; ZUGANG_GEZEIGT=1; }
zugang_bei_abbruch() {
    local rc=$?
    (( rc != 0 && ! ZUGANG_GEZEIGT )) || return 0
    echo
    echo "  Das Admin-Konto ist schon angelegt. Diese Angaben gelten auch nach dem Abbruch:"
    zeige_zugang
}
trap zugang_bei_abbruch EXIT
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
# Das Apache-Modul heisst je nach Ubuntu-Ausgabe php8.3, php8.5 … – und es darf genau eines aktiv
# sein, nämlich das zur Kommandozeilen-Version passende. Liegen zwei PHP-Fassungen auf dem Server
# (ein Upgrade, ein Fremd-Repository), baut composer vendor/ gegen die eine und Apache führt es mit
# der anderen aus. Das Ergebnis ist ein 500, während jeder artisan-Befehl einwandfrei läuft.
PHP_ZWEIG="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
PHP_MODUL="php$PHP_ZWEIG"
if [[ -e "/etc/apache2/mods-available/$PHP_MODUL.load" ]]; then
    for MODUL in /etc/apache2/mods-enabled/php*.load; do
        [[ -e "$MODUL" ]] || continue
        AKTIV="$(basename "$MODUL" .load)"
        [[ "$AKTIV" == "$PHP_MODUL" ]] || { a2dismod -q "$AKTIV" >/dev/null 2>&1 || true
            echo "  Apache-Modul $AKTIV abgeschaltet – die Kommandozeile läuft mit PHP $PHP_ZWEIG"; }
    done
    a2enmod -q "$PHP_MODUL" >/dev/null 2>&1 || true
    echo "  Apache führt PHP $PHP_ZWEIG aus (Modul $PHP_MODUL)"
else
    # Kein mod_php für diese Fassung: dann läuft PHP über php-fpm oder gar nicht. Beides ist hier
    # nicht vorgesehen, aber ein stiller Fehlschlag wäre schlimmer als eine deutliche Meldung.
    echo "  Achtung: /etc/apache2/mods-available/$PHP_MODUL.load fehlt – Apache und Kommandozeile"
    echo "  benutzen womöglich verschiedene PHP-Fassungen. Prüfen: apachectl -M | grep php"
fi
# Uploads: 2 MB Standard reichen für eine Modulkatalog-Ernte oder eine lange Notenliste nicht.
# memory_limit: die Kommandozeile läuft unter Ubuntu ohne Limit, Apache mit 128M. Ein Import oder
# eine Sicherung stösst dort an.
#
# pcre.jit = 0: PCRE übersetzt reguläre Ausdrücke bei Bedarf in Maschinencode und braucht dafür
# Speicher, der zugleich beschreibbar und ausführbar ist. Ubuntu 26.04 härtet den Apache-Dienst so,
# dass genau das verboten ist (systemd MemoryDenyWriteExecute beziehungsweise das AppArmor-Profil).
# PHP meldet dann bei jedem preg_match eine Warnung – und weil Laravel Warnungen in Ausnahmen
# umwandelt, fliegt sie schon in HandleExceptions::bootstrap(), also bevor der Container steht.
# Die Fehlerseite lässt sich ohne Container nicht rendern, und übrig bleibt im Protokoll nur die
# Folgemeldung «A facade root has not been set» – auf der Kommandozeile läuft alles, über Apache
# antwortet dasselbe Portal mit 500. Die Härtung ist richtig und bleibt; PCRE-JIT ist reine
# Geschwindigkeitsoptimierung für reguläre Ausdrücke und hier nicht messbar. Das ist auch genau
# der Weg, den die PHP-Meldung selbst vorschlägt.
PHP_INI_INHALT='upload_max_filesize = 16M
post_max_size = 16M
memory_limit = 256M
pcre.jit = 0
'
PHP_INI_NEU=0
for CONFD in /etc/php/*/apache2/conf.d; do
    [[ -d "$CONFD" ]] || continue
    if [[ "$(cat "$CONFD/99-notenportal.ini" 2>/dev/null)" != "$PHP_INI_INHALT" ]]; then
        printf '%s' "$PHP_INI_INHALT" > "$CONFD/99-notenportal.ini"
        PHP_INI_NEU=1
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
VERZEICHNIS_BLOCK="    <Directory \"$VERZ/public\">
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
    DocumentRoot "$VERZ/public"
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
    DocumentRoot "$VERZ/public"
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
    DocumentRoot "$VERZ/public"
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
# Ein reload übernimmt geänderte PHP-Einstellungen nicht: mod_php liest die php.ini beim Start des
# Moduls, nicht bei jeder Anfrage. Nach einer Änderung an 99-notenportal.ini also wirklich neu starten.
NEUSTART="$(date '+%Y-%m-%d %H:%M:%S')"
if (( PHP_INI_NEU )); then
    systemctl restart apache2 || { dienst_klemmt apache2 "$NEUSTART" "$(apache_ports)"; exit 1; }
else
    systemctl reload apache2 2>/dev/null || systemctl restart apache2 || { dienst_klemmt apache2 "$NEUSTART" "$(apache_ports)"; exit 1; }
fi
if (( FIREWALL )) && command -v ufw >/dev/null && ufw status | grep -q "Status: active"; then
    ufw allow "$PORT/tcp" >/dev/null
    if (( HTTPS )); then ufw allow "$HTTPS_PORT/tcp" >/dev/null; fi
fi

schritt "Zeitplan (tägliche Sicherung)"
CRON="/etc/cron.d/${NAME//[^A-Za-z0-9_-]/_}"
echo "* * * * * www-data cd \"$VERZ\" && $(command -v php) artisan schedule:run >> /dev/null 2>&1" > "$CRON"
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
setze_rechte
if ! sudo -u www-data test -r "$VERZ/public/index.php"; then
    echo "  Achtung: www-data kann $VERZ nicht lesen – übergeordnete Verzeichnisse prüfen (z. B. chmod o+x)."
fi
# Beweis statt Annahme: www-data muss in storage/ tatsächlich schreiben können, sonst scheitert die
# erste Sitzung mit einer Ausnahme, die Laravel nur noch als «A facade root has not been set» meldet.
for SCHREIBZIEL in storage/logs storage/framework/sessions storage/framework/views bootstrap/cache; do
    if ! sudo -u www-data test -w "$VERZ/$SCHREIBZIEL"; then
        echo "  Achtung: www-data kann in $SCHREIBZIEL nicht schreiben – das Portal antwortet mit 500."
        echo "  Behebt sich mit: sudo chgrp -R www-data \"$VERZ\" && sudo chmod -R g+w \"$VERZ/storage\" \"$VERZ/bootstrap/cache\""
    fi
done

schritt "Selbstprüfung"
# Erst den Webeinstieg unter dem Webserver-Benutzer und mit der php.ini von Apache durchspielen.
# Geht dabei etwas schief, erscheint die Meldung hier ungefiltert – über HTTP käme nur ein nacktes
# «500», und im Protokoll stünde bloss die Folgemeldung «A facade root has not been set».
APACHE_INI="$(dirname "$(ls -d /etc/php/*/apache2/conf.d 2>/dev/null | tail -n 1)" 2>/dev/null || true)"
if [[ -d "$APACHE_INI" ]]; then
    PRUEFDATEI="$(mktemp /tmp/notenportal-webpruefung-XXXXXX.php)"
    schreibe_sonde "$PRUEFDATEI"
    WEB_AUSGABE="$(sudo -u www-data env "NP_WURZEL=$VERZ" php -c "$APACHE_INI" \
        -d display_errors=1 -d error_reporting=-1 "$PRUEFDATEI" 2>&1 || true)"
    rm -f "$PRUEFDATEI"
    # Auch die eigene Fehlerseite gilt als Fehlschlag: sie ist gültiges HTML und käme sonst als
    # Erfolg durch, obwohl die Anwendung in Wahrheit mit einer Ausnahme geantwortet hat.
    if [[ "$WEB_AUSGABE" == *"SONDE-FEHLER"* || "$WEB_AUSGABE" == *"Fatal error"* \
       || "$WEB_AUSGABE" == *"Uncaught"* || "$WEB_AUSGABE" != *"<!DOCTYPE"* ]]; then
        printf '\n\033[1;31m✖ Die Anwendung startet unter dem Webserver-Benutzer nicht.\033[0m\n'
        echo "$WEB_AUSGABE" | grep -av 'facade root' | head -n 18 | sed 's/^/  /'
        echo "  PHP unter Apache: $(php -c "$APACHE_INI" -r 'echo PHP_VERSION;' 2>/dev/null || echo '?'), Kommandozeile: $(php -r 'echo PHP_VERSION;')"
        echo "  Sauber neu beginnen: sudo ./install.sh --entfernen && sudo ./install.sh"
        exit 1
    fi
    echo "  Die Anwendung startet als www-data"
fi
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
            # Nach einem PHP-Fatalen versucht Laravel noch eine Fehlerseite zu rendern, findet aber
            # keinen Container mehr und meldet «A facade root has not been set». Diese Folgemeldung
            # füllt beide Protokolle und verdeckt die Ursache – darum wird sie hier weggefiltert.
            echo "  «A facade root has not been set» ist nur die Folgemeldung, nicht die Ursache."
            GEFUNDEN=0
            for LOGDATEI in "/var/log/apache2/$NAME-ssl-error.log" "/var/log/apache2/$NAME-error.log"; do
                [[ -s "$LOGDATEI" ]] || continue
                URSACHE="$(grep -a -E 'PHP (Fatal error|Parse error)' "$LOGDATEI" \
                    | grep -av 'facade root' | tail -n 2 | cut -c1-300 || true)"
                [[ -n "$URSACHE" ]] || continue
                echo "  --- $LOGDATEI"; echo "$URSACHE" | sed 's/^/  /'; GEFUNDEN=1
            done
            (( GEFUNDEN )) || echo "  Im Protokoll steht nur die Hülle."
            # Läuft die Anwendung auf der Kommandozeile, aber nicht durch Apache, hilft kein
            # Protokoll weiter: Laravel ersetzt die ursprüngliche Ausnahme durch seine eigene
            # Fehlerseite und scheitert dabei ein zweites Mal. Also eine Sonde in public/ legen,
            # die Laravels Fehlerbehandlung durch eine ersetzt, die die Ausnahme im Klartext
            # ausgibt – und sie über genau den Weg abrufen, der eben mit 500 geantwortet hat.
            SONDE="np-diagnose-$(openssl rand -hex 8).php"
            aufraeumen_sonde() { rm -f "$VERZ/public/$SONDE"; }
            trap 'rc=$?; aufraeumen_sonde; (exit $rc); zugang_bei_abbruch' EXIT
            schreibe_sonde "$VERZ/public/$SONDE"
            chown "$BESITZER":www-data "$VERZ/public/$SONDE" 2>/dev/null || true
            echo "  Ausnahme im Klartext, abgerufen durch Apache:"
            if (( HTTPS )); then
                SONDEN_AUSGABE="$(curl -s --max-time 30 --cacert "$SSL_VERZ/ca.crt" \
                    --resolve "$HOST:$HTTPS_PORT:127.0.0.1" "https://$HOST$HTTPS_SUFFIX/$SONDE" 2>&1 || true)"
            else
                SONDEN_AUSGABE="$(curl -s --max-time 30 "http://127.0.0.1:$PORT/$SONDE" -H "Host: $HOST" 2>&1 || true)"
            fi
            aufraeumen_sonde
            trap zugang_bei_abbruch EXIT
            if [[ -n "$SONDEN_AUSGABE" ]]; then
                echo "$SONDEN_AUSGABE" | head -n 24 | sed 's/^/  /'
            else
                echo "  (keine Ausgabe – der Fehler tritt auf, bevor die Anwendung überhaupt startet)"
            fi
            echo
            echo "  Läuft sie auf der Kommandozeile und nur unter Apache nicht, liegt es an dem, was"
            echo "  allein der Apache-Prozess mitbringt – Härtung, Speicherschutz, Erweiterungen:"
            echo "    systemctl show apache2 -p MemoryDenyWriteExecute -p PrivateTmp"
            echo "    sudo aa-status | grep -i apache"
            echo "  Ganze Lage auf einen Blick: sudo ./install.sh --pruefen"
            echo "  Ergänzend: tail -30 $VERZ/storage/logs/laravel-*.log"
            ;;
        000|"") echo "  Keine Antwort: läuft Apache? systemctl status apache2 – und hört er auf Port $PORT?" ;;
        *) echo "  Fehlerprotokoll: tail -30 /var/log/apache2/$NAME-error.log" ;;
    esac
    zeige_zugang
    exit 1
fi
printf '\n\033[1;32m✔ Notenportal läuft: %s\033[0m  (HTTP %s)\n' "$URL" "$STATUS"
zeige_zugang
if [[ "$ZUGANG" == *Startpasswort* ]]; then
    echo "  Nach dem ersten Anmelden neues Passwort setzen – danach führt die Einrichtung durch alle Schritte."
else
    echo "  Zugang verloren? Neues Startpasswort: sudo ./install.sh --neues-admin-passwort"
fi
if [[ -n "${NP_UMGEZOGEN_VON:-}" ]]; then
    echo "  Das Portal liegt jetzt in $VERZ (vorher $NP_UMGEZOGEN_VON) – weiter mit: cd $VERZ"
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
