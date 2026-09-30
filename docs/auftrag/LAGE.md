# Lage — Stand 30.09.2026

Diese Datei ist der Einstieg für jede neue Claude-Code-Sitzung am Notenportal. Sie sagt, was
gesichert ist, was falsch ist und woran gearbeitet wird. Zuerst lesen, dann `CLAUDE.md`, dann den
eigenen Auftrag in dieser Mappe.

Wer hier etwas als erledigt erkennt, trägt es ein. Wer etwas findet, das dieser Datei widerspricht,
ändert die Datei — mit Quelle.

---

## 1. Was das Produkt ist

Notenverwaltung für Lernende in der Schweizer Berufslehre. Lernende erfassen Noten, sehen
Zeugnisnoten, Promotion und Modulfortschritt; Berufsbildner begleiten; Admins verwalten.
Laravel 13, MariaDB, Blade + Tailwind 4. Produkteigner: David von Allmen.

**Stand 30.09.2026 abends: nichts ist produktiv.** Es gibt keine schützenswerte Instanz und keine
echten Daten; die Datenbank ist leer. David klont morgen früh frisch auf einen neuen Ubuntu-Server
und führt `sudo ./install.sh` aus. Das ist das Abnahmekriterium für alles, was heute Nacht entsteht.
Vorgehen: `docs/auftrag/NACHTLAUF.md` — **eine** Sitzung auf `main`, Parallelität über Subagenten.

---

## 2. Der zentrale Befund: die Notenrechnung ist falsch

> **Behoben mit Block 1 (30.09., 3458e6c a6946be b12a955 90e6cf2, Prüfer-Fixes 7b229e5 a9a6274).**
> Der Notenbaum rechnet je Knoten mit Gewicht, Rundung, Fallnote, max. ungenügend und Minuspunkten;
> die Abnahmefälle aus `NOTENBAUM.md` stehen in `tests/Unit/Auswertung/NotenbaumAbnahmeTest.php`.
> Hat der Lehrberuf einen Baum, ist dessen Wurzel die Gesamtnote; ohne Baum rechnet das Portal wie
> unten beschrieben weiter (`Rechenkern.php:74`). Prod braucht `notenportal:migrate`
> (`2026_10_01_000001` bis `_000003`) und einen zugeordneten Baum. Der Befund unten bleibt als
> Begründung stehen.

Am 30.09.2026 belegt: Das Portal rechnet die Vornote anders, als es die Bildungsverordnung
vorschreibt. **Jede Gesamtnote, die es heute anzeigt, ist unzutreffend.**

Heute (`app/Services/Auswertung/Rechenkern.php:66-100`): Kategorie-Schnitt = **ungewichtetes**
Mittel aller Elementnoten; Gesamtnote = mit `kategorien.gewicht_gesamt` gewichtetes Mittel der
Kategorien. Ein einzelnes Fach oder Modul kann kein eigenes Gewicht haben, und es gibt keine Ebene
zwischen Kategorie und Gesamtnote.

Vorgeschrieben für Informatiker/in EFZ (BiVo SR 412.101.220.10, Art. 18/19, Stand 1.1.2026):

```
QV-Gesamtnote
├─ Praktische Arbeit (IPA)                40 %   Fallnote: muss ≥ 4 sein
│    Ausführung/Resultat 50 % · Dokumentation 20 % · Präsentation/Fachgespräch 30 %
├─ Allgemeinbildung                       20 %
│  ├─ Erfahrungsnote ABU                   1/3
│  ├─ Schlussarbeit (früher Vertiefungsarbeit) 1/3
│  └─ Schlussprüfung                       1/3
├─ Erfahrungsnote erweiterte Grundkompetenzen  10 %
│    Mittel der 8 Semesterzeugnisnoten (Englisch, Mathematik), auf ganze/halbe Note
└─ Erfahrungsnote Informatikkompetenzen   30 %   Fallnote: muss ≥ 4 sein
   ├─ Modulnoten Berufsfachschule          80 %
   └─ ÜK-Modulnoten                        20 %
```

Bestanden, wenn **alle drei** zutreffen: IPA ≥ 4 **und** Erfahrungsnote Informatikkompetenzen ≥ 4
**und** Gesamtnote ≥ 4. Allgemeinbildung und erweiterte Grundkompetenzen sind keine Fallnoten.
Rundung: Teilmittel auf ganze/halbe Note, Endresultat auf eine Dezimalstelle (Art. 34 Abs. 2 BBV).

Die **80/20-Gewichtung zwischen Schul- und ÜK-Modulen** ist der Kern des Problems: das Portal kennt
sie nicht und kann sie heute nicht abbilden.

