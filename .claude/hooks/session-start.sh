#!/bin/bash
# SessionStart-Hook für Claude Code on the web (Cloud-Container).
#
# Richtet den Container so ein, dass Tests, Pint, Vite-Build und die Browser-Prüfwerkzeuge
# sofort laufen: MariaDB, Datenbanken notenportal_test/notenportal_demo, Composer, npm,
# Build, ein beständiger APP_KEY und die Demo-Instanz auf http://127.0.0.1:8099.
#
# Läuft nur, wenn CLAUDE_CODE_REMOTE=true gesetzt ist (Cloud). Auf der VM tut er nichts –
# dort gelten .env und docs/betrieb.md. Idempotent: jeder Schritt prüft zuerst, ob er nötig ist.
# Geheimnisse: der APP_KEY wird einmal erzeugt und in $HOME/.notenportal/app_key abgelegt
# (ausserhalb des Repos). Das Demo-Passwort bleibt allein in database/seeders/DemoSeeder.php.
set -uo pipefail

if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
    exit 0
fi

PROJEKT="${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/../.." && pwd)}"
cd "$PROJEKT"
ZUSTAND="$HOME/.notenportal"
mkdir -p "$ZUSTAND"
LOG="$ZUSTAND/session-start.log"
: >"$LOG"
MELDUNGEN=()
melde() { MELDUNGEN+=("$1"); echo "[session-start] $1" >>"$LOG"; }
SOCKET=/run/mysqld/mysqld.sock

export DEBIAN_FRONTEND=noninteractive
export COMPOSER_ALLOW_SUPERUSER=1
export npm_config_update_notifier=false npm_config_fund=false npm_config_audit=false
export DB_CONNECTION=mariadb DB_HOST=localhost DB_SOCKET="$SOCKET" DB_USERNAME=root DB_PASSWORD=
export DB_MIGRATE_USERNAME=root DB_MIGRATE_PASSWORD=
export APP_TIMEZONE=Europe/Zurich APP_LOCALE=de APP_FALLBACK_LOCALE=de APP_FAKER_LOCALE=de_CH
export NP_URL="${NP_URL:-http://127.0.0.1:8099}"

# 1. MariaDB installieren (nur im frischen Container) und starten
if ! command -v mysqld_safe >/dev/null 2>&1; then
    melde "MariaDB wird installiert"
    apt-get update -qq >>"$LOG" 2>&1 && apt-get install -y -qq mariadb-server mariadb-client >>"$LOG" 2>&1 \
        || melde "FEHLER: MariaDB-Installation fehlgeschlagen (siehe $LOG)"
fi
# Ohne .env liest phpdotenv bei jedem Hochfahren ins Leere; Collision zählt die unterdrückte Warnung je Test
# («1066 warnings», gemessen 01.10.2026). Die Werte kommen aus der Umgebung, die Datei bleibt leer und ist gitignored.
[ -f "$PROJEKT/.env" ] || printf '# Umgebung kommt aus .claude/hooks/session-start.sh (Cloud-Sitzung)\n' > "$PROJEKT/.env"

# ssh-keygen braucht SicherungKopieTest (app/Services/Betrieb/SicherungKopie.php); im Container fehlt openssh-client.
if ! command -v ssh-keygen >/dev/null 2>&1; then
    apt-get update -qq >>"$LOG" 2>&1; apt-get install -y -qq openssh-client >>"$LOG" 2>&1 \
        || melde "openssh-client nicht installiert – SicherungKopieTest fällt (siehe $LOG)"
fi
if command -v mysqld_safe >/dev/null 2>&1 && ! mysqladmin --socket="$SOCKET" ping >/dev/null 2>&1; then
    # mysqld_safe startet mariadbd als Benutzer mysql. Socket-, Protokoll- und Datenverzeichnis müssen
    # ihm gehören, sonst stirbt der Dienst ohne sichtbare Meldung (Syslog gibt es im Container nicht;
    # gemessen 01.10.2026: /run/mysqld gehörte root, der Socket liess sich nicht anlegen).
    install -d -o mysql -g mysql /run/mysqld /var/log/mysql
    chown -R mysql:mysql /var/lib/mysql
    rm -f /run/mysqld/mysqld.pid "$SOCKET"
    (nohup mysqld_safe --log-error=/var/log/mysql/error.log >"$ZUSTAND/mariadb.log" 2>&1 &)
    for _ in $(seq 1 40); do mysqladmin --socket="$SOCKET" ping >/dev/null 2>&1 && break; sleep 1; done
