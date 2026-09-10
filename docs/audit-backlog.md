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

## Hinweise

- `bemerkung`-Feld auf `lernende` fehlt weiterhin (braucht manuelles sudo mysql, siehe CLAUDE.md)
- border-red-500 vs border-border auf demselben Element: Gewinner hängt von CSS-Reihenfolge ab — falls roter Fehler-Rahmen nicht sichtbar, `!border-red-500` verwenden
