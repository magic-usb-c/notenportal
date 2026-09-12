---
name: notenportal-pruefwerkzeuge
description: Automatisierte Playwright-Prüfwerkzeuge in ~/tools/visual (tippen, knoepfe, lint-knopf, leer, rollen, sprache, umbruch, pruefen, bogen) – wann welchen Check laufen lassen, Sicherheitsregeln, haiku-Bildprüfung. Laden, bevor Notenportal-UI-Änderungen geprüft oder ein Verdacht auf einen der bekannten Bug-Muster abgeklärt wird.
---

# Notenportal-Prüfwerkzeuge

Liegen unter `~/tools/visual/` (ausserhalb des Repos, Node + Playwright, `node_modules` schon da). Deterministische Skripte zuerst, haiku nur für vorgefilterte Screenshots – so bleibt der Tokenverbrauch klein.

## Sicherheit (gilt für JEDES Skript, immer)
- Standardziel ist Prod (`http://127.0.0.1`). Passwort **nur** über `NP_TEST_PW` (env), nie im Code/CLI/Log.
- Jeder Check ruft `schreibschutz(page)` (aus `lib/np.mjs`) auf: alle nicht-GET-Requests werden abgefangen und abgebrochen – nichts schreibt je auf Prod.
- Nie Logout-/Löschen-Selektoren anklicken (`istVerboten()`/`VERBOTENE_SELEKTOREN` in `lib/np.mjs`).
- Pool/Kontexte klein halten (max. 3 gleichzeitige Seiten, kleiner Server).
- Eingeloggte Nutzer bekommen ihre Sprache aus `user.locale` (DB) – ohne echten Schreibzugriff gibt es keinen Weg, sie für einen Test auf Englisch umzuschalten. Deshalb prüft `sprache.mjs` Übersetzungslücken statisch (Blade-Quellen gegen `lang/en.json`) statt live umzuschalten; nur Gastseiten (Login) werden live mit `Accept-Language: en` geprüft.

## Welcher Check wann

| Check | Findet | Wann laufen lassen |
|---|---|---|
| `tippen.mjs` | Drawer/Modal-Formulare, in denen ein Feld beim Tippen Fokus/Wert verliert (Alpine-Re-Render-Bug) | Nach jeder Änderung an Drawern/Modalen mit Formularen |
| `knoepfe.mjs` | Benannter Submit-Knopf (`name`+`value`), der wegen synchronem `:disabled` seinen Wert nicht mitschickt | Nach Änderungen an Formularen mit mehreren Submit-Knöpfen |
| `lint-knopf.mjs` | Statischer Zwilling zu `knoepfe.mjs`: `loading = true` synchron gesetzt + benannter Knopf daran gebunden | Schnell, ohne Login – vor jedem Commit an Formular-Views |
| `leer.mjs` | Fast einfarbige/leere Seiten (>97 % dominante Farbe), sichtbar gebliebene `[x-cloak]`, JS-Fehler beim Laden | Nach Layout-/Theme-Änderungen, bei Verdacht auf kaputten Screenshot/leere Seite |
| `rollen.mjs` + `funktionen.json` | Eine Funktion (Klick → erwartetes Element) funktioniert für eine Rolle nicht (z.B. fehlendes `x-data`) | Bei rollenabhängigen Buttons/Aktionen; neue Einträge in `funktionen.json` ergänzen |
| `sprache.mjs` | `__()`-Strings ohne Eintrag in `lang/en.json` (blieben im Englisch-Modus Deutsch), fehlende `validation.php`-Attribute | Nach neuen Views/Texten, vor Releases |
| `umbruch.mjs` | Knöpfe/Links/Nav/Tabellenköpfe/Tabs/Badges, die bei 390/1024/1440/2560px umbrechen oder abgeschnitten werden | Nach Layout-/Breakpoint-Änderungen |
| `pruefen.mjs` | Orchestriert alle obigen Checks als eigene kurze Prozesse (speicherschonend) | `node pruefen.mjs all` für einen Gesamtdurchlauf |
| `bogen.mjs` | Baut aus markierten Screenshots einen 3×3-Bogen (400px/Kachel) für die haiku-Sichtprüfung | Nach `leer.mjs`, wenn Bilder zur Sichtprüfung anfallen |