fi
if mysqladmin --socket="$SOCKET" ping >/dev/null 2>&1; then
    for db in notenportal_test notenportal_demo; do
        mysql --socket="$SOCKET" -u root -e "CREATE DATABASE IF NOT EXISTS \`$db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" >>"$LOG" 2>&1
    done
    melde "MariaDB läuft ($(mysql --socket="$SOCKET" -u root -N -e 'SELECT VERSION()' 2>/dev/null)), Datenbanken notenportal_test und notenportal_demo vorhanden"
else
    melde "FEHLER: MariaDB läuft nicht – Tests und Demo-Server stehen nicht zur Verfügung ($(grep ERROR /var/log/mysql/error.log 2>/dev/null | tail -2 | tr '\n' ' '))"
fi

# 2. Composer
if [ ! -f vendor/autoload.php ]; then
    # Dist-Archive kommen von api.github.com und codeload.github.com. Hinter dem Proxy der Cloud ist das
    # nicht verlässlich: am 01.10.2026 antwortete api.github.com mit 403, am 02.10. mit 200 – und brach
    # dann jeden Zipball-Download mit «Proxy CONNECT aborted due to timeout» ab; codeload gab 403.
    # Composer wich auf git clone aus (zehn Minuten für 122 Pakete) und scheiterte an phpstan/phpstan,
    # das keine Git-Quelle hat. Darum entscheidet eine echte Archiv-Probe, nicht eine Wurzel-URL: liefert
    # codeload ein Archiv, läuft composer direkt (Packagist-Hashes, Erstquelle); sonst über den
    # Packagist-Spiegel (composer-spiegel.sh, dieselben Versionen, unter einer Minute). Der Spiegel ist ein
    # Drittanbieter, composer.lock trägt keine Prüfsummen für seine Archive – SETUP-CLAUDE.md Abschnitt 12.
    # Scheitert der gewählte Weg, folgt einmal der andere.
    ARCHIV_PROBE="https://codeload.github.com/laravel/framework/zip/refs/tags/v13.0.0"
    composer_direkt() { composer install --no-interaction --prefer-dist --no-progress >>"$LOG" 2>&1; }
    composer_spiegel() {
        bash "$PROJEKT/.claude/hooks/composer-spiegel.sh" >>"$LOG" 2>&1 \
            && COMPOSER=composer.local.json composer install --no-interaction --prefer-dist --no-progress >>"$LOG" 2>&1
    }
    if [ "$(curl -s -o /dev/null -w '%{http_code}' --max-time 30 "$ARCHIV_PROBE" 2>/dev/null)" = "200" ]; then
        melde "composer install direkt von GitHub"
        composer_direkt \
            || { melde "direkt gescheitert – zweiter Versuch über den Packagist-Spiegel (composer.local.lock)"; composer_spiegel; } \
            || melde "FEHLER: composer install (siehe $LOG)"
    else
        melde "GitHub-Archive nicht erreichbar – composer install über den Packagist-Spiegel (composer.local.lock)"
        composer_spiegel \
            || { melde "Spiegel gescheitert – zweiter Versuch direkt von GitHub"; composer_direkt; } \
            || melde "FEHLER: composer install (siehe $LOG)"
    fi
fi

# 3. APP_KEY – einmal erzeugen, dann beständig
if [ ! -s "$ZUSTAND/app_key" ] && [ -f vendor/autoload.php ]; then
    php artisan key:generate --show 2>>"$LOG" | tr -d '\r\n' >"$ZUSTAND/app_key"
    chmod 600 "$ZUSTAND/app_key"
fi
if [ -s "$ZUSTAND/app_key" ]; then
    APP_KEY="$(cat "$ZUSTAND/app_key")"
    export APP_KEY
else
    melde "FEHLER: kein APP_KEY – php artisan key:generate --show schlug fehl"
fi

# 4. npm und Vite-Build
if [ ! -d node_modules ]; then
    melde "npm install läuft"
    npm install --no-audit --no-fund >>"$LOG" 2>&1 || melde "FEHLER: npm install (siehe $LOG)"
fi
if [ ! -f public/build/manifest.json ] && [ -d node_modules ]; then
    melde "npm run build läuft"
    npm run build >>"$LOG" 2>&1 || melde "FEHLER: npm run build (siehe $LOG)"