Parallel dazu die Berufsmaturität (BMV vom 13. Juni 2025, SR 412.103.1, in Kraft seit 1.3.2026) mit
völlig anderer Struktur — siehe Abschnitt 4.

**Der Umbau dazu ist in `docs/auftrag/NOTENBAUM.md` spezifiziert. Er hat Vorrang vor allem
anderen.**

---

## 3. Bestätigte Lücken im Datenmodell

Erhoben am 30.09.2026 gegen den Code, jede Zeile belegt.

| # | Lücke | Fundstelle | Folge |
|---|---|---|---|
| 1 | `noten.note_wert` ist `DECIMAL(4,2)` mit DB-CHECK 1.0–6.0 | `database/migrations/2026_02_05_000018_create_noten_table.php:33,76` | Sport A/B/C nicht speicherbar; kein Konzept «zählt nicht» |
| 2 | Kategorie-Schnitt ungewichtet, keine Zwischenebene, kein QV-Modell | `app/Services/Auswertung/Rechenkern.php:77-96` | siehe Abschnitt 2 |
| 3 | `track_typ` ist `ENUM('BMS','ABU')` | `…_000005_create_faecher_table.php:14`, `…_000012_create_lernender_tracks_table.php:19`, Validierung `in:BMS,ABU` an 5 Stellen, feste `<option>` in 3 Views | Mehrere BM-Ausrichtungen nebeneinander unmöglich |
| 4 | Kein Feld «Unterrichtsbereich» (Grundlagen/Schwerpunkt/Ergänzung) auf `faecher` | `…_000005_create_faecher_table.php` | Lektionentafel nicht abbildbar |
| 5 | Semesterplan global, keine Solldauer je Lehrberuf | `…_000003_create_semester_table.php` | 7 (BM1) vs. 8 (EFZ) vs. 2/4 (BM2) nicht hinterlegbar |
| 6 | Kein Feld für Lektionen je Fach/Semester | — | Lektionentafel nicht abbildbar |
| 7 | Kein `destroy` für Fächer, nur `aktiv`-Schalter | `routes/web.php:293-302` | Fehleingaben bleiben stehen |
| 8 | Einrichtungsschritt «Lehrberufe & Fächer» bietet nur Ankreuzen | `resources/views/admin/einrichtung/professions.blade.php` | Nutzer glaubt, Fächer seien gar nicht pflegbar. Die echte Pflege liegt unter Admin → Stammdaten → Fächer (`/admin/master-data/subjects`, `app/Support/Navigation.php:55`) — der Assistent verlinkt sie nicht |

**Stand 30.09. abends:** erledigt sind 1 (Stufen für Sport, «zählt nicht» je Fach: a6946be),
2 (Notenbaum, siehe Abschnitt 2), 7 (Fächer löschbar: a6946be) und 8 (Einrichtung verlinkt Fächer- und
Baumpflege, lädt Vorlagen: a3f664c). 3–6 bleiben bewusst offen, Begründung in `docs/audit-backlog.md`
«Notenbaum und Stammdaten: bewusst gelassen».

Nicht-Lücken, ausdrücklich geprüft: Fächer sind freier Text und frei anlegbar/umbenennbar/
deaktivierbar; «Französisch» ist **nirgends** fest verdrahtet, es steht nur als Vorschlag in
`app/Support/Einrichtung.php:52`.

---

## 4. Gesicherte Fachfakten (mit Quelle)

Alles in diesem Abschnitt wurde am 30.09.2026 an der Primärquelle geprüft. Wer damit arbeitet,
braucht nicht neu zu recherchieren. Wer etwas ergänzt, nennt die Quelle.

### 4.1 Berufsmaturität — BMV vom 13. Juni 2025

SR 412.103.1, erlassen 13.06.2025, **in Kraft seit 1.3.2026**, löst die BMV 2009 ab.
Volltext: fedlex.data.admin.ch, ELI `cc/2025/408/20260301`.
Prüfungen nach altem Recht noch bis 2033 (Art. 35); kantonale Anpassungsfrist war der 31.7.2026.

> **Achtung:** Die gbchur-Lektionentafel TALS1 zitiert «Verordnung vom 13. Juni **2026**». Eine
> solche Verordnung existiert nicht. Sechs der sieben übrigen Tafeln zitieren korrekt 2025 — es ist
> ein Tippfehler der Schule, nicht eine neuere Fassung.

