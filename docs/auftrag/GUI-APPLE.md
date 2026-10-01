# Auftrag: Oberfläche nach Apple-Art

Ergänzt `docs/gui-konzept.md`. Ziel ist nicht, wie macOS auszusehen, sondern nach denselben
Grundsätzen entschieden zu sein. Stand 01.10.2026.

## Massstab

**Desktop-Browser, 1920×1080 bis 2560×1440, Dunkelmodus.** Alle Personen, die das Portal benutzen,
arbeiten so. Der Dunkelmodus wird nach den Human Interface Guidelines perfektioniert; der helle Modus
und schmale Fenster müssen mit denselben Tokens funktionieren, bekommen aber keine eigene
Gestaltungsarbeit und keine Abnahme. Keine Mobile- und keine Touch-Kriterien mehr.

## Quelle

`https://developer.apple.com/design/human-interface-guidelines/` – **selbst lesen, nicht aus dem
Gedächtnis arbeiten.** Die HTML-Seiten liefern nur eine JavaScript-Hülle; der Inhalt derselben Seite
liegt unter `https://developer.apple.com/tutorials/data/design/human-interface-guidelines/<seite>.json`.
Die für den Dunkelmodus massgebenden Regeln stehen mit Quelle im Skill `notenportal-dunkelmodus`,
die Tokens und Muster im Skill `notenportal-ui`. Besonders: Dark Mode, Color, Materials, Typography,
Layout, Accessibility, Sidebars, Toolbars, Lists and tables, Entering data, Feedback, Modality.

Übertragen auf eine Web-Anwendung heisst das: verstehen, *warum* dort etwas so entschieden wurde,
und dieselbe Frage für Blade und Tailwind beantworten. Nicht Screenshots nachbauen.

## Harte Grenzen

- **SF Pro und SF Symbols dürfen nicht eingebettet werden.** Apples Font-Lizenz erlaubt die
  Schriften nur für Mockups von Software auf Apple-Plattformen (`https://developer.apple.com/fonts/`).
  Inter Variable ist gesetzt; die System-Schriftfamilie darf als erster Eintrag im Font-Stack
  stehen, damit Apple-Geräte ihre eigene benutzen.
- **Apple-Systemfarben nicht hart kodieren.** Apple sagt selbst, die Werte schwanken je Version.
  Alles läuft über die bestehenden Tokens in `resources/css/theme.css`.
- Der Seitencontainer heisst `np-seite`, nie `max-w-7xl`.
- Kein Farb-Hardcode irgendwo. Der Hook `.claude/hooks/view-pruefung.sh` meckert sonst zu Recht.
- UI-Texte Schweizer Hochdeutsch, **ss statt ß**, keine Erklärtexte und keine Entwicklernotizen in
  der Oberfläche.

## Was zu tun ist

Vor jeder Arbeit an Views oder CSS: Skills `notenportal-ui` und `notenportal-dunkelmodus`.

Geh die drei Rollenbereiche selbst durch – Lernende, Berufsbildner, Admin – und arbeite die Stellen
ab, an denen die Oberfläche im Dunkeln unruhig, unklar oder bei 1920 und 2560 px schlecht
ausgenutzt ist.

Worauf es dabei besonders ankommt:

- **Tiefe über Helligkeit, nicht über Schatten.** Grund, Karte, Tabellenkopf und Overlay sind vier
  erkennbar verschiedene Helligkeiten; Schatten tragen im Dunkeln fast nichts.
- **Glas nur für die funktionale Ebene** (Navigation, Toolbar, Menüs, Toasts). Karten, Tabellen,
  Formulare und Diagramme sind matt.
- **Hierarchie statt Rahmen.** Nicht alles ist eine Karte. Abstand, Grösse und Gewicht tragen die
  Gliederung; Rahmen werden gezielt für das ausgegeben, was wirklich hervorstehen soll.
- **Dichte mit Luft.** Tabellen und Listen dürfen dicht sein, aber Zeilenhöhe und Ausrichtung der
  Zahlen müssen stimmen – Ziffernspalten mit `tabular-nums`, rechtsbündig.
- **Ein Akzent.** Farbe bedeutet etwas oder sie ist weg. Genau eine Primäraktion je Ansicht;
  semantische Farben für gut, knapp, ungenügend bleiben getrennt vom Akzent des Themes und tragen
  ihre Bedeutung immer zusätzlich über Form oder Text.
- **Die Oberfläche erklärt sich durch Anordnung**, nicht durch Hinweistexte.
- **Tastatur und Fokus:** sichtbarer Fokusring überall (auch im Dunkeln ≥ 3:1), Escape schliesst,
  `prefers-reduced-motion` respektiert.
- **Zustände:** leer, lädt, Fehler, viel zu viele Daten. Alle vier müssen gut aussehen, nicht nur
  der Normalfall.
- **Nichts Wichtiges am unteren Fensterrand** (macOS-Regel: Fenster werden unter die Kante
  geschoben) – kein fixierter Fuss mit Primäraktion.

## Entschieden

Die Hauptnavigation ist eine **Seitenleiste** nach HIG und je Person umschaltbar auf die Tableiste
oben (Einstellungen → Darstellung). Standard für neue Konten ist die Seitenleiste
(`app/Support/Darstellung.php`, `NAVIGATION_SEITE`). Die Topbar bleibt als Variante erhalten.

## Beweis

Für jeden Bereich, der angefasst wurde:

1. `node tools/pruefung/rundgang.mjs <email>` je betroffener Rolle mit Exit 0 (keine JS-Fehler,
   kein Überlauf, kein abgeschnittener Text, keine Querscrollleiste), bei 1920 px.
2. Screenshots der betroffenen Seiten **dunkel bei 1920 und 2560 px** mit
   `node tools/pruefung/shot.mjs <email> "/pfad" --breite=1920|2560`, jedes Bild mit `Read`
   angesehen und gegen die Prüfliste in `notenportal-dunkelmodus` §5 beurteilt (Agent `bildpruefer`).
3. Hell einmal stichprobenartig (`--hell`): darf nicht brechen.
4. Vor einem Blockabschluss der Workflow `notenportal-dunkel-rundgang` über alle drei Rollen.

Werkzeuge liegen im Repo unter `tools/pruefung/` (Skill `notenportal-pruefwerkzeuge`); der
Demo-Server kommt aus `tools/pruefung/demo-server.sh`, das Passwort nur aus `NP_TEST_PW`. Ohne
laufenden Server wird nichts als geprüft gemeldet – dann die Prüfung als offenen Punkt ins
Übergabebrett schreiben.
