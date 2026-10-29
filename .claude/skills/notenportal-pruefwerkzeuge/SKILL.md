---
name: notenportal-pruefwerkzeuge
description: Browser-Prüfwerkzeuge im Repo (tools/pruefung – shot, klick, rundgang, demo-server) für Screenshots und Layoutbefunde im Dunkelmodus auf Desktop-Breiten, Sicherheitsregeln, bekannte Regressionsmuster. Laden, bevor Notenportal-UI-Änderungen geprüft werden.
---

# Notenportal-Prüfwerkzeuge

Liegen im Repo unter `tools/pruefung/` (Node 22 + Playwright, Chromium). In der Cloud findet
`browser.mjs` Playwright global (`/opt/node22/lib/node_modules/playwright`) und Chromium über
`PLAYWRIGHT_BROWSERS_PATH`; anderswo `npm i -D playwright && npx playwright install chromium` oder
`NP_PLAYWRIGHT=<pfad zu index.mjs>` / `NP_CHROMIUM=<pfad>` setzen.

## Sicherheit (gilt für jedes Skript, immer)
- Ziel über `NP_URL` (Standard Demo-Server `http://127.0.0.1:8099`). Passwort **nur** über `NP_TEST_PW`,
  nie im Code, auf der Kommandozeile oder im Log:
  `export NP_TEST_PW=$(grep -oP "DEMO_PASSWORT\s*=\s*'\K[^']+" database/seeders/DemoSeeder.php)`
- `rundgang.mjs` schreibt nichts: nach der Anmeldung werden alle Nicht-GET-Requests abgebrochen.
- Nie Logout-/Löschen-Selektoren mit `klick.mjs` anklicken.
- Standard ist **dunkel, 1920×1080**. Hell nur mit `--hell`, grosse Bildschirme mit `--breite=2560 --hoehe=1440`.

## Werkzeuge

| Werkzeug | Aufruf | Findet / liefert |
|---|---|---|
| `demo-server.sh` | `tools/pruefung/demo-server.sh start\|stop\|status\|neu` | Demo-Instanz (DB `notenportal_demo`); `neu` setzt die Daten mit `DemoSeeder` zurück |
| `shot.mjs` | `node tools/pruefung/shot.mjs <email> "/pfad,/pfad2" [--breite=1920] [--hoehe=1080] [--hell] [--fenster] [--name=x] [--dir=.]` | Screenshot je Pfad (ganze Seite), HTTP-Status, `UEBERLAUF`, JS-Fehler |
| `clip.mjs` | `node tools/pruefung/clip.mjs <email> /pfad <x,y,breite,hoehe> --out=<datei.png> [--open=<selektor>]` | Ausschnitt einer Seite als Bild für Details, die im Vollbild zu klein sind (Scrollkanten, Tabellenköpfe, Fokusringe); `NP_EVAL='<js>'` wertet vorher JS aus (z. B. `getComputedStyle`) und gibt es aus |
| `klick.mjs` | `node tools/pruefung/klick.mjs <email> /pfad "sel1,sel2" [--dir=.]` | Zustand nach Klicks (Menü, Tab, Drawer) als Fenster-Screenshot |
| `leistung.mjs` | `node tools/pruefung/leistung.mjs <email> "/pfad,/pfad2" [--frames=150] [--laeufe=3] [--palette] [--menue=<Selektor>] [--ohne-glas] [--trace] [--json=datei]` | Je Seite LCP/Load, DOM-Knoten, sichtbare Glasflächen (Anzahl, % des Fensters), Bilddauer p50/p95/max in Ruhe und beim Scrollen, lange Bilder > 33.4 ms, Layout-/Stil-/Skriptzeit (CDP); `--palette` misst Strg+K, Tippen, Escape; `--menue=<Selektor des Auslösers>` klickt ihn (z. B. `button[aria-label^="Konto"]`), misst im offenen Menü die Glasflächen und beim Überfahren der Zeilen die Bilddauer; `--ohne-glas` setzt `data-transparenz="reduziert"` (derselbe Lauf mit und ohne zeigt, was das Glas kostet); `--trace` summiert Raster-, Paint- und Composite-Zeit (Glas wird auf Raster-Threads bezahlt, rAF-Dauern sehen nur den Hauptthread). Headless rastert in Software: nur Vorher/Nachher auf derselben Maschine mit **denselben Argumenten** vergleichen (Median aus 3 Läufen; Grenzen in `docs/gui-konzept.md`, «Glas-Budget») |
| `kontrast.mjs` | `node tools/pruefung/kontrast.mjs [--alle] [--minimum [--quelle=card\|surface-2]] [--glas=0.74] [--glanz=0.07] [--stufen] [--json=datei]` | WCAG-Kontrast aller Theme- und Akzentblöcke aus `theme.css` samt Materialien (Alpha über bg/card/surface-2). Deckungen der Materialien liest es direkt aus `app.css` (darum sind sie Zahlenliterale). Exit 1 bei Pflichtverstoss; `--minimum` = kleinste Glas-Deckung je Theme, die Text über jeder Token-Unterlage lesbar hält (`--quelle=surface-2` für Material aus `surface-2`, z. B. `glass-overlay` dunkel); `--glanz=0.07` legt die Lichtschicht über jedes Material; `--stufen` prüft dunkel `bg` < `grund-hoch` < `card` < `surface-2` |
| `rundgang.mjs` | `node tools/pruefung/rundgang.mjs <email> [--max=150] [--start=/dashboard] [--shots=dir]` | Folgt allen internen Links einer Rolle; je Seite `UEBERLAUF`, `KLEIN` (<10 px), `ABGESCHNITTEN`, `ELLIPSE<90`, `RAND`, `VORFAHR-CLIP`, `UEBERLAPPUNG`, `STATUS`, `JS`. Exit 1 bei Befunden |

