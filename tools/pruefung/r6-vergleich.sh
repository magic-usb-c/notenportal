#!/bin/bash
# Vorher/nachher-Leistungsmessung für die R6-Abnahme auf derselben Maschine, abwechselnd je Rolle.
#
#   NP_TEST_PW=… tools/pruefung/r6-vergleich.sh <vorher-url> <nachher-url> <ausgabeordner>
#
# <vorher-url> ist eine zweite Instanz desselben Repos auf dem Stand vor der Änderung (git worktree + artisan serve
# gegen dieselbe Demo-DB), <nachher-url> der Demo-Server. Misst je Rolle 1920×1080 mit Palette und 1920×540 mit
# erzwungenem Scrollweg, jeweils Median aus 3 Läufen, und schreibt JSON plus vergleich.md. Nur in einem ruhigen
# Fenster laufen lassen (keine Agents, keine Testsuite): CPU-Last verfälscht Bildzeiten um ein Vielfaches.
set -euo pipefail
VORHER="${1:?vorher-url}"
NACHHER="${2:?nachher-url}"
AUS="${3:?ausgabeordner}"
mkdir -p "$AUS"
cd "$(dirname "$0")/../.."

declare -A SEITEN=(
    [nina.huber]="/dashboard,/grades,/grades/calculator,/exams,/qualification"
    [laura.frei]="/admin,/admin/reports/grades,/admin/learners"
    [michael.baumann]="/trainer,/trainer/learners,/trainer/learners/1"
)

for konto in nina.huber laura.frei michael.baumann; do
    for stand in vorher nachher; do
        url="$VORHER"; [ "$stand" = nachher ] && url="$NACHHER"
        NP_URL="$url" node tools/pruefung/leistung.mjs "$konto@demo.example" "${SEITEN[$konto]}" \
            --laeufe=3 --palette --json="$AUS/$stand-$konto-1080.json" >/dev/null
        NP_URL="$url" node tools/pruefung/leistung.mjs "$konto@demo.example" "${SEITEN[$konto]}" \
            --hoehe=540 --laeufe=3 --json="$AUS/$stand-$konto-540.json" >/dev/null
        echo "$konto $stand fertig"
    done
done

liste() { ls "$AUS"/"$1"-*-"$2".json | paste -sd, -; }
{
    node tools/pruefung/vergleich.mjs --vorher="$(liste vorher 1080)" --nachher="$(liste nachher 1080)" --titel="1920×1080, --laeufe=3 --palette"
    echo
    node tools/pruefung/vergleich.mjs --vorher="$(liste vorher 540)" --nachher="$(liste nachher 540)" --titel="1920×540, --laeufe=3"
} >"$AUS/vergleich.md"
cat "$AUS/vergleich.md"
