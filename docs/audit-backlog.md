# Audit-Backlog (Stand 11.06.2026, Abend-Session)

Findings aus dem Multi-Agent-Audit vom 11.06. — Status nach der Umsetzungs-Session.

## Erledigt (verifiziert + umgesetzt, siehe git log 11.06.)

- Doppelte Erfolgsmeldungen (Toast + Inline-Banner): 15 Inline-Banner entfernt, Toast einzige Quelle; Maschinen-Token profile-/password-updated ersetzt
- Glass-Konsistenz portalweit inkl. profile/edit, admin/benutzer/edit, Stammdaten/Lernende/Berufsbildner/Berichte (Commits 9d73fec, a6661c4)
- rgba()-Misch-Syntax → rgb(var()/a) (6867acc); Glass-Light-Mode-Alphas angehoben (Border 0.12, Schatten 0.13, Deckung 0.70); glass-subtle hell auf Card-Weiss
- Notenfarb-Skala Admin-Bericht 4-stufig; Bericht-Empty-State-Variable korrigiert
- bg-white-Hardcodes (Badge, kbd) tokenisiert; simple-tailwind-Pagination auf Tokens
- WCAG AA: Notenfarben hell auf -700, text-red-500 (87×) → red-600 + dark-Variante, --muted hell auf Slate-600 — Konvention in CLAUDE.md nachgeführt
- Viertelnoten: step=0.05 (create/edit/rechner)
- Feldgenaue @error-Anzeige + Pflichtfeld-* in Noten-create/edit
- Drucken-Zurück (window-target-Fallback); destroy() mit semester_id; markGesehen/markAlleGesehen via back() + opened_note
- Notiz-Inline-Speichern: Fehleranzeige bei !res.ok/Netzwerkfehler
- Dreifach-Empty-State (war bereits durch @if($notes->isNotEmpty()) behoben)
- Rechner: Status «Gewichtung fehlt» bei g≤0, min=5/max=100
- Edit-Formular: Live-Semester-Hinweis wie im Create
- Betreuung: neue Betreuung beendet offene automatisch (gueltig_bis = Vortag), Flash-Hinweis
- «Alle gesehen»: Zähler über alle Seiten/ungefiltert im Controller, Button mit Anzahl
- Admin-Lernende: Betreuung/Tracks-Aktionen auch <lg sichtbar, opacity-Hover entfernt
- BB-Kommentarfeld: textarea (2 Zeilen) + Ctrl/Cmd+Enter
- CSV-Formel-Injection: App\Support\Csv::safe() in allen 5 Exporten
- Rollen-ID 2 → dynamisch (Berufsbildner via rollen-Tabelle)
- Admin updateProfil: lehrberuf/lehrbeginn required (NOT-NULL-500 behoben)
- Deaktivierte Benutzer: EnsureUserIsActive-Middleware global (web-Gruppe)
- Semester-Überlappungs-Check in store/update
- Inaktive Kategorien: Formular gefiltert, store-Validierung mit aktiv=1 (update bewusst offen für Legacy-Noten)
- Nav-Dropdowns: Klick/Tastatur/aria-expanded/Escape/click.outside; Mobile-Menü Escape; Benutzer-Dropdown Escape
- Stammdaten-Tabellen: overflow-x-auto-Container (5 Views)
- Touch-Targets: Bearbeiten/Löschen im Accordion als 36px-Flächen; ×-Abbrechen 36px + aria-label
- Kommentar-Inputs: aria-label (3 Views)
- Loading-States: 45+ mutierende Formulare (x-data loading + defaultPrevented-Guard)
- Stammdaten create/edit: 10 Browser-Titel
- Label-for/id-Verknüpfung portalweit (Workflow-Session, Rest siehe unten)

## Offen (bewusst zurückgestellt)

- **[niedrig] Admin-Formular-Labels** weichen vom CLAUDE.md-Label-Standard ab (`text-sm font-medium text-muted` statt uppercase/tracking) — rein kosmetisch, grosser Diff; bei nächstem Admin-Redesign mitnehmen.
- **[niedrig] Lichtkanten-Insets im Light-Mode wirkungslos** (weisse 1px-Inset auf weissem Panel) — totes Tuning, kein visueller Schaden. Fix-Idee: Highlight-Farbe als Variable (hell: 15 23 42 / 0.05).
- **[niedrig] Glow-Alphas themen-unabhängig** (np-glow-yellow hell unsichtbar, red/green hell verwaschen) — Fix-Idee: --glow-scale-Variable (hell 0.6).
- **[niedrig] BenutzerController::update speichert vor zweiter Validierung** — Teil-Update bei Fehler im Lernenden-Teil. Fix: Regel-Sets zu einem validate() zusammenführen + DB::transaction.
- **[mittel] markAlleGesehen markiert ALLE Noten, auch ausserhalb des aktiven Filters** — Button ist jetzt klar beschriftet («Alle N als gesehen markieren»), echte Filter-Einschränkung wäre Folgearbeit (Filter-Parameter im Form mitschicken).
- **[niedrig] BB-Soft-Delete-Inkonsistenz** (Berufsbildner-Model ohne SoftDeletes trotz geloescht_am-Spalte) — adversarial geprüft: kein erreichbarer Exploit-Pfad (kein Code setzt die Spalte). Defense-in-Depth-Kandidat.