Aufrufmuster (alle Checks gleich): `node <check>.mjs [--rolle=learner|trainer|admin] [--breite=1280] [--prod|--demo] [--ziel=/pfad]`.

## haiku-Bildprüfung (nur für vorgefilterte Screenshots aus `leer.mjs`/`bogen.mjs`)

Nie ganze Screenshot-Ordner an ein Modell schicken – erst `leer.mjs` filtert (nur auffällige Seiten), dann `bogen.mjs` bündelt bis zu 9 Bilder in einen Bogen. Für den Bogen an haiku genau dieser Prompt:

```
Bild zeigt einen 3×3-Bogen nummerierter Screenshots (1–9, manche Zellen können leer sein).
Beantworte JEDE Kachel mit GENAU einer Zeile, keine Prosa, kein Vorspann:
  <nr> ok
oder
  <nr> ✗<frage-nr> <Befund in ≤12 Wörtern>

Fragen (nur die Nummer zitieren):
1 leer/einfarbig?
2 Text abgeschnitten/überlappend?
3 Deutsch, obwohl Englisch erwartet?
4 unschöner Zeilenumbruch?
5 Kontrast unlesbar?
6 Layout sichtbar kaputt (verschoben/übereinander)?
```

Beispiel-Antwort:
```
1 ok
2 ✗1 nur Hintergrundfarbe, kein Inhalt
3 ok
4 ✗4 Knopf "Kalender-Abo" bricht in zwei Zeilen
5 ok
6 ok
7 ✗6 Drawer überlappt Navigation
8 ok
9 ok
```

## Bekannte, bereits behobene Fälle (Regressionsschutz, nicht neu reproduzieren)
- `/grades` „Neue Note“-Drawer: Formular wurde per `x-html` neu gerendert und verlor beim Tippen den Fokus (Commit 2d2d306, jetzt direktes Einfügen + Alpine-Observer). `tippen.mjs` muss hier grün bleiben.
- „Hinweis entfernen“ / „Verbindung testen“ (`admin/betrieb/_hinweis.blade.php`, `_kopie.blade.php`): Knopf sperrte sich synchron und schickte den falschen Wert – jetzt `setTimeout(() => loading = true)`. `knoepfe.mjs`/`lint-knopf.mjs` müssen hier grün bleiben.
- Kalender-Abo-Knopf für Betreuer/Admin (`verwaltung/pruefungen/index.blade.php`): reagierte nicht (kein `x-data` in der Vorfahrenkette) – seit Commit 1cad406 verlinkt der Knopf stattdessen direkt auf `/settings/calendar`. `rollen.mjs`/`funktionen.json` (Eintrag `kalender-abo`) prüfen das für alle drei Rollen.
- Reale, aktuell offene Auffälligkeit: `tippen.mjs`/`leer.mjs` melden auf jeder eingeloggten Seite einen Alpine-Fehler `routeName is not defined` (Feedback-Widget, `resources/views/components/feedback-widget.blade.php` Zeile 168: `x-text="routeName ?? pfad"` – beide Variablen existieren nicht im äusseren `feedbackDialog()`-Scope, nur als Closure-Werte in `senden()`). Kein Fund der Prüfwerkzeuge, sondern ein echter, noch offener Bug.

## Output-Format
Alle Checks nutzen `lib/np.mjs`: `✗ <check> <role> <width> <path> <detail>` je Fund, eine Zusammenfassungszeile, JSON unter `~/tools/out/checks/<datum>/<check>.json`. Exit-Code 0 = keine Funde, 1 = Funde vorhanden.
