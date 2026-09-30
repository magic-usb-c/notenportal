# Auftrag: Oberfläche nach Apple-Art

Ergänzt `docs/gui-konzept.md`. Ziel ist nicht, wie macOS auszusehen, sondern nach denselben
Grundsätzen entschieden zu sein.

## Quelle

`https://developer.apple.com/design/` — Human Interface Guidelines. **Selbst lesen, nicht aus dem
Gedächtnis arbeiten.** Besonders: Foundations (Layout, Typography, Color, Materials, Accessibility),
Patterns (Navigation, Feedback, Loading, Entering data), Components (Sidebar, Toolbar, Lists and
tables, Forms).

Übertragen auf eine Web-Anwendung heisst das: verstehen, *warum* dort etwas so entschieden wurde,
und dieselbe Frage für Blade und Tailwind beantworten. Nicht Screenshots nachbauen.

## Harte Grenzen

- **SF Pro und SF Symbols dürfen nicht eingebettet werden.** Lizenz. Eine gut gewählte freie
  Schrift mit derselben Haltung ist der richtige Weg; die System-Schriftfamilie darf als erster
  Eintrag im Font-Stack stehen, damit Apple-Geräte ihre eigene benutzen.
- **Apple-Systemfarben nicht hart kodieren.** Alles läuft über die bestehenden Tokens.
- Der Seitencontainer heisst `np-seite`, nie `max-w-7xl`.
- Kein Farb-Hardcode irgendwo. Der Hook `.claude/hooks/view-pruefung.sh` meckert sonst zu Recht.
- Hell und Dunkel müssen beide vollständig tragen, mit denselben Tokens.
- UI-Texte Schweizer Hochdeutsch, **ss statt ß**, keine Erklärtexte und keine Entwicklernotizen in
  der Oberfläche.

## Was zu tun ist

Vor jeder Arbeit an Views oder CSS: Skill `notenportal-ui`.

Geh die drei Rollenbereiche selbst durch — Lernender, Berufsbildner, Admin — und arbeite die
Stellen ab, an denen die Oberfläche unruhig, unklar oder auf kleinen Bildschirmen kaputt ist.
Prüf jeden Bereich in hell und dunkel und auf schmalen Fenstern.

Worauf es dabei besonders ankommt:

- **Hierarchie statt Rahmen.** Nicht alles ist eine Karte. Abstand, Grösse und Gewicht tragen die
  Gliederung; Rahmen und Schatten werden gezielt für das ausgegeben, was wirklich hervorstehen soll.
- **Dichte mit Luft.** Tabellen und Listen dürfen dicht sein, aber die Zeilenhöhe und die
  Ausrichtung der Zahlen müssen stimmen — Ziffernspalten mit `tabular-nums`.
- **Ein Akzent.** Farbe bedeutet etwas oder sie ist weg. Semantische Farben für gut, knapp,
  ungenügend bleiben getrennt vom Akzent des Themes.
- **Die Oberfläche erklärt sich durch Anordnung**, nicht durch Hinweistexte.
- **Tastatur und Fokus**: sichtbarer Fokusring überall, `prefers-reduced-motion` respektiert.
- **Zustände**: leer, lädt, Fehler, viel zu viele Daten. Alle vier müssen gut aussehen, nicht nur
  der Normalfall.

## Ein Entscheid, der nicht der Sitzung gehört

Ob die Topbar-Navigation nach macOS-Art in eine **Seitenleiste** wandert, hat David noch nicht
entschieden. Die HIG sprechen dafür, und es wäre der grösste einzelne Gewinn.

Vorgehen: bauen, aber umschaltbar und ohne die Topbar zu löschen. Wenn das nicht sauber geht, einen
Vorschlag mit Skizze ablegen und unter «Offen für David» ins Übergabebrett schreiben. Nicht
kommentarlos das Bestehende wegwerfen.

## Beweis

Screenshots der drei Rollen in hell und dunkel, bei 390 px, 768 px und 1440 px, keine
horizontale Scrollleiste, kein abgeschnittener Text. Werkzeuge: `~/tools/visual`
(Skill `notenportal-pruefwerkzeuge`). In einer Cloud-Sitzung ohne laufende Instanz stattdessen
die Views lesen und die Prüfung als offenen Punkt vermerken — nicht behaupten, es sei geprüft.