## Qualitätsblock Notenlogik & Dashboards (10.09.2026) – bewusst weggelassen

- **Zwischengruppen innerhalb eines Moduls** (`modul_note_gruppen`, `noten.gruppe_id`) entfernt statt ausgebaut: keine UI konnte sie anlegen, das Modul rechnet mit Ziel-Gewichtssumme. Falls ein Lehrbetrieb Teilnoten pro LB braucht, als eigene Ebene im Rechenkern nachrüsten.
- **`bewertungsregeln`** entfernt: nie befüllt; Grenzwerte liegen in `einstellungen`, Rundung/Promotion pro Kategorie.
- **Zielrechner mit unterschiedlichen Noten je offener Prüfung**: alle Unbekannten erhalten dieselbe Note (verständlichste Antwort auf «was brauche ich im Schnitt»). Individuelle Werte gehen über den Was-wäre-wenn-Modus.
- **Aktivitätsdiagramm Admin nach `erstellt_am`**: in der Demo-DB wirken alle Noten am Seed-Tag erfasst; in echten Daten korrekt. Kein Umbau auf `pruefungsdatum`, weil «Erfassungsaktivität» die Frage ist.
- **Lichtkanten-/Glow-Feinheiten** (oben, [niedrig]) weiterhin offen – kein Einfluss auf Lesbarkeit.
- **Zebra-Streifen und sticky thead** in Stammdaten-, Berichts- und Dashboard-Tabellen nicht ergänzt: Tabellen liegen in Glass-Karten mit Trennlinien, Zebra auf transparentem Glass wirkt unruhig; die Tabellen sind kurz (< 30 Zeilen) oder paginiert. Bei langen Listen (Lernende > 50) nachrüsten.

## Erstinbetriebnahme & Stammdaten (10.09.2026) – bewusst weggelassen

- **Lernende und Berufsbildner endgültig löschen**: nicht in der UI. Deaktivieren sperrt sofort, Notenhistorie und Betreuungsnachweis bleiben erhalten. Löschen nach Ablauf der Aufbewahrungsfrist gehört in einen eigenen Datenschutz-Prozess (Export, dann Anonymisierung), nicht als Knopf neben «Bearbeiten».
- **Modul wiederholen nur durch Lernende**: sie erfassen ihre Noten selbst und wissen, wann ein Modul neu beginnt. Berufsbildner sehen den Versuch über die Noten; eine Verwaltungsaktion folgt, falls im Pilot gewünscht.
- **Keine offiziellen Modulkataloge in den Lehrberuf-Vorlagen**: die Modullisten ändern sich je Bildungsverordnung und Kanton; falsche Vorgaben wären schlimmer als eine leere Liste. Module werden in der Einrichtung zeilenweise eingefügt (Nummer + Titel, Lernort je Feld).
- **Aktiv-Schalter für berufsspezifische Fächer** (`lehrberuf_faecher.aktiv`): wird nirgends gelesen, deshalb kein Schalter in der UI; Zuordnung entfernen genügt.
- **Letzter Admin**: die eigene Admin-Rolle lässt sich nicht entziehen; damit bleibt immer mindestens der handelnde Admin bestehen.

## Dokumente & Importe (10.09.2026) – bewusst weggelassen

- **Texterkennung (OCR) für eingescannte Zeugnisse und Fotos**: kein Tesseract auf dem Server; ein zusätzlicher Systemdienst mit Sprachdaten ist für den Pilot zu viel Betrieb. Scans werden abgelegt, der Abgleich meldet «Kein Text im PDF erkannt». Nachrüstbar über `thiagoalessio/tesseract_ocr` + Paket `tesseract-ocr-deu`.
- **Virenscan beim Upload**: kein ClamAV im Lab. Schutz über Typ-Whitelist (Endung und vom Server erkannter MIME-Typ), private Ablage, Auslieferung mit `nosniff` und Sandbox-CSP; SVG und HTML sind ausgeschlossen.
- **Import aus Excel: mehrere Blätter**: gelesen wird das erste Blatt. Mehrblättrige Mappen sind bei Notenlisten selten; wer eine hat, speichert das Blatt einzeln.
- **Ungültige Noten in der Vorschau** (z. B. 4.33): das Feld bleibt leer statt den Rohwert zu zeigen; die Zeile ist rot markiert und abgewählt.
- **Dateien im Backup**: erledigt mit Block F (Sicherung enthält `dateien/lernende`).

