---
name: notenportal-blockabschluss
description: Abgeschlossenen Arbeitsblock/Plan-Punkt sauber abschliessen – Tests, Build, ß-Prüfung, Mobile-Breite 390px, reviewer, ui-checker, Befunde fixen, Commit, Merge nach main, zweite Instanz aktualisieren, Kurzbericht. Laden, sobald ein Block fertig implementiert ist.
---

# Block abschliessen

Reihenfolge einhalten. Jeder Schritt hat ein Prüfkriterium. Nicht erfüllt → beheben → Schritt wiederholen.
Arbeitsverzeichnis immer `/var/www/notenportal`. Branch muss `feature/claude-fertigstellung` sein (`git branch --show-current`).

## 1. Migrationen
`git status --short database/migrations` zeigt neue Dateien? → zuerst Skill `notenportal-migration`, dann hier weiter.

## 2. Build + Caches
```bash
npm run build 2>&1 | tail -3          # muss "built in" enthalten
php artisan optimize:clear >/dev/null
```

## 3. Tests
```bash
php artisan test 2>&1 | tail -5       # muss "Tests: N passed" ohne "failed" zeigen
```
Laufen parallel andere Agents Tests? Dann `DB_DATABASE=notenportal_b_test php artisan test` (oder `_c_test`).
Den Guard in tests/TestCase.php nie entfernen.

## 4. Textprüfung
```bash
grep -rn "ß" resources/views/ lang/ 2>/dev/null          # muss leer sein
```

## 5. Seiten laden (Prod) + Mobile-Breite
Für jeden geänderten Pfad (Beispiel `/dashboard`):
```bash
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1/login          # 200
cd ~/tools/visual
node breite.mjs http://127.0.0.1 admin@example.local Chur7000 --breite=390 /dashboard /pfad2
node breite.mjs http://127.0.0.1 david.vonallmen@example.local Chur7000 --breite=390 /grades
cd /var/www/notenportal
```
Kriterium: jede Zeile beginnt mit `200 ✓`. `✗` = seitliches Überlaufen → beheben.
Benutzer-E-Mails unsicher? `php artisan tinker --execute="echo App\Models\User::pluck('email')->implode(PHP_EOL);"`
Screenshots bei Bedarf: `node shot.mjs <baseUrl> <email> <pw> <outdir> [--dunkel] [--mobil] <pfade…>` und Bilder mit Read ansehen.

## 6. Review (parallel, eine Nachricht, zwei Agent-Aufrufe)
- `reviewer` (sonnet): «Prüfe `git diff origin/main...HEAD` plus uncommittete Änderungen (`git diff`). Block: <Name>.»
- `ui-checker` (haiku): nur wenn Views geändert; Liste der geänderten Views mitgeben (`git diff --name-only origin/main -- resources/views`), einmal pro Rollenbereich.
Echte Befunde fixen, dann Schritt 3 wiederholen. Nicht Umgesetztes mit Begründung in `docs/audit-backlog.md`.

## 7. Doku
- Neue Funktionen → `docs/funktionsumfang.md`; neue Klassen/Tabellen → `docs/architektur.md`.
- Änderungen ausserhalb des Repos (DB, Apache, PHP, cron, ufw) → `docs/betrieb.md`.
- Bewusst Weggelassenes → `docs/audit-backlog.md`.

## 8. Commit + Push + Merge
```bash
set -o pipefail                                                             # sonst zählt bei `check | tail` nur tail
git status --short | grep -E '(^|/)\.env' && echo "STOPP: .env im Commit"   # darf nichts ausgeben
git add <pfade des blocks>                                                  # explizit, nie -A (tmp-testdaten/, fremde Agent-Hunks)
rest=$(git status --short | grep -v '^[MADR] ' || true); [ -z "$rest" ] || echo "Nicht gestaged: $rest"
git commit -m "Feat: <Deutsch, Imperativ, eine Zeile>" -m "Claude-Session: <link>"
git log --oneline -1                                                        # Pflicht: stimmt die Meldung?
git push && git push origin feature/claude-fertigstellung:main              # Fast-Forward nach main
```
Nie `[ -z "$(git status --short)" ]` als Gate nach `git add` – gestagte Dateien zählen mit, der Commit wird still übersprungen.
Liegen in einer Datei Hunks eines anderen, noch laufenden Agents: nur die eigenen Hunks stagen (`git diff datei > p; …; git apply --cached --recount p`).
Präfixe: `Feat:` `Fix:` `GUI:` `Refactor:` `Test:` `Docs:` `Chore:`. Lokal gibt es keinen main-Branch.
Merge schlägt fehl (kein Fast-Forward)? → `git fetch && git log --oneline HEAD..origin/main` ansehen, nie force-pushen.

## 9. Zweite Instanz aktualisieren
```bash
cd /var/www/notenportal-i2 && git pull -q origin feature/claude-fertigstellung && sudo ./install.sh --port 8082 --ohne-firewall 2>&1 | tail -4
cd /var/www/notenportal
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8082/login     # 200
```
Kriterium: Ausgabe enthält «Notenportal läuft» und HTTP 200.

## 10. Bericht an den User
Höchstens 8 Zeilen: was neu ist, Testzahl, Commit-Hash, offene Punkte/Backlog. Keine Wiederholung des Plans.
