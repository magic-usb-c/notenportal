---
name: notenportal-pruefwerkzeuge
description: Browser-Prüfwerkzeuge im Repo (tools/pruefung – shot, klick, clip, rundgang, leistung, kontrast, vergleich, demo-server) für Screenshots, Layoutbefunde, Bildzeiten und Kontraste im Dunkelmodus auf Desktop-Breiten, mit den Zustandsflags für reduzierte Bewegung, Transparenz und mehr Kontrast, Sicherheitsregeln, bekannte Regressionsmuster. Laden, bevor Notenportal-UI-Änderungen geprüft werden.
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
| `shot.mjs` | `node tools/pruefung/shot.mjs <email> "/pfad,/pfad2" [--breite=1920] [--hoehe=1080] [--hell] [--fenster] [--name=x] [--dir=.] [Zustandsflags]` | Screenshot je Pfad (ganze Seite), HTTP-Status, `UEBERLAUF`, JS-Fehler, Zustandszeile |
| `klick.mjs` | `node tools/pruefung/klick.mjs <email> /pfad "sel1,sel2" [--dir=.] [Zustandsflags]` | Zustand nach Klicks (Menü, Tab, Drawer) als Fenster-Screenshot |
| `clip.mjs` | `node tools/pruefung/clip.mjs <email> /pfad x,y,breite,hoehe --out=datei.png [--open=<selektor>]` (`NP_EVAL='<js>'` wertet vor dem Bild aus und gibt aus) | Ausschnitt statt Vollbild: Scrollkanten, Tabellenköpfe, Fokusringe, `getComputedStyle`-Werte |
| `leistung.mjs` | `node tools/pruefung/leistung.mjs <email> "/pfad,/pfad2" [--frames=150] [--laeufe=3] [--palette] [--menue=<Selektor>] [--ohne-glas] [--trace] [--json=datei] [Zustandsflags]` | Je Seite LCP/Load, DOM-Knoten, sichtbare Glasflächen (Anzahl, % des Fensters), Bilddauer p50/p95/max in Ruhe und beim Scrollen, lange Bilder > 33.4 ms, Layout-/Stil-/Skriptzeit (CDP); `--palette` misst Strg+K, Tippen, Escape; `--menue=<Selektor des Auslösers>` klickt ihn und misst Glasanteil und Hover im offenen Menü (`menue.glas`, `menue.hover`); `--ohne-glas` setzt `data-transparenz='reduziert'`; `--trace` summiert Raster-, Paint- und Composite-Zeit. Headless rastert in Software: nur Vorher/Nachher auf derselben Maschine mit denselben Argumenten vergleichen |
| `vergleich.mjs` | `node tools/pruefung/vergleich.mjs --vorher=a.json[,a2.json] --nachher=b.json[,b2.json]` | Markdown-Tabelle vorher/nachher/Differenz aus zwei `leistung.mjs --json`-Ausgaben |
| `r6-vergleich.sh` | `tools/pruefung/r6-vergleich.sh <vorher-url> <nachher-url> <ordner>` | Vorher/nachher je Rolle auf derselben Maschine (Median aus 3 Läufen, 1920×1080 mit Palette und 1920×540 Scroll); nur in einem ruhigen Fenster, ohne laufende Agents oder Testsuite |
| `kontrast.mjs` | `node tools/pruefung/kontrast.mjs [--alle] [--minimum [--quelle=card\|surface-2]] [--glas=0.7] [--glanz=0.07] [--stufen] [--json=datei]` | WCAG-Kontrast aller 24 Theme-Blöcke und 14 Akzentblöcke aus `theme.css` samt Materialien (Deckungen direkt aus den `@utility`-Blöcken in `app.css`). Exit 1 bei Pflichtverstoss. `--minimum` = kleinste Glas-Deckung je Theme, die Text über jeder Token-Unterlage lesbar hält, `--quelle=surface-2` für Material aus `surface-2`; `--glanz=a` legt die Lichtschicht über jedes Material; `--stufen` prüft dunkel bg < grund-hoch < card < surface-2; Fokusring auf jedem Material und Glaskante gegen `surface-2` je ≥ 3:1. Liest nur Dateien, braucht keinen Build und keinen Server |
| `rundgang.mjs` | `node tools/pruefung/rundgang.mjs <email> [--max=150] [--start=/dashboard] [--shots=dir] [Zustandsflags]` | Folgt allen internen Links einer Rolle; je Seite `UEBERLAUF`, `KLEIN` (<10 px), `ABGESCHNITTEN`, `ELLIPSE<90`, `RAND`, `VORFAHR-CLIP`, `UEBERLAPPUNG`, `STATUS`, `JS`. Exit 1 bei Befunden |

**Zustandsflags** (an `shot`, `klick`, `rundgang`, `leistung`; Pflicht bei Arbeit an Material, Bewegung und Kontrast, weil jede Seite in jeder Einstellung lesbar bleiben muss):
- `--bewegung=reduziert` – `prefers-reduced-motion: reduce` und zusätzlich `html[data-bewegung='reduziert']` (Variante `ruhig:`).
- `--transparenz=reduziert` – `prefers-reduced-transparency: reduce` per CDP; Playwright kann die Medienabfrage nicht über `matchMedia` sichtbar machen, darum setzt das Werkzeug zusätzlich `html[data-transparenz='reduziert']` und meldet «Ersatz: data-transparenz» (Variante `deckend:`).
- `--kontrast=mehr` – `prefers-contrast: more` (Variante `kontrastreich:`).
- Jede Seite druckt eine **Zustandszeile**: `reduced-motion`, `contrast-more`, `reduced-transparency` und `fenster` (`document.documentElement.dataset.fenster`, `aktiv` oder `inaktiv`; das Werkzeug holt das Fenster mit `bringToFront()` nach vorn). Erst die Zeile prüfen, dann das Bild werten.

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
# Material, Bewegung, Kontrast: dieselbe Seite in den drei Einstellungen
node tools/pruefung/shot.mjs nina.huber@demo.example "/dashboard" --bewegung=reduziert --transparenz=reduziert --kontrast=mehr --dir=$D --name=z
node tools/pruefung/kontrast.mjs && node tools/pruefung/kontrast.mjs --stufen && node tools/pruefung/kontrast.mjs --minimum
```
Bildzeiten (Glas-Budget in `docs/gui-konzept.md`), nur auf einer ruhigen Maschine und immer mit denselben Argumenten vor und nach der Änderung:
```bash
node tools/pruefung/leistung.mjs nina.huber@demo.example "/dashboard,/grades" --laeufe=3 --palette --json=$D/nachher.json
node tools/pruefung/leistung.mjs nina.huber@demo.example "/grades" --hoehe=540 --laeufe=3
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