## Datensicherung (10.09.2026) – bewusst weggelassen

- **Wiederherstellen im Browser**: ein Knopf, der die laufende Datenbank überschreibt, ist ein zu grosses Risiko (Fehlklick, halbe Wiederherstellung bei Zeitüberschreitung). Wiederherstellung bleibt eine Notfallprozedur im Terminal (betrieb.md, LIESMICH.txt im ZIP).
- **Kopie ausser Haus**: erledigt 11.09.2026 (rsync in Ordner oder per SSH, Betrieb → Sicherungen). S3 bewusst nicht: braucht SDK und Zugangsschlüssel; ein SSH-Ziel oder eingebundenes Netzlaufwerk deckt den Pilot ab.
- **Verschlüsselung der ZIP-Datei**: Download nur für Admins über HTTPS-Ziel geplant; ein Passwort, das niemand mehr findet, macht die Sicherung wertlos.
- **Wochen-/Monatsstände**: 14 Tagesstände reichen für den Pilot; Speicherplatz wächst mit den Dokumenten.

## E-Mail, Agenda-Backend, Go-Live, echte Daten (11.09.2026) – bewusst weggelassen

- **Tracks nach Datum der Note**: `NoteService::erlaubteFaecher` gibt Fächer nach den heute aktiven Tracks frei. Wer die BM verlassen hat, kann alte BM-Zeugnisnoten nicht übernehmen. Richtig wäre: Track gültig am Prüfungsdatum. Eingriff in den Rechenkern → eigener Block mit Tests.
- **Schulnetz-«Zeugnisnoten» im Notenimport ohne Datum**: das PDF enthält nur Semesterspalten; das Datum wird in der Vorschau gesetzt. Automatisch aus dem Semesterende ableiten wäre möglich, falsch zugeordnete Semester wären aber schwer zu sehen.
- **Scans ohne Text** (siehe OCR oben) und **Ø-Spalte ohne Tabulator** im Zeugnis: nicht zuverlässig von einer Semesternote zu trennen.
- **S3/WebDAV als Kopie-Ziel**: siehe Datensicherung.
- **Let's Encrypt**: die VM ist nur im internen Netz erreichbar (keine öffentliche DNS/Port 80 von aussen); deshalb eigene CA. Mit öffentlichem Namen: `certbot --apache`.
- **HTTP → HTTPS-Umleitung**: bewusst noch nicht, solange Geräte die Lab-CA nicht vertrauen; Go-Live-Liste in betrieb.md.

## Hinweise

- `bemerkung`-Feld auf `lernende` fehlt weiterhin (braucht manuelles sudo mysql, siehe CLAUDE.md)
- border-red-500 vs border-border auf demselben Element: Gewinner hängt von CSS-Reihenfolge ab — falls roter Fehler-Rahmen nicht sichtbar, `!border-red-500` verwenden

## Sprache (11.09.2026) – bewusst weggelassen

- **Klassen, Methoden, Variablen, Views, DB-Tabellen auf Englisch**: Pflichtteil (Routennamen und URL-Pfade) ist umgesetzt, mit 301 von den alten Pfaden (`App\Support\LegacyPaths`). Der Rest berührt ~400 Dateien und das DB-Schema (Tabellen `benutzer`, `lernende`, `noten` … mit Fremdschlüsseln); für den Go-Live am 30.09. zu riskant. Vorgehen danach: pro Bereich ein Workflow mit Haiku-Agents (Umbenennen) und Tests als Netz, DB zuletzt mit Dump/Tag und Views/Aliassen.
- **Sprachdateien und Umschaltung Deutsch/Englisch pro Benutzer**: alle Texte stehen direkt in den Views; Auslagern nach `lang/` lohnt sich erst, wenn eine zweite Sprache wirklich gebraucht wird. Die Oberfläche bleibt Deutsch.

## Agenda, Feedback, Themes (11.09.2026)

- iCal-Export-Oberfläche für Berufsbildner/Admins: Server kann es (`CalendarExport::forUser`), Anzeige nur für Lernende gebaut – Bedarf im Pilot abwarten.
- Feedback: keine Screenshot-Vorschau vor dem Senden (Aufnahme erst beim Senden, robuster); keine Duplikaterkennung.
- Agenda-Query-Parameter (`ansicht`, `monat`) noch deutsch; bei der späteren Code-Umbenennung mitziehen (LegacyPaths betrifft nur Pfade).
- Mobile Filterformulare (Lernende, Benutzer) sehr lang: Filterleiste in GUI-Paket 5.
- `Uebersicht::berufsbildner()` liefert `vergleich` noch, das BB-Dashboard nutzt seit Paket 6 Small Multiples – entfernen, sobald Paket 4 das Dashboard umbaut.
- Senkrechte Genügend-Linie für `balken()` (Lernenden-Dashboard) mit Paket 3.
