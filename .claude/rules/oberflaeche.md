---
paths:
  - "resources/views/**/*.blade.php"
  - "resources/css/**"
  - "resources/js/**"
---

# Oberfläche – gilt bei jeder Änderung an Views, CSS, JS

- Zuerst Skill `notenportal-ui` (Tokens, Typografie, Flächen, Muster) und `notenportal-dunkelmodus` laden.
- Massstab: Desktop-Browser 1920×1080 bis 2560×1440 im Dunkelmodus nach Apple HIG. Hell und schmal
  dürfen nicht brechen, bekommen aber keine eigene Arbeit.
- Keine Farb-Hardcodes, keine Tailwind-Palettenfarben für Bedeutung, keine harten Schriftgrössen,
  kein `text-white`/`text-accent` für Text, kein Glas auf Karten/Tabellen/Formularen.
- Glas nur funktional (Seitenleiste, Symbolleiste, Menüs, Overlays), nie auf Karten, Tabellen, Formularen,
  Diagrammen; kein Glas auf Glas. Deckungen von Materialien sind Zahlenliterale in `app.css` (erstes Literal
  je Modus im `@utility`-Block; `kontrast.mjs` liest sie dort); Licht-, Kanten-, Schatten-Farben nur als Token.
  Jedes neue Material braucht die Varianten `deckend:` (reduzierte Transparenz) und `kontrastreich:`.
- Reduzierte Bewegung: Variante `ruhig:` statt `motion-reduce:`; Dauern, Wege und Skalierung aus den Tokens
  (`--dauer-*`, `--np-weg`, `--np-skala-von`); in JS nur `bewegungRuhig()`. `view-transition-name` nur auf
  leeren Marker-Spans (`np-leiste-auswahl`, `np-tab-auswahl`), nie auf Containern oder Zeilen.
- Diagramme: Notenachse fest 1–6, Notenbalken ab 1, Anzahlen und Säulen ab 0, Veränderung als Hantel;
  Satz mit echten Werten, Tabelle in `<details>`, Stufe nie nur als Farbe.
- Schweizer Hochdeutsch, ss statt ß, keine Hinweistexte oder Entwicklernotizen in der Oberfläche.
- Nach CSS/JS-Änderungen `npm run build`. Jede sichtbare Änderung mit `tools/pruefung/shot.mjs`
  (dunkel, 1920) ansehen und das Bild mit `Read` beurteilen, bevor sie als fertig gilt.
  Materialien zusätzlich mit `--bewegung=reduziert --transparenz=reduziert --kontrast=mehr`, `kontrast.mjs`
  (Exit 0) und `leistung.mjs` (Glas-Budget in `docs/gui-konzept.md`).
