# Nachtlauf — ein Portal, eine Sitzung

Ersetzt `docs/auftrag/ARBEITSTEILUNG.md`. Stand 30.09.2026, abends.

## Die Lage hat sich geändert

**Nichts ist mehr produktiv.** Es gibt keine schützenswerte Instanz, keine echten Daten, keine
Rücksicht auf Laufendes. Die Datenbank ist leer und darf überall hin. David klont morgen früh das
Repository frisch auf einen neuen Ubuntu-Server und führt `sudo ./install.sh` aus.

**Das ist das einzige Abnahmekriterium:**

```bash
git clone https://github.com/magic-usb-c/notenportal.git
cd notenportal && sudo ./install.sh
```

Frischer Server, ein Befehl, danach ein Portal, das anmeldet, einrichtet, rechnet und gut aussieht.
Wenn das morgen früh nicht sauber durchläuft, war die Nacht umsonst — egal wie viel Code entstanden
ist.

## Eine Sitzung, nicht vier

Vier parallele Sitzungen ergeben vier Branches, die auseinanderlaufen, und am Ende vier Portale,
die sich widersprechen. Das ist ausdrücklich nicht gewollt.

**Stattdessen: eine einzige Sitzung, die auf `main` arbeitet und Subagenten fächerförmig einsetzt.**
Subagenten teilen sich den Arbeitsbaum der Sitzung — sie können nichts auseinanderlaufen lassen.
Die Parallelität kommt von ihnen, die Kohärenz von der Sitzung, die sie führt.

Die Sitzung darf und soll jederzeit mehrere Subagenten gleichzeitig starten: recherchieren,
suchen, prüfen. Implementieren lässt sie am besten nacheinander oder an klar getrennten
Verzeichnissen, damit sich zwei Agenten nicht dieselbe Datei umschreiben.

Kein Worktree, kein Feature-Branch, kein Force-Push. Committen nach jedem lauffähigen Stand,
pushen auf `main`.

## Reihenfolge

Streng von oben nach unten. Jeder Schritt lässt das Portal lauffähig zurück und wird committet.

1. **Notenbaum** — `docs/auftrag/NOTENBAUM.md`. Die Vornote ist heute falsch berechnet; das ist der
   einzige Fehler, der das Produkt in seinem Kern kaputt macht. Zuerst die Abnahmefälle als Tests,
   dann das Schema, dann der Rechenkern.
2. **Stammdaten nutzbar machen** — Lücken 7 und 8 aus `LAGE.md`: der Einrichtungsassistent
   verlinkt die echte Fächerpflege nicht, und Fächer lassen sich nicht löschen. Der Produkteigner
   hat heute daran aufgegeben. Dazu: Vorauswahl-Listen raus aus dem Code, rein in ladbare Vorlagen
   (Italienisch statt Französisch, Bündner Realität).
3. **Oberfläche** — `docs/auftrag/GUI-APPLE.md`. Der grosse sichtbare Gewinn.
4. **Installation härten** — `install.sh` auf einem wirklich frischen System durchspielen, jeden
   Pfad prüfen, den Einrichtungsassistenten bis zum Abschluss durchlaufen.
5. **Aufräumen** — Tests, `docs/rueckmeldungen-2026-09-12.md`, Backlog A, i18n, Dokumentation auf
   Stand.

Wenn die Zeit knapp wird, gilt: lieber 1 bis 3 fertig und bewiesen als alles fünf halb.

## Was «fertig» heisst

Nicht «der Code ist geschrieben», sondern:

- `php artisan test` vollständig grün, keine übersprungenen Tests, die etwas verstecken.
- Die Abnahmefälle aus `NOTENBAUM.md` Abschnitt 3 liefern exakt die dort genannten Zahlen.
- Ein frischer Durchlauf von `install.sh` endet mit einem erreichbaren Portal.
- Der Einrichtungsassistent lässt sich von Schritt 1 bis 8 durchklicken, ohne dass man eine URL
  von Hand eintippen muss.
- Ein Lernender kann sich anmelden, eine Note erfassen und sieht einen plausiblen Schnitt.
- Agent `pruefer` hat jeden Block gegengelesen und seine Befunde sind abgearbeitet.

## Fortschritt festhalten

Nach jedem Block ein Eintrag in `docs/auftrag/UEBERGABE.md`. Was committet und gepusht ist, ist
sicher; was nur im Chatverlauf steht, ist weg, sobald die Sitzung endet. Deshalb lieber einmal zu
oft committen.