### Zustandsflags (shot, klick, rundgang, leistung; Kopfkommentar in `tools/pruefung/browser.mjs`)

| Flag | Wirkung |
|---|---|
| `--bewegung=reduziert` | `prefers-reduced-motion: reduce` (emulateMedia) plus `html[data-bewegung='reduziert']` |
| `--transparenz=reduziert` | `prefers-reduced-transparency: reduce` (CDP); weil `matchMedia` das danach nicht meldet, setzt das Werkzeug zusätzlich `html[data-transparenz='reduziert']` |
| `--kontrast=mehr` | `prefers-contrast: more` (emulateMedia) |

Nach jedem Laden gibt das Werkzeug eine Zustandszeile mit den `matchMedia`-Zuständen und `dataset.fenster` aus – ein Bild gilt nur als Prüfung dieses Zustands, wenn die Zeile ihn zeigt. Für die Abnahme eines Materials gehören dazu: dunkel 1920 und 2560, `--bewegung=reduziert`, `--transparenz=reduziert`, `--kontrast=mehr`, hell als Stichprobe (Grundsatz G16).

Konten: `nina.huber@demo.example` (Lernende), `michael.baumann@demo.example` (Berufsbildner),
`laura.frei@demo.example` (Admin). Start-Pfade: Lernende `/dashboard`, Berufsbildner `/trainer`, Admin `/admin`.

## Standardlauf nach UI-Änderungen
```bash
export NP_TEST_PW=$(grep -oP "DEMO_PASSWORT\s*=\s*'\K[^']+" database/seeders/DemoSeeder.php)
D=<scratch-ordner>
node tools/pruefung/shot.mjs nina.huber@demo.example "/dashboard,/grades" --dir=$D --name=l
node tools/pruefung/shot.mjs nina.huber@demo.example "/dashboard" --breite=2560 --hoehe=1440 --dir=$D --name=l
node tools/pruefung/rundgang.mjs nina.huber@demo.example
node tools/pruefung/rundgang.mjs michael.baumann@demo.example
node tools/pruefung/rundgang.mjs laura.frei@demo.example
```
Bilder mit `Read` ansehen – jedes, nicht nur das erste. Sichtprüfung nach Skill `notenportal-dunkelmodus`;
für viele Bilder Agent `bildpruefer` (sonnet high) oder Workflow `notenportal-dunkel-rundgang`.

## Bekannte, bereits behobene Fälle (Regressionsschutz, nicht neu reproduzieren)
- `/grades` «Neue Note»-Drawer: Formular wurde per `x-html` neu gerendert und verlor beim Tippen den
  Fokus (Commit 2d2d306, jetzt direktes Einfügen + Alpine-Observer).
- «Hinweis entfernen» / «Verbindung testen» (`admin/betrieb/_hinweis.blade.php`, `_kopie.blade.php`):
  Knopf sperrte sich synchron und schickte den falschen Wert – jetzt `setTimeout(() => loading = true)`.
- Kalender-Abo-Knopf für Betreuer/Admin: reagierte nicht (kein `x-data` in der Vorfahrenkette) –
  seit Commit 1cad406 verlinkt der Knopf direkt auf `/settings/calendar`.

## VM-Werkzeugkasten
Auf der VM liegt zusätzlich die ältere Sammlung `~/tools/visual` (tippen, knoepfe, lint-knopf, leer,
rollen, sprache, umbruch, abgeschnitten, zielgroesse, pruefen, bogen, breite, nutzung). Sie ist nicht
im Repo und in der Cloud nicht vorhanden; was davon gebraucht wird, wird nach `tools/pruefung/`
portiert (Passwort nur über `NP_TEST_PW`).
