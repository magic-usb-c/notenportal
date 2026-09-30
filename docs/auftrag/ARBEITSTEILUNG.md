> **Überholt seit 30.09.2026 abends.** Es wird nicht mehr in mehreren Sitzungen und Worktrees
> gearbeitet, sondern in **einer** Sitzung auf `main` mit Subagenten — siehe
> `docs/auftrag/NACHTLAUF.md`. Nichts ist produktiv. Die Verzeichnis-Zuordnung unten bleibt nur als
> Hinweis stehen, welche Bereiche fachlich zusammengehören.

# Arbeitsteilung für parallele Sitzungen

Mehrere Claude-Code-Sitzungen am selben Repository funktionieren nur mit klarem Besitz. Diese Datei
regelt, wer was anfassen darf. Sie ist bindend — wer sie verletzt, erzeugt Merge-Konflikte, die
David von Hand auflösen muss.

---

## 1. Eiserne Regel

**Niemand arbeitet direkt in `/var/www/notenportal`.** Diese Arbeitskopie ist Produktion. Am
11.09.2026 haben halbfertige Agent-Änderungen dort zweimal alle Seiten lahmgelegt.

Jede Sitzung arbeitet in einem eigenen Git-Worktree auf einem eigenen Branch:

```bash
claude --worktree notenbaum      # legt .claude/worktrees/notenbaum an
```

Nur David merged nach `main`. Kein Force-Push auf `main`, nie.

---

## 2. Besitz je Sitzung

Eine Sitzung ändert ausschliesslich Dateien in ihren Verzeichnissen. Braucht sie eine Änderung
ausserhalb, schreibt sie eine Zeile nach `docs/auftrag/UEBERGABE.md` statt zu editieren.

### Sitzung K — Kern und Notenlogik (lokal, Opus)

Auftrag: `docs/auftrag/NOTENBAUM.md`.

| Besitzt | |
|---|---|
| `app/Services/Auswertung/` | Rechenkern, Rundung, Promotion |
| `app/Models/` | |
| `database/migrations/`, `database/seeders/` | |
| `docs/notenlogik.md` | fachliche Referenz |
| `tests/Unit/`, `tests/Feature/Noten*`, `tests/Feature/Promotion*` | |

**Muss lokal laufen.** Braucht MariaDB, `notenportal_probe`, `php artisan notenportal:migrate`
und die Prod-Datenbank für die Abnahme. Eine Cloud-Sitzung kann das nicht.

### Sitzung V — Verwaltung und Stammdaten (lokal oder Cloud, Sonnet)

Auftrag: Lücken 7 und 8 aus `docs/auftrag/LAGE.md`, danach Punkt 3 aus Abschnitt 7.

| Besitzt | |
|---|---|
| `app/Http/Controllers/Admin/`, `app/Http/Controllers/Verwaltung/` | |
| `app/Support/Einrichtung.php`, `app/Support/Navigation.php` | |
| `resources/views/admin/` | |
| `tests/Feature/Admin*`, `tests/Feature/Einrichtung*` | |

Erste Aufgaben, konkret:
1. Der Einrichtungsschritt «Lehrberufe & Fächer» bietet nur Ankreuzen. Er muss sichtbar auf
   Admin → Stammdaten → Fächer verweisen, wo Anlegen und Umbenennen wirklich geht. Nutzer glauben
   sonst, Fächer seien gar nicht pflegbar.
2. Fächer löschen ist nicht möglich (`routes/web.php:293-302` hat kein `destroy`). Ergänzen —
   mit Schutz, wenn schon Noten daran hängen.
3. Die Fächer-Vorauswahl in `app/Support/Einrichtung.php:52` enthält Französisch. In Graubünden
   ist die zweite Landessprache Italienisch. Die Liste gehört nicht in den Code, sondern in eine
   ladbare Vorlage (siehe `NOTENBAUM.md` Schritt 4).

### Sitzung O — Oberfläche (Cloud geeignet, Opus)

Auftrag: `docs/gui-konzept.md`, Apple Human Interface Guidelines.

| Besitzt | |
|---|---|
| `resources/views/` **ausser** `admin/` | |
| `resources/css/`, `resources/js/` | |
| `docs/gui-konzept.md` | |

Vor jeder Arbeit Skill `notenportal-ui` laden. Keine Farb-Hardcodes, kein `max-w-7xl` — der
Seitencontainer heisst `np-seite`. Offener Entscheid, den David noch nicht gefällt hat: ob die
Topbar-Navigation nach macOS-Art in eine Seitenleiste wandert. **Nicht eigenmächtig umbauen** —
einen Vorschlag mit Begründung nach `docs/auftrag/UEBERGABE.md`.

### Sitzung Q — Qualität, Tests, Dokumentation (Cloud geeignet, Sonnet)

| Besitzt | |
|---|---|
| `tests/` ausser den oben zugeteilten | |
| `docs/` ausser `notenlogik.md`, `gui-konzept.md`, `auftrag/` | |
| `.claude/` | Agents, Skills, Hooks |
| `README.md`, `CONTRIBUTING.md` | |

Aufgaben: `docs/rueckmeldungen-2026-09-12.md` abarbeiten, Backlog A aus `docs/audit-backlog.md`,
`notenportal:i18n-scan` senken, Testabdeckung der Rollentrennung, `docs/betrieb.md` nachführen.

---

## 3. Was eine Cloud-Sitzung nicht kann

Cloud-Sitzungen laufen auf Anthropic-Infrastruktur und klonen das GitHub-Repository. Sie haben
Netzzugang nur auf eine Freigabeliste (Paketquellen, GitHub, übliche Entwicklerdienste).

Daraus folgt:
- **Kein Zugriff auf `srv-lab-dva-001` oder `srv-lab-dva-003`**, keine Prod-Datenbank, kein Apache,
  kein Playwright gegen die laufende Instanz.
- Keine Migration gegen `notenportal_probe` oder `notenportal`.
- Sie kann sich eine eigene MariaDB und PHP installieren und `php artisan test` fahren, wenn das
  im Setup-Skript der Umgebung steht — aber das ist ihre eigene, leere Datenbank.

Deshalb gehört Sitzung K lokal. Alles, was nur Code, Tests und Dokumentation berührt, kann in die
Cloud und schont das Abo-Kontingent.

---

## 4. Übergabe zwischen Sitzungen

`docs/auftrag/UEBERGABE.md` ist das gemeinsame Brett. Format: eine Zeile je Eintrag, neueste unten.

```
[K] 30.09. 21:14  Rechenkern rekursiv, Fälle 3.1 A-D grün. Branch claude/notenbaum.
[V] 30.09. 21:40  Braucht in app/Services/Auswertung eine Methode baumFuerLehrberuf() – bitte K.
[O] 30.09. 22:02  Vorschlag Seitenleiste liegt als docs/gui-seitenleiste.md vor, Entscheid David.
```

Jede Sitzung liest das Brett beim Start und nach jedem abgeschlossenen Block.

---

## 5. Wie oft committet wird

Nach jedem lauffähigen Stand: Tests grün, Seite lädt. Deutsche Commit-Meldung, Imperativ, eine
Zeile, Präfix aus `CLAUDE.md`. Push auf den eigenen `claude/*`-Branch, nie auf `main`.

Am Ende eines Blocks: Skill `notenportal-blockabschluss`.
