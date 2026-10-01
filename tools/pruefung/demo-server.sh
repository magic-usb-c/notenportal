#!/bin/bash
# Demo-Instanz für Browser-Prüfungen: php artisan serve gegen die Datenbank notenportal_demo.
#
#   tools/pruefung/demo-server.sh start|stop|status|neu
#
# start   startet den Server auf NP_URL (Standard http://127.0.0.1:8099), falls er nicht läuft
# stop    beendet ihn
# status  Exit 0, wenn er antwortet
# neu     setzt notenportal_demo zurück (migrate:fresh + DemoSeeder) und startet neu
#
# Verbindung über die exportierten DB_*-Variablen (Cloud: SessionStart-Hook; VM: .env des Projekts).
# Das Demo-Passwort steht nur in database/seeders/DemoSeeder.php – für die Werkzeuge:
#   export NP_TEST_PW=$(grep -oP "DEMO_PASSWORT\s*=\s*'\K[^']+" database/seeders/DemoSeeder.php)
set -euo pipefail

PROJEKT="$(cd "$(dirname "$0")/../.." && pwd)"
URL="${NP_URL:-http://127.0.0.1:8099}"
HOST="$(echo "$URL" | sed -E 's#https?://([^:/]+).*#\1#')"
PORT="$(echo "$URL" | sed -nE 's#https?://[^:/]+:([0-9]+).*#\1#p')"
PORT="${PORT:-8099}"
ZUSTAND="${NP_ZUSTAND:-$HOME/.notenportal}"
PID_DATEI="$ZUSTAND/demo-server.pid"
LOG="$ZUSTAND/demo-server.log"
mkdir -p "$ZUSTAND"

laeuft() { curl -s -o /dev/null -w '%{http_code}' --max-time 3 "$URL/login" 2>/dev/null | grep -q '^200$'; }

starten() {
    if laeuft; then echo "Demo-Server läuft bereits: $URL"; return 0; fi
    cd "$PROJEKT"
    DB_DATABASE=notenportal_demo APP_ENV=local APP_DEBUG=true APP_URL="$URL" SESSION_DRIVER=file \
        nohup php artisan serve --host="$HOST" --port="$PORT" >"$LOG" 2>&1 &
    echo $! >"$PID_DATEI"
    for _ in $(seq 1 30); do laeuft && { echo "Demo-Server gestartet: $URL"; return 0; }; sleep 1; done
    echo "Demo-Server antwortet nicht – siehe $LOG" >&2
    return 1
}

stoppen() {
    if [ -f "$PID_DATEI" ]; then
        pkill -P "$(cat "$PID_DATEI")" 2>/dev/null || true
        kill "$(cat "$PID_DATEI")" 2>/dev/null || true
        rm -f "$PID_DATEI"
    fi
    pkill -f "artisan serve --host=$HOST --port=$PORT" 2>/dev/null || true
    echo "Demo-Server gestoppt."
}

case "${1:-status}" in
    start) starten ;;
    stop) stoppen ;;
    status) if laeuft; then echo "läuft: $URL"; else echo "läuft nicht"; exit 1; fi ;;
    neu)
        stoppen
        cd "$PROJEKT"
        DB_DATABASE=notenportal_demo APP_ENV=local php artisan migrate:fresh --force --seeder=DemoSeeder --no-interaction
        starten
        ;;
    *) echo "Aufruf: $0 start|stop|status|neu" >&2; exit 2 ;;
esac