Noten und Promotion:
- Semesterzeugnisnote je Fach = Mittel aus mindestens zwei benoteten Leistungen, gerundet auf
  **ganze oder halbe Note** (Art. 16 Abs. 2, Art. 23 Abs. 5).
- Erfahrungsnote je Fach = Mittel aller Semesterzeugnisnoten, auf **eine Dezimalstelle**
  (Art. 23 Abs. 3).
- Abschlussnote je Fach = **½ Prüfungsnote + ½ Erfahrungsnote**, auf ganze/halbe Note
  (Art. 23 Abs. 1 und 4). Fach ohne Abschlussprüfung: Abschlussnote = Erfahrungsnote.
- Abschlussprüfungen nur in den **4 Grundlagenfächern + 2 Schwerpunktfächern** (Art. 20 Abs. 1).
  Ergänzungsfächer zählen nur über die Erfahrungsnote.
- IDAF/IDPA-Abschlussnote = ½ IDPA + ½ Erfahrungsnote IDAF (Art. 23 Abs. 6).
- **Die IDAF-Note zählt nicht zur Promotion** (Art. 16 Abs. 3).
- Semesterpromotion bestanden, wenn **alle drei**: Gesamtnote ≥ 4,0 **und** Summe der Differenzen
  ungenügender Noten zu 4 («Minuspunkte») ≤ 2,0 **und** höchstens 2 Noten unter 4 (Art. 16 Abs. 4).
  Einmalige provisorische Promotion möglich, beim zweiten Mal Ausschluss (Abs. 6).
- Bestehensnorm der Abschlussprüfung strukturell identisch (Art. 24 Abs. 2).
- **Sport kommt in der BMV nicht vor** — weder Grundlagen- noch Schwerpunkt- noch
  Ergänzungsbereich (Art. 8–10). Es wird in der BM nicht benotet und zählt nicht zur BM-Note.

### 4.2 Bildungsgänge an der Gewerblichen Berufsschule Chur

Sieben Bildungsgänge, alle mit 1440 Lektionen und IDPA 40, aber unterschiedlicher Dauer:

| Kürzel | Ausrichtung | Dauer |
|---|---|---|
| TALS1 | Technik, Architektur und Life Sciences (BM1) | 7 Semester |
| ARTE1 | Gestaltung und Kunst (BM1) | 7 Semester |
| TALS2 VZ | Technik, Architektur und Life Sciences (BM2 Vollzeit) | 2 Semester |
| TALS2 TZ | dito, Teilzeit | 4 Semester |
| TALS2 BL | dito, Blended Learning | 2 Semester |
| ARTE2 | Gestaltung und Kunst (BM2) | 2 Semester |
| NLL2 | Natur, Landschaft und Lebensmittel (BM2) | 2 Semester |

**TALS1** — Grundlagen: Deutsch 240, Mathematik 200, Italienisch 120, Englisch 160. Schwerpunkt:
Mathematik erweitert 200, Naturwissenschaften 240 (Chemie 80 / Physik 160, je nach Profil «Technik
und Architektur» oder «Life Sciences»). Ergänzung: Geschichte und Politik 120, Wirtschaft und
Recht 120. IDAF in die Fächer integriert, IDPA 40 separat.

**ARTE1** — gleiche Grundlagen; Schwerpunkt: Gestaltung, Kunst, Kultur 320 und Information und
Kommunikation 120. Ergänzung: Geschichte und Politik 120, Technik und Umwelt 120.

**In Graubünden ist die zweite Landessprache Italienisch, nicht Französisch.** Auf allen Tafeln
bestätigt. In den BM1-Tafeln getrennt als «Italienisch Anfänger» und «Italienisch Fortgeschrittene»
(je 120 Lektionen), in den BM2-Tafeln nur eine Zeile «Italienisch».

Andere Ausrichtungen sind an anderen Bündner Schulen: Wirtschaft und Dienstleistungen Typ
Wirtschaft an der KV Wirtschaftsschule Chur; Gesundheit und Soziales (nur BM2) am BGS Chur.

### 4.3 EFZ-Lektionentafeln der gbchur

Angeboten werden nur **INAP**, **INPE** und **INBE** (Betriebsinformatik). ICT-Fachmann/-frau,
Entwickler/in digitales Business und Informatikpraktiker/in EBA gibt es dort nicht.

**INAP und INPE**: 8 Semester, Schultage 2/2/2/2/1/1/1/1, total 2000 Lektionen.
Erweiterte Grundkompetenzen 320 (Englisch 200, Mathematik 120) · Informatikkompetenzen 960
(24 Module à 40) · Gesellschaft 240, Sprache/Kommunikation 240, Sport 240 unter der Klammer
«Allgemeine schulische Bildung / Sport» = 720.
Semester 1, 2 und 8 sind bei beiden identisch (231/431/319/162 · 117/164/122/114 · 241/245),
ab Semester 3 unterscheiden sie sich.

