---
name: notenportal-blockabschluss
description: Abgeschlossenen Arbeitsblock/Plan-Punkt sauber abschliessen – Build, Tests, ß-Prüfung, Demo-Seiten im Dunkelmodus auf 1920/2560, Rundgang je Rolle, reviewer/ui-checker/pruefer, Befunde fixen, Commit und Push auf main, Übergabe-Eintrag, Kurzbericht. Laden, sobald ein Block fertig implementiert ist.
---

# Block abschliessen

Reihenfolge einhalten. Jeder Schritt hat ein Prüfkriterium. Nicht erfüllt → beheben → Schritt wiederholen.
Arbeitsverzeichnis ist die Repo-Wurzel, Branch `main` (`git branch --show-current`).

## 1. Migrationen
`git status --short database/migrations` zeigt neue Dateien? Cloud: `DB_DATABASE=notenportal_demo php artisan migrate --force`
muss durchlaufen, danach `tools/pruefung/demo-server.sh neu`. VM: zuerst Skill `notenportal-migration`.

## 2. Build + Caches
```bash
npm run build 2>&1 | tail -3          # muss "built in" enthalten
php artisan optimize:clear >/dev/null
```

## 3. Tests + Stil
```bash
vendor/bin/pint --dirty                # keine Änderungen übrig
php artisan test 2>&1 | tail -5       # muss "Tests: N passed" ohne "failed" zeigen
```
Laufen parallel Agents Tests? Dann `DB_DATABASE=notenportal_b_test php artisan test`. Den Guard in `tests/TestCase.php` nie entfernen.

## 4. Textprüfung
```bash
grep -rn "ß" resources/views/ lang/ 2>/dev/null          # muss leer sein
```

## 5. Seiten ansehen (Demo, dunkel, Desktop)
```bash
tools/pruefung/demo-server.sh status || tools/pruefung/demo-server.sh start
export NP_TEST_PW=$(grep -oP "DEMO_PASSWORT\s*=\s*'\K[^']+" database/seeders/DemoSeeder.php)
D=<scratch-ordner>
node tools/pruefung/shot.mjs <email> "/geänderter/pfad,/zweiter" --dir=$D --name=b          # 1920 dunkel
node tools/pruefung/shot.mjs <email> "/geänderter/pfad" --breite=2560 --hoehe=1440 --dir=$D --name=g
node tools/pruefung/rundgang.mjs <email der betroffenen Rolle>                              # Exit 0
```
Kriterium: Status 200, kein `UEBERLAUF`, Rundgang ohne Befunde, **jedes Bild mit Read angesehen** und
gegen Skill `notenportal-dunkelmodus` beurteilt. Konten: Skill `notenportal-pruefwerkzeuge`.

## 6. Review (eine Nachricht, parallel)
- `reviewer` (sonnet high): «Prüfe `git diff origin/main...HEAD` plus uncommittete Änderungen (`git diff`). Block: <Name>.»
- `ui-checker` (opus high): nur wenn Views geändert; Liste der geänderten Views mitgeben
  (`git diff --name-only origin/main -- resources/views`), einmal pro Rollenbereich.
- `pruefer` (fable, effortLevel low): die Fertig-Behauptung des Blocks wörtlich, mit den Beweisen aus 3 und 5.
Echte Befunde fixen, dann Schritt 3 wiederholen. Nicht Umgesetztes mit Begründung in `docs/audit-backlog.md`.

## 7. Doku
- Neue Funktionen → `docs/funktionsumfang.md`; neue Klassen/Tabellen → `docs/architektur.md`.
- Änderungen ausserhalb des Repos (VM: DB, Apache, PHP, cron, ufw) → `docs/betrieb.md`.
- Bewusst Weggelassenes → `docs/audit-backlog.md`.
- Eintrag in `docs/auftrag/UEBERGABE.md` (Format dort), Offenes unter «Offen».

## 8. Commit + Push
```bash
set -o pipefail
git status --short | grep -E '(^|/)\.env' && echo "STOPP: .env im Commit"   # darf nichts ausgeben
git status --short | grep -E 'tmp-testdaten' && echo "STOPP: Testdaten"      # darf nichts ausgeben
git add <pfade des blocks>                                                  # explizit, nie -A
rest=$(git status --short | grep -v '^[MADR] ' || true); [ -z "$rest" ] || echo "Nicht gestaged: $rest"
git commit -m "Feat: <Deutsch, Imperativ, eine Zeile>" -m "Co-Authored-By: …" -m "Claude-Session: <link>"
git log --oneline -1                                                        # Pflicht: stimmt die Meldung?
git push origin HEAD:main
```
Push abgelehnt (kein Fast-Forward)? `git fetch origin main && git rebase origin/main`, Tests erneut, dann
pushen. Nie force-pushen. Liegen in einer Datei Hunks eines anderen, noch laufenden Agents: nur die
eigenen Hunks stagen (`git diff datei > p; …; git apply --cached --recount p`).
Präfixe: `Feat:` `Fix:` `GUI:` `Refactor:` `Test:` `Docs:` `Chore:`. Keine Modellnamen im Commit.

## 9. Bericht an den User
Höchstens 8 Zeilen: was neu ist, Testzahl, Commit-Hash, was gesehen wurde, offene Punkte/Backlog.
Keine Wiederholung des Plans. Danach ohne Rückfrage den nächsten Punkt nehmen.
