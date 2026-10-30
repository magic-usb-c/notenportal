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
- Schweizer Hochdeutsch, ss statt Eszett, keine Hinweistexte oder Entwicklernotizen in der Oberfläche.
- Nach CSS/JS-Änderungen `npm run build`. Jede sichtbare Änderung mit `tools/pruefung/shot.mjs`
  (dunkel, 1920) ansehen und das Bild mit `Read` beurteilen, bevor sie als fertig gilt.
- Glas nur in der funktionalen Ebene (Seitenleiste, Symbolleiste, Kapseln, Menüs, Palette, Toasts); Weichzeichner
  nur über Inhalt, nie Glas auf Glas. Deckungen sind Zahlen-Literale in `app.css` (kein `var()`), jedes Material
  bringt `deckend:` und `kontrastreich:` mit.
- Reduzierte Bewegung ist die Variante `ruhig:` (ersetzt `motion-reduce:`); Dauern und Wege über Tokens
  (`--dauer-*`, `--np-weg`), Knöpfe skalieren nie. `view-transition-name` nur auf den leeren Marker-Spans
  `np-leiste-auswahl` und `np-tab-auswahl`.
- Diagramme: Notenachse fest 1 bis 6, Notenbalken ab 1, Anzahlen ab 0; Zusammenfassung mit echten Werten,
  Tabelle in `<details>`, Stufe nie nur als Farbe.