fi

# 5. Umgebungsvariablen für die Sitzung (DB_DATABASE bewusst nicht: phpunit.xml setzt notenportal_test,
#    der Demo-Server setzt notenportal_demo – ein globaler Wert würde eines von beiden überschreiben)
if [ -n "${CLAUDE_ENV_FILE:-}" ]; then
    {
        echo "export DB_CONNECTION=mariadb DB_HOST=localhost DB_SOCKET=$SOCKET DB_USERNAME=root DB_PASSWORD="
        echo "export DB_MIGRATE_USERNAME=root DB_MIGRATE_PASSWORD="
        echo "export COMPOSER_ALLOW_SUPERUSER=1"
        echo "export npm_config_update_notifier=false npm_config_fund=false"
        echo "export APP_TIMEZONE=Europe/Zurich APP_LOCALE=de APP_FALLBACK_LOCALE=de APP_FAKER_LOCALE=de_CH"
        echo "export NP_URL=$NP_URL"
        [ -n "${APP_KEY:-}" ] && echo "export APP_KEY=$APP_KEY"
    } >>"$CLAUDE_ENV_FILE"
fi

# 6. Demo-Datenbank befüllen und Demo-Server starten
if mysqladmin --socket="$SOCKET" ping >/dev/null 2>&1 && [ -f vendor/autoload.php ] && [ -n "${APP_KEY:-}" ]; then
    DEMO_USERS="$(mysql --socket="$SOCKET" -u root -N -e 'SELECT COUNT(*) FROM notenportal_demo.benutzer' 2>/dev/null || echo 0)"
    if [ "${DEMO_USERS:-0}" = "0" ]; then
        melde "notenportal_demo wird migriert und mit DemoSeeder befüllt"
        DB_DATABASE=notenportal_demo APP_ENV=local php artisan migrate:fresh --force --seeder=DemoSeeder --no-interaction >>"$LOG" 2>&1 \
            || melde "FEHLER: Demo-Datenbank (siehe $LOG)"
    fi
    if bash tools/pruefung/demo-server.sh start >>"$LOG" 2>&1; then
        melde "Demo-Server läuft: $NP_URL (Konten laura.frei/michael.baumann/nina.huber@demo.example)"
    else
        melde "FEHLER: Demo-Server startet nicht (siehe $ZUSTAND/demo-server.log)"
    fi
fi

# 7. Plugins aus dem offiziellen Marktplatz (enabledPlugins in .claude/settings.json schaltet nur ein,
#    installiert aber nichts – jeder Container muss selbst installieren; geprüft mit CLI 2.1.286)
if command -v claude >/dev/null 2>&1; then
    # Die mitgelieferte Kopie des Marktplatzes ist im frischen Container veraltet und kennt die Plugins
    # nicht («not found in marketplace», 01.10.2026) – deshalb zuerst aktualisieren.
    timeout 120 claude plugin marketplace update claude-plugins-official >>"$LOG" 2>&1 || melde "Marktplatz claude-plugins-official nicht aktualisiert (siehe $LOG)"
    for PLUGIN in php-lsp@claude-plugins-official frontend-design@claude-plugins-official; do
        if grep -q "\"$PLUGIN\"" "$HOME/.claude/plugins/installed_plugins.json" 2>/dev/null; then
            continue
        fi
        if timeout 120 claude plugin install "$PLUGIN" >>"$LOG" 2>&1; then
            melde "Plugin installiert: $PLUGIN"
        else
            melde "Plugin nicht installiert: $PLUGIN (siehe $LOG)"
        fi
    done
fi

# 8. Zusammenfassung – stdout landet im Kontext der Sitzung
echo "Notenportal-Cloud-Umgebung: PHP $(php -r 'echo PHP_VERSION;' 2>/dev/null), Node $(node -v 2>/dev/null), Composer $(composer --version --no-ansi 2>/dev/null | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)"
printf '%s\n' "${MELDUNGEN[@]}"
echo "Tests: php artisan test (DB notenportal_test) · Pint: vendor/bin/pint --dirty · Build: npm run build"
echo "Browser-Prüfung: export NP_TEST_PW=\$(grep -oP \"DEMO_PASSWORT\\s*=\\s*'\\K[^']+\" database/seeders/DemoSeeder.php); node tools/pruefung/shot.mjs <email> </pfad>"
exit 0