**INBE**: eigene BiVo vom 14.05.2021, 8 Semester, 2360 Lektionen, andere Module. Die Modulverteilung
aus dem separaten PDF deckt sich nicht vollständig mit der Lektionentafel-Grafik — **ungeklärt**.

In den Tafeln heissen die allgemeinbildenden Fächer **«Gesellschaft»** und
**«Sprache/Kommunikation»** getrennt, nicht «ABU». «ABU» ist die interne Sammelbezeichnung. Da die
Semesterzeugnisnote ohnehin das Mittel beider ist, darf ein Betrieb ABU als **ein** Fach führen.

### 4.4 Allgemeinbildung — VMAB vom 9. April 2025

SR 412.101.241, **in Kraft seit 1.1.2026**, löst die Verordnung von 2006 ab.
Note des Qualifikationsbereichs Allgemeinbildung = gleich gewichtetes Mittel aus **Erfahrungsnote,
Schlussarbeit und Schlussprüfung** (je 1/3, Art. 6). «Vertiefungsarbeit» heisst neu
**«Schlussarbeit»**. Erfahrungsnote = Mittel der Semesterzeugnisnoten, wobei die Semesterzeugnisnote
das Mittel aus «Gesellschaft» und «Sprache und Kommunikation» ist (Art. 7/8). Mindestanteil der
Allgemeinbildung an der QV-Gesamtnote: 20 % (Art. 5 Abs. 3).

### 4.5 Sport

Der Bund schreibt **keine** Notenskala vor. SpoFöV Art. 54 verlangt nur mindestens eine
Qualifizierung pro Schuljahr; die Form legt die Schule fest.

Die **A/B/C-Skala ist eine Hausregel der gbchur** («Sportunterricht Inhalt und Qualifizierung»,
gültig ab 1.8.2022): drei Kompetenzbereiche (Fach-, Sozial-, Methoden-/Selbstkompetenz), je
A = 2 / B = 1 / C = 0 Punkte; Summe 5–6 → A, 2–4 → B, 0–1 → C; «d» = dispensiert.
Wörtlich aus dem Dokument: «Diese individuelle Qualifizierung trägt nicht zur Promotion bei, d.h.
sie hat keinen Einfluss auf das Bestehen des Qualifikationsverfahrens.»

Folge fürs Portal: erfassen und anzeigen, aber **nicht in die Rechnung** nehmen.

### 4.6 Kanton Graubünden

Die kantonale Berufsmaturitätsverordnung (BR 430.400) regelt nur den **Vollzug**; Noten- und
Promotionsregeln richten sich vollständig nach Bundesrecht. Ein eigenständiges kantonales
Notenreglement wurde nicht gefunden.

**Offen:** ob die kBMV bereits an die BMV 2025 angepasst wurde (Frist war 31.7.2026). Der
Versionsstand des Dokuments liess sich nicht zuverlässig klären.

### 4.7 Quellen

- BMV 2025: `fedlex.data.admin.ch`, SR 412.103.1, ELI `cc/2025/408/20260301`
- BiVo Informatiker/in EFZ: `fedlex.data.admin.ch`, SR 412.101.220.10, ELI `cc/2020/941/20260101`
- VMAB: SR 412.101.241
- Rahmenlehrplan BM (SBFI, 13.6.2025): `sbfi.admin.ch/dam/de/sd-web/umUlonTbNY5o/Rahmenlehrplan_de_web.pdf` — **nicht volltextgelesen**
- Lektionentafeln: `gbchur.ch/wp-content/uploads/2026/07|08|09/…`
- Sport-Qualifizierung gbchur: `gbchur.ch/wp-content/uploads/2023/01/Sport-Qualifizierung-ab-August-2022.pdf`
- Ausführungsbestimmungen QV Informatiker/in EFZ (12.06.2024): `ict-berufsbildung.ch/resources/Informatiker-EFZ_Ausfuehrungsbestimmungen_QV_202406121.pdf`
- kBMV: `gr-lex.gr.ch`, BR 430.400

### 4.8 Offene Fragen an die Fachseite

Nicht raten — als offen markieren und David fragen:
1. Ist die kantonale BMV an die BMV 2025 angepasst?
2. INBE-Modulverteilung: Lektionentafel oder Modulverteilungs-PDF gilt?
3. Warum trennen die BM2-Tafeln Italienisch nicht nach Niveau?
4. Wie rechnet die gbchur die Schlussarbeit intern (Prozess/Produkt/Präsentation)?
5. IPA-Kriterienkatalog (Punkte → Note) liegt bei der kantonalen Prüfungskommission, nicht öffentlich.

