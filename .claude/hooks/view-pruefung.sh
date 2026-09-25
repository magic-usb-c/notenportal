#!/usr/bin/env bash
# PostToolUse-Hook: prüft nach Edit/Write an Blade-Views auf ß und Farb-Hardcodes.
# Meldung per stderr + exit 2 → Claude sieht den Befund und korrigiert.
datei="$(jq -r '.tool_input.file_path // empty')"
[[ "$datei" == *resources/views/*.blade.php && -f "$datei" ]] || exit 0

befunde=""
ss="$(grep -n 'ß' "$datei")"
[[ -n "$ss" ]] && befunde+="ß gefunden (ss verwenden):\n$ss\n"

# Mail-Vorlagen brauchen Inline-Farben (Mailprogramme kennen keine CSS-Variablen) → dort keine Farbprüfung.
# Druckansicht (notenblatt) darf Schwarz/Weiss nutzen.
if [[ "$datei" != */views/mail/* && "$datei" != */views/vendor/mail/* && "$datei" != *notenblatt* ]]; then
    farbe="$(grep -nE '(^|[" :])(bg-white|text-black|bg-gray-(800|900)|bg-slate-(800|900))([" /:]|$)|(class|style)="[^"]*#[0-9a-fA-F]{3,8}\b' "$datei")"
    [[ -n "$farbe" ]] && befunde+="Farb-Hardcode (Tokens bg-card/bg-bg/text-text/text-muted verwenden):\n$farbe\n"
    weiss="$(grep -nE '(^|[" :])text-white([" /:]|$)' "$datei" | grep -vE 'bg-(accent|red|green|emerald|yellow|amber|blue|sky|indigo|violet|rose|orange)-?' )"
    [[ -n "$weiss" ]] && befunde+="text-white ohne farbigen Hintergrund auf derselben Zeile (nur auf bg-accent/Statusfarbe erlaubt, sonst ignorieren falls Hintergrund anderswo gesetzt):\n$weiss\n"
    akzent="$(grep -nE "(^|[\" ':])text-accent([\"' /]|$)" "$datei" | grep -v 'application-logo')"
    [[ -n "$akzent" ]] && befunde+="text-accent für Text (eigene Akzentfarbe garantiert nur 3:1) – text-accent-text verwenden:\n$akzent\n"
fi

[[ -z "$befunde" ]] && exit 0
printf "view-pruefung %s:\n%b" "${datei#/var/www/notenportal/}" "$befunde" >&2
exit 2
