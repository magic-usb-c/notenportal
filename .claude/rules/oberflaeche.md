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
- Schweizer Hochdeutsch, ss statt ß, keine Hinweistexte oder Entwicklernotizen in der Oberfläche.
- Nach CSS/JS-Änderungen `npm run build`. Jede sichtbare Änderung mit `tools/pruefung/shot.mjs`
  (dunkel, 1920) ansehen und das Bild mit `Read` beurteilen, bevor sie als fertig gilt.