---

## 5. Rechtliches

- **Modulkatalog-Inhalte** (Modulidentifikationen, Handlungsziele, Leistungsbeurteilungen) gehören
  ICT-Berufsbildung Schweiz. Sie dürfen auf einer Instanz liegen und dort benutzt werden, aber
  **nicht ins Repository, nicht in die Dokumentation, nicht in Testfixtures.** Fixtures verwenden
  erfundene Modulnummern und -titel. Eine schriftliche Einwilligung liegt nicht vor.
- Der Bearer-Token von `modulbaukasten.ch/assets/auth.php` gehört zum Dynamics-Tenant von
  ICT-Berufsbildung. **Nicht benutzen, nicht speichern, nicht loggen.**
- Beim Ernten `--pause` (Vorgabe 1500 ms) nie herabsetzen.
- Apples SF Pro und SF Symbols nicht einbetten, Apple-Systemfarben nicht hart kodieren.
- Dokumentationsbilder nur von der Demo-Instanz, nie aus Produktion, nie mit echten Personen.
- Lizenz und Meldeweg für Sicherheitslücken sind offene Produktentscheide (`docs/endspurt-plan.md`).

---

## 6. Sicherheit

- Passwörter gehören in die `.env` auf der jeweiligen Instanz und in Keeper — nie in Code, Docs,
  Logs, Commits oder OneNote.
- `tmp-testdaten/` enthält echte Noten. Nie committen, nie in Fixtures, nie in Dokumentation.
- Das Prod-Testpasswort aus Commit `171ed72` liegt in der Git-Historie und **muss rotiert werden**.
- Subagenten führen keine schreibenden Git-, Composer- oder npm-Befehle aus, auch keine Dry-Runs.
- Kein Force-Push auf `main`.

---

## 7. Woran gearbeitet wird

> **Stand 30.09. abends: alle sechs Punkte bearbeitet**, Details und Commits im Übergabebrett
> (`UEBERGABE.md`). Offen sind nur die Entscheide unter «Offen für David» dort, darunter der Standard
> der Navigation (Seitenleiste ist gebaut und je Person umschaltbar), und das Bewusst-Gelassene in
> `docs/audit-backlog.md`.

Reihenfolge ist bindend; jeder Schritt lässt das Portal lauffähig zurück.

1. **Notenbaum** — `docs/auftrag/NOTENBAUM.md`. Erschlägt die Lücken 1–5 auf einmal. Vorrang.
2. **Stammdaten-Vorlagen** — Graubünden als ladbare Vorlage, **nicht** als Konstanten im Code.
   Gleicher Weg wie der Modulkatalog-Import.
3. **Einrichtung entschärfen** — Lücke 8: der Assistent muss auf die echte Stammdatenpflege
   verlinken; Lücke 7: Fächer löschbar machen.
4. **Rückmeldungen** aus `docs/rueckmeldungen-2026-09-12.md`.
5. **Oberfläche** nach `docs/gui-konzept.md` (Apple HIG). Offener Entscheid, nicht gefällt:
   Topbar-Navigation nach macOS-Art in eine Seitenleiste verlegen.
6. **Backlog A** aus `docs/audit-backlog.md`.

Alles, was bewusst liegen bleibt, gehört mit Begründung nach `docs/audit-backlog.md` — nicht in
einen Kommentar im Code und nicht in den Chatverlauf.

---

## 8. Arbeitsweise

Gilt zusätzlich zu `CLAUDE.md`:

- **Nicht raten.** Wer eine Ursache vermutet, baut ein Messinstrument und weist sie nach. In dieser
  Sache wurde schon dreimal falsch geraten, bevor eine Sonde den echten Fehler in einem Lauf fand.
- **Fakten mit Quelle**, Lücken ausdrücklich als Lücke markieren. Eine ehrliche Lücke ist mehr wert
  als eine geratene Zahl.
- **Keine Rückfragen**, solange eine vertretbare Annahme möglich ist. Annahme treffen, im Bericht
  nennen, weiterarbeiten.
- **Berichte an David: höchstens 8 Zeilen**, Deutsch, keine Füllwörter, keine Rückschau.
- Vor jedem Schema-Eingriff Dump und Tag (Skill `notenportal-migration`).
- Nach jedem abgeschlossenen Block Skill `notenportal-blockabschluss`.
