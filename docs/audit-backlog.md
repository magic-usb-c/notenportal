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

- ~~**[niedrig] Admin-Formular-Labels** weichen vom CLAUDE.md-Label-Standard ab~~ erledigt mit GUI-Paket 5 (einheitlich `text-sm font-medium text-text`, 11.09. geprüft: kein `<label … uppercase>` mehr unter `resources/views/admin`).
- ~~**[niedrig] Lichtkanten-Insets / Glow-Alphas im Light-Mode**~~ obsolet (11.09. geprüft): `np-glow-*`, `glass-lift`, `np-card-lift` und die Insets wurden in GUI-Paket 1/2 (f82b7e2, 5a6aad0) ersatzlos entfernt, siehe `docs/gui-konzept.md` «Entfallen».
- ~~**[niedrig] BenutzerController::update speichert vor zweiter Validierung**~~ erledigt (11.09.): bereits ein einziges `validate()` + `DB::transaction` in `Admin\BenutzerController::update` und `Verwaltung\LernendeController::update`; Tests `BenutzerControllerTest::ungueltige_eingabe_speichert_nichts` und `AnlegenUndBearbeitenTest::ungueltiges_bearbeiten_speichert_nichts` grün.
- ~~**[mittel] markAlleGesehen markiert ALLE Noten, auch ausserhalb des aktiven Filters**~~ erledigt (11.09.): Filter (`kategorie_id`/`semester_id`) wird im Formular mitgeschickt und serverseitig über `NotenGesehenController::gefilterteNoten()` angewendet (geteilt mit dem Zähler in `LernendeNotenController::index`); Test `AlleGesehenTest::markiert_nur_noten_im_aktiven_filter` grün.
- ~~**[niedrig] BB-Soft-Delete-Inkonsistenz** (Berufsbildner-Model ohne SoftDeletes trotz geloescht_am-Spalte)~~ erledigt (11.09. geprüft): `Berufsbildner` nutzt `SoftDeletes` mit `DELETED_AT = 'geloescht_am'`, Raw-Queries filtern `bb.geloescht_am`.
- ~~**[niedrig] Kalenderabgleich-Fehler im Admin-Handlungsbedarf** verlinken bei mehreren betroffenen Lernenden nur auf den ersten~~ erledigt (24.09.): ein Eintrag je betroffene Person mit Link; Test `DashboardTest::kalenderabgleich_fehler_mehrerer_lernender_verlinken_jede_person`.

## Qualitätsblock Notenlogik & Dashboards (10.09.2026) – bewusst weggelassen

- **Zwischengruppen innerhalb eines Moduls** (`modul_note_gruppen`, `noten.gruppe_id`) entfernt statt ausgebaut: keine UI konnte sie anlegen, das Modul rechnet mit Ziel-Gewichtssumme. Falls ein Lehrbetrieb Teilnoten pro LB braucht, als eigene Ebene im Rechenkern nachrüsten.
- **`bewertungsregeln`** entfernt: nie befüllt; Grenzwerte liegen in `einstellungen`, Rundung/Promotion pro Kategorie.
- **Zielrechner mit unterschiedlichen Noten je offener Prüfung**: alle Unbekannten erhalten dieselbe Note (verständlichste Antwort auf «was brauche ich im Schnitt»). Individuelle Werte gehen über den Was-wäre-wenn-Modus.
- **Aktivitätsdiagramm Admin nach `erstellt_am`**: in der Demo-DB wirken alle Noten am Seed-Tag erfasst; in echten Daten korrekt. Kein Umbau auf `pruefungsdatum`, weil «Erfassungsaktivität» die Frage ist.
- ~~**Lichtkanten-/Glow-Feinheiten**~~ obsolet, siehe oben.
- **Zebra-Streifen und sticky thead** in Stammdaten-, Berichts- und Dashboard-Tabellen nicht ergänzt: Tabellen liegen in Glass-Karten mit Trennlinien, Zebra auf transparentem Glass wirkt unruhig; die Tabellen sind kurz (< 30 Zeilen) oder paginiert. Bei langen Listen (Lernende > 50) nachrüsten.

## Erstinbetriebnahme & Stammdaten (10.09.2026) – bewusst weggelassen

- **Lernende und Berufsbildner endgültig löschen**: nicht in der UI. Deaktivieren sperrt sofort, Notenhistorie und Betreuungsnachweis bleiben erhalten. Löschen nach Ablauf der Aufbewahrungsfrist gehört in einen eigenen Datenschutz-Prozess (Export, dann Anonymisierung), nicht als Knopf neben «Bearbeiten».
- **Modul wiederholen nur durch Lernende**: sie erfassen ihre Noten selbst und wissen, wann ein Modul neu beginnt. Berufsbildner sehen den Versuch über die Noten; eine Verwaltungsaktion folgt, falls im Pilot gewünscht.
- **Keine offiziellen Modulkataloge im Repo** (ersetzt den früheren Punkt «keine Modulkataloge in den Vorlagen»): Vorlagen mit festen Modullisten wären veraltet, sobald eine Bildungsverordnung wechselt – und die Inhalte gehören ICT-Berufsbildung Schweiz. Stattdessen holt sie jeder Betrieb selbst: `tools/modulkatalog-ernte.mjs` erntet modulbaukasten.ch, `notenportal:modulkatalog` liest die Ernte ein (Vorschau, alles-oder-nichts). Siehe `docs/modulkatalog.md`. Offen dort: Bewertungskriterien je LBV-Element (dritte Ebene unter den Elementen, mit Gewichtungsspannen und geprüften Handlungszielen) werden geerntet, aber noch nicht gespeichert – erst wenn ein Betrieb danach fragt, sonst wären es ungenutzte Felder.
- **Aktiv-Schalter für berufsspezifische Fächer** (`lehrberuf_faecher.aktiv`): wird nirgends gelesen, deshalb kein Schalter in der UI; Zuordnung entfernen genügt.
- **Letzter Admin**: die eigene Admin-Rolle lässt sich nicht entziehen; damit bleibt immer mindestens der handelnde Admin bestehen.

## Modulkatalog – erste echte Ernte (13.09.2026)

- **Version je Zuordnung statt je Modul** (erledigt 24.09.2026 mit Migration `2026_09_24_000001_lehrberuf_modul_version`: Spalte `lehrberuf_module.version`, Feld «Katalogversion» im Modulformular, `_detail.blade.php` zieht sie der Modulversion vor, seit 02.10. auch in der Beschriftung «Katalogversion» – Nachtrag 02.10.; Ausgangslage:) `module.modul_nummer` ist eindeutig, eine Modulzeile führt also eine Version. 17 der 334 geernteten Nummern sind in zwei gleichzeitig gültigen Versionen im Umlauf (117 V4 für die Bildungsverordnung 2021, V5 für 2026), weil jeder Jahrgang seine eigene führt. Der Verweis «Im Modulbaukasten öffnen» zeigt darum für den älteren Jahrgang auf die neuere Version, und `lehrberuf_module` kann es nicht besser wissen. Richtig wäre eine Spalte `lehrberuf_module.version`, vom Import aus der Abschlussliste gefüllt, und ein Verweis, der sie der Modulversion vorzieht.
- **Was der Modulbaukasten nicht liefert**: von 48 EDB-Modulen haben 6 LBV-Elemente, `kompetenzfeld` füllt er für EDB gar nicht (dafür Kompetenz und Objekt bei allen 48). Die Felder bleiben im Portal leer, ohne dass ein Fehler vorliegt – nicht suchen gehen.
- **Warum eigene Module auf Prod UEK01/ABU01 heissen**: der erste echte Import hat eigene Module verschluckt, deren erfundene Nummern der Katalog anders belegt (801 ist «Grundgesetze der Farbenlehre», 301 «Office Werkzeuge anwenden»). Weil die Nummer im Portal eindeutig ist, diente eine Zeile zwei Bedeutungen, und Mediamatik erbte «ABU (EDB): Digitale Kommunikation». Der Import meldet solche Nummern jetzt als Konflikt und lässt sie aus (`--eigene-uebernehmen` übergeht das); die eigenen Module heissen jetzt `UEK01`/`UEK02` und `ABU01`/`ABU02`. Warum das genügt, war zuerst falsch begründet: `UEK` streift das Portal ab, `UEK01` wurde also zu `01` und blieb damit eine Katalognummer. Es genügt erst, weil Katalognummern drei- oder vierstellig sind – gemessen an 334 geernteten Nummern, keine zweistellige darunter. Die Nummernprüfung in `App\Support\Modulbaukasten` verlangt das jetzt ausdrücklich, statt es anzunehmen. Siehe `docs/modulkatalog.md`.

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

- ~~**Tracks nach Datum der Note**: `NoteService::erlaubteFaecher` gibt Fächer nach den heute aktiven Tracks frei. Wer die BM verlassen hat, kann alte BM-Zeugnisnoten nicht übernehmen. Richtig wäre: Track gültig am Prüfungsdatum. Eingriff in den Rechenkern → eigener Block mit Tests.~~ erledigt (11.09.): `erlaubteFaecher(…, ?$stichtag)` (Standard heute, Rechner unverändert); Auswahllisten zeigen Fächer aller bisherigen Tracks (`auswahlFaecher`), Speichern/Bearbeiten/Import/Agenda prüfen den Track am Prüfungsdatum mit klarer Meldung; Import-Vorschau markiert «Track am Datum nicht aktiv». Tests: `TrackNachDatumTest`.
- **Schulnetz-«Zeugnisnoten» im Notenimport ohne Datum**: das PDF enthält nur Semesterspalten; das Datum wird in der Vorschau gesetzt. Automatisch aus dem Semesterende ableiten wäre möglich, falsch zugeordnete Semester wären aber schwer zu sehen.
- **Scans ohne Text** (siehe OCR oben) und **Ø-Spalte ohne Tabulator** im Zeugnis: nicht zuverlässig von einer Semesternote zu trennen.
- **S3/WebDAV als Kopie-Ziel**: siehe Datensicherung.
- **Let's Encrypt**: die VM ist nur im internen Netz erreichbar (keine öffentliche DNS/Port 80 von aussen); deshalb eigene CA. Mit öffentlichem Namen: `certbot --apache`.
- **HTTP → HTTPS-Umleitung**: bewusst noch nicht, solange Geräte die Lab-CA nicht vertrauen; Go-Live-Liste in betrieb.md.

## Hinweise

- ~~`bemerkung`-Feld auf `lernende` fehlt~~ erledigt (11.09. geprüft): Spalte auf Prod vorhanden, genutzt in `Verwaltung/LernendeController` und `verwaltung/lernende/edit`.
- border-red-500 vs border-border auf demselben Element: Gewinner hängt von CSS-Reihenfolge ab — falls roter Fehler-Rahmen nicht sichtbar, `!border-red-500` verwenden

## Sprache (11.09.2026) – bewusst weggelassen

- **Klassen, Methoden, Variablen, Views, DB-Tabellen auf Englisch**: Pflichtteil (Routennamen und URL-Pfade) ist umgesetzt, mit 301 von den alten Pfaden (`App\Support\LegacyPaths`). Der Rest berührt ~400 Dateien und das DB-Schema (Tabellen `benutzer`, `lernende`, `noten` … mit Fremdschlüsseln); für den Go-Live am 30.09. zu riskant. Vorgehen danach: pro Bereich ein Workflow mit Haiku-Agents (Umbenennen) und Tests als Netz, DB zuletzt mit Dump/Tag und Views/Aliassen.
- ~~**Sprachdateien und Umschaltung Deutsch/Englisch pro Benutzer**~~ überholt: Gerüst umgesetzt am 11.09. (siehe «Sprache DE/EN» unten, `docs/i18n-plan.md`).

## Agenda, Feedback, Themes (11.09.2026)

- ~~iCal-Export-Oberfläche für Berufsbildner/Admins: Server kann es (`CalendarExport::forUser`), Anzeige nur für Lernende gebaut – Bedarf im Pilot abwarten.~~ erledigt 11.09.: Seite «Prüfungstermine» (`/exams`) mit Abo-Box (Link, Kopieren, `webcal://`, Neuen-Link-Button) für Berufsbildner/Admin gebaut, Termin-URL im iCal zeigt für sie neu aufs Lernenden-Cockpit, alle iCal-Texte in der Sprache des Empfängers.
- Feedback: keine Screenshot-Vorschau vor dem Senden (Aufnahme erst beim Senden, robuster).
- ~~Feedback: keine Duplikaterkennung~~ erledigt 12.09.: Stimmen «Betrifft mich auch» + manuelle Duplikat-Markierung durch Admins (Migration `2026_09_12_000011`). Bewusst weggelassen: keine automatische Duplikaterkennung (Textähnlichkeit); wer eine Meldung nur unterstützt hat, wird beim Erledigen nicht benachrichtigt (nur die Absender von Original und Duplikaten); keine Stimmen im CSV-Export; Aufheben einer Duplikat-Markierung zieht bereits kopierte Stimmen nicht zurück; die Seitenzählung im Feedback-Widget setzt `route_name` voraus, Meldungen ohne Route-Namen zählen nicht mit.
- Agenda-Query-Parameter (`ansicht`, `monat`) noch deutsch; bei der späteren Code-Umbenennung mitziehen (LegacyPaths betrifft nur Pfade).
- Mobile Filterformulare (Lernende, Benutzer) sehr lang: Filterleiste in GUI-Paket 5.
- ~~`Uebersicht::berufsbildner()` liefert `vergleich` noch~~ erledigt mit Paket 4 (11.09. geprüft: kein `vergleich` mehr in `Uebersicht.php`).
- ~~Senkrechte Genügend-Linie für `balken()` (Lernenden-Dashboard) mit Paket 3.~~ erledigt.

## Paket 3 – Lernende (11.09.2026) – bewusst weggelassen

- ~~Drawer für «+ Note» nur auf `/grades`; auf der Übersicht führt «+ Note» noch auf die Seite `grades/create`.~~ erledigt (11.09., Reste-Session): `NoteService::drawerNachFehler()` + neue Komponente `<x-noten-drawer>` extrahiert, Dashboard (`dashboards/lernender.blade.php`) nutzt jetzt denselben Drawer wie `/grades`.
- ~~Kein «Als Tabelle»-Umschalter für die Diagramme «Wo stehe ich» und «Verlauf» (Screenreader bekommen nur `aria-label`/Tooltip).~~ erledigt (11.09.): `<details>`-Tabellenalternative (gleiches Muster wie `<x-diagramm>`) unter beiden Diagrammen ergänzt, serverseitig aus denselben Daten gerendert (`resources/views/dashboards/lernender.blade.php`); Test `UebersichtUndNotenTest::dashboard_bietet_tabellenalternative_fuer_wo_stehe_ich_und_verlauf`.
- ~~Segment-Umschalter (`role="radiogroup"`) ohne Pfeiltasten-Navigation; Tab + Enter funktioniert.~~ erledigt (11.09., Reste-Session): `x-radiogroup`-Direktive in `np.js` (Roving Tabindex + Pfeiltasten), angewendet auf alle `role="radiogroup"`-Container in `resources/views` (native `<input type="radio">` in `notifications/settings.blade.php` bewusst unverändert, dort bereits nativ bedienbar).
- ~~`np.js` `notenKlasse` nutzt noch Palettenfarben statt Noten-Tokens (nicht im Paket).~~ erledigt (11.09., Reste-Session): `TEXT`-Map auf `text-note-*`-Tokens umgestellt.
- ~~Bullet Graph ohne Bandnamen~~ erledigt (11.09.): Legende ungenügend/knapp/genügend/gut unter «Wo stehe ich», Spalte «Stufe» in «Als Tabelle». Stufennamen in Admin-/Berichtsdiagrammen erledigt (11.09.): Komponente `<x-noten-legende>` (Quelle `NotenSkala::stufenNamen()`) im Notenbericht (Verteilung + «Stufe»-Spalte, Tiefste Fächer) und in der Zeugnisnoten-Heatmap des Cockpits. Lange Fach-/Modulnamen mobil weiterhin auf 16 Zeichen gekürzt (voll im Tooltip).
- ~~`x-sparkline` zeigt standardmässig den letzten Wert als Zahl, auch in der BB-Tabelle~~ erledigt mit Paket 4 (`:zahl="false"`, siehe unten).
- Einzelnoten-Tabelle mobil: Spalte «Schnitt vor Rundung» ab `sm` ausgeblendet.

## Paket 4 – Berufsbildner (11.09.2026) – bewusst weggelassen

- `Uebersicht::berufsbildner()` liefert `vergleich`/`vergleichDiagramm()` nicht mehr – die Small-Multiples-Karte «Verlauf im Vergleich» (Paket 6) entfällt, «Im Vergleich» ist jetzt die sortierbare Verlauf-Spalte in der Tabelle (siehe Zeile 104 oben, damit erledigt).
- ~~Spalten der Tabelle «Meine Lernenden» sind nicht klicksortierbar~~ erledigt (11.09.): Sortierlinks mit `aria-sort` (`?sort=name|status|semester|gesamt|trend&dir=`), Allowlist in `Uebersicht::BB_SORTIERUNGEN`, Standard weiter Status → Nachname; Test `DashboardSortierungTest`.
- ~~Segment-Filter in «Meine Lernenden» auf 390px ausgeblendet~~ erledigt (11.09.): auch mobil sichtbar, horizontal scrollbar unter der Suche.
- `x-sparkline` in Tabelle und Cockpit-Stand mit `:zahl="false"` verwendet, um die Redundanz mit der danebenstehenden Semester-/Gesamt-Note zu vermeiden (löst den Hinweis aus Paket 3 oben).
- Keine «Vorher»-Screenshots erstellt, bevor die Änderungen begannen (Vorgabe verpasst); nur «Nachher»-Screenshots (hell/dunkel/mobil) unter `~/tools/out/paket4/` liegen vor.
- Cockpit-Reiter «Noten», «Dokumente», «Rechner» verlinken auf die bestehenden Einzelseiten statt Inline-Panels zu zeigen (kein eigener Seiteninhalt für diese Tabs im Blueprint verlangt).

## Paket 5 – Tabellen & Admin (11.09.2026)

- Erledigt: Filterleiste (`x-filterleiste`) auf allen gefilterten Admin-Indexseiten (Benutzer, Lernende, Module, Semester, Feedback, Mail-Log); Tabellen auf Katalog-Design (44px-Zeilen, sticky `bg-surface-2`-Header ohne Versalien, rechtsbündige `tabular-nums`-Zahlen, Hover/Focus-Reveal-Aktionen, auf Mobil immer sichtbar).
- Erledigt: Formular-Labels von Versalien/`tracking-widest` auf `text-sm font-medium text-text` vereinheitlicht (Admin-Einrichtung, Benutzer, Stammdaten, Lernende, Noten, Profil, Rechner, Dokumente, Import) – absichtlich unverändert gelassen: Statistik-Kacheln/Sektionsüberschriften im Dashboard-Stil (z. B. `einrichtung/modules.blade.php`, `rechner/index.blade.php`, `dokumente/index.blade.php`, `import/index.blade.php`) sowie ein erzwungenes Versal-Kürzelfeld (`einrichtung/professions.blade.php`).
- Erledigt: Admin-Übersicht neu strukturiert – eine «Handlungsbedarf»-Liste oben (Einrichtungslücken, Sicherung älter als 2 Tage, offene Meldungen, kritische Lernende) statt vier Karten, Kennzahlen als Statuszeile, Berufsbildner-Last- und Erfassung-12-Wochen-Karten bleiben. Backend dafür: `Uebersicht::admin()` liefert jetzt `handlungsbedarf` statt `einrichtung`/`kritisch`/`jahrgaenge`, neue private Methode `adminHandlungsbedarf()`.
- Erledigt: `admin/einrichtung/*` und `admin/berichte/noten` auf `<x-seitenkopf>` umgestellt; Diagramm-Logik in `admin/berichte/noten` unverändert gelassen.
- Erledigt: Bare-`glass`-Alias durch Tokens (`rounded-2xl border border-border bg-card` bzw. `rounded-xl …`) ersetzt, `glass-btn` unverändert gelassen; dabei auch verstreute Farb-Hardcodes (`red-*`, `green-*`, `yellow-*`) auf Noten-Tokens umgestellt.
- Erledigt (Reste-Session, 11.09.): letzter verbliebener Nutzer des Alias (`layouts/guest.blade.php`) auf Tokens umgestellt; `@utility glass` danach ohne Nutzer und aus `resources/css/app.css` entfernt (`glass-overlay`/`glass-bar`/`glass-scrim`/`glass-btn` bleiben bestehen).
- Test: `tests/Feature/Admin/DashboardTest.php` neu (Statuszeile/Handlungsbedarf, Sicherung-Alter-Hinweis) – zusammen mit dem restlichen Admin/Verwaltung-Testset grün (67 bzw. 45 Tests via `DB_DATABASE=notenportal_d_test`).
- Bug gefunden und behoben (nicht Teil der eigentlichen Aufgabe, aber blockierend): `admin/benutzer/index.blade.php` mischte die Inline-Form `@php(...)` mit einer späteren Block-Form `@php … @endphp` im selben Template – Blade paart beim Kompilieren den *ersten* `@php` unabhängig von seiner Form mit dem *nächsten* `@endphp` und verschluckte dadurch den kompletten Abschnitt dazwischen (Filterleiste, Tabelle) als unkompilierten Rohblock, was zu einem 500er auf `/admin/users` führte. Fix: Zeile auf reines `<?php … ?>` umgestellt; nur diese eine Zeile geändert, keine Alt-Logik berührt.
- ~~Bewusst weggelassen: Migration der Karte «Gesamtschnitt nach Lehrjahr» vom Admin-Dashboard in den Notenbericht (`admin/berichte/noten`).~~ erledigt (11.09.): kompakte Karte in `admin/berichte/noten.blade.php` (Tabelle Lehrjahr/Ø/Lernende), `Bericht::nachLehrjahr()` gruppiert die bereits vorhandenen `gesamt`-Werte aus dem Rechenkern nach `Lernender::lehrjahr()`; Test `BerichtTest::gesamtschnitt_nach_lehrjahr_gruppiert_und_zaehlt_lernende`.
- Keine «Vorher»-Screenshots erstellt (wie schon in Paket 4); nur «Nachher» (hell/dunkel/mobil) unter `~/tools/out/paket5/`.

## Sichtprüfung Pakete 3–5 (11.09.2026)

- ~~Admin «Erfasste Noten pro Woche»: KW-Beschriftungen schräg; laut Konzept jede 4. KW beschriften, nicht schräg (`charts.js`).~~ erledigt (Reste-Session): `maxRotation`/`minRotation` 0, Tick-`callback` beschriftet nur jede 4. KW (von rechts gezählt).
- ~~`/admin/learners` mobil: jede Karte hat einen gefüllten «Noten»-Button → mehrere Primäraktionen; «Noten» sekundär oder Zeilenklick.~~ erledigt (Reste-Session): «Noten»-Link auf sekundären Stil (Rahmen statt `bg-accent`) umgestellt, «Profil» bleibt einzige Primäraktion.
- ~~Lernenden-Verlauf: Direktlabels kürzerer Reihen («ÜK», «ABU») stehen auf der Linie am Reihenende statt rechts neben der Grafik.~~ erledigt (Reste-Session): `direktlabelPlugin` verankert die X-Position jetzt immer am rechten Plot-Rand (`chartArea.right + 6`) statt am eigenen letzten Datenpunkt der Reihe.
- ~~«Gesamtschnitt nach Lehrjahr» vom Admin-Dashboard entfernt, im Notenbericht noch nicht ergänzt.~~ erledigt (11.09.), siehe Paket 5 oben.

## Nachbesserungen Sichtprüfung Paket 5 (11.09.2026, Reste-Session)

Drei zusätzliche Befunde aus dem Review, im Rahmen derselben Session behoben:

- Filterformular in `verwaltung/noten/index.blade.php` liess sich ohne JS nicht absenden (nur `onchange` auf den `<select>`n) → `<noscript>`-Button «Filtern» ergänzt; Test `NotenKorrekturTest::notenliste_filter_hat_ohne_js_einen_absende_button`.
- `filterleiste.blade.php`: «Weitere Filter»-Panel nutzte `x-cloak` und blieb ohne JS dauerhaft unsichtbar (z. B. «Inaktive anzeigen» in `verwaltung/lernende/index`) → `x-cloak` entfernt, Alpine-State startet offen (`offen: true`), wenn in «Weitere» aktive Filter stehen.
- `scope="col"` auf allen `<th>` ergänzt in: `verwaltung/lernende/index`, `admin/benutzer/index`, `admin/feedback/index`, `admin/berufsbildner/index`, `admin/stammdaten/{module,kategorien,lehrberufe,faecher,semester}/index`, `admin/mail-log/index`, `dashboards/admin` (Berufsbildner-Last-Tabelle). `admin/notifications/index` und `verwaltung/noten/index` enthalten keine `<th>` (keine Tabelle) — nichts zu ändern.

## Sprache DE/EN (11.09.2026, Gerüst fertig, Sprachwahl aus)

- Vor dem Einschalten von `sprachwahl_aktiv`: englische Texte von einem Menschen gegenlesen lassen (`lang/en.json`, `lang/areas/*/en.json`).
- ~~Mails mit vorgebautem `MailContent` gehen in der Sprache des Auslösers statt des Empfängers: `KommentarController`, `Lernender/NotenController`, `NotenImportController`, `FeedbackController`, `Verwaltung/{NotenGesehen,Betreuung,LernendeNoten}Controller`, `Admin/{Feedback,MailSettings}Controller`, `Auth/{NewPassword,PasswortWechsel,Password}Controller`, `CheckNotifications`, `SicherungErstellen`, `GradeWatcher`, `AccountMails` → Inhalt erst im `Notifier` pro Empfänger bauen.~~ erledigt (11.09.): alle Aufrufe geben den Inhalt als Closure an `Notifier::send`/`dispatch`, `CheckNotifications::melden()` nimmt jetzt `MailContent|Closure`. `AccountMails` und `Auth/{NewPassword,PasswortWechsel,Password}Controller` seit b132c58 mit `__()` (11.09. geprüft); `Admin/MailSettingsController::test()` unverändert, da der Test-Empfänger kein `User` mit eigener Locale ist.
- Übersetzungen sind portalweit flach (ein Wert pro deutschem Schlüssel, 26 Kollisionen beim Zusammenführen nach Glossar vereinheitlicht). Mehrdeutige Wörter («Semester», «Berufsbildner», «Fehler») bei Bedarf mit Kontext-Schlüsseln lösen.
- ~~`Dokument::ARTEN`-Labels, Einrichtungs-Info-Texte, Import-Meldungen, Konto-/Passwort-Mails unübersetzt~~ erledigt (11.09.): `Dokument::label()`, `Einrichtung::stand()`, `NotenImport` (Status über internen Code), `AccountMails`/Auth-Controller mit `__()`; `i18n-scan` 26 → 21 Textknoten.
- Rest laut `notenportal:i18n-scan` (bewusst offen): CSV-Vorlage `NotenImport::vorlage()` (Kopfzeilen bleiben deutsch, der Import erkennt beide), Beispielwerte in Platzhaltern, Kürzel BMS/ABU.
  Nachtrag 30.09.: Der Scan steht auf 0. Beispielwerte (Hostname, Pfad, URL), `<?php ?>`, `<code>` und die Kürzel BMS/ABU/OK zählen nicht mehr, «}}@endif» gilt wie in Blade als Direktive; `SchluesselTest::views_enthalten_keine_texte_ausserhalb_von_uebersetzungen` hält die Views bei null. Die CSV-Kopfzeilen bleiben deutsch (Code, kein View).
- ~~Pint-Altlasten: `KommentarController`, `routes/web.php`~~ erledigt (11.09.).
- Nach dem Go-Live: Umbenennungen Code/Model/DB gemäss `docs/i18n-plan.md`.

## Browser-Rundgang (11.09.2026)

Skript `~/tools/visual/rundgang.mjs` (Playwright + axe-core, alle GET-Seiten je Rolle, 1280/390 px): keine JS-Fehler, kein seitliches Überlaufen.
- ~~Rechner-Anfrage im Noten-Formular mit 422 bei Noten nach Lehrende~~ erledigt: `Rechner::katalog()` deckt die Prüfungsdaten bestehender Noten ab.
- ~~axe serious/critical: Footer-Kontrast, Logo-Link ohne Namen (mobil), verschachteltes Element in `<summary>` (Lernenden-Dashboard), scrollbare Tabellen nicht fokussierbar, Link nur per Hover erkennbar (Module), Kontrast Notenblatt-Druck und Semester~~ erledigt; Scrollbereiche zentral über `registriereScrollbereiche()` in `np.js`.
- Wiederholung 11.09. nach Sicherheits-Headern, Übersetzungen und Stufen-Legende: 106 Seitenaufrufe, keine Befunde.
- Dokument-Detailseite im Browser (axe) nicht geprüft: keine Dokumente in den Prod-Testdaten. Gerendert und auf Englisch geprüft wird sie in `EnglischeSeitenTest` (lädt ein Dokument hoch).

## Englische Oberfläche & Abfragen (11.09.2026)

- ~~Deutsche Reste in der englischen Oberfläche~~ erledigt (9ab78eb): `EnglischeSeitenTest` rendert 72 GET-Seiten je Rolle mit `locale=en` (inkl. Detail-/Bearbeiten-Seiten, Einrichtung) und prüft sichtbaren Text samt aria-label/title/placeholder/alt; 11 Reste übersetzt, Pagination-Views übersetzt, 7 unbenutzte Pagination-Views entfernt. Bewusst ausgenommen: Seed-Daten und der feste Katalog `Einrichtung::LEHRBERUFE/FAECHER`.
- ~~N+1 im Admin-Dashboard (ungesehene Noten je Berufsbildner)~~ erledigt (39e232d): eine Abfrage für alle Berufsbildner. `AbfragenAnzahlTest` sichert 11 Seiten ab (Abfragezahl unabhängig von der Datenmenge). Obergrenzen 40/60 sind Startwerte – nach dem Go-Live mit echten Datenmengen nachjustieren. Antwortzeiten auf Prod (11.09., Seed-Daten, angemeldet per curl): alle Hauptseiten der drei Rollen 33–125 ms. `composer audit` und `npm audit` (inkl. dev): keine bekannten Sicherheitslücken.
- Zugriffsschutz-Audit vor dem Go-Live (11.09., Code-Lektüre aller Routen/Controller): keine hohen oder mittleren Befunde. Sichtbarkeit zentral über `Lernender::sichtbarFuer()` + `Betreuung::aktiv()` (fremde/abgelaufene Betreuung → 404), Fremd-IDs in `NoteService::kategorieFuer()`, Downloads mit UUID-Namen, iCal-Token widerrufbar, Login/Passwort-vergessen rate-limitiert. Hinweis für die Abnahme: jeder aktiv betreuende Berufsbildner kann die Betreuung an einen beliebigen anderen Berufsbildner übergeben (`Verwaltung\BetreuungController::store`, bewusst so).
- Statische Analyse (11.09.): PHPStan Stufe 1 auf `app/` ohne Larastan – 98 Meldungen, alle Laravel-Magie (statische Eloquent-Aufrufe, lokale Scopes, DB-Spalten als Attribute), kein echter Fehler. Keine Debug-Ausgaben (`dd`/`dump`/`console.log`) und keine TODO/FIXME im Code. Larastan als Dev-Abhängigkeit erst nach dem Go-Live (neue Abhängigkeit vor dem Freeze vermeiden).

## Persönliche Darstellung (11.09.2026)

Block U: eigenes Theme/Akzent/Schriftgrösse/Bewegung je Benutzer (`benutzer.praeferenzen` JSON, `App\Support\Darstellung`), vier neue Themes (Wald, Abendrot, Papier, Mitternacht) und sieben Akzent-Presets, Vorschaukarte als Blade-Komponente (`x-theme-vorschau`, ersetzt die duplizierte Vorschau in `admin/betrieb/_theme.blade.php`). Migration `2026_09_11_000009_benutzer_praeferenzen` liegt bereit, ist aber noch **nicht** auf Prod eingespielt (macht die Vorgabe, das selbst zu tun) – bis dahin bleibt die alte Kontrast-Checkbox im Profil aktiv (`Darstellung::praeferenzenOptionVerfuegbar()`-Guard).

- ~~Bewusst weggelassen: Dichte kompakt (kleinere Zeilenhöhen/Abstände als eigene Präferenz) – im Auftrag nur als Beispiel für „offene Ideen“ genannt, keine eigene Anforderung; `Darstellung::fuer()` ist so gebaut (unbekannte Schlüssel fallen still auf Standard zurück), dass ein weiterer Schlüssel `dichte` später ohne neue Migration ergänzt werden kann.~~ erledigt (11.09., Block X): Dichte normal/kompakt im Profil und in Ctrl+K.
- Bewusst weggelassen: `resources/views/noten/notenblatt.blade.php` (Druckansicht) bekommt keine `data-*`-Attribute – die Seite lädt weder `theme.css` noch `app.css`, sondern hat ein eigenständiges, fest verdrahtetes Inline-`<style>` (Druck-Layout mit Unterschriftsfeld); Personalisierung hätte dort keine Wirkung.
- Bewusst weggelassen: kein Integrationstest für den Guard-Zustand „Spalte `praeferenzen` fehlt noch“ (alte Kontrast-Checkbox) – die Testdatenbank wird über `RefreshDatabase` immer vollständig migriert, ein Zwischenzustand liesse sich nur über Reflection auf den statischen Cache in `Darstellung::praeferenzenOptionVerfuegbar()` erzwingen; das Verhalten selbst ist durch den bestehenden Zweig in `ProfileController::update()`/`ProfileUpdateRequest` sowie das analoge, bereits getestete Muster `Theme::kontrastOptionVerfuegbar()` abgedeckt.
- `docs/gui-konzept.md` Abschnitt d) nur kurz nachgeführt (Themetabelle, Akzent-Absatz); die maschinell erzeugten Pro-Theme-Kontrasttabellen (`gen-md.mjs`) wurden nicht neu für Wald/Abendrot/Papier/Mitternacht erzeugt – die eigentliche Kontrastprüfung läuft jetzt ohnehin dauerhaft über `tests/Feature/ThemeKontrastTest.php` (parst `theme.css` direkt) statt über die Doku.
- Orphane Übersetzung `"Hilfsmittel"` in `lang/en.json` entfernt (nicht Teil dieses Auftrags, aber vom Schlüssel-Scan-Test aufgedeckt und in derselben Session mit entfernt, da sonst der geforderte grüne Testlauf nicht möglich war): kein Code verwendet den Schlüssel mehr, nur noch `"Erlaubte Hilfsmittel"`.

## Tastenkürzel (11.09.2026)

- ~~Offen: Tastenkürzel zusätzlich als Einträge in der Befehlspalette (Ctrl+K) anzeigen.~~ erledigt (11.09., Persönliche Darstellung II): Eintrag «Tastenkürzel anzeigen» in `Navigation::befehle()`, öffnet den Dialog über das Event `open-tastenkuerzel` (analog `#feedback-modal` in `suche.js`).
- ~~Offen: Präferenz «Tastenkürzel aus» im Profil (für Screenreader-Nutzer mit eigenen Einzeltasten-Kürzeln).~~ erledigt (11.09., Persönliche Darstellung II): Präferenz `tastenkuerzel` (an/aus, `Darstellung::TASTENKUERZEL_*`); bei «aus» rendert `<x-tastenkuerzel>` weder den Dialog noch registriert das Alpine-Listener (kein toter Listener im DOM).
- Offen: Unter 1024 px (Tablet mit Tastatur) kein sichtbarer Einstieg in die Tastenkürzel-Übersicht – nur «?». Eintrag im mobilen Menü erst, wenn Nachfrage besteht (Touch-Geräte brauchen ihn nicht). Nachtrag 02.10.2026: bleibt bewusst offen – Massstab ist der Desktop ab 1920 px (`CLAUDE.md`), schmale Fenster bekommen keine eigene Gestaltungsarbeit.

## Mehr persönliche Konfiguration (11.09.2026)

Block X: Dichte (normal/kompakt, `data-dichte`), Dashboard-Karten je Rolle ein-/ausblenden (`App\Support\DashboardKarten`, Profil-Abschnitt «Übersicht», mind. eine Karte bleibt sichtbar), Schnellwechsel Theme/Schrift/Dichte in der Befehlspalette (Ctrl+K) über neuen Endpunkt `PATCH /profile/preferences`. Löst die frühere Notiz oben („Dichte bewusst weggelassen“) ein – die dort beschriebene Erweiterbarkeit von `Darstellung::fuer()` hat wie vorgesehen funktioniert.

- Karten-Daten werden bei ausgeblendeter Karte weiterhin berechnet, nur das Rendern entfällt (`DashboardController::kartenSichtbar()` + `@if($sichtbar['…'] ?? true)` in den drei `dashboards/*.blade.php`): `App\Services\Uebersicht` liefert alle Karten einer Rolle in einem Methodenaufruf (`lernender()`/`berufsbildner()`/`admin()`), ein selektives Weglassen einzelner Berechnungen wäre eine grössere Umstrukturierung dieses Service gewesen – wie im Auftrag als Ausweichlösung vorgesehen.
- Lost-Update-Risiko bei `PATCH /profile/preferences`: Der Endpunkt liest `benutzer.praeferenzen`, ändert nur das eine übermittelte Feld (`darstellung`/`theme`/`schrift`/`dichte`/`karten_ausgeblendet`) und schreibt das ganze JSON zurück – ohne Version/Lock. Zwei nahezu gleichzeitige Schnellwechsel desselben Benutzers (z. B. zwei offene Tabs, Ctrl+K in beiden) können sich so gegenseitig überschreiben: der zuletzt geschriebene Request gewinnt, der andere Wert geht kommentarlos verloren. Bei einem einzelnen Benutzer, der eigene, leicht wiederholbare Einstellungen ändert, ist der Schaden gering (kein Datenverlust an fremden Daten, einfach erneut umschalten) – bewusst nicht behoben, siehe Auftrag. **Erledigt 01.10.2026:** `ProfileController::preferences()` liest und schreibt in einer Transaktion mit `lockForUpdate()`; die Einstellungsseite schickt ihre Änderungen zudem streng nacheinander (`resources/js/praeferenzen.js`).

## Datenauskunft (11.09.2026)

Block AA: eigene Daten als ZIP herunterladen (Art. 25 DSG) über `App\Support\Datenauskunft` – `GET /profile/data-export` (eigenes Konto, Benutzermenü + Ctrl+K «Meine Daten herunterladen», hinter `password.confirm`) und `GET /admin/users/{user}/data-export` (Admin, beliebiges Konto, Knopf auf der Bearbeiten-Seite, ohne `password.confirm` – konsistent mit den übrigen Admin-Aktionen, die keine erneute Passwortbestätigung verlangen). README, `konto.json` (ohne Passwort-Hash/Tokens), je nach Rolle Noten/Prüfungen (inkl. Prüfungsart/Raum/Lehrperson/Hilfsmittel/Stoff/Notizen)/Ziele/Belegungen/Dokumente (inkl. `dokumente.json`-Metadaten) und eigene Kalender-Abonnements/-Termine (`kalender.json`; vom Abonnement nur Label und Host der Feed-Adresse, Pfad und Token nie – seit 03.10.2026, vorher stand die ganze URL samt Token darin) oder Betreuungen/eigene Kommentare, dazu für alle eigenes Feedback (inkl. Seite/URL/User-Agent/Ansicht), das eigene Versandprotokoll, `gesehen.csv` (eigene Ansichten von Noten) und eigene Tageszusammenfassungen.

- Der gesamte Export läuft in `withLocale($user->preferredLocale())` (Trait `Localizable`): CSV-Kopfzeilen, README und Dokumentnamen folgen der Sprache des exportierten Kontos, unabhängig von der Sprache der auslösenden Person (Admin-Export).
- Bewusst weggelassen: `kalender_token` (Auth-Token des persönlichen iCal-Feeds) fehlt weiterhin in `konto.json` und in `kalender.json` – ein Geheimnis wie ein Passwort, dessen Offenlegung den Kalender-Feed für Dritte abonnierbar machen würde.
- `remember_token` gibt es in diesem Schema gar nicht (die `benutzer`-Tabelle hat bewusst keine Spalte dafür, siehe `LoginRequest`) – die Isolations-Tests prüfen entsprechend nur auf `kalender_token`/Passwort-Hash als Geheimnisse.
- `NotenGesehen` (`gesehen.csv`) und `CalendarFeed`/`CalendarEvent` (`kalender.json`) sind neu Teil der Auskunft (vorher bewusst weggelassen, jetzt auf Wunsch des Reviews nachgerüstet).
- Dokumente werden unkomprimiert (`ZipArchive::CM_STORE`) ins ZIP gelegt; oberhalb von `Datenauskunft::$maxDokumenteBytes` (500 MB Summe `dokumente.groesse`) oder bei zu wenig freiem Speicher liefert der Controller statt eines ZIP eine Fehlermeldung per Redirect zurück.
- `EnglischeSeitenTest` (Seiten-Scan) wurde nicht um die beiden neuen Routen ergänzt: Downloads sind laut diesem Test ausdrücklich keine Seiten (CSV/ZIP-Export analog zu bestehenden Exporten) und werden dort grundsätzlich nicht geprüft; die Übersetzung der README/CSV-Kopfzeilen ist stattdessen über `SchluesselTest` (Schlüssel-Scan, literale `__()`-Aufrufe) abgesichert.
- `admin.users.data-export` funktioniert bewusst auch für Lernenden-Konten (anders als `admin.users.edit`, das dorthin auf `admin.learners.show` umleitet) – der Auftrag verlangt die Auskunft für „jedes Konto“ – und findet Konten auch nach Soft-Delete (`User::withTrashed()`).

## Persönliche Darstellung II (11.09.2026)

Block Z: eigene Akzentfarbe (`akzent_eigen`, `#rrggbb`, `App\Support\Farbe` leitet kontrastsichere Light-/Dark-Tokens im selben Format wie die eingebauten `[data-akzent]`-Blöcke in `theme.css` her, ausgegeben als Inline-`<style>` über `<x-akzent-eigen-stil>`), Schriftart (standard/serif/lesefreundlich, `data-schriftart`), Ecken (rund/eckig, `data-ecken`, reduzierte Radius-Tokens), Transparenz (normal/reduziert, `data-transparenz`, schaltet Liquid-Glass auf Leiste/Overlay ab), persönliche Startseite nach dem Login (`Darstellung::STARTSEITEN`, rollenbezogene Whitelist, nur ohne intended-URL wirksam), Tastenkürzel an/aus (siehe oben) und «Auf Standard zurücksetzen» im Profil.

- Bewusst vereinfacht: Die Live-Vorschau der eigenen Farbe im Profilformular setzt beim Farbwählen sofort den rohen Farbton als `--accent` (Alpine, kein Seiten-Neuladen) – ohne die serverseitige Kontrastgarantie (`App\Support\Farbe::tokens()`, Textfarbe/Ring/Chart-1 bleiben unverändert vom aktuellen Theme). Die kontrastsicheren Tokens gelten erst nach dem Speichern/Neuladen über `<x-akzent-eigen-stil>`. Eine vollständige Client-Portierung des Kontrast-Suchalgorithmus (OKLCH/HSL, stufenweise Anpassung) für eine 1:1-Vorschau war ausserhalb des Rahmens dieses Auftrags; die Garantie selbst ist unverändert serverseitig erzwungen und durch `tests/Feature/FarbeKontrastTest.php` (69 Tests, u. a. alle im Auftrag genannten Extremfarben plus 20 geseedete Zufallsfarben je hell/dunkel) abgesichert.
- Bewusst weggelassen: `rounded-full` (Avatar, Status-Punkt) reagiert nicht auf «Ecken: eckig» – es ist nicht Token-basiert (`--radius-full` gibt es in Tailwind nicht als überschreibbare Variable, `rounded-full` ist fix `9999px`), eine kreisförmige Fläche eckig zu machen wäre zudem kein sinnvolles Resultat.
- Bewusst weggelassen: «Transparenz: reduziert» deckt nur `.glass-bar`/`.glass-overlay` ab (Hauptnavigation, Befehlspalette/Menüs/Toasts/Popover); `.glass-btn` ist bereits deckend (Sekundärbutton-Bestandsklasse), das bare `.glass` hat in `app.css` keine eigene Definition (toter, unbenutzter Selektor) und wurde entsprechend nicht angefasst.
- «Auf Standard zurücksetzen» setzt nur die Felder dieses Formularabschnitts zurück (Theme/Akzent/Schriftgrösse/-art/Bewegung/Dichte/Ecken/Transparenz/Tastenkürzel/Startseite plus das alte `kontrast`-Flag) – bewusst **nicht** die ausgeblendeten Dashboard-Karten (`karten_ausgeblendet`, eigener Formularabschnitt «Übersicht», siehe Block X) und **nicht** die Spalte `darstellung` (Hell/Dunkel-Umschalter in der Navigation, unabhängig von den Präferenzen) – ein Reset des Erscheinungsbilds soll nicht implizit auch das Dashboard-Layout oder den Tagesmodus verändern.
- `DELETE /profile/preferences` (Reset) trägt denselben Lost-Update-Vorbehalt wie `PATCH /profile/preferences` (siehe «Mehr persönliche Konfiguration» oben): schreibt das ganze `praeferenzen`-JSON ohne Version/Lock zurück. Bei einer bewussten, einmaligen Reset-Aktion (mit Bestätigungsdialog) ist das Risiko eines verlorenen, gleichzeitig in einem zweiten Tab geänderten Werts vernachlässigbar – bewusst nicht behoben, aus denselben Gründen wie dort.
- Startseite: nur die im Auftrag genannten zwei zusätzlichen Ziele je Rolle (Lernende: Noten/Agenda; Berufsbildner/Admin: Lernende/Prüfungstermine) – keine freie URL-Eingabe, um versehentliche Weiterleitungen auf inzwischen ungültige oder rollenfremde Seiten auszuschliessen; die Whitelist ist zentral in `Darstellung::STARTSEITEN` gepflegt und dadurch leicht erweiterbar.
- `Darstellung::fuer()` ruft `DashboardKarten::rolleFuer()` (Rollenabfragen) nur noch auf, wenn tatsächlich eine von «dashboard» abweichende Startseite gespeichert ist – sonst wäre die Methode (jetzt an mehreren Stellen pro Seite aufgerufen: Layout, Tastenkürzel-Gating, Befehlspalette) bei den meisten Benutzern unnötig teurer geworden (siehe `AbfragenAnzahlTest`).

## Aktivitätsprotokoll (11.09.2026)

Block AB: Audit-Log für Admins über `App\Support\Protokoll::schreiben()` – Tabelle `aktivitaeten` (Migration `2026_09_12_000010_aktivitaeten`), Listener in `app/Listeners` (Login/Failed/Logout/PasswordReset, Laravel-Auto-Discovery, keine neue Provider-Registrierung nötig), Hooks in den bestehenden Admin-Controllern direkt nach der erfolgreichen Aktion. Seite `GET /admin/activity` (Admin only), Filter Aktion/Person/Zeitraum, Cleanup-Kommando `notenportal:aktivitaeten-aufraeumen` (365 Tage, täglich 03:15). Bis zur Migration auf Prod greift ein statisch gecachter `Schema::hasTable`-Guard (`Protokoll::verfuegbar()`) – ohne Tabelle wird nichts geschrieben und nichts bricht.

- Bewusst zusammengeführt: „Passwort geändert“ und „Passwort-Reset abgeschlossen“ sind eine einzige Aktion `auth.passwort_geaendert`. Laravel feuert für beide Broker-Flows («Passwort vergessen» und Admin-/Konto-Mail-Reset über `invites`) dasselbe Event `Illuminate\Auth\Events\PasswordReset` (in `NewPasswordController::store()` manuell ausgelöst, da der `PasswordBroker` es nicht selbst feuert); eine Unterscheidung wäre nur über eine Restrukturierung der Broker-Flows möglich gewesen und stand ausserhalb des Rahmens dieses Auftrags.
- Bewusst weggelassen: „Konto gelöscht“ als Ereignis. Im gesamten Code gibt es keine Lösch-Route für Admin-/Berufsbildner-Konten (nur ungenutzte Soft-Delete-Spalten) – ein Ereignis für eine nicht existierende Aktion zu protokollieren wäre erfunden; stattdessen sind Deaktivieren/Reaktivieren (`admin.konto_deaktiviert`/`admin.konto_reaktiviert`) abgedeckt, die tatsächlich existierende Admin-Aktion.
- Bewusst eingeschränkt: Bei „Betrieb geändert“ und „E-Mail-Einstellungen geändert“ (beide auf die gemeinsame Aktion `admin.betrieb_geaendert` gemappt, da inhaltlich dieselbe Kategorie „Betriebseinstellungen“) werden nur die geänderten Feldnamen protokolliert (`array_keys($validated)`), nie Werte – insbesondere nie das SMTP-Passwort oder andere Geheimnisse. Die separate «Kopie ausser Haus»-Konfiguration (rsync/SSH) und der Theme-Umschalter auf «Betrieb» lösen kein eigenes Protokoll-Ereignis aus (kein sicherheitsrelevanter Kontoeingriff im Sinn des Auftrags).
- `LogFailedLogin`: die eingegebene E-Mail wird nur protokolliert, wenn sie zu einem existierenden, aktiven, nicht gelöschten Konto gehört (`Illuminate\Auth\Events\Failed::$user`, von `EloquentUserProvider::retrieveByCredentials()` bereits so vorgefiltert) – sonst ausschliesslich der Hinweis „unbekanntes Konto“, nie der rohe Eingabewert.
- `App\Support\Protokoll::SPERR_MUSTER` filtert `details` rekursiv gegen `passwort*`/`password*`/`token*`/`secret*`/`*geheim*` (case-insensitiv) – trifft u. a. `smtp_passwort`; damit landen Geheimnisse nie im Log, selbst falls ein künftiger Aufrufer versehentlich ein ganzes Validierungs-Array durchreicht.
- Schreibfehler (z. B. DB kurzzeitig nicht erreichbar) brechen die eigentliche Aktion nie ab: `Protokoll::schreiben()` fängt jede Exception und schreibt nur `Log::warning()`.

### Review-Nachbesserungen (11.09.2026)

- Passwortänderung im Profil (`PasswordController::update()`) und Erstpasswort-Wechsel (`PasswortWechselController::update()`) protokollieren jetzt direkt `auth.passwort_geaendert` – beide Flows feuern kein `PasswordReset`-Event (das existiert nur im Broker-Reset über `NewPasswordController::store()`), daher kein Doppel-Eintrag mit `LogPasswordReset`; per Test abgesichert.
- Lernenden-Konto-Anlage protokolliert jetzt überall `admin.konto_angelegt` mit `['rolle' => 'Lernender']`: `Verwaltung/LernendeController::store()` und `Admin/EinrichtungController::lernende()` (Ersteinrichtung). `EinrichtungController::personen()` legt nie Lernende an (nur Berufsbildner/Admin, siehe Validierung `personen.*.rolle`) und protokolliert dort konsequent die tatsächliche Rolle statt `Lernender`.
- `AktivitaetController::index()` validiert Filter jetzt (`aktion` gegen `array_keys(Protokoll::LABELS)`, `person` max 100, `von`/`bis` als `Y-m-d`) über `Validator::valid()` – ungültige Werte werden still verworfen (kein 500, kein Redirect-Loop), da GET-Filter keine echte Formulareingabe mit Fehlermeldung sind.
- Personenfilter escaped LIKE-Platzhalter (`addcslashes($person, '%_\\')`), damit `%`/`_`/`\` in der Sucheingabe nicht als Wildcard wirken.
- `Protokoll::schreiben()` kürzt `ziel_bezeichnung` mit `mb_substr` auf 150 Zeichen (Spaltenlänge der Migration).
- Migration `2026_09_12_000010_aktivitaeten::up()` bricht nicht mehr ab, falls die Tabelle bereits existiert (`Schema::hasTable`-Guard am Anfang).
- Eigene Datenauskunft (`DatenauskunftController::eigene()`, `GET /profile/data-export`) protokolliert neu die eigene Aktion `auth.datenauskunft_eigene` («Eigene Datenauskunft heruntergeladen») statt fälschlich `admin.datenauskunft_erstellt` – die Admin-Aktion bleibt für `Admin\DatenauskunftController::zeigen()` (Datenauskunft für ein fremdes Konto) reserviert.
- `POST /login` zusätzlich mit `throttle:60,1` (pro IP) versehen – bewusst 60 statt eines niedrigeren Werts, da ganze Schulklassen oft hinter einer gemeinsamen NAT-IP hängen; ergänzt (nicht ersetzt) das bestehende Limit von 5 Fehlversuchen pro E-Mail+IP in `LoginRequest`.

## Web-App / PWA (11.09.2026)

Block AC: Manifest (`GET /manifest.webmanifest`, `App\Http\Controllers\ManifestController`, Name/Icons/Farben aus Betriebsname und Betriebs-Theme), Offline-Seite (`GET /offline`, statisch, ohne Personendaten), Service Worker `public/sw.js` (cacht nur `public/build/*`, Icons, die Offline-Seite; Navigation network-first; Version = SHA-256 des Vite-Manifests, alte Caches werden beim `activate` gelöscht), Registrierung in `resources/js/pwa.js` (nur Produktion, nur HTTPS/localhost), Cache-Leerung beim Abmelden per `postMessage`. Icons (192/512 + 512 maskable) einmalig aus dem bestehenden Logo (`application-logo.blade.php`) mit Playwright gerendert, `public/app-icons/`.

- Bewusst weggelassen: Push-Benachrichtigungen (Web Push/Notification API) und Background-/Periodic-Sync – nicht beauftragt, brauchen eigene Infrastruktur (VAPID-Schlüssel, Server-Endpunkt) und stehen ausserhalb des Rahmens dieses Auftrags.
- Bewusst weggelassen: kein eigenes „Neue Version verfügbar“-Update-Banner. `skipWaiting()`/`clients.claim()` sorgen dafür, dass ein neuer Service Worker beim nächsten Laden aktiv wird; eine Nutzeroberfläche dafür war nicht verlangt.
- Bewusst weggelassen: kein eigenes Installations-UI (`beforeinstallprompt`-Abfangen mit eigenem Knopf) – die Browser zeigen ihr Standard-Install-Angebot aus dem Manifest, das genügt dem Auftrag («installierbar»).
- Bewusst eingeschränkt: Der Service Worker cacht ausschliesslich gebaute Assets, Icons und die eine statische Offline-Seite – nie HTML mit Personendaten oder API-Antworten (Vorgabe des Auftrags); dadurch bleiben Noten/Namen/Termine offline nicht einsehbar, nur das App-Gerüst lädt sofort.
- Icon-Generator (`playwright`, einmalig gegen `~/tools/visual/node_modules` ausgeführt) ist keine Projektdatei und wurde nach dem Lauf wieder gelöscht – die drei PNGs liegen fest unter `public/app-icons/`, ein erneuter Lauf ist nur bei einer neuen Logo-Fassung nötig.
- Nachgebessert (87f0c35): Die Icons lagen zuerst unter `public/icons/`. Debian-Apache belegt `/icons/` aber global per `mod_alias`, daher 404, `cache.addAll()` im `install` schlug fehl und der Service Worker wurde nie aktiv. Nach `public/app-icons/` verschoben. Browserprobe: SW aktiv, 14 Einträge im Cache (nur Assets/Icons/`/offline`), Offline-Seite greift, Abmelden leert die Caches. `PwaTest` verbietet `/icons/` jetzt.

## Themes & Anzeige (11.09.2026)

Block AD: Drei neue Farbthemen Fjord (kühles Petrol/Türkis), Bernstein (warmes Honig/Amber) und Schiefer (kühles Blaugrau) in `resources/css/theme.css` + `App\Support\Theme` (je hell/dunkel, `tests/Feature/ThemeKontrastTest.php` grün). Neue Präferenz «Diagrammfarben» (standard/farbenblind, Okabe-Ito-Palette) als `data-diagramm` am `<html>`, überschreibt `--chart-1..6`; neue Präferenz «Notenanzeige» (1/2 Nachkommastellen) für `<x-note>`-Durchschnitte. Beide vollständig über `App\Support\Darstellung` (Konstante, `fuer()`, Reset), Profilformular, PATCH-Whitelist (nur Diagrammfarben, siehe unten) und Ctrl+K (nur Diagrammfarben).

- Bewusst nicht überschrieben: `[data-diagramm='farbenblind']` ersetzt ausschliesslich `--chart-1..6`, nicht die Notenband-Farben `--note-gut`/`--note-knapp`/`--note-ungenuegend` (rot/gelb/grün). Diese Tokens sind nicht diagrammexklusiv – sie stecken app-weit auch in Badges, Tabellen und der Notenskala (`App\Support\NotenSkala`) –, eine Überschreibung nur „in Diagrammen“ ist mit reinem CSS (Attribut-Selektor am `<html>`) nicht abgrenzbar, ohne auch Badges/Tabellen einzufärben. Für farbenblinde Nutzer bleibt die Unterscheidung in Diagrammen über die (bereits vorhandene) Form/Position der Datenpunkte und die textuelle Beschriftung erhalten.
- Bewusst nicht quick-switchbar: «Notenanzeige» ist **nicht** in der Ctrl+K-Befehlspalette und **nicht** in der PATCH-`/profile/preferences`-Whitelist (anders als «Diagrammfarben», analog zu Dichte). Begründung: Notenanzeige wirkt ausschliesslich serverseitig beim Rendern von `<x-note>` (keine CSS-Variable, kein `data-*`-Attribut, keine clientseitige Live-Vorschau via `document.documentElement.dataset`) – ein „Schnellwechsel ohne Neuladen“ ist für diese Präferenz technisch nicht sinnvoll umsetzbar, anders als bei Theme/Schrift/Dichte/Diagrammfarben. Dasselbe Muster existiert bereits für `startseite` und `tastenkuerzel` (auch nicht in der PATCH-Whitelist). **Überholt 01.10.2026:** Seit die Einstellungen jede Änderung sofort speichern (wie die macOS-Systemeinstellungen), nimmt `PATCH /profile/preferences` alle Darstellungsschlüssel an (`App\Http\Requests\PraeferenzenRequest`); in der Befehlspalette fehlen Notenanzeige, Startseite und Tastenkürzel weiterhin bewusst.
- Bewusst eingeschränkt: «Notenanzeige» wirkt nur beim bisherigen Standardfall von `<x-note :stellen="1">` (Notendurchschnitte auf interaktiven Seiten). Einzelne Noten (`:stellen` nicht gesetzt, z. B. Badges) und die bewusst abweichenden `:stellen="2"`-Stellen (Verwaltungslisten `verwaltung/lernende/index.blade.php`) bleiben unverändert – das ist der bestehende, gewollte Unterschied in der Anzeigepräzision und keine Baustelle dieses Auftrags. Notenblatt-Druck, Exporte und E-Mails rufen `NotenSkala::format()` direkt auf (nicht über `<x-note>`) und sind damit von der Präferenz architekturell unberührt – kein zusätzlicher Code nötig, um sie auszunehmen.
- Bewusst nicht übersetzt: Die neuen Theme-Namen «Fjord», «Bernstein», «Schiefer» haben keinen eigenen Eintrag in `lang/en.json` – wie bei allen bestehenden Themes (z. B. «Gletscher») wird der Name als `:name`-Parameter in `__('Theme: :name', ...)` eingesetzt statt selbst übersetzt zu werden (Eigennamen bleiben deutsch, siehe `Navigation::darstellungsBefehle()`).
- **Erledigt (30.09.2026):** `ProfileController::preferences()` übernimmt inzwischen alle Schlüssel aus `$aktuell` (`array_merge`); Regressionstest in `DarstellungProfilTest::seitenleiste_laesst_sich_im_profil_und_per_schalter_waehlen` (Schnellwechsel der Dichte lässt die Seitenleiste stehen). Ursprünglicher Befund: ~~Vorbestehender Fehler entdeckt, nicht behoben (ausserhalb des Rahmens dieses Auftrags): `ProfileController::preferences()` (PATCH-Schnellwechsel) baut `$user->praeferenzen` als komplett neues Array nur aus `theme`/`akzent`/`schrift`/`bewegung`/`dichte` (+ neu `diagramm`) auf; `akzent_eigen`, `schriftart`, `ecken`, `transparenz`, `tastenkuerzel`, `startseite` und `notenanzeige` werden dabei nicht aus `$aktuell` übernommen und fallen beim nächsten Lesen (`Darstellung::fuer`) still auf ihren Standard zurück – ein Schnellwechsel z. B. der Dichte über Ctrl+K löscht damit unbeabsichtigt eine zuvor im Profilformular gesetzte «Ecken: eckig» oder «Schriftart: Serif» (verifiziert per Tinker). Dieses Verhalten bestand bereits vor Block AD (für `schriftart`/`ecken`/`transparenz`/`tastenkuerzel`/`startseite`) und wurde für `diagramm` bewusst identisch fortgeführt, um den Fehler nicht zusätzlich zu vergrössern; `notenanzeige` wurde deshalb absichtlich **nicht** in diese Whitelist aufgenommen (siehe oben). Eine echte Behebung (`array_merge($aktuell, [...])` statt Neuaufbau) war nicht Teil dieses Auftrags und sollte in einem eigenen, fokussierten Block nachgezogen werden.~~

## Notenrechner (11.09.2026)

Block AF: Notenrechner-Drawer auf der Notenseite (`learner.grades.index`, Knopf «Notenrechner», `?rechner=1`, `resources/views/lernender/noten/index.blade.php`) – bis zu 10 hypothetische Noten gleichzeitig, Auswirkung alt → neu mit Differenz auf Kategorie-/Semester-/Gesamtschnitt sowie Promotionsstand. Serverseitig `POST learner.grades.calculator.simulate` (`App\Http\Controllers\Lernender\RechnerController::simulieren()`, `throttle:30,1`) rechnet über dieselbe Engine wie die echte Anzeige: `App\Services\Auswertung\Rechner::berechne()` (bereits bestehender Was-wäre-wenn-/Zielrechner) mit einer festen Füllvorgabe `ziel: 'gesamt', zielwert: 4.0`, da jede simulierte Zeile immer einen Wert hat (keine „unbekannten“ Leistungen) – dasselbe Muster wie die bereits bestehende Live-Vorschau im Notenformular (`npNotenFormular` in `resources/js/rechner.js`, ruft denselben Zielrechner mit `zielwert: 4` als Platzhalter auf).

- Bewusst kein «unsaved model mixing» und keine DB-Transaktion mit Rollback nötig: `App\Services\Auswertung\Leistung` ist ein reines `readonly`-Wertobjekt (kein Eloquent-Model). `Rechner::berechne()` mischt echte, aus der DB geladene `Leistung`-Objekte (`NotenQuelle::fuerLernenden()`) mit den aus dem Request gebauten hypothetischen `Leistung`-Objekten in einem PHP-Array und rechnet beide Varianten (vorher/nachher) rein im Speicher über `Rechenkern::auswerten()`. Es gibt in der gesamten Kette keinen einzigen schreibenden DB-Zugriff – „speichert nichts“ ist damit architektonisch garantiert, nicht nur per Test geprüft (der Test prüft es trotzdem: `Note::count()`, `modul_belegungen`-Anzahl und `MailLog::count()` vor/nach identisch).
- Bewusst `App\Services\Noten\NoteService::normalizeForSave()` **nicht** wiederverwendet: dessen Modul-Zweig ruft `resolveOrCreateOpenModulBelegung()` auf, was bei Bedarf eine neue `ModulBelegung`-Zeile in die DB schreibt – ein Seiteneffekt, der die „speichert nichts“-Vorgabe verletzt hätte. Stattdessen baut der Drawer für Modul-Zeilen direkt den Element-String `"modul:{id}"`; die Zugehörigkeit zum Lehrberuf des Lernenden prüft `Rechner::katalog()`/`zeile()` (reiner Lesezugriff auf `lehrberuf_module`) und wirft bei fremden Modulen dieselbe `ValidationException` (422) wie beim echten Erfassen.
- Bewusst eigene Validierungsregeln statt `Rechner::regeln()`: Letztere kennt keine `multiple_of:0.05`-Einschränkung. Der Drawer validiert `note_wert` mit denselben Regeln wie `NotenController::store()` (`min:1, max:6, multiple_of:0.05`), zusätzlich `zeilen` als Array mit `max:10`.
- Bewusst als «Ampelstatus» nur die bestehende Noten-Farbcodierung (`App\Support\NotenSkala`, gut/genügend/knapp/ungenügend – dieselben Klassen wie auf der echten Notenseite) plus der Promotionsstand aus `Rechner::promotionen()` gezeigt, nicht das grössere `Lernstand`/`LernstandRechner`-Dashboardkonzept (überfällige Prüfungen, mehrsemestrige Trends, Lehrbeginn/-ende). Letzteres ist für eine einzelne hypothetische Zeile nicht sinnvoll berechenbar und für Trainer-/Admin-Dashboards gedacht, nicht für die Lernenden-Notenseite.
- Bewusst keine eigene «keine Benachrichtigung»-Prüfung über `Notifier`/`GradeWatcher` nötig: Diese werden ausschliesslich von `NotenController::store()`/`update()` aufgerufen (echtes Speichern einer `Note`), nie vom Simulations-Endpunkt – der Test prüft das indirekt über eine unveränderte `MailLog`-Anzahl.
- Bewusst kein eigenes Protokoll-/Audit-Log-Ereignis (`App\Support\Protokoll`, siehe Block AB oben): Es gibt keinen sicherheitsrelevanten Kontoeingriff und keine tatsächliche Datenänderung, die ein Ereignis rechtfertigen würde; eine reine Leseoperation wird im bestehenden Aktivitäten-Katalog auch sonst nirgends protokolliert.
- Neue EN-Schlüssel (nur Ergänzungen, nichts umsortiert/gelöscht) in `lang/areas/learner/en.json`: `"Note entfernen"`, `"Note hinzufügen"`, `"Notenrechner"`, `"Nur eine Simulation – es wird nichts gespeichert."` (Letzterer seit 01.10.2026 entfernt, Hinweis gestrichen). Alle übrigen im Drawer verwendeten Texte (Feldbeschriftungen, Fehlermeldungen, «erfüllt»/«gefährdet») nutzen bereits vorhandene Schlüssel aus `lang/en.json` bzw. `lang/areas/learner/en.json`.
- `resources/js/rechner.js` musste sofort nach dem Hinzufügen des Drawer-Markups gebaut werden (`npm run build`), da die View die neue Alpine-Komponente `npNotenrechnerDrawer` sofort referenziert und Prod aus dieser Arbeitskopie läuft – kurzzeitiger JS-Fehler auf `/grades` während der Bearbeitung wurde vom Koordinator gemeldet und durch den Build behoben (`curl /login` = 200 danach geprüft).

## Systemhinweis & Sitzungs-Timeout (11.09.2026)

Block AE: Systemhinweis-Banner (`App\Support\Systemhinweis`, Werte in `einstellungen`, Wegklicken pro Benutzer über `notification_marks` – dasselbe Muster wie der Feedback-Hinweis) und Sitzungs-Timeout (`App\Support\Sitzung`, `sitzung_minuten` überschreibt `config('session.lifetime')` im `AppServiceProvider`, neben `MailSettings::apply()`). Admin-Formulare unter «Betrieb» (`_hinweis.blade.php`, `_sitzung.blade.php`, nach `_sprache`), neue Routen `admin.operations.notice.update`, `admin.operations.session.update`, `system-notice.dismiss`, `session.keep-alive`. Client: `resources/js/sitzung.js` (`registriereSitzung()`), Warnung 2 Minuten vor Ablauf, Cross-Tab-Sync über `localStorage`/`storage`-Event, sicheres Abmelden 5 Sekunden vor Ablauf (Service-Worker-Nachricht `ABMELDEN`, `fetch(logout, {keepalive:true})`, `location.replace`). 419 (abgelaufenes CSRF-Token) führt bei Nicht-JSON-Anfragen jetzt zu einem Redirect mit Meldung statt der Fehlerseite (`bootstrap/app.php`, `withExceptions`).

- Bewusst keine Migration: `einstellungen.wert` ist bereits `TEXT`, `notification_marks` hat bereits die passende `unique(['user_id','type','subject_key'])`. Beide Tabellen existierten schon vor diesem Block.
- Bewusst weggelassen (laut Auftrag): Links oder Markdown im Banner (Text geht ausschliesslich über `{{ }}`, kein `{!! !!}`), mehrere Banner gleichzeitig, ein Verlauf vergangener Hinweise.
- Bewusst weggelassen (laut Auftrag): unterschiedliche Timeout-Dauern pro Rolle, eine absolute Sitzungs-Höchstdauer unabhängig von Aktivität, automatisches Keep-Alive bei Maus-/Tastaturaktivität (nur per Klick auf «Angemeldet bleiben» – sonst hält ein offen gelassener Tab auf einem Schulgerät die Session ewig).
- Bewusst weggelassen (laut Auftrag): der Systemhinweis-Banner erscheint nur im eingeloggten Layout (`layouts.app`) und optional auf der Anmeldeseite, nicht auf Fehlerseiten (403/404/500) oder im übrigen Gast-Layout.
- Bewusst weggelassen (laut Auftrag): keine Mail-Benachrichtigung, wenn ein neuer Systemhinweis gesetzt wird.
- Bewusst nicht geprüft (laut Auftrag, kommt separat ins Backlog): `Cache-Control: no-store` für angemeldete Seiten – für geteilte Schulgeräte relevant (ein Browser-Zurück nach der erzwungenen Abmeldung könnte sonst noch den letzten gerenderten Stand zeigen), aber ausserhalb des Rahmens dieses Auftrags.
- `hinweis_version` (erste 16 Zeichen von `sha1` über den normalisierten Text) wird beim Speichern in `einstellungen` festgeschrieben statt bei jeder Anzeige neu berechnet – funktional identisch (der Hash hängt nur vom Text ab), aber so muss `SystemhinweisController::schliessen()` den aktuellen Text nicht laden, um die eingeschickte Version zu prüfen, und der Banner bleibt nach einer reinen Art-Änderung (gleicher Text) korrekt weggeklickt.
- «Hinweis entfernen» ist ein zweiter Submit-Knopf (`formnovalidate`) im selben Formular wie «Speichern»: er überspringt die normale Validierung, leert nur `hinweis_text`/`hinweis_version` und lässt Art/Zielgruppe/Zeitfenster unangetastet – damit bleiben diese Einstellungen erhalten, wenn der Hinweis später mit neuem Text reaktiviert wird.
- `SystemhinweisController::schliessen()` antwortet bei falscher/fehlender Version mit einer eigenen JSON-422-Antwort statt einer `ValidationException`, damit der Status unabhängig vom `Accept`-Header immer 422 ist (eine `ValidationException` würde bei einer Nicht-JSON-Anfrage stattdessen redirecten).
- Protokoll-Labels (`ADMIN_SYSTEMHINWEIS_GEAENDERT`, `ADMIN_SITZUNGSDAUER_GEAENDERT`) und die Admin-Formulartexte liegen in `lang/areas/admin/en.json` (Auftragsvorgabe); Layout-/Login-Texte sowie die generische Fehlermeldung „Ungültige Version.“ in `lang/en.json`. Bereits vorhandene Schlüssel («Abmelden», «Hinweis schliessen», «Alle», «Berufsbildner», «Lernende», «Text», «Ende», «Art», «Warnung») wurden wiederverwendet, nicht doppelt angelegt.
- Der Dialog-Fokus liegt auf «Angemeldet bleiben» (erster Knopf, wie im Auftrag verlangt); «Abmelden» daneben löst denselben sicheren Abmelde-Ablauf aus wie der automatische Timeout, nicht das normale Logout-Formular (kein zusätzlicher Seitenaufruf nötig).

## Betriebslogo (11.09.2026)

Block AH: Logo-Upload unter «Betrieb» (`_logo.blade.php`, neben `_hinweis`/`_sitzung`), `App\Support\Betriebslogo` (Validierung, Speichern/Entfernen auf dem privaten Disk unter `betrieb/`, feste Datei `logo.<ext>`, Einstellung `logo_datei`), öffentliche Auslieferung über `GET /branding/logo` (`BrandingController`, kein Login, `nosniff`, ETag/Last-Modified mit 304, `Cache-Control: public, max-age=300`, vom `no-store`-Header für angemeldete Nutzer ausgenommen wie Manifest/Offline). Anzeige in Navigation/Login (`components/application-logo.blade.php`), Mail-Kopf (`mail/portal.blade.php`) und Notenblatt-Druck, überall mit stillem Rückfall auf den bisherigen Platzhalter bzw. reinen Textnamen, auch bei verwaister Einstellung (Datei fehlt). Inhaltsprüfung über `getimagesize()` (echte Bilddaten, nicht nur Dateiendung/MIME-Header) lehnt SVG, als Bild getarntes HTML/Text zuverlässig ab. Sicherung (`Sicherung.php`) nimmt `betrieb/` neu mit ins ZIP unter `dateien/`. `tests/Feature/BetriebslogoTest.php` (19 Tests).

- Bewusst weggelassen (kein Auftragsbestandteil): kein Bildzuschnitt/keine Grössenanpassung im Browser oder Server – die Grenzen (1 MB, 1024×1024 px) müssen vom hochgeladenen Bild bereits eingehalten werden, keine automatische Verkleinerung/Kompression.
- Bewusst weggelassen: kein separates Logo für helles/dunkles Theme oder für E-Mail vs. Web – ein einziges Bild für alle Auslieferungsorte, mit fester max. Höhe skaliert (Seitenverhältnis über `w-auto`/`height:auto` erhalten).
- Bewusst weggelassen: keine automatische Thumbnail-/Favicon-Erzeugung aus dem Logo; das bestehende PWA-Icon-Set (`public/app-icons/`, Block AG) bleibt unverändert und unabhängig vom Betriebslogo.
- Bewusst weggelassen: kein Verlauf früherer Logos, kein «Wiederherstellen» einer entfernten Datei – Entfernen löscht die Datei endgültig (die letzte Sicherung enthält sie weiterhin, siehe oben).
- Bewusst kein automatisierter Screenshot-/Pixel-Test für den Notenblatt-Druck: `notenblatt.blade.php` ruft `Betriebslogo::url()` direkt in seinem eigenen `@php`-Block auf (kein Umweg über einen View-Composer) und zeigt das Bild nur, wenn `$logoUrl` gesetzt ist – geprüft ist das über bestehende Notenblatt-Tests (Seite rendert ohne Fehler mit und ohne Logo), eine visuelle Prüfung des gedruckten PDF-Layouts war nicht Teil dieses Auftrags.
- «Logo entfernen» ist ein zweiter, unbenannter Wert tragender Submit-Knopf (`name="logo_entfernen" value="1"`, `formnovalidate`) im selben Formular wie «Speichern» (Muster wie «Hinweis entfernen» in Block AE); da der Knopf einen Wert mitschicken muss, sperrt `@submit` erst asynchron (`setTimeout`) statt synchron, sonst geht `logo_entfernen` beim Klick verloren (Vorgabe des Koordinators während dieses Auftrags).
- EN-Texte in `lang/areas/admin/en.json` (Formular/Protokoll-Labels, Auftragsvorgabe) und `lang/en.json` (Validierungsfehler «kein gültiges Bild», «Pixel gross»), keine bestehenden Schlüssel doppelt angelegt.
- Opus-Review (11.09.2026), nachgebessert: `GET /branding/logo` läuft ohne Session-/Cookie-/CSRF-Middleware (`->withoutMiddleware([...])`), eigene CSP `default-src 'none'; sandbox` und `Content-Disposition: inline`, Upload wird mit GD neu kodiert (entfernt EXIF/Polyglot-Anhänge), `Betriebslogo::url()` trägt `?v=<Zeitstempel>` (Cache-Busting), Cache-Control dadurch `max-age=86400`.

## Notenrechner übersichtlicher (11.09.2026)

Block E (PO-Rückmeldung #10): Standard-Tab des Notenrechners ist nie mehr «Gesamt» – neue `Rechner::standard()` wählt Fach/Modul mit der nächsten geplanten Prüfung (`Rechner::vorschlaege()`, bereits vorhandene `pruefungen`-Abfrage), sonst das zuletzt benotete Fach/Modul, sonst das erste im Katalog. `Rechner::start()` übersteuert das nur, wenn die Seite mit `?ziel=` aufgerufen wird (bestehender Mechanismus, siehe `App\Services\Uebersicht`) – kein neuer `?fach=`-Parameter nötig. Beide Controller (`Lernender\RechnerController`, `Verwaltung\RechnerController`) reichen `start` an die View durch. Je Tab ein erklärender Satz (`$fragen` in `rechner.index`), Ergebnis als ein zusammenhängender Satz statt Einzelfragmenten (`heroSatz`-Getter in `rechner.js`, Satzvorlagen kommen aus Blade via `__()`, nur Platzhalter-Ersetzung in JS). Dabei nebenbei eine bestehende Verletzung der UI-Skill-Regeln behoben (`uppercase tracking-widest` → `text-xs font-medium`).

- Bewusst kein neuer `?fach=`-Parameter: der bestehende `?ziel=`/`?zielwert=`-Mechanismus deckt die Anforderung „per URL übersteuerbar" bereits ab und wird an mehreren Stellen (u. a. `Uebersicht.php`) so verlinkt – ein zweiter, redundanter Parameter hätte nur Verwirrung gestiftet.
- Bewusst keine neue Berechnungslogik: `heroSatz` liest ausschliesslich vorhandene Felder aus `Zielrechner`/`Rechner::berechne()` (Status `benoetigt`/`erreicht`/`unerreichbar`/`ohne_einfluss`/`keine_unbekannten`), keine neuen Formeln.
- EN-Texte (17 neue Schlüssel: Tab-Fragen, Satzvorlagen) in `lang/areas/learner/en.json` (Auftragsvorgabe, `lang/en.json` war parallel in Bearbeitung).
- Sichtprüfung bei 390 px (`breite.mjs`, `shot.mjs --mobil` gegen die Demo-Instanz): kein horizontales Überlaufen, keine JS-Fehler, Standard-Tab korrekt auf «Fach» statt «Gesamt».
- Neue Tests in `tests/Feature/Auswertung/RechnerTest.php` (5 neue, u. a. Prüfungs-Priorität vor letzter Note, Fallback-Kette, nie «gesamt», `?ziel=`-Übersteuerung) – gesamter Rechner-Testfilter 74 grün, `vendor/bin/pint` sauber, `ß`-Grep leer, `view-pruefung.sh` sauber.
- Noch offen (nicht Teil dieses Auftrags, für einen eigenen Block): Mail-Kopf-Logo braucht eine öffentlich erreichbare, echte HTTPS-`APP_URL` (sonst zeigen externe Mail-Clients kein Bild bzw. laden es unverschlüsselt) – `route('branding.logo', ...)` erzeugt aktuell eine URL nach `APP_URL`, ohne Prüfung, ob diese von aussen erreichbar/HTTPS ist. `trustHosts` in `bootstrap/app.php` ist nicht gesetzt (Host-Header-Validierung). `App\Support\Einstellungen::get()` liest pro Aufruf aus dem Cache statt pro Request zu memoisieren (`Cache::memo()`) – bei den vielen `Betriebslogo`-Aufrufen pro Seite (Navigation, evtl. Mail-Rendering mehrerer Anlässe) unnötig viele Cache-Zugriffe.
  Nachtrag 02.10.2026: `Einstellungen::get()` memoisiert statisch pro Request (`Einstellungen::$cache`, war schon so). `trustHosts` ist seit heute in `bootstrap/app.php` gesetzt (`App\Support\TrustedHostPatterns`: Einträge aus `TRUSTED_HOSTS`, das `install.sh` mit allen Namen und Adressen des Zertifikats füllt und bei dem es Hand-Einträge behält, plus Host aus `APP_URL` samt Subdomains; leeres `TRUSTED_HOSTS` heisst bewusst keine Einschränkung, damit ein `git pull` ohne Installer keinen Alias auf 400 setzt – `notenportal:bereitschaft` warnt dann (`Bereitschaft::trustedHosts()`); lokal und in Tests aus; `TrustedHostsTest` prüft Registrierung, Muster und den Durchstich 400/200; Betriebsfolge in `docs/betrieb.md` «HTTPS»). HTTPS-`APP_URL` prüft `notenportal:bereitschaft` (`Bereitschaft::appUrl()`, Warnung ohne `https://`); ob die Adresse von aussen erreichbar ist, bleibt eine Betriebsprüfung vor der Freigabe.

## Relative Semesternamen & Restdauer (11.09.2026)

Block B (PO-Wunsch): Rohcodes wie «24/25-1» werden dort, wo genau ein Lernender im Kontext ist, durch eine relative, personalisierte Nummerierung ersetzt («5. Semester» für jemanden, der im 24/25-1 begonnen hat und jetzt im 26/27-1 ist). `App\Support\Lehrsemester` (statisch, pro Request gecacht über `$ersteSortierung`) berechnet Nummer/Name aus `lehrbeginn` + `semester.sortierung`; `App\Services\Auswertung\Konfiguration::semesterName(?int $id, ?int $lernenderId = null)` ist der zentrale Dispatcher: personalisierte Nummer wenn `$lernenderId` bekannt, sonst neutraler Name über `App\Models\Semester::neutralerName()` («HS 2026/27»/«FS 2027», aus dem Startdatum), sonst Rohcode als letzter Rückfall. Restdauer des aktuellen Semesters mit automatischer Einheit (Tage/Wochen/Monate) über `App\Support\Format::restdauer()`. Neue Tests: `tests/Unit/Auswertung/LehrsemesterTest.php` (8), `tests/Unit/Auswertung/RestdauerTest.php` (7).

- Anzeigeregel je Kontext: **Einzelner Lernender** (Notenübersicht, Notenformulare, Dashboard, Notenrechner-Drawer) → personalisierte Nummer «N. Semester». **Mehrere Lernende gleichzeitig** (Admin-Sammelbericht `admin/berichte/noten.blade.php`, Berufsbildner-Dashboard-Listen) → neutraler Name «HS 2026/27», da dasselbe Semester je nach Lehrbeginn pro Lernendem eine andere Nummer hätte. **Stammdaten-Verwaltung** (`verwaltung/lernende/_cockpit/profil.blade.php`, Track-Start-/Endsemester-Auswahl) → bewusst unverändert Rohcode, da dort das Semester als Stammdaten-Schlüssel bearbeitet wird, nicht als Anzeige für einen Betrachtungskontext. Tooltips (`[title]`) zeigen weiterhin den Rohcode als technische Referenz.
- Zwei produktionsrelevante Nachbesserungen während dieses Auftrags (Details siehe Commit-/Sitzungsverlauf): `admin/berichte/noten.blade.php` rief `anzeigeName()` (Model-Methode) auf `stdClass`-Zeilen einer `DB::table()`-Abfrage auf → 500 auf `/admin/reports`; behoben durch Umstieg auf den statischen Helfer `Semester::neutralerName($start)`. N+1 bei personalisierten Namen in `LernstandRechner::fuer()` (ein `Lehrsemester::nummer()`-Query pro neu gesehenem Lernenden in einer Liste) behoben durch `Lehrsemester::vorladen()` (Massenvorladung in derselben bereits vorhandenen `lehrbeginn`-Abfrage, kein zusätzliches Query).
- `App\Services\Auswertung\Konfiguration::semesterName()` bleibt bewusst frei von einer harten `__()`-Abhängigkeit im Rechenkern-Pfad: eine Prüfung `app()->bound('translator')` lässt reine `PHPUnit\Framework\TestCase`-Unit-Tests ohne gebootete Laravel-App (`tests/Unit/Auswertung/RechenkernTest.php`, ruft `Auswertung::fach()`/`toArray()` ohne `$lernenderId` auf) auf den Rohcode zurückfallen, statt mit `BindingResolutionException` abzustürzen – in jedem echten HTTP-Request ist der Übersetzer immer gebunden, der Rückfall greift ausschliesslich in isolierten Unit-Tests.
- Bewusst nicht angefasst (ausserhalb des Rahmens, andere Blöcke): `resources/js/rechner.js` (JS-off-limits) – dessen `semesterVon()` zeigt `.name` aus der vom Server gelieferten `semesterListe` per `x-text` an; ein Rohcode-Leck dort wurde stattdessen an der Quelle behoben (`App\Services\Noten\NoteService::semesterListe(?int $lernenderId = null)`, personalisiert jetzt den `name`-Wert selbst, bevor er ans JS übergeben wird). `resources/views/noten/notenblatt.blade.php` (Notenblatt-Druck, Logo-/Betrieb-Territorium) – Semesteranzeige dort unverändert gelassen. `verwaltung/lernende/_cockpit/profil.blade.php`'s separate `semesterListe`-Abfrage (Track-Start-/Endsemester, andere Bedeutung als die Notenformular-Liste) – unverändert, siehe oben. `App\Services\Datenauskunft`/`App\Services\Zielgroesse` (falls dort Semesterbezeichnungen auftauchen) wurden nicht durchsucht/geändert, da ausserhalb des vom Koordinator umrissenen Sichtbereichs für diesen Block.
- Sichtprüfung: `node breite.mjs --base=http://127.0.0.1 --rolle=learner --breite=390 /grades /dashboard` (kein horizontales Überlaufen; Passwort nur über `NP_TEST_PW`) plus ein temporäres Playwright-Textscan-Skript (seither gelöscht) bestätigte «N. Semester» sichtbar und keinen Rohcode ausserhalb von `[title]`-Tooltips auf `/grades`.
- Serieller Gesamttestlauf am Ende: 828 grün, 4 rot – alle 4 verifiziert unabhängig von diesem Block: 2× `I18n\SchluesselTest` (fehlende EN-Schlüssel in `resources/views/rechner/index.blade.php`, Block E), `I18n\LaufzeitTest` (fehlender EN-Schlüssel «Daten» in `resources/views/settings/_tabs.blade.php`, Block C), `ProfilBearbeitenTest` (transiente Nebenläufigkeits-Störung durch parallele Hintergrund-Agenten auf derselben Arbeitskopie – isoliert erneut ausgeführt: 6/6 grün).

### Review-Nachbesserung (11.09.2026, Abend)

Sieben Punkte aus der Review behoben: (1) `Rechner::berechne()`/`ziele()`/`vergleich()` riefen `Rechenkern::auswerten()` direkt auf statt über `NotenQuelle::auswertung()`, wodurch `Auswertung::$lernenderId` null blieb und Notenrechner-Labels (Ziel/Vergleich/Promotion) auf derselben Seite den neutralen statt dem relativen Namen zeigten, obwohl die Auswahl daneben schon relativ war – `$lernenderId` wird jetzt nach jedem direkten `auswerten()`-Aufruf explizit gesetzt, `Zielgroesse::label()` nimmt neu einen optionalen `?int $lernenderId`-Parameter und reicht ihn an `semesterName()` durch (auch in `App\Services\Uebersicht::zieleMitBedarf()` nachgezogen). (2) `Semester::neutralerName()` bestimmte Herbst/Frühling an einer festen Kalendergrenze (`Monat >= 8`) statt an der tatsächlich konfigurierten Semesterplanung (frei wählbare Startdaten, `App\Support\Einrichtung::semesterPlan()`) – jetzt leitet eine neue private `Semester::neutralerPlan()` die Jahreszeit aus der chronologischen Position im über `Konfiguration::ausDb()->semester` geladenen, sortierten Semesterplan ab (1./3./5. … = Herbst, 2./4./6. … = Frühling) und hängt bei einer verbleibenden Namenskollision (z. B. drei Semester pro Jahr) das Startdatum an, damit der neutrale Name garantiert eindeutig bleibt; neuer Test `tests/Unit/Auswertung/SemesterNeutralerNameTest.php` (4 Fälle: Standardplan, untypischer Startmonat, erzwungene Kollision, unbekanntes Datum). (3) `SemesterClosed::betreuer()` (Sammel-Mail an Betreuende über mehrere Lernende) zeigte noch den Rohcode statt `Semester::neutralerName()` in Betreff/Zeilen/Digest-Titel – nachgezogen. (4) `verwaltung/noten/index.blade.php` und (5) `Notenblatt::fuer()` reichten die vorhandene `$a->lernenderId` nicht an `semesterName()` weiter, obwohl der Kontext personalisiert ist – beide ergänzt. (6) `Lehrsemester::vergessen()` war totes Coding (nie aufgerufen), während `Konfiguration::vergessen()` an 5 Stellen bei Semester-/Einstellungsänderungen im selben Request lief (`EinrichtungController.php` ×4, `Betrieb::speichern()`) – an allen 5 Stellen `Lehrsemester::vergessen()` ergänzt, damit personalisierte Nummerierung nach einer Änderung nicht veraltet bleibt. (7) `Format::restdauer()`s Schwellen (`> 60`/`> 14` Tage) machten die Einzahl-Texte «noch 1 Woche»/«noch 1 Monat» unerreichbar (`round()` an der Grenze ergab immer ≥ 2) – Schwellen auf `>= 30`/`>= 7` Tage verschoben, `RestdauerTest.php` entsprechend neu geschrieben (7 Fälle inkl. beider Einzahl-Grenzen).

- Gezielte Tests (gesamt 122, alle grün): `SemesterNeutralerNameTest` (4, neu), `RestdauerTest` (7, neu geschrieben), `LehrsemesterTest` (8), `RechenkernTest` (17), `ZielrechnerTest` (9), `Feature\Auswertung\RechnerTest` (18), `Feature\Admin\EinrichtungTest` (4), `Feature\BetriebslogoTest` (24), `Feature\Lernender\NotenrechnerDrawerTest` (7), `Feature\Lernender\UebersichtUndNotenTest` (11), `Feature\MailLinksTest` (4).
- Serieller Gesamttestlauf: instabil während dieser Nachbesserung, weil mehrere andere Blöcke parallel an gemeinsam genutzten Dateien arbeiten (12–33 rote Tests je nach Momentaufnahme, u. a. `App\Services\Auswertung\NotenQuelle.php`/`Modulstatus.php` durch Block F, `lang/en.json` durch Block C/G) – jede einzeln nachgeprüfte Fehlschlagsgruppe war reproduzierbar unabhängig von den hier geänderten Dateien (`ModulstatusAnzeigeTest`, `I18n\SchluesselTest`/`EnglischeSeitenTest`/`BerufsbildnerEnglischTest`/`LernendeEnglischTest`, `Lernender\TrackNachDatumTest`, `DemoSeederTest`, `Performance\AbfragenAnzahlTest` – letzterer war ein transienter `array_push()`-Fehler in `NotenQuelle.php`, der zwischen zwei Läufen von Block F selbst behoben wurde). Keine dieser Dateien wurde in dieser Nachbesserung angefasst.
- `vendor/bin/pint` auf allen geänderten Dateien: keine Beanstandung. `php -l` auf allen geänderten PHP-Dateien: fehlerfrei. `/login` weiterhin 200, `grep -rn "ß" resources/views lang` weiterhin leer, `.claude/hooks/view-pruefung.sh` auf der geänderten Blade-Datei sauber.

## Notenimport: Vorschau prüft wie die Datenbank, «Erneut prüfen», Alles-oder-nichts (11.09.2026)

Block H, Schritte H1–H3 (PO-Rückmeldung #16 «Datenimporte intelligenter – exakt so, wie die Datenbank es erwartet»). H1: Wert-Parsing (Datum d.m.Y/Excel-Seriennummer/ISO, Dezimalkomma, Note, Gewicht) aus `NotenImport` in eine eigene, zustandslose `App\Services\Import\WertParser` ausgelagert; aus `NoteService::normalizeForSave` eine seiteneffektfreie `pruefeZeile()` (Lehrbeginn/-ende, Semester am Datum, Kategorie) extrahiert, `normalizeForSave` ruft sie selbst auf (eine Regelquelle) und ergänzt nur noch die Modul-Belegung. H2: `NotenImport::vorschau()` ruft dieselbe `pruefeZeile()` über eine neue geteilte `bewerten()`-Methode auf → Zeilenfehler schon in der Vorschau statt erst beim Speichern; Dubletten innerhalb derselben Datei werden erkannt (`datei_doppelt`); unsichere Zuordnungen (Status «prüfen», unscharfe Fach/Modul-Erkennung) sind nicht mehr vorausgewählt; mehrdeutiges Datum (z. B. «03/04/2026», Tag/Monat vertauschbar) und mehrdeutiges Gewicht (Zahl ≤ 1 ohne «%», z. B. «0.5») erscheinen als Warnung mit erklärendem Text, bleiben aber vorausgewählt, da die Zuordnung selbst sicher ist. H3: neue Route `.../import/validate` (`NotenImportController::pruefen()`, Lernende und Verwaltung) prüft bearbeitete Vorschauzeilen nach Korrekturen serverseitig neu über `NotenImport::pruefeZeilen()` («Erneut prüfen»-Knopf in der Vorschau); `importieren()` sammelt zuerst alle ausgewählten Zeilen, prüft jede gegen `NoteService::pruefeZeile()` und speichert nur, wenn keine einzige einen Fehler hat (alles oder nichts) – sonst wird nichts gespeichert und alle Zeilenfehler kommen zurück.

- Neue Datei `app/Services/Import/WertParser.php` mit eigenem Unit-Test `tests/Unit/Import/WertParserTest.php` (6 Tests, reines PHPUnit ohne Laravel-Bootstrap, da zustandslos).
- Geänderte Kerndateien: `app/Services/Import/NotenImport.php` (neue `bewerten()`- und `pruefeZeilen()`-Methoden, `importieren()` alles-oder-nichts), `app/Services/Noten/NoteService.php` (`pruefeZeile()` extrahiert), `app/Http/Controllers/NotenImportController.php` (neue `pruefen()`-Aktion), `routes/web.php`/`routes/verwaltung.php` (neue `import.validate`-Route je Bereich, erbt bestehende `role:`-Middleware der Gruppe – von `ZugriffsschutzTest` automatisch erfasst, keine manuelle Listenpflege nötig), `resources/views/import/index.blade.php` (Fehlerzeilen-Checkbox deaktiviert, «Erneut prüfen»-Knopf, Warnung-Status in Statistik/Farben).
- Neue/angepasste Tests: 5 neue Fälle in `tests/Feature/NotenImportTest.php` (Dubletten in derselben Datei, mehrdeutiges Datum/Gewicht als Warnung, «Erneut prüfen» über die neue Route, eine ausgewählte Fehlzeile verhindert den gesamten Import – explizit die geforderte 0-Noten-Prüfung auf Controller-Ebene), je 1 Anpassung in `tests/Feature/NotenImportTest.php` (Vorauswahl «prüfen» entfernt) und `tests/Feature/Lernender/TrackNachDatumTest.php` (alles-oder-nichts statt Teilübernahme). Alle bestehenden Noten-/Import-Tests (`ImportFormateTest`, `Verwaltung/NotenImportVerwaltungTest`, `ZeugnisAbgleichTest`, gesamte Noten-Suite) unverändert grün.
- Erledigt am 30.09.2026 (Nachtrag unten: Batch lädt Semester/Tracks einmal, 163 → 17 Abfragen). Ursprünglich bewusst offen gelassen (ausserhalb des Rahmens H1–H3, hier dokumentiert statt stillschweigend übergangen): `bewerten()` ruft `NoteService::pruefeZeile()` pro Vorschauzeile auf, was pro Zeile mehrere DB-Abfragen auslöst (Lernender, Semester am Datum, Track-Zugehörigkeit, Kategorie) – bei sehr grossen Importdateien (bis `TabellenLeser::MAX_ZEILEN`) ergibt das ein spürbares N+1-Muster. Für H1–H3 nicht optimiert, da Korrektheit vor Performance stand; ein späterer Block könnte die Lernender-/Semester-/Track-Daten einmal pro Vorschau vorladen statt pro Zeile neu zu laden.
- Kein `npm run build`: nur bereits im aktuellen Build vorhandene Klassen verwendet (`disabled:opacity-60` statt einer neuen `disabled:opacity-40`-Variante, um während der parallelen Arbeit anderer Agenten an `resources/js` keinen Build auszulösen); Alpine-Erweiterungen (`erneutPruefen()`, `pruefeLaedt`) sind reines Inline-Alpine in der bestehenden View, keine Änderung an `resources/js`.
- Nachtrag 24.09.2026: Die Verwaltungsvariante der Route `.../grades/import/validate` war gebaut, blieb aber unversioniert im Arbeitsbaum liegen und von keinem Test berührt. Jetzt eingecheckt und belegt (`NotenImportVerwaltungTest`: Admin lässt korrigierte Zeilen erneut prüfen; ein Berufsbildner wird schon in `kontext()` mit 403 abgewiesen, denn die Berechtigung hängt am Anlegen von Noten – sonst verriete die Antwort Fach- und Modulzuordnungen einer fremden Person). Dabei kam eine Lücke der Sicherheitsregel zum Vorschein: `pruefeZeilen()` wertete nur das *Ändern* eines geratenen Bezugs als bewusste Wahl (`$vorherBezug !== null && $vorherBezug !== $bezug`), nicht das erstmalige Zuordnen einer Zeile, die gar keinen Bezug hatte. Ausgerechnet der häufigste Korrekturfall blieb damit auf «prüfen» stehen und unausgewählt. Bedingung jetzt `$bezug !== null && $vorherBezug !== $bezug`; «Erneut prüfen» ohne Korrektur stuft weiterhin nichts auf.

- Nachtrag 30.09.2026 zum offen gelassenen N+1: Der Batch-Cache in `NoteService` war nach Datum geschlüsselt und traf deshalb kaum, denn in einer Notenliste hat fast jede Zeile ein anderes Datum. Gemessen: Vorschau 24 Abfragen bei 4 Zeilen, 163 bei 60; «Erneut prüfen» 15 bzw. 155. Im Batch lädt der Service Semester und Tracks jetzt einmal und filtert in PHP nach Datum (dieselbe Regel wie `activeTrackTypesForLernender`), die Fachkategorien gelten je Kombination aktiver Tracks, die Skala je Fach. Danach 18/17 und 9/9. Einzelaufrufe ausserhalb des Batch fragen unverändert die DB. Festgehalten in `NotenImportTest::vorschau_und_erneutes_pruefen_fragen_die_datenbank_nicht_pro_zeile` (rot mit dem alten Stand).
- Nachtrag 24.09.2026 zur Frage, was Rückmeldung #16 sonst noch an Importen verlangt: Der Notenimport erfüllt Zuordnung, Vorschau und Validierung. Für Personen gibt es bewusst keinen Dateiimport, sondern den geführten Massenweg der Einrichtung (`admin.setup.people` bis 50 Berufsbildner/Admins, `admin.setup.learners` bis 100 Lernende mit Lehrberuf, Lehrbeginn, Lehrende, Berufsbildner und Track, erzeugte Startpasswörter zum Drucken). Dieser Weg ist nicht auf die Ersteinrichtung beschränkt: `EinrichtungController::show()` kennt keine Sperre nach `Einrichtung::abschliessen()`, und der Betriebsbereich verlinkt ihn dauerhaft. Ein zusätzlicher CSV-Import für Personen wäre damit Doppelarbeit und wurde nicht gebaut; festgehalten ist das Verhalten neu in `EinrichtungTest::mehrere_lernende_lassen_sich_auch_nach_abgeschlossener_einrichtung_anlegen`, damit ein späterer Block die Einrichtung nicht versehentlich hinter einem «nur solange offen»-Gate verschliesst. Ohne Weboberfläche bleiben weiterhin: der Modulkatalog-Import (nur CLI) und einzelne Fächer (nur über den Lehrberufe-Schritt der Einrichtung).

## Einstellungen & schlankes Benutzermenü (11.09.2026)

Block C (PO-Rückmeldungen #8 «eine zentrale Einstellungsseite» und #4 «Benutzermenü aufräumen»): neue Seite «Einstellungen» unter `/settings/…`, je Tab eine eigene Route (`settings.profile`, `settings.calendar`, `settings.data`, dazu `notifications.settings` für Benachrichtigungen), serverseitige Tab-Navigation über `settings/_tabs.blade.php` (kein Alpine-Tab-Zustand, `request()->routeIs()` für den aktiven Tab). Profil-Tab zeigt weiterhin Darstellung/Sprache sowie neu einen «Tastenkürzel anzeigen»-Knopf (`$dispatch('open-tastenkuerzel')`, öffnet dieselbe `<x-tastenkuerzel>`-Komponente wie `Ctrl+/`). Kalender-Tab (`settings.calendar`, alle drei Rollen) bündelt den Abo-Link (`<x-kalender-abo>`, generische `App\Services\Calendar\CalendarExport::token()/resetToken()` statt dreier rollenspezifischer Routen) und – nur bei Lernenden – den Schulnetz-Kalender-Import; ersetzt die alten Abo-Drawer in Agenda (`kalender`) und Prüfungstermine (`abo`), `?kalender=1` auf der Agenda-Seite leitet jetzt serverseitig auf `settings.calendar` um. Daten-Tab (`settings.data`) verlinkt die bestehende Datenauskunft. Benutzermenü dadurch auf genau 3 Einträge gekürzt (Desktop-Dropdown und Mobilmenü, alle Rollen): «Einstellungen», «Feedback», «Abmelden» – der separate Hell/Dunkel-Umschalter und der Feedback-Knopf in der Werkzeugleiste bleiben unverändert eigene Werkzeuge, zählen nicht zum Benutzermenü.

- Bewusst nicht umgesetzt (laut Auftrag, Schritt 3 des Konzepts explizit ausgenommen): keine eigene «Darstellung»-Unterseite/-Route (`settings.appearance` existiert nicht) – Darstellung bleibt, wie bisher, zusammen mit Sprache auf dem Profil-Tab. Sollte eine spätere Session Darstellung/Sprache/Tastenkürzel doch stärker auftrennen wollen, ist das hier als offener Punkt vermerkt. Nachtrag 02.10.2026: bleibt bewusst so – Darstellung und Sprache zusammen auf dem Profil-Tab sind laut Auftrag gewollt; eine eigene Unterseite erst, wenn der Tab zu lang wird.
- Drei bisher getrennte `calendar.token.reset`-Routen (Lernende, Berufsbildner, Admin – zuvor über `routes/verwaltung.php` doppelt gemountet) zu einer einzigen `settings.calendar.token.reset` zusammengeführt, da `CalendarExport::resetToken()` bereits rollenunabhängig war; keine funktionale Änderung für bestehende Abos, nur ein Routenname weniger zu pflegen.
- Neue/umbenannte Routen in `tests/Feature/Auth/ZugriffsschutzTest::OHNE_ROLLE` (Berechtigung prüft der `SettingsController` selbst, analog zu `profile.*`) und in `tests/Feature/I18n/EnglischeSeitenTest` aufgenommen; neuer `tests/Feature/BenutzermenueTest.php` (3 Tests, datengetrieben über alle drei Rollen) prüft je Rolle exakt 3 Einträge in `#np-benutzermenue` (Desktop) und `#np-benutzermenue-mobil`, über eine selbstgebaute Tiefenzählung der `<div>`-Verschachtelung statt eines DOM-Parsers.
- `resources/views/lernender/agenda/_kalender.blade.php` und `resources/views/layouts/_sprache.blade.php` gelöscht (beide vollständig durch die neue Kalender-/Profil-Tab-Struktur ersetzt, vorher per `grep` auf verbliebene Referenzen geprüft).
- EN-Texte («Settings», «Data») in `lang/en.json` ergänzt, bestehende Schlüssel (u. a. «Datenauskunft», «Daten herunterladen», «Kalender-Abo») wiederverwendet statt doppelt angelegt.
- Während dieses Auftrags eine unabhängige Regression in `app/Http/Controllers/ProfileController.php` behoben (fehlende `Lernender`/`DB`-Imports und die private Methode `hatAktivenBmsTrack()`, durch eine frühere fehlerhafte Entfernung verursacht, nicht Teil dieses Blocks selbst) – ohne die Korrektur schlug `ProfileController::update()` für Lernende mit aktivem BMS-Track fehl.
- Kein `npm run build`: alle in den neuen Views (`settings/calendar.blade.php`, `settings/data.blade.php`, `settings/partials/externe-kalender.blade.php`, `layouts/navigation.blade.php`) verwendeten Tailwind-Klassen sind Kopien bereits an anderer Stelle im Portal verwendeter Muster und bereits im aktuellen Build enthalten; keine JS-Änderungen ausserhalb von bereits vorhandenem Inline-Alpine.
- Serieller Gesamttestlauf am Ende: 834 grün, 2 rot – beide verifiziert unabhängig von diesem Block: `I18n\SchluesselTest` (fehlende EN-Übersetzung in `app/Services/Feedback/Anhang.php`, Block G; verwaiste Schlüssel «Lob»/«Einstellungen» in `lang/en.json`, das während dieses Auftrags parallel von einem anderen Agenten bearbeitet wurde) – keine der beiden Dateien wurde in diesem Block angefasst.

## Feedback III: Kategorie «Sonstiges», technische Angaben, schwebender Knopf, eigene Anhänge (12.09.2026)

Block G (Rückmeldungen #7 und #12). Kategorie «Lob» → «Sonstiges»: Enum-Umbenennung dreistufig in einer Migration (`2026_09_12_000012_feedback_technik_anhaenge.php`, weiten → Daten umschreiben → verengen, jeder Teilschritt einzeln idempotent über `information_schema`-Prüfung, `down()` symmetrisch), `Feedback::kategorieLabel()` bündelt die Anzeige (bildet auch den alten Wert `lob` weiterhin auf «Sonstiges» ab) und ersetzt alle Direktzugriffe auf `KATEGORIEN[...]` in Controllern/Admin-View/Mail; `Feedback::hatSonstigesWert()` (statisch gecacht) lässt den Server bis zur Prod-Migration weiterhin `lob` schreiben/filtern. Technische Angaben: Checkbox «Technische Angaben mitsenden» (Default an) mit aufklappbarer Vorschau im schwebenden Panel; `technik_details`-JSON-Spalte (Migration, `Schema::hasColumn`-Guard `hatTechnikSpalte()`) mit serverseitiger Whitelist (`FeedbackController::technikDetails()`: Bildschirm, Pixelverhältnis, Sprache, Zeitzone, Darstellung, Online-Status, höchstens 3 fehlgeschlagene Anfragen (Pfad+Status, kein Body, via `window.fetch`-Wrapper in `feedback.js`), App-Version über `App\Support\AppVersion` (liest `.git/HEAD`/`packed-refs` direkt, kein Shell-Aufruf, `null` falls kein Git-Verzeichnis)). Schwebender Knopf unten rechts (`resources/views/components/feedback-widget.blade.php`, ersetzt den bisherigen Vollbild-Dialog vollständig – eine Implementierung statt zwei), rund, mit Icon, expandiert per Hover/Fokus zu «Feedback / Fehler melden», öffnet ein kleines Panel statt eines Vollbild-Modals; ein-/ausschaltbar über `Einstellungen::FEEDBACK_KNOPF` (Default an) unter Betrieb → neue `_feedback.blade.php`-Sektion, `BetriebController::feedbackKnopfSpeichern()`. Eigene Anhänge: bis zu 3 Dateien, je max. 5 MB, Inhalt geprüft (nicht nur Endung) über `App\Services\Feedback\Anhang` (Bild via `getimagesize()` + GD-Neukodierung wie `Betriebslogo`, PDF über Signatur `%PDF-`, Text über UTF-8/Nullbyte-Prüfung), Tabelle `feedback_anhaenge` (Migration, Guard `hatAnhaengeTabelle()`), Auslieferung nur an Admin/meldende Person über `GET feedback.attachment` (`Content-Disposition: attachment`, `nosniff`, sandboxende CSP), Löschung mit der Meldung (`Feedback::booted()`-Hook), in `Sicherung.php` (`feedback`-Ordner ergänzt) und `Datenauskunft::feedback()` (nur Dateinamen) berücksichtigt.

- Bestehende Alpine-Mechanik bewusst erhalten: `open-modal`/`close-modal`-Fensterereignisse mit `detail: 'feedback'` funktionieren unverändert (Navigation-Dropdown, Mobilmenü, `suche.js`/Befehlspalette lösen weiterhin dasselbe Panel aus) – der schwebende Knopf selbst dispatcht beim Öffnen ebenfalls `open-modal`, damit alle Auslöser über denselben Weg laufen und die «ähnliche Meldungen»-Unterkomponente (unverändertes `@open-modal.window`) weiterhin lädt. Screenshot-Fix (`entferneAlpineAttribute`, Animations-Stopp vor der Aufnahme) unverändert aus `feedback.js` übernommen.
- Bewusst weggelassen (nicht Teil des Auftrags, hier dokumentiert): keine eigene Anhänge-Spalte/-Vorschau in der «Meine Meldungen»-Liste der meldenden Person (`resources/views/feedback/index.blade.php`) – der Auftrag verlangte die Anzeige nur in der Admin-Ansicht, der Download-Link selbst funktioniert für die meldende Person aber bereits (gleiche Route, gleiche Berechtigungsprüfung).
- Bewusst weggelassen: kein Client-seitiger Fehlertext für «zu viele Anhänge»/«Datei zu gross» im Panel selbst – überzählige Dateiauswahl wird still auf 3 gekappt, echte Validierungsfehler kommen als Server-422 über den bereits vorhandenen generischen Fehlertext zurück (reduziert die Anzahl neuer Übersetzungsschlüssel, ohne Funktionalität zu verlieren).
- Der Hinweis-Banner («Ctrl/Cmd K») und seine Position wurden minimal angepasst (rückt bei aktivem Feedback-Knopf von `bottom-4` auf `bottom-20`, damit er den neuen Knopf nicht überlappt); sein Text erwähnt «Lob» nicht mehr, dafür allgemein «oder sonst etwas».
- Neue Tests: `tests/Feature/FeedbackTechnikAnhangTest.php` (9 Tests: Kategorie-Anzeige alt/neu, technische Angaben an/aus, erlaubte Anhänge inkl. Neukodierung, getarnte Datei mit falschem Inhalt trotz erlaubter Endung, zu grosser Anhang, zu viele Anhänge, Fremdzugriff auf Anhang 403, Feedback-Knopf an/aus in der Seite, Admin-Umschalter). `ZugriffsschutzTest::OHNE_ROLLE` um `feedback.attachment` ergänzt. Bestehende `FeedbackTest`/`FeedbackDuplikatTest`/`FeedbackStimmenTest`/`FeedbackViewportTest`/`AbfragenAnzahlTest` unverändert grün (Admin-Liste bleibt dank `->with('anhaenge')` bei einer zusätzlichen Abfrage unabhängig von der Datenmenge).
- `npm run build` ausgeführt (nur eigene, unveränderte `resources/js/feedback.js` betroffen, vorher `pgrep`/`git status` geprüft: kein anderer Agent aktiv in `resources/js`) – neue Tailwind-Klassen (`max-w-12`, `h-12`, u. a.) danach im Build vorhanden (`app-D3C7ADbc.css`).
- Serieller Gesamttestlauf am Ende: 852 grün, 2 rot – beide verifiziert unabhängig von diesem Block: `I18n\SchluesselTest` (fehlende EN-Übersetzungen in `app/Services/Auswertung/Modulstatus.php`, Block B; verwaiste Schlüssel «Einstellen»/«Wähle, welche Mails du erhältst und wie oft.» in `lang/en.json`, während dieses Auftrags parallel von einem anderen Agenten – vermutlich Block C – bearbeitet) – keine der beiden Dateien wurde in diesem Block angefasst.

## Modulstatus mit Terminen (12.09.2026)

Block F (Rückmeldung #14): Lernende sehen je Modul/Fach, wo sie chronologisch und notenmässig stehen. Datenmodell: bewusst **keine neue Tabelle** für Abgabetermine/Meilensteine – stattdessen `pruefungen` um eine `art`-Spalte (`pruefung`|`abgabe`, Default `pruefung`) erweitert (Migration `2026_09_12_000013_modulstatus.php`, `Schema::hasColumn`-Guard, vollständiges `down()`). Begründung: Ein Abgabetermin ist strukturell identisch zu einer Prüfung (Lernender, Datum, Titel, Fach/Modul-Bezug, optionale Gewichtung, Kalender-/Agenda-Anbindung, `abgesagt_am`) – eine eigene Tabelle hätte dieselben Spalten dupliziert und zusätzlich `NotenQuelle::geplante()` (ungewichtete/geplante Leistungen für den Rechenkern), den iCal-Export und die Prüfungstermine-Seite je zweigleisig gemacht. Die einzige echte Abweichung ist die Bezeichnung/Semantik («Abgabetermin» statt «Prüfung»), die eine einzelne Spalte trägt. Als bewusster Modell-Kompromiss: `art` ist rein deskriptiv, ändert an der Notenberechnung nichts (ein Abgabetermin ohne Note fliesst wie jede andere geplante Prüfung als `Leistung::GEPLANT` in `NotenQuelle::geplante()` ein).

- Berechnung: `App\Services\Auswertung\Modulstatus` (neu) – reine `status()`-Methode (unit-testbar ohne DB, 6 Tests) plus DB-ladendes `fuerLernenden()` (max. 3 Queries pro Aufruf: `NotenQuelle::auswertung(mitGeplanten: true)`, `pruefungen`, `modul_belegungen`, unabhängig von der Anzahl Module/Fächer). Nutzt ausschliesslich bestehende Bausteine (`Rechenkern`, `Element`, `Leistung`, `Konfiguration::semesterName()`, `App\Support\Format::restdauer()` read-only) – keine neue Notenlogik. Eigene, von `Format::restdauer()` unabhängige `seit()`-Hilfsmethode für *vergangene* Dauer (Format.php deckt nur zukünftige Restdauer ab und ist read-only, durfte nicht erweitert werden).
- Zwei Fehler in bestehendem, fremdem Code entdeckt und minimal behoben, weil sie den neuen Rechenpfad blockierten (nicht Teil des eigentlichen Auftrags, aber notwendig für grüne Tests): `App\Services\Auswertung\NotenQuelle::fuerLernende()` übergab `$out[$lernenderId] ??= []` direkt als Referenz-Argument an `array_push()` – seit PHP 8 kein gültiger Referenzausdruck, führte bei jedem `mitGeplanten: true`-Aufruf mit tatsächlich vorhandenen geplanten Prüfungen zu einem Fatal Error; in zwei Anweisungen aufgeteilt. Eigener Fehler in `Modulstatus::fuerLernenden()` (Zeile mit `$juengsteBelegungJeModul[$element->modulId]?->start_datum`): direkter Array-Zugriff auf eine `Collection` ohne `isset`-Schutz wirft bei fehlendem Schlüssel (Modul ohne `ModulBelegung`) eine `ErrorException` – auf `->get($element->modulId)` umgestellt.
- Anzeige: kompakte Zeile in der Notenübersicht des Lernenden (`lernender/noten/index.blade.php`, in der bereits vorhandenen Detailklappe je Element) und eine neue Karte «Modulstatus» im Verwaltungs-Cockpit (`verwaltung/lernende/_cockpit/uebersicht.blade.php`), beide gespeist über je einen `Modulstatus::fuerLernenden()`-Aufruf pro Seite (kein N+1).
- Pflege der Termine: bewusst nur **Abgabetermine** (`art=abgabe`) admin-/BB-seitig erfass-/bearbeit-/löschbar (`Verwaltung\PruefungenController::store/update/destroy`, neue Routen `exams.store/update/destroy`), reguläre Prüfungen bleiben wie bisher ausschliesslich Sache der/des Lernenden – der Auftrag verlangte wörtlich «Abgabetermine … erfassen/bearbeiten/löschen», eine Ausweitung auf reguläre Prüfungen hätte die bestehende Trennung (Lernende pflegen ihre Prüfungstermine selbst) ohne Auftrag verändert. Erfassen ist nur möglich, wenn die bestehende Lernenden-Filterung auf der Prüfungstermine-Seite (`/exams?lernender_id=…`) einen einzelnen Lernenden auswählt (Fach/Modul-Optionen sind lernendenspezifisch über `NoteService::bezugOptionen()`); ohne Filter erscheint nur ein Hinweistext statt einer neuen Navigationsebene.
- Tests: `tests/Unit/Auswertung/ModulstatusTest.php` (6: kein Termin, nur vergangene Termine, Termin heute, 0 %/100 % bewertet, fehlendes Beginn-Datum), `tests/Feature/Auswertung/ModulstatusAnzeigeTest.php` (2: Anzeige auf Notenübersicht und im Cockpit inkl. berechnetem Prozentsatz), `tests/Feature/Verwaltung/AbgabeterminTest.php` (7: Erfassen/Bearbeiten/Löschen je Rolle, Rollen-/Betreuungs-Schutz, reguläre Prüfung nicht über die Abgabe-Route veränderbar, Formular erst nach Lernenden-Filter).
- Bewusst nicht umgesetzt: kein eigener Fortschrittsbalken für «Fortschritt zwischen Beginn und letztem Termin» in der UI (Wert `fortschritt_prozent` wird vom Service geliefert, aber in beiden Views nicht separat visualisiert) – die beiden vorhandenen UI-Slots (Notenübersicht-Detailzeile, Cockpit-Karte) zeigen bewusst nur den bewerteten Anteil als Balken plus Text für Dauer/nächsten Termin, um die ohnehin dichten Karten (390 px-Vorgabe) nicht zu überladen; der Rohwert steht im Array bereit, falls ein späterer Block eine eigene Darstellung dafür will.
- Bewusst nicht umgesetzt: kein Modulstatus für Elemente ohne jede Beginn-Angabe und ohne jeden Termin (weder `ModulBelegung` noch `pruefungen`) – solche Elemente liefern `dauer_seit_beginn: null`, `naechster_termin: null`; die Anzeige blendet die gesamte Statuszeile dafür aus (kein leerer/irreführender Platzhalter).
- `zielGewicht` (Standard 100 % je Modul, `module.ziel_gewicht_summe_default`) bestimmt den Nenner des bewerteten Anteils, sofern gesetzt – ein Modul mit einer einzelnen 40-%-Note zeigt also 40 % bewertet, nicht 66.7 % (Anteil nur unter den bisher erfassten/geplanten Gewichten); das entspricht der bestehenden Bedeutung von `zielGewicht` im Rechenkern (Zielrechner/Fortschrittsanzeigen andernorts) und wurde nicht für Modulstatus neu interpretiert.

## Feedback III – Sicherheits-Nachbesserungen (12.09.2026, Block G)

Aus der Sicherheitsprüfung (Opus) von Block G: HOCH/MITTEL umgesetzt (GD-Dekompressionsbomben-Schutz `Anhang::MAX_PX`, Dialog/Auslöser unabhängig vom Schalter `Einstellungen::FEEDBACK_KNOPF`, Toast-Überlappung, UI blendet Technik-/Anhang-Optionen vor der Prod-Migration aus). Zwei TIEF-Befunde bewusst zurückgestellt:

- ~~**GD-Fallback behält Originalbytes**~~ erledigt (842e284, 28.09.): `Anhang::speichern()` weist ein Bild ab, das sich nicht neu kodieren lässt (Test `FeedbackTechnikAnhangTest::bild_ohne_erfolgreiche_neukodierung_wird_abgelehnt_statt_ungeprueft_uebernommen`). Nachgeprüft 30.09.: Der Feedback-Screenshot (`Screenshot::speichern()`) und das Betriebslogo behalten ihre Bytes weiterhin – der Screenshot nimmt nur JPG/PNG/WebP an und geht mit Sandbox-CSP und `nosniff` nur an Admins, das Logo lädt nur ein Admin hoch und wird mit GD neu kodiert, das `install.sh` mitinstalliert. Beides bewusst so gelassen.
- **`Feedback::booted()`-`deleting`-Hook räumt nur bei Eloquent-Löschung auf**: Screenshot/Anhänge werden nur entfernt, wenn eine `Feedback`-Zeile über das Model gelöscht wird (`$feedback->delete()`). Ein direkter `DB::table('feedback')->delete()` (kommt in der Anwendung aktuell nirgends vor) würde die Datenbankzeile per Fremdschlüssel-Kaskade entfernen, die zugehörigen Dateien auf der Disk aber verwaisen lassen. Kein akutes Risiko (kein Code-Pfad nutzt Raw-Deletes auf `feedback`), aber ohne Model-Event nicht automatisch behebbar; bei Bedarf ein periodisches Aufräum-Kommando (verwaiste Pfade unter `feedback/anhaenge`/`feedback/screenshots` ohne passende DB-Zeile) ergänzen.
  Nachtrag 02.10.2026: erledigt – `notenportal:feedback-aufraeumen` (Schedule wöchentlich Montag 03:45, `--vorschau` listet nur) löscht Dateien unter `feedback/` ohne Datenbankzeile, sofern sie älter als ein Tag sind (laufender Upload bleibt); `tests/Feature/Console/FeedbackAufraeumenTest`.

## Verwaiste Schlüssel in `lang/areas/*/en.json` (12.09.2026)

**Erledigt (nachgeprüft 30.09.2026):** keiner der sechs Schlüssel steht mehr in `lang/`, und
`SchluesselTest::geschlossene_uebersetzungsdateien_haben_keine_verwaisten_schluessel` prüft Waisen je Bereichsdatei.

Beim Abschluss von Block G aufgefallen: Die Umbenennung «Lob» → «Sonstiges» hatte drei tote Schlüssel in `lang/areas/trainer/en.json` hinterlassen (`Lob`, der alte Bannersatz «… oder Lob – über das Benutzermenü oder mit», `unter «Feedback melden» erreichst du uns jederzeit.`). Kein Test hat angeschlagen: `tests/Feature/I18n/Schluessel.php` liest die Bereichsdateien zwar ein, prüft sie aber nur auf *fehlende* Übersetzungen – auf verwaiste Schlüssel wird ausschliesslich `lang/en.json` geprüft. `tests/Feature/I18n/offen.php` hält diese Lücke bereits fest. Die drei Trainer-Waisen sind mit Block G entfernt.

Sechs weitere Waisen bleiben bewusst liegen, weil sie aus fremden Blöcken stammen und ein Aufräumen den G-Commit über seinen Titel hinaus erweitert hätte (dieselbe Unschärfe wie bei `3d56070`). Gesucht wurde jeder Schlüssel literal in `app/`, `resources/`, `routes/`, `config/`, `database/`:

- `lang/areas/admin/en.json`: `Keine Meldungen gefunden.` – bereits durch `3d407e0`/`25f1f39` verwaist, der heutige Leertext lautet `Keine Meldungen für diese Filter.` bzw. `Noch keine Meldungen`.
- `lang/areas/learner/en.json`: `:anzahl nicht übernommen: :fehler` (Notenimport), `auch mit 1.0 bleibt es bei `, `die offenen Prüfungen zählen hier nicht`, `höchstens, mit lauter 6.0` (Zielrechner-Texte), `Agenda abonnieren` (Kalender).

Vorschlag: `Schluessel.php` um eine Waisen-Prüfung je Bereichsdatei erweitern und die sechs Einträge im selben Zug entfernen – zusammen ein kleiner, eigener Commit. Die Prüfung per literaler Suche ist eine Heuristik; dynamisch zusammengesetzte Schlüssel würden fälschlich als verwaist gelten, deshalb muss jeder Treffer wie hier einzeln gegengeprüft werden.
Nachtrag 02.10.2026: erledigt – `SchluesselTest::geschlossene_uebersetzungsdateien_haben_keine_verwaisten_schluessel` prüft jede Bereichsdatei, seit `tests/Feature/I18n/offen.php` leer ist (learner/trainer/admin fertig); die sechs Einträge sind weg.

## Ungenutzte öffentliche API in `Note` und `Leistung` (12.09.2026)

**Erledigt (30.09.2026):** entfernt samt ihren Tests. Nachgeprüft: kein Aufruf in `app/`, `resources/`
(Views) oder `routes/`; `Leistung` implementiert weder `Arrayable` noch `JsonSerializable`, `toArray()`
wurde also auch nicht implizit gerufen. `Note::OVERVIEW_RELATIONS` bleibt (Lernenden-Noten der Verwaltung).

Beim Schliessen der Testlücken (Block AI) fiel auf, dass die `Note`-Scopes
`forLernender`, `filterKategorie`, `filterSemester`, `ordered`, `withOverview`
sowie `Leistung::toArray()` im App-Code nirgends aufgerufen werden. Sie sind
inzwischen getestet – als öffentliche API ist das richtig –, aber es ist zu
klären, ob sie noch gebraucht werden oder als toter Code entfallen können.
Kein Fehler, nur Aufräumbedarf; vor dem Entfernen prüfen, ob Blade-Views oder
kommende Blöcke (Import H4–H8) sie einplanen.

## Seitenbreite als persoenliche Einstellung (12.09.2026)

Der Seitencontainer `np-seite` (`resources/css/app.css`) fuellt seit #13 das Fenster und ist bei
2048 px gedeckelt – eine feste Entscheidung fuer alle. Wer sehr breite Schirme hat und trotzdem
kuerzere Zeilen will, kann das heute nicht einstellen.

Bewusst weggelassen, weil es ohne Rueckmeldung aus dem Pilotbetrieb geraten waere, welche Stufen
sinnvoll sind. Falls es kommt, ist der Platz vorbereitet: `App\Support\Darstellung` haelt die
persoenlichen Einstellungen als JSON in `benutzer.praeferenzen` (Schriftgroesse, Dichte, Ecken,
Bewegung). Eine Konstante `BREITEN` dort, ein `data-breite` am `<html>` und ein
`[data-breite='schmal'] .np-seite { max-width: 80rem }` in `app.css` genuegen – **keine Migration**.

## Einzelner, nicht reproduzierbarer Testfehlschlag (12.09.2026)

Beim Abschluss von #13 meldete ein Suite-Lauf `1 failed, 871 passed`. Die beiden unmittelbar
folgenden Laeufe waren `872 passed` (16752 Assertions, keine Warnungen, keine uebersprungenen
Tests). Welcher Test es war, ist **nicht mehr feststellbar**: die Ausgabe lief durch `tail -4`,
der Name stand weiter oben und ist verloren.

Fehler meinerseits: bei einem Lauf, der fehlschlagen kann, gehoert die Ausgabe in eine Datei und
erst die Datei durch `tail`. So wird es hier ab jetzt gemacht.

Wahrscheinlichste Ursache: die 159 kompilierten Blade-Dateien in `storage/framework/views` waren
nach 54 geaenderten Views veraltet, waehrend parallel neu kompiliert wurde. `view:clear` und
`optimize:clear` sind als `www-data` gelaufen. Falls die Suite erneut sporadisch kippt: zuerst
`sudo -u www-data php artisan view:clear`, dann den Lauf mit voller Ausgabe in eine Datei
wiederholen und den Testnamen hier eintragen.

## Zwei Feeds mit derselben `extern_uid` (Rückmeldung #15 Phase 1, 12.09.2026)

Seit `pruefungen.calendar_feed_id` (Migration `2026_09_12_000014`) ist jede importierte Prüfung
einem Feed zugeordnet, damit der Abgleich eines Kalenders nicht die Prüfungen eines anderen
Feeds desselben Lernenden aufräumt (`CalendarSync::apply()` schränkt das Löschen/Absagen jetzt
zusätzlich mit `where('calendar_feed_id', $feed->id)` ein).

Liefern zwei verschiedene Feeds desselben Lernenden zufällig dieselbe `extern_uid` (z. B. weil
beide vom selben Schulserver stammen), gilt weiterhin die eindeutige Zeile
`uq_pruef_extern (lernender_id, extern_uid)` – die Prüfung gehört dann dem Feed, der zuletzt
abgeglichen hat, `calendar_feed_id` wandert entsprechend mit. Bewusst hingenommen (Entscheidung F
der Spezifikation): eine zusammengesetzte Eindeutigkeit `(lernender_id, calendar_feed_id,
extern_uid)` würde das Problem nur verschieben (dieselbe Prüfung erschiene doppelt) und eine
UID-Kollision zwischen zwei unabhängigen Kalendern ist im Pilotbetrieb nicht beobachtet. Falls es
doch auftritt: `extern_uid` müsste dann feed-lokal statt lernender-lokal eindeutig sein.

## `raum` steht in `SPERRBARE_FELDER`, lässt sich aber nie sperren

`Pruefung::SPERRBARE_FELDER` führt `raum` auf, und die Beschriftungstabelle in
`lernender/agenda/_form.blade.php` hält bereits einen Eintrag dafür bereit. Entstehen kann die
Sperre trotzdem nie: Das Bearbeitungsformular hat kein Raum-Feld, und
`PruefungenController::validiere()` liefert nie einen `raum`-Schlüssel – `array_key_exists('raum',
$neu)` ist damit immer `false`. Kein Fehler und kein Datenverlust, aber irreführend.

Bewusst so belassen, weil ein zusätzliches Eingabefeld kurz vor dem Go-Live eine Funktions- und
keine Abschlussänderung wäre. Aufzulösen später auf einem von zwei Wegen: entweder ein Raum-Feld
im Formular ergänzen – dann greift die Sperre ohne weiteren Eingriff –, oder `raum` aus der
Konstante streichen. Der erste Weg ist der wahrscheinlichere, weil der Raum aus dem Kalender kommt
und genau die Art Wert ist, die eine Schule kurzfristig ändert.
Nachtrag 02.10.2026: erledigt auf dem zweiten Weg – `raum` steht nicht mehr in `Pruefung::SPERRBARE_FELDER` (Kommentar dort: kommt nur aus dem Kalenderabgleich, eine Sperre griffe nie).

## Die Modulkatalog-Seiten sind nur statisch geprüft

**Erledigt (30.09.2026):** Die Überlaufmatrix des GUI-Blocks misst `/admin/master-data/modules`,
`…/modules/create`, `…/modules/catalog` und `/grades` bei 320, 390, 640, 768, 1024 und 1280 px, dazu mit
Seitenleiste bei 1024 und 1280 px: kein seitliches Überlaufen. Historischer Stand:

Die vier im Block «Modulkatalog» geänderten Seiten (Stammdaten → Module als Liste, Neu und
Bearbeiten, Lernender → Noten) wurden auf Quellcode-Ebene geprüft: Schweizer Hochdeutsch, kein
Entwicklertext, `rel="noopener noreferrer"` an jedem `target="_blank"`, Escaping jedes Werts. Die
visuelle Prüfung bei 390 Pixeln Breite mit den Werkzeugen in `~/tools/visual` fand nicht statt,
weil sich ohne Passwort in der Umgebungsvariablen `NP_TEST_PW` keine angemeldete Seite öffnen
lässt – und das Passwort gehört nach Vorgabe ausschliesslich in die `.env`, nie in Code, Doku oder
einen Befehl.

Nachzuholen, sobald `NP_TEST_PW` für einen Prüflauf gesetzt ist:
`node ~/tools/visual/breite.mjs --breite=390 --rolle=admin admin/master-data/modules
admin/master-data/modules/create` und `… --rolle=learner grades`. Erwartet wird kein seitliches
Überlaufen. Das Risiko ist klein, weil die neuen Elemente nur ein zusätzliches Eingabefeld im
bestehenden Formularraster und je einen Textlink in einer bereits umbruchfähigen Zeile sind.

## Die Installationsanleitung ist geprüft, aber nie auf einer leeren Maschine gelaufen

**Erledigt (30.09.2026):** die README-Befehle wörtlich in frischen Ubuntu-24.04-Containern mit systemd
(Klon aus GitHub, Home-Verzeichnis, `sudo ./install.sh`): Selbstprüfung HTTPS 200, Einrichtung 1–8 per
Klick, Lernende erfasst Noten. Dazu gemessen: zweiter Lauf, zweiter Klon, belegte Ports 80/443/3306,
maskierter Dienst, fehlendes Ausführungsbit, Abbruch nach dem Admin-Konto (`docs/betrieb.md`,
«Neuinstallation mit install.sh»). Offen bleibt nur 26.04 auf echter VM im Container-Prüfstand
(bootet dort kein systemd); srv-lab-dva-003 lief am 30.09. mit 26.04 durch. Historischer Stand:

`README.md`, `.env.example` und `CONTRIBUTING.md` wurden Aussage für Aussage gegen `install.sh`,
`config/notenportal.php`, `app/Support/Einrichtung.php`, `database/seeders/BasisSeeder.php`,
`phpunit.xml` und `tests/TestCase.php` geprüft: Befehlsfolge, alle fünf Optionen, das Verhalten
beim zweiten Lauf, die acht Einrichtungsschritte und jeder Schlüssel der Vorlage stimmen mit dem
Code überein. Was fehlt, ist der Beweis am lebenden Objekt: niemand hat `git clone` und
`sudo ./install.sh` auf einer frischen, leeren Ubuntu-Maschine durchgespielt. Die zweite Instanz
`notenportal-i2` läuft auf demselben Server und hat Apache, MariaDB, PHP und Node bereits
vorgefunden – sie beweist den Update-Weg, nicht die Erstinstallation.

Nicht geprüft sind damit genau die Dinge, die nur ein nackter Server zeigt: ob `apt` alle Pakete
in der erwarteten Version liefert, ob die Anmeldung am MariaDB-Konto `root` über `unix_socket`
gelingt, ob Node 22 auch ohne vorhandenes Node installiert wird, und ob die Schlussmeldung mit
Adresse und Startpasswort wirklich als Letztes erscheint. Ein Fehler an einer dieser Stellen
träfe den ersten Eindruck eines fremden Benutzers ungebremst.

Nachzuholen, sobald eine leere virtuelle Maschine mit Ubuntu 24.04 bereitsteht: die vier Befehle
aus dem README wörtlich ausführen, ohne Vorbereitung und ohne Nacharbeit, und danach
`php artisan notenportal:bereitschaft` sowie einen Aufruf von `/login` prüfen. Erst dieser Lauf
schliesst «github installations ready» ab; bis dahin gilt der Weg als belegt, aber nicht bewiesen.

Nicht auf dieser Maschine: Der Produktionsserver trägt das laufende Portal, hat rund 4,5 GB freien
Arbeitsspeicher und steht kurz vor dem Go-Live. Eine Probeinstallation in einem Container hier
würde eine Netzbrücke und Firewallregeln neben den laufenden Apache setzen – dafür ist der
Nachweis zu billig und das Risiko zu teuer. Er gehört auf eine eigene, leere VM (24.04 oder 26.04),
wo ein misslungener Lauf nichts kostet.

## Modulseiten auf 390 Pixel (14.09.2026, geprüft 24.09.2026)

~~Die drei Seiten unter `/modules` waren im schmalen Fenster ungeprüft.~~ Erledigt (24.09.): ein
Playwright-Crawl über alle GET-Seiten je Rolle auf der Demo-Instanz (1280 und 390 px, Passwort
nur über `NP_TEST_PW`) meldet für `/modules`, `/modules/{id}`, `/modules/create` und
`/modules/{id}/edit` weder seitliches Überlaufen noch JS-Fehler oder verschachtelte Formulare.

Ebenfalls offen: die Leistungsbeurteilungs-Elemente (`modul_lbv_elemente`) zeigt die Detailseite
nur an, erfassen lassen sie sich über die Oberfläche noch nicht – sie kommen bisher allein aus
dem Katalogimport. Für den Fall «Modul fehlt ganz» genügen Nummer, Titel, Ziele und Unterlagen;
wer die Beurteilungsvorgabe selbst erfassen will, braucht ein eigenes Formular.
Nachtrag 02.10.2026: bleibt bewusst offen – ein Erfassungsformular ist eine Produktentscheidung; der Katalogimport füllt die Elemente vollständig, Handmodule kommen laut Entscheid ohne aus.

## Audit v3 vor dem Code-Freeze (24.09.2026)

Mehrdimensionales Audit (Korrektheit, Berechtigung, Datei-Fehlerpfade, Kalender, Views) plus
Crawl aller GET-Seiten je Rolle bei 1280/390 px. 15 bestätigte Befunde, alle behoben, je mit
Regressionstest (fällt ohne Fix):

- Prüfung bearbeiten: verschachtelte Formulare (Anhang entfernen) liessen Speichern ins Leere laufen.
- Ampel zählte benotete und abgesagte vergangene Prüfungen als fehlende Noten.
- «Tiefer Schnitt» in BB-Übersicht und Lernendenliste rechnete per SQL mit fixer Grenze statt über
  `Auswertung` mit der eingestellten Genügend-Grenze.
- Fehlgeschlagenes Speichern von Dateien (Ablage, Modulablage, Feedback, Logo, Katalog) legte
  Datensätze ohne Datei an; Datenauskunft zeigte bei ZIP-Fehler 500 → `DateiNichtGespeichert`, Toast.
- iCal-Abo deaktivierter Konten lieferte weiter Termine; Noten-CSV schützte Semester/Kategorie nicht
  gegen Formeln.
- Semesterende-Erinnerung fiel nach einem ausgefallenen Scheduler-Lauf aus (Fenster statt Stichtag).
- Mehrtägige Ganztagestermine im iCal-Abo endeten nach einem Tag.
- Pilot-Vorbereitung löschte Feedback-Dateien nicht von der Disk.
- Massenanlage nahm gelöschte Berufsbildner an.
- Übernahme erkannter Kalenderprüfungen kürzte Felder nicht wie der Abgleich (500 bei langen Werten,
  Gewichtung > 100 %); gesperrter Bezug meldete sich unsichtbar → Toast.
- Modulnummer wurde erst nach der Längenprüfung grossgeschrieben (ß → SS sprengte die Spalte).
- «Prüfung planen» und «Abgabetermin erfassen» zeigten Validierungsfehler nie (Drawer blieb zu);
  «Bearbeiten» eines Abgabetermins öffnete das leere Erfassen-Formular.
- Restliche Palettenfarben (`text-red-600`, `text-white` auf Accent, Gelb) in Auth-, Agenda-,
  Einrichtungs- und Kalender-Views durch Tokens ersetzt.
- Kalenderfehler mehrerer Lernender erschienen als ein einziger Handlungsbedarf ohne Link auf die Person.
- Pilot-Vorbereitung löschte Feedback innerhalb der Transaktion (Dateien weg, Rollback liesse Zeilen stehen).

Nachprüfung (read-only) ohne Befund: Mails/Benachrichtigungen (Empfänger, Idempotenz, signierte
Abmeldelinks, Fehler pro Empfänger), Import (Berechtigung, Encoding, Transaktion, Dubletten, SSRF)
und Berufsbildner-Abläufe (nur aktive Betreuung, verschachtelte Objekte über den Lernenden).
Seit 24.09. prüft CI (`.github/workflows/tests.yml`) jeden Pull Request mit Pint, Build, ss und Tests.

## Modulkatalog-Export (28.09.2026)

Behoben (Review): das von Hand gesetzte Lehrsemester ging beim Export verloren (nur das Lehrjahr
wurde geschrieben, 1–12 liess sich daraus nicht zurückholen) – die Datei führt jetzt `semester` mit,
der Import bevorzugt es; die Download-Route prüft die Katalogspalten und ist gedrosselt.

Bewusst gelassen: Der Export schreibt die Modulnummern in der internen Form (`M987`), nicht in der
rohen Form einer Ernte (`987`). Der Import normalisiert beides gleich
(`Modulbaukasten::nummerNormalisieren`), ein Umschreiben brächte nur Kosmetik und ein zweites Format.

## Notenbaum und Stammdaten: bewusst gelassen (30.09.2026)

- **LAGE Lücken 3–6** (Track als ENUM statt Bildungsgänge, Unterrichtsbereich, Solldauer, Lektionen):
  jede davon ist eine Schemaänderung quer durch Noten, Tracks und Einrichtung. Der Notenbaum löst das
  Rechenproblem ohne sie; umgebaut wird erst mit einem Abnahmetest, der ohne Bildungsgänge nicht geht.
- **Baumstruktur nur über Export → Datei → Import**: Gewichte, Rundung, Grenzen und «entfällt mit»
  sind in der Oberfläche editierbar, Knoten anlegen oder verschieben nicht. Ein Baumeditor ist viel
  Oberfläche für eine Handvoll Bäume pro Betrieb; `BaumVorlage::pruefen()` fängt Fehler in der Datei ab,
  Positionen wandern über den Code mit.
- **Rechner simuliert keine Positionen von Hand**: eine fehlende IPA bleibt im Szenario fehlend.
- **Mittel über Lernende mischen QV-Prognose und flache Gesamtnote** (Übersicht, Bericht nach Lehrjahr):
  die Gesamtnote einer Person ist mit Baum dessen Wurzel. Ein Mittel über Personen mit und ohne Baum
  ist gewollt «Gesamtnote», aber nicht dieselbe Rechenart; getrennt ausweisen erst, wenn beides im
  selben Betrieb vorkommt.
- **Rundung der Promotions-Schnitte auf Zehntel** (4,25 → 4,3): so seit der Notenlogik vom 10.09.;
  ob die BMV ungerundet vergleicht, ist nicht an der Primärquelle belegt.
- **Positionen aus dem Zwischenstand 0b655d1** (30.09., 17:15–18:01 auf `main`): Das damalige Umziehen
  setzte `aktualisiert_am` neu (und bei fehlender `DB_TIMEZONE` in DB-Zeit). Solche Positionen können
  beim nächsten Wechsel als «zuletzt erfasst» gewinnen, obwohl sie älter sind. Gemessen vom Prüfer mit
  nachgebautem Altcode; ob auf Prod oder dem Testserver solcher Bestand liegt, ist nicht geprüft (aus
  dieser Umgebung nicht erreichbar); die Demo-DB wird neu gesät. Kein Reparaturskript: es
  bräuchte eine Quelle für das echte Erfassungsdatum, und die gibt es nicht. Neue Installationen
  setzen `DB_TIMEZONE` (install.sh), neue Positionen stempelt nur noch Eloquent.
- **Löschen eines abgelösten Baums vergleicht keine Zeitstempel** (`verlorenePositionen`): zählt nur
  Codes, die im aktiven Baum fehlen. Eine neuere Note im abgelösten Baum entsteht seit dem Sperren beim
  Speichern (Abschluss) und beim Sammeln (BaumWechsel) nur noch aus dem Bestand oben.

## Oberfläche nach Apple-HIG: bewusst gelassen (30.09.2026)

Gemessen mit einer Überlaufmatrix über alle GET-Seiten je Rolle (320/390/640/768/1024/1280 px,
dazu 1024/1280 mit Seitenleiste). Tabellen schalten ihre Spalten nach der Kartenbreite
(Container-Queries), nicht nach dem Fenster; schmal zeigen sie Karten oder tragen die
ausgeblendeten Werte in der Namenszelle nach.

- **Zeugnisnoten-Heatmap scrollt auf dem Telefon seitlich** (Mindestbreite in der Demo 609 px,
  die Namensspalte bleibt stehen). Ab 768 px mit Leiste oben passt sie. Karten je Fach
  wären keine Heatmap mehr: der Vergleich über die Semester ist ihr Zweck. Mit Seitenleiste bei
  1024 px scrollt sie ebenfalls (Cockpit-Spalte 471 px).
- **Leiste heisst auch mit Seitenleiste «Hauptnavigation»**: ab lg enthält sie dann nur Suche,
  Profil und den Schalter. Die Beschriftung hinge an Präferenz und Fensterbreite zugleich und
  müsste beim Umschalten ohne Neuladen mitwechseln; der Gewinn für Screenreader ist klein, weil
  die Seitenleiste als eigene Landmarke «Seitenleiste» erreichbar ist.
- **Silbentrennung** (`hyphens-auto` in den Tabellenköpfen) wirkt nur, wo der Browser ein
  deutsches Wörterbuch hat; der Prüfbrowser (Headless Chromium) hat keines, gemessen wurde
  deshalb ohne Trennung.
- **Standard der Navigation** (oben oder Seitenleiste) entscheidet David, siehe Übergabebrett.
- **Verlaufs-Sparkline in «Meine Lernenden» unter 42rem Kartenbreite**: ohne Ersatzgrafik; die
  Veränderung zum Vorsemester steht als Zahl in der Semesterzelle, die Sortierung nach Verlauf in der
  Auswahl (Prüferbefund 30.09.).
- **Passen die Container-Stufen zueinander?** Geprüft per Test nur, wo es am meisten kostet: Auswahl
  gegen Spaltenköpfe in «Meine Lernenden» (`UebersichtKompaktTest`) und jede schmal ausgeblendete
  Spalte des Notenberichts gegen die Namenszelle (`BerichtTest`). Für die übrigen Tabellen belegt die
  Überlaufmatrix nur «kein Querscrollen», nicht «kein Wert fehlt»; das hat der Prüfer im Code nachgelesen.
- **Schwellenlabel in winzigen Diagrammen** (Zeichenfläche unter etwa 70 × 30 px) wird weggelassen statt
  über die Achsen gezeichnet; die Linie selbst bleibt.

## Aufräumen Block 5: bewusst gelassen (30.09.2026)

- **Abgabetermin ohne Gewichtung zählt 100 %** (ea7e431, vorher Abbruch mit 500): so will es
  `docs/notenlogik.md` («leer = 100»), die Spalte und die Übernahme erkannter Prüfungen. Fachlich offen
  (Prüferbefund): In einem teilweise benoteten Modul verdrängt das volle Gewicht das Restgewicht. Beispiel:
  5.0 und 4.0 zu je 35 %, offen 30 % – eine leere Abgabe geht mit 100 % ein (59 % statt 30 % Anteil), bei
  4.0 ergibt das 4.21 statt 4.35. Besser wäre, wenn das Formular das Restgewicht des Moduls vorschlägt;
  das ist eine Funktion, kein Fehler, und braucht den Entscheid, ob «leer» künftig «Rest» heissen soll.
- **Vollständigkeitsprüfungen der Rollentrennung** erfassen Routen mit Objekt-ID im Pfad (`trainer.*` mit
  Lernenden-/Prüfungsbezug, `comments.*`, `role:Lernender`). IDs im Formularinhalt (`trainer.exams.store`,
  `pruefung_id`/`ersetzt` bei Lernenden) prüfen eigene Tests, nicht der Sweep.
- **«Ctrl K» in der Leiste** steht fest statt über `__('Strg/Cmd K')` wie an anderer Stelle; auf Schweizer
  Tastaturen heisst die Taste Ctrl. Vereinheitlichen, wenn die Tastenbeschriftung je Plattform kommt.

## Dunkelmodus nach HIG: bewusst gelassen (01.10.2026)

- **Vierte Flächenstufe (systemGray3, 72 72 74) ist nicht möglich**, solange `--muted` 174 174 178
  bleibt: gemessen 4.24:1, unter 4.5:1. Overlays liegen deshalb auf `surface-2` (58 58 60, e52ab7a).
  Wer eine weitere Stufe braucht, hellt zuerst `--muted` auf (etwa 180 180 184 → 4.55:1 auf gray3)
  und prüft alle 24 Theme-Blöcke mit `~/tools/kontrast/alpha.mjs`.
- **`--note-ungenuegend` (253 115 109) auf getönter Fläche**: auf `surface-2` erreicht die Farbe
  ungetönt 4.24:1, mit `bg-note-ungenuegend/14` auf der Karte noch weniger; `hover:/22` kann 4.5:1 nie
  erreichen. Ein Wert wie 255 145 140 gäbe 4.94:1 auf `/14`, verschiebt aber die ganze Notenampel.
  Das ist ein Token-Entscheid (Skill `notenportal-dunkelmodus` §1 müsste Notentext aufnehmen), kein
  View-Fix. Bis dahin bleibt die Unterstreichung die zweite Kodierung (nie nur Farbe).

## R4 Lernende: bewusst gelassen (01.10.2026)

- **Ziel-Chip im Rechner trägt `bg-accent/12`** (rechner/index.blade.php, Kopfzeile «Ziele»): er ist ein
  Auswahlzustand (`aria-pressed`), kein Schmuck; Akzent ist dort nach HIG gerechtfertigt.
- **Promotion-Marke «erfüllt» in `note-gut`-Grün** (rechner/index.blade.php, Tabelle Promotion): ein
  Status, keine Notenstufe – gleiches Muster wie `x-status` «Im Plan». Bleibt, bis Status-Grün ein eigenes
  Token bekommt.
- **Offene Prüfungen im Rechner bleiben ein CSS-Grid**, keine `np-tabelle`: jede Zeile trägt Eingabefelder,
  ein Tabellenumbau wäre ein Umbau auf Verdacht.
- **Differenz-Glyphen ▲/▼ ohne Screenreader-Text** (Rechner «Auswirkung», Dashboard «Stand»): Zahl und
  Vorzeichen stehen daneben; ein `sr-only`-Wort kommt, wenn die Diagramme ihre Textalternativen bekommen.
- **Modulliste (`module/index.blade.php`) beim Scrollen 112 px über dem Fensterrand**: sticky mit fester
  Höhe `calc(100dvh − Symbolleiste − 8rem)`; exakt nur mit JS lösbar, nicht wert.
- **Zebra der `np-tabelle` im Dunkeln (3 %)** ist kaum sichtbar; stärker würde die Flächenstufen brechen
  (Skill `notenportal-dunkelmodus` §2). Die Haarlinien tragen die Zeilen.
- **Statuszeile auf /grades nutzt `x-note` in `text-2xl`** wie `kachel.blade.php` und das Dashboard;
  der Skill reserviert `text-2xl` für h1 – Kennzahlenregel einmal gesamt klären, nicht je View.
- **«Keine Noten in 2. Semester»** (lang/en.json, Leerzustand Notenliste) ist grammatisch «im»; der
  Schlüssel wird mit dem Semesterwähler in einem Zug überarbeitet.
- **Neutrale Diagrammfarbe uneinheitlich**: «Wo stehe ich» nutzt `text/50`, `NotenSkala::BALKEN`,
  Histogramm und Sparkline noch `chart-1`; die Legende (`x-noten-legende`, Prop `neutral`) folgt jeweils
  ihren Balken. Vereinheitlichen auf Grau in einem eigenen Schritt (Admin-Bericht, Cockpit-Sparklines).
- **`Symbole.php` kennt kein `arrow-path`**: der Lade-Spinner im Import ist CSS (`animate-spin` +
  Rahmen) nach dem Muster von `admin/betrieb/_logo`.
- **Weiss auf Akzent 4.57:1** (`--accent-contrast`): WCAG AA erfüllt, 7:1 nicht; jede Alternative
  (dunkler Akzent, getönter Text) verfehlt AA oder den HIG-Blauton. Token-Entscheid, kein View-Fix.
- **Abschluss: Ergebniskarte und Aufbau-Tabelle enden nicht bündig** (18 px): die Karte ist sticky und
  trägt ihre natürliche Höhe; Strecken würde das Mitlaufen zerstören.
- **Primärknopf in der Symbolleiste rechts, Lesespalte mittig** (Abschluss, Import bei 2560): Konvention
  der macOS-Toolbar, gilt portalweit; keine Ausnahme je Seite. Ebenso die Agenda-Liste (Deckel 78 rem,
  bei 2560 rechts 384 px frei): lieber ein ruhiger Rand als Zeilen, deren Aktionen 1000 px vom Text stehen.
- **Dashboard Lernende: Leerzustand-Bedingung** (`! $alsNaechstes && ! $zeigen['ziele']`): zeigt den Leerzustand
  auch, wenn ein Ziel existiert, die Karte «Ziele» aber ausgeblendet ist, und Karten statt Leerzustand, wenn
  «Als Nächstes» fehlende Module nennt. Beides ist gewollt: Massstab ist «nichts Sichtbares», nicht «0 Datensätze».
- **Dokumente-Abgleich, Zweig `status 'fehlt'` mit `portal null` und Checkbox**: im Prüflauf nur mit PDF ohne
  Textlayer gerendert; der Zweig ist durch AbgleichTest abgedeckt, aber nicht als Bild gesehen.
- **Dashboard Lernende: Kartenkanten der linken und rechten Spalte laufen versetzt** (Stand 389 px gegen
  Als Nächstes 410 px): zwei inhaltsgetriebene Spalten mit 2 gegen 3 Karten; gleiche Reihenhöhen hiessen leere
  Flächen in den kürzeren Karten. Apple-Dashboards (Health, Aktien) richten Spalten ebenfalls nicht reihenweise aus.

## R4 Berufsbildner: bewusst gelassen (01.10.2026)

- **Lernendenliste ohne Paginierung** (`LernendeController`, `->get()`): ein Lehrbetrieb führt Dutzende
  Lernende, keine Tausende; Suche und Filter tragen die Liste. Paginierung erst, wenn ein Betrieb sie braucht.
- **Lernendenliste: Statusspalte wächst, «Noten» fest `w-24`** (R4 Admin, 02.10.): Die feste Statusspalte
  `w-80` brach bei drei Lernenden die Marken zweizeilig (Zeile 60 statt 44 px). Jetzt trägt die Statusspalte
  den freien Raum; alle Zeilen 44 px bei 1920 und 2560, gemessen mit Playwright.
- **KPI «Neu» in Akzent** (`verwaltung/noten/index.blade.php`, `x-kachel ton=accent`): Akzent bedeutet
  dort «ungelesen», wie der Punkt vor «n neu» in der Liste und in Mail; neutral nur bei 0.
- **Zähler «Note fehlt» in Bernstein** (`verwaltung/pruefungen/_zaehler.blade.php`): nach dem Entfärben von
  «vor N Tagen» ist die Marke der einzige Träger der Warnung; `title` nennt den Grund.
- **`@container` in `verwaltung/noten/index.blade.php` bleibt**: die `@4xl:`-Varianten der Inspektorspalte
  sind Container-Queries, der Bildprüfer-Befund «ohne Nutzung» traf nicht zu.
- **Fussnote «Für Lernende nicht sichtbar.»** unter der Bemerkung im Lernendenformular: keine
  Entwicklernotiz, sondern die Vertraulichkeitszusage, die einen Fehleintrag verhindert.
- **Startpasswort-Kasten** (`verwaltung/lernende/show.blade.php`, nur nach POST sichtbar): Änderung
  `font-mono text-xl` per Klassenlesung geprüft, nicht als Bild gesehen.
- **Sparkline-Farbe `chart-1`** in Dashboard und Cockpit: siehe «Neutrale Diagrammfarbe uneinheitlich»
  unter R4 Lernende – ein Schritt für alle Diagramme.
- **`admin/benutzer/_person.blade.php`: Hinweis «Buchstaben, Ziffern und . _ -»** unter dem Benutzernamen
  ist erklärend; kommt mit dem Admin-Block (Validierungsmeldung statt Dauerhinweis).
- **Prüfungstermine (Verwaltung): Nebenspalte «Lernende» endet bei 2560 rund 385 px vor der Symbolleiste**
  (`verwaltung/pruefungen/index.blade.php`, Raster `minmax(0,78rem)_20rem`): dieselbe Entscheidung wie die
  Agenda der Lernenden (R4 Lernende, «Deckel 78 rem») – Terminzeilen mit 300 px Inhalt auf 1600 px zu strecken
  bringt nichts; die Zähler sind jetzt beschriftet («9 ohne Note»).
- **Schul-Track nur beim Erfassen, nicht beim Bearbeiten** (`verwaltung/lernende/_formular.blade.php`,
  `@unless($lernender)`): ein laufender Track hat Startsemester und Verlauf und wird im Cockpit unter
  «Profil & Betreuung» gestartet oder beendet; im Bearbeiten-Formular wäre eine Änderung mehrdeutig.
- **Bildprüfer-Befund «Letzte Note» linksbündig**: nicht bestätigt – Zelle und Kopf sind `text-right`,
  «vor 24 Tagen» endet an derselben Kante wie «–» (1920/_trainer_learners.png, x≈1340).
- **Cockpit-Reiter Noten und Abschluss enden bei 2560 rund 385 px vor der Symbolleiste, Übersicht und Rechner
  nicht** (`verwaltung/noten/index.blade.php`, `max-w-[100rem]`; Commit 8037bac «bis zur Inhaltskante»): Notenliste
  mit Inspektor und Abschlusstabelle gewinnen durch 385 px mehr Breite nichts, das Kartenraster der Übersicht und die
  zwei Spalten des Rechners schon. Der Sprung der rechten Kante beim Reiterwechsel ist der Preis dafür.
- **Inspektor der Notenliste «lässt rechts leer»** (Bildprüfer 1920 und 2560): Artefakt der Ganzseitenaufnahme – der
  Inspektor ist `sticky` (`verwaltung/noten/index.blade.php:177`) und bleibt beim Scrollen neben der Liste.
- **Datumsfelder zeigen «dd.mm.yyyy»**: Platzhalter des nativen `<input type="date">` in der Sprache des Browsers;
  Chromium im Container läuft en-US, ein de-CH-Browser zeigt «TT.MM.JJJJ».
- **Notenchips in der Notenliste zentriert statt am Dezimalpunkt ausgerichtet** (`<x-note>`): Marken sind Pillen
  gleicher Breite (macOS-Badge) und werden als Marke gelesen; Spalten, in denen Noten verglichen werden
  (Lernendenliste «Ø gesamt»), stehen rechtsbündig mit `tabular-nums`.
- **«–» der vier Kennzahlen im Cockpit liegen 5 px auseinander** (`_cockpit/uebersicht.blade.php`): Gesamt- und
  Semesternote sind `<x-note variante="hero">` in `text-3xl`, Nächste und Letzte Prüfung Text in `text-xl`; die
  Grundlinie der grösseren Ziffer liegt tiefer. Angleichen hiesse, die Heldenzahl zu verkleinern.
- **Platzhalter «–» in Heldengrösse wirkt wie ein grauer Balken** (Abschluss, «Prognose» ohne Note): derselbe
  Platzhalter wie auf der Abschlussseite der Lernenden (`NotenSkala::format(null)`); ein Text «keine Note» neben
  «0 % erfasst» wäre doppelt.
- **Lernende ohne Noten (Cockpit): «Noch keine Noten» ohne Weiterweg, Primäraktion führt auf eine leere Liste**:
  die leere Notenliste trägt «Note erfassen» als Primäraktion; ein zweiter Akzent im Cockpit wäre einer zu viel.
- **Hinweistexte in Modul- und Einstellungsformularen** («Der Link ist geheim – nicht weitergeben», «Gilt für die
  Anzeige, nicht für Notenblatt und Exporte», «Vom Betrieb festgelegt.», «Eine Zeile je Ziel …», «Erzeugt den Verweis
  auf den Modulbaukasten», «Was du hier ergänzt, steht sofort allen …»): bleiben nach `notenportal-ui` §1 – jeder
  verhindert einen Fehleintrag oder sagt, warum ein Feld gesperrt ist. Der Admin-Block prüft sie mit den übrigen
  Formularen noch einmal.
- **Feldrand heller als die Karten-Haarlinie** (`np-feld`, `border-border-strong/70`): im Token-Katalog so
  festgelegt (`notenportal-ui` §2), damit das Feld auf `bg-card` als Eingabe erkennbar bleibt.
- **Titel-Feld im Modulformular ohne Platzhalter**: Pflichtfelder tragen keinen Platzhalter, freiwillige «Optional»
  (`notenportal-ui` §4); «M100» und «https://» sind Formatbeispiele.
- **Karten einer Reihe ungleich hoch** (Dashboard, Rechner): `items-start` ist das Raster der Übersichten
  (`notenportal-ui` §5); Karten mit Listen strecken sich nicht auf die Nachbarin.
- **«Hochladen…» und «Modul anlegen» doppelt (Symbolleiste und Leerzustand)**: `<x-leer>` bietet die Aktion am Ort
  des Lesens an, die Symbolleiste am gewohnten Ort; nur die Symbolleiste trägt den Akzent.
- **Prüfungstermine: Lernende ohne Termin ohne Zähler**: Zähler erscheinen nur mit Inhalt (wie `zaehler` am
  Seitenkopf); «0» wäre eine Marke ohne Aussage.
- **Abschluss: Zeilen mit Eingabefeld 8 px höher als Zeilen ohne**: `np-feld` (36 px) in der 36-px-Zeile plus
  Zellenabstand; `np-feld-klein` (28 px) ist für Filterleisten bestimmt, eine Noteneingabe braucht die volle
  Trefferfläche.
- **Akzentblau ohne Linkfunktion** (Seitenleistensymbole, Kartenkopf-Symbole): Seitenleiste ist Navigation
  (erlaubt), die Kartenkopf-Symbole sind seit R4 Lernende so entschieden. Die Datumskachel «OKT» im Dashboard
  war ein Befund und steht jetzt wie in der Terminliste in `text-muted` ohne Versalien.
- **Zeugnismatrix (`components/heatmap.blade.php`) ist eine Hand-Tabelle, keine `np-tabelle`**: die Namensspalte
  klebt (`sticky`) und braucht einen deckenden Grund; die halbtransparenten Streifen von `np-tabelle` schienen
  durch. Begründete Ausnahme zu `notenportal-ui` §5.
- **Dashboards mit `gap-5` statt `gap-4`**: beide Dashboards (Lernende seit R4 Lernende, Berufsbildner) halten
  20 px zwischen den grossen Karten; `gap-4` gilt für Karten innerhalb eines Abschnitts.
- **Leere Filterergebnisse als Tabellenzeile statt `<x-leer>`** (Lernendenliste «Keine Treffer», Dashboard
  «Keine Lernenden für diesen Filter»): `<x-leer>` ist laut eigener Doku für leere Ansichten, in Tabellen reicht
  die Zeile – sie trägt jetzt einen Weg zurück («Filter zurücksetzen» bzw. «Alle anzeigen»).
- **Zeugnismatrix «Noch keine Noten» ohne Weiterweg**: die Matrix steht bei Lernenden (die keine Noten erfassen
  können) und Berufsbildnern; der Weg zum Erfassen ist die Primäraktion der Notenliste.

## R4 Admin: bewusst gelassen (02.10.2026)

Befunde aus vier Bildprüfer-Läufen (56 Seiten, 1920 dunkel), zwei UI-Checker-Läufen (61 Views) und der
eigenen 2560-Sicht, die nach Prüfung am Markup oder an den Regeln nicht umgesetzt werden:

- **Listenbreite der Stammdaten** (Fächer, Kategorien, Semester, Notenbäume, Lehrberufe, Benutzerkonten,
  Berufsbildner): jede Liste füllt `np-seite` (Deckel 2048 px), die erste Spalte wächst, Zahlen stehen
  rechts (Finder-Muster, gleiche Entscheidung wie bei der Lernendenliste). Eine Sonderbreite je Liste wäre
  ein zweites Raster; bei 2560 bleibt der freie Raum zwischen Name und Zahlenspalten.
- **Modulliste ohne Seitenaufteilung oder Sticky-Kopf** (`admin/stammdaten/module/index`): 68 Zeilen; die
  Leiste bietet Suche, Lehrberuf-, Lernort-Filter und Gruppierung – das ist die Progressive Disclosure.
- **Notenskala-Balken gesättigt** (`admin/betrieb/_felder`, Einrichtung Schritt 1): die vier Notenfarben
  sind Tokens aus `theme.css` und gelten überall; Dämpfung wäre Theme-Arbeit mit Kontrastrechnung, nicht
  ein View-Eingriff.
- **Gerahmte Eingabefelder im Notenbaum-Aufbau und in den Lehrberuf-Modulen**: es sind Bearbeitungstabellen,
  Felder ohne Rahmen wären nicht als editierbar erkennbar (HIG: Bedienelemente erkennbar). Häkchen für
  Pflicht/Aktiv sind die Standard-Checkbox mit `text-accent`-Füllung.
- **Namensreihenfolge** «Nachname Vorname» in Listen (Sortierschlüssel), «Vorname Nachname» in Fliesstext,
  Dashboard und Formular. Bewusst, wie in Mac-Kontakten.
- **Spalte «Benutzername»** in den Benutzerkonten: eigenes Feld für die Anmeldung, nicht nur der Teil vor
  dem @; bleibt, weil es abweichen kann.
- **Aktionsspalte «Profil» / «Bearbeiten»**: das Ziel unterscheidet sich (Lernende → Cockpit, Konten →
  Formular); eine gemeinsame Beschriftung würde das verbergen.
- **Rollen-Chips klein**: `np-marke` (text-2xs 600) auf `bg-fill` mit `text-muted` – dasselbe Tokenpaar
  wie jede neutrale Marke im Portal; nicht einzeln vergrössern.
- **Seitenleiste: alle Symbole in Akzent, aktiver Eintrag nur über die Fläche**: Navigationsentscheid aus
  R4 Lernende (Apple-Seitenleiste), nicht je Rolle anders.
- **Prüfungstermine (Admin): «Kürzlich vergangen, ohne Note» ohne Kürzung, Marke «n ohne Note» amber**:
  Arbeitsliste – die Länge ist die Arbeit; amber heisst Handlungsbedarf, nicht Dekoration.
- **Einstellungen: Seitentitel wiederholt den aktiven Tab**: Muster der Einstellungsseiten (R4 Lernende),
  Browser-Titel und Überschrift müssen die Seite nennen.
- **/feedback («Meine Meldungen») ohne Markierung in der Seitenleiste**: erreichbar über den
  Symbolleisten-Knopf, bewusst kein Seitenleisteneintrag; der Admin-Eintrag «Feedback» ist der Posteingang.
- **Cockpit-Hinweise mit gleichfarbigem Punkt**: der Punkt zeigt den Lernstand (rot/gelb) der Person, nicht
  die Schwere jedes Hinweises; eine Schwere je Hinweis gibt es im Modell nicht.
- **Deaktivierter roter «Löschen»-Knopf im Semester unter 3:1**: inaktive Bedienelemente sind von
  WCAG 1.4.3 ausgenommen; die Sperre wird daneben im Text erklärt.
- **Filter «Nur aktive» zeigt Abgeschlossene**: «aktiv» ist das Konto (`benutzer.aktiv`), nicht die
  laufende Lehre; Abgeschlossene mit aktivem Konto bleiben sichtbar, der Filter «Warnung» blendet sie aus.
- **Einrichtung: «Abschluss» mit Haken, obwohl Kategorien und E-Mail offen sind**: `Einrichtung::offen()`
  ist das Flag «Assistent läuft»; der Haken heisst «Assistent abgeschlossen». Kategorien gilt erst als
  erledigt, wenn jemand sie bestätigt hat (`KATEGORIEN_GEPRUEFT`), E-Mail erst mit konfiguriertem Versand –
  beides optional, darum offen und trotzdem abschliessbar.
- **Einrichtung, Schritt Kategorien als vier Karten à sechs Felder**: Formularmuster «gruppierte Liste» je
  Kategorie; eine Tabelle mit 24 Eingabefeldern wäre dichter, aber kein Muster des Portals.
- **Feldbezeichnung «Track»**: Fachbegriff des Portals (Fächer-Tabelle, Lehrberuf, Lernende) für BMS/ABU.
- **Benachrichtigungen: Umbau auf `np-gruppe`/`x-einstellung`**: Formular mit vielen Array-Feldern ohne
  Feature-Test der Feldnamen; korrigiert wurden Breite (`np-spalte`), Abstände, Ausrichtung und die
  Fehlerverdrahtung. Umbau erst mit einem Test, der das Speichern aller Felder belegt.
- **«Testmail senden» aktiv bei leerem Server**: die Serverseite meldet den fehlenden Server als Fehler;
  ein clientseitiges Sperren müsste die Regel doppeln.
- **Abschluss-Seite: «Speichern» in der Symbolleiste**: Seitenkopf-Muster (eine Primäraktion oben rechts),
  gleich wie in Betrieb (Einstellungen). Benachrichtigungen speichert unter dem langen Formular – nach HIG
  gehört der Formularabschluss unter das Formular, beide Muster sind im Skill erlaubt.
- **Admin-Feedback-Leerzustand ohne Weiterweg**: der Admin erzeugt keine Meldungen, es gibt keinen Weg.
- **Benutzer anlegen: Rolle als Segment, bearbeiten: Schalter**: beim Anlegen genau eine Rolle, beim
  Bearbeiten Mehrfachrollen (`rollen[]`); verschiedene Semantik, verschiedene Bedienelemente.
- **Lernende erfassen: Primärknopf bei 1080 px nahe dem unteren Rand**: Formularabschluss gehört unter das
  Formular (HIG: nichts Wichtiges fixiert), die Seite scrollt.
- **Betrieb: «älter als zwei Tage» fest im Code** (`admin/betrieb/edit.blade.php`, `subDays(2)`): Grenze der
  Systemgesundheit (Sicherung, Kopie), keine Betriebsfrist – bleibt im Code.
- **Benutzer bearbeiten: nach einem Validierungsfehler bei «Rollen» fallen die Schalter auf den gespeicherten
  Stand zurück** (`old('rollen', $rollen->all())`): Wer alle Rollen abwählt, sieht nach der Fehlermeldung
  Berufsbildner wieder eingeschaltet. Produktlogik, Fehlermeldung trägt die Information.
- **Notenbäume-Liste ohne Lehrberufs-Kürzel**: die Index-Query wählt `l.kuerzel` nicht; «Gilt für» unterscheidet
  die Zeilen schon über den vollen Namen.
- **`table-fixed` ohne `<colgroup>`** (Versandprotokoll, Aktivitätsprotokoll, Berichte, Lehrberuf-Detail): die
  Spaltenbreiten stehen am `th` und wirken bei `table-fixed` gleich; Umbau nur mit erneuter Bildprüfung, ohne
  sichtbaren Nutzen (UI-Checker, mittel).
- **Einrichtung Lehrberufe: Felder «Kürzel»/«Weiterer Lehrberuf» nur mit `aria-label` und Platzhalter**: der
  zugängliche Name ist da; sichtbare Spaltenköpfe wie bei den Personen lohnen sich erst ab drei Feldern je Zeile.
- **Einrichtung Abschluss: Fusszeile nicht über `_fuss`**: der Fuss trägt ein POST-Formular («Einrichtung
  abschliessen»), das der Teil-View nicht kennt; eigener Fuss bleibt.

## R5 Sichtprüfung: bewusst gelassen (02.10.2026)

Rundgang über alle drei Rollen (96 Seiten, 1920 dunkel, Workflow `notenportal-dunkel-rundgang`,
Bildprüfer sonnet): 136 Befunde. Die gegnerische Verifikation im Workflow (272 Opus-Aufrufe) brach am
Nutzungslimit ab und verwarf bestätigte Ursachen durch ihre UND-Logik; darum wurden alle 136 Befunde in
der Hauptsession am Markup nach Ursache gebündelt. Umgesetzt (31 Befunde, Commit siehe Übergabe):
Verlauf-Reihen mit Strichmuster, Spalte «Prüfungen» bündig, Import-Knopf bis zur Dateiwahl gesperrt
(auch nach Drag-and-drop), «Gewicht» vor Prozentwerten (Lernende und Berufsbildner), ein Akzent in
/modules, Modul-Fussnote auf einen Satz, Benachrichtigungs-Beschreibung je Rolle, «Vom Betrieb
festgelegt.» als Schloss mit sr-only, Aktivitätsprotokoll ohne leere Spalten (Karte dann `max-w-4xl`),
Einrichtung (Lehrberufe/Fächer lesbar mit «vorhanden», Kategorien erst nach Bestätigung erledigt,
Abschluss-Haken nur wenn alles erledigt, Zahlenfelder rechtsbündig, neue Semester mit Marke «neu»),
Feedback-Eintrag auf /feedback aktiv, Abschluss-Notenfelder bündig, acht Listen mit Kartenobergrenze
(`max-w-5xl` bis `max-w-7xl`), Prüfungstermine-Gitter `minmax(0,64rem)`. Der Rest nach Regel:

- **Leerflächen und ungleiche Kartenhöhen in Spaltenlayouts** (Dashboards aller Rollen, Cockpit,
  Rechner, Abschluss, Berichte; 14 Befunde): Spalten sind unabhängig hoch, Karten folgen ihrem Inhalt;
  Angleichen hiesse leere Fläche *in* Karten (HIG Layout: gruppieren über Negativraum). Entscheid R3/R4.
- **Leerzustand plus Symbolleisten-Aktion** (Meldungen, Dokumente, «als gesehen markieren»): Muster
  «eine Primäraktion in der Symbolleiste, Weg weiter im Leerzustand» (`x-leer`), kein Doppel.
- **Seitenleisten-Symbole alle in Akzent** (alle Rollen): Entscheid R4 Lernende (Apple-Seitenleiste
  tönt alle Glyphen, aktiv ist die Fläche), nicht je Rolle anders.
- **Feldränder «hart/hell», Feldbreiten uneinheitlich, Platzhalter uneinheitlich** (Modulformular,
  Lernende erfassen, Notenbaum, Semester, Betrieb; 13 Befunde): `border-border-strong/70` ist der
  vorgeschriebene Feldrand (`notenportal-ui` §2), Breiten folgen dem Feldtyp (§7: w-72/w-32/w-24/w-44/
  w-56) und zeigen die erwartete Länge, «Optional» steht nur an freiwilligen Feldern (§4).
- **Hinweistexte** (Katalogversion, Handlungsziele, Kalender-Abo, Bemerkung intern, Promotion-Block,
  Darstellung, Ablagezone): geprüft, jeder nennt eine Folge oder Grenze (wer sieht es, welches Format,
  was passiert beim Leerlassen) – bleibt nach `notenportal-ui` §1. Entfernt wurde nur der zweite Satz
  der Modul-Fussnote.
- **Notenfeld ohne Platzhalter oder Formathinweis** (Note erfassen, Cockpit): Formatfehler meldet
  `validation.custom` verständlich; ein Hinweis vor dem Fehler ist Selbstverständliches (§1).
- **Zebra-Streifen in gruppierten Abschlusstabellen**: `np-tabelle` streift immer (macOS-Tabellen),
  Gruppenköpfe tragen Gewicht und Einzug; Entscheid R4.
- **Datumsfelder mit «dd.mm.yyyy» und nativem Look** (Aktivitätsprotokoll, Lernende erfassen,
  Semester): Werkzeugartefakt – headless Chromium hat keine deutschen UI-Strings, `--lang=de-CH` und
  `locale: 'de-CH'` ändern den Platzhalter nicht (geprüft 02.10.); im Browser steht «TT.MM.JJJJ».
  `text-right` wirkt auf `type="date"` nicht; eine Regel auf `::-webkit-datetime-edit` wäre Browser-CSS.
- **Knapp nur durch Farbe** (Zeugnisnoten, Berichte): `NotenSkala` färbt knapp als Vorwarnstufe und
  unterstreicht ungenügend; die zweite Kodierung trägt die Stufe, die eine Folge hat (Token-Entscheid,
  siehe «Dunkelmodus nach HIG»).
- **Akzent für «Neu»-Kennzahl und «QV-Prognose»**: «QV-Prognose» ist ein Link (`text-accent-text`);
  der Akzentpunkt bei «Neu» ist die Marke «ungesehen», gleich wie die «n neu»-Chips der Listen.
- **Status «Beobachten»/Amber in fast allen Zeilen, zwei Notenbäume gleichen Namens, Prognose ohne
  Wert, rote Punkte bei allen Hinweisen**: Demo-Daten bzw. Modell (Punkt = Lernstand der Person,
  R4 Admin); keine View-Ursache.
- **Deaktivierte Entfernen-/Löschen-Knöpfe unter 3:1**: WCAG 1.4.3 nimmt inaktive Bedienelemente aus,
  die Sperre steht daneben im Text (R4 Admin). «Zwei Zeilen hervorgehoben» war Hover plus Zebra im
  Moment der Aufnahme.
- **Namensreihenfolge, «Track», Seitentitel der Einstellungen, Rollenwahl Segment/Schalter,
  Formularabschluss nahe Unterkante, Modulliste ohne Gruppierung, Abschnittsüberschriften der
  Prüfungstermine, Karte in Karte bei Personen, Benachrichtigungs-Umbau**: Entscheide aus R4 Admin
  (oben), unverändert.
- **Diagrammbalken gesättigt, Achsen 2xs ohne Achsentitel, Direktbeschriftung 3xs**: Token-Entscheid
  (`--chart-1`), Achsen- und Beschriftungsgrössen nach `notenportal-dunkelmodus` §3 erlaubt,
  Achsentitel steht im Kartentitel.
- **Zurück-Pfeil vorne in der Symbolleiste, «Noten»-Aktion rechts in der Zeile, Speichern als
  Sekundärknopf in Profil-Formularen, «Auf Standard zurücksetzen» rechts**: Seitenkopf-, Tabellen- und
  Formularmuster (`notenportal-ui` §5/§7); Profil hat mehrere Formulare und darum keinen Primärknopf.
- **«Lehrzeit» neben der Heldenzahl**: Überschrift der drei Kategorie-Kacheln (Lehrzeit-Schnitte
  gegenüber dem Semesterwert), bewusst dort.
- **Kontrast-Kachel wirkt wie zweite Auswahl**: die Kachel zeigt ihr Thema in eigener Farbe (Vorschau);
  Auswahl ist der Ring (R4 Lernende).
- **Doppelte Semesterübersicht in der Einrichtung**: «Vorschau» ist der Plan ab Startjahr, «Vorhanden»
  der ganze Bestand (auch 22/23 vor dem Startjahr).
- **Offene Schritte schwächer als erledigte** (Schrittliste): Hierarchie aktiv (fett) › erledigt
  (`text-text` mit Haken) › offen (`text-muted`, ≥ 4.5:1).
- **Zurückgestellt (Gestaltung, kein Regelverstoss)**: «Vorlage (CSV)» fluchtet nicht mit der
  Dropzone; Detailkopf der Module springt bei unterschiedlich langen Titeln; Profil/Darstellung als
  lange Seite (Themenraster aufgeklappt); «Neuen Link erzeugen» nicht bündig; Gruppen- und
  Semesterschnitt-Zahlen kleiner als Zeilenwerte; Statustext der Berichte bricht mit Einzelwort um;
  Titelspalte der Einstellungsseiten springt. Lösung je Punkt braucht einen Entwurf, nicht einen Fix.
- **Lernende (Admin): Status-Spalte bekommt bei `max-w-7xl` nur 15rem**: 64rem feste Spalten plus
  Kartenpolster füllen die höchste Stufe fast aus; drei Etiketten in einer Zeile brechen um (`flex-wrap`,
  Zeile wird zweizeilig). Breiter ginge nur mit einer Stufe über `max-w-7xl` oder schmaleren festen
  Spalten (Name `w-72` ist für lange Doppelnamen schon knapp).
- **Fächer, Semester, Lehrberufe, Module: Breiten bleiben am `th`**: `table-fixed` ist gesetzt, die
  Breiten am Kopf wirken wie ein `<colgroup>`; nur die Kartenobergrenze (`max-w-5xl` bis `max-w-7xl`)
  ist neu. Berufsbildner und Benutzer hatten weder `table-fixed` noch Breiten und tragen jetzt ein
  `<colgroup>`.
- Einrichtung, Schritt Kategorien: das Flag `einrichtung_kategorien` setzt nur der Einrichtungsschritt (`EinrichtungController`), nicht die reguläre Kategorienseite – wer Kategorien dort pflegt, sieht in der Einrichtung «noch nicht bestätigt», bis der Schritt einmal gespeichert ist. Gewollt: der Schritt ist die Bestätigung (Prüfer-Notiz 02.10.).
- Aktivitätsprotokoll: ob die Spalten Ziel und Details erscheinen, entscheidet jede Seite für sich (`$hatZiel`/`$hatDetails` über die Zeilen der Seite) – zwischen Seite 1 und 2 kann der Spaltensatz wechseln. Gelassen: eine zweite Abfrage über alle Treffer je Aufruf wäre teurer als der Effekt (Prüfer-Notiz 02.10.).

## R5 Audit-Workflow: bewusst gelassen (02.10.2026)

Workflow `notenportal-audit` (72 Agents, Dunkelmodus/Design/UX Lernende/UX Verwaltung/Korrektheit,
jeder Befund gegnerisch verifiziert): 33 Befunde, 20 bestätigt und umgesetzt (Commit siehe Übergabe),
13 verworfen. Die verworfenen mit dem Grund des Verifiers:

- **Vorderste Ebene fehlt (Drawer, Dialog, Alert in `bg-card`)**: `notenportal-ui` §2 führt den Dialog
  ausdrücklich unter `bg-card`; die Stufe «Overlay heller als Karte» in `notenportal-dunkelmodus` §2 ist
  dort als Übertragung markiert, die HIG nennt base/elevated nur unter iOS/iPadOS. Bei offenem Blatt
  dunkelt der Scrim (0.45) alles dahinter ab, das Blatt ist die hellste Fläche und trägt Haarlinie plus
  `--elev-3`. Der Regelkonflikt der zwei Skills bleibt notiert; aufgelöst wird er mit dem nächsten
  Token-Entscheid, nicht mit einem vierten Flächen-Token.
- **Monatsraster der Agenda (Punktfarbe, mehrere Akzentflächen)**: der Navigationszähler entsteht nur
  für Admin-Feedback, Heute-Kreise stehen in sich ausschliessenden Zweigen, der einzige `bg-accent`-Knopf
  ist die Primäraktion. Der vorgeschlagene Ring übernähme dieselbe reine Farbtrennung Prüfung/Termin
  aus `AgendaArt` – kein Gewinn.
- **Karten mit `max-w-4xl` bis `max-w-7xl`**: Entscheid vom selben Tag (3d63e7e, «Listenkarten auf die
  Breite ihrer Spalten begrenzen»); Karten sitzen linksbündig in `np-seite`, D6 (springende Achse)
  entsteht nicht.
- **Leerzustände ohne nächsten Schritt** (Fächer, Kategorien, Semester, Module, Lehrberufe,
  Notenbäume): der nächste Schritt ist die einzige Primäraktion im Seitenkopf, gleichzeitig sichtbar;
  `x-leer` erlaubt null Aktionen. Dass andere Listen die Aktion im Slot wiederholen, ist eine
  Einheitlichkeitsfrage, kein Verstoss.
- **Hinweistexte** (Kalender-Abo, Darstellung, Kalender-Anleitungen, externe Kalender, Benutzer
  anlegen): schon in «R5 Sichtprüfung» entschieden; jeder Satz nennt eine Folge oder verhindert eine
  falsche Erwartung (Portaländerungen fliessen nicht in den Quellkalender zurück). Einzig das Wort
  «aktuell» in `kalender-anleitungen.blade.php:25` klingt nach Entwicklungsstand – Textpflege, kein Fix.
- **Versalien/Typo-Reste in der Einrichtung**: `uppercase` am Kürzelfeld zeigt den gespeicherten Wert
  (`mb_strtoupper` im Controller); `normal-case` in `people.blade.php:3` ist wirkungslos,
  `tracking-normal` und `px-2` weichen bei «lesefreundlich» von `np-feld` ab (Aufräumarbeit, kein
  Regelverstoss); der Rahmen in `people.blade.php:88` wäre als `bg-fill-2`-Fläche konsistenter – alle
  drei als Kleinigkeiten für die nächste Einrichtungs-Session.
- **Agenda: Speichern führt zur Notenseite**: gilt in der ganzen App (Dashboard, Notenseite, Agenda);
  Abbrechen bleibt im Kontext. Ein Sonderweg für die Agenda schüfe die Uneinheitlichkeit, die er beheben
  soll. Die Redirects im `PruefungenController` verlieren `ansicht`/`monat` – Gestaltungsfrage.
- **«Offene Meldungen» öffnet die ungefilterte Feedback-Liste**: der Admin-Eintrag «Feedback» ist der
  Posteingang mit Ungelesen-Markierung (Entscheid R4 Admin); spürbar erst, wenn eine offene Meldung
  hinter 25 erledigten auf Seite 2 liegt.
- **Cockpit ohne Drill-down (Prüfungen, Aktivität, Zeugnisnoten)**: das Cockpit lädt nur offene
  Prüfungen (Zweig «mit Note» wäre tot), die Heatmap kennt keine `kategorie_id` (Link wäre zu grob)
  und ist eine gemeinsame Komponente. «Noten ansehen» und die Reiter sind der Weg.
- **Berufsbildner-Tabellen ohne Zeilenlink**: `data-href` ist eine Bauanleitung, keine Pflicht; jede
  Zahl führt in die gefilterte Lernendenliste (Kommentar Z. 1), `cursor: pointer` nur auf `tr[data-href]`.
- **Gewichteter Durchschnitt in `Schulnetz::schnitt()`**: Zuordnungsheuristik beim Parsen (bildet die
  Schulnetz-Formel nach, Ergebnis wird verworfen); `Auswertung` wäre hier fachlich falsch.
  `ImportFormateTest` deckt den Fall ab.
- **Rückdatierte Betreuung löscht abgelöste Zeiträume hart** und **Semesterdaten trotz Noten
  änderbar**: beides Produktentscheide mit Datenwirkung, stehen unter «Offen für David» in der
  Übergabe mit den Gründen des Verifiers.

Aus der Umsetzung des Dunkelmodus-Blocks offen geblieben:

- **Marke «ungenügend»/«knapp» auf Hover-Zeilen unter 4.5:1 in anderen Themes**: mit dem neuen
  Gletscher-dunkel-Rot (255 158 150) liegt die Marke auf Karte 5.29, Zebra 4.88, Hover 4.50. Gleiches
  Verfahren über alle Theme-Blöcke: ungenügend dunkel `papier` 4.20 und `bernstein` 4.31, ungenügend
  hell 4.19–4.33 und knapp hell 4.09–4.19 in allen Themes (auf Karte und Zebra meist darüber). Hell ist
  nicht Massstab, die zwei Dunkelthemes brauchen je einen eigenen Ton – Token-Arbeit je Theme, nicht
  ein Fix.
- **Drawer bei «Bewegung reduzieren»**: das Blatt erscheint ohne Überblendung (nur der Scrim blendet),
  weil der `aside` keine Opazitäts-Transition hat; `motion-reduce:translate-x-0` nimmt nur die
  Bewegung weg. Überblendung ergänzen wäre eine zweite Transition am Blatt.
- `np-haken`: Schalterknopf (`white`) und Auswahlpfeil (`%2386868b`) bleiben hartcodiert in SVG-Data-
  URLs; beide liegen auf eigenen Flächen (Accent bzw. Eingabefeld) und waren nicht Teil des Befunds.

## R6 Welle 1 (Fundament, JS-Bausteine): bewusst gelassen (03.10.2026)

Review-Empfehlungen aus dem Workflow `notenportal-r6-welle`, keine Blocker. Alles kommt in den
geplanten Scheiben, nicht als Nachbesserung in Welle 1.

- **Harte Dauern unter «Bewegung reduzieren»**: Seitenleiste 260 ms, `np-slide-down`, `np-schalter`
  tragen noch eigene Millisekunden statt `--dauer-*`; die Variante `ruhig` greift dort erst, wenn
  R6-02 (Materialien) und R6-05 (Bedienelemente) die Komponenten auf die Tokens umstellen.
- **`np-grund` fehlt auf `layouts/guest.blade.php` und den Fehlerseiten**: Anmeldung und 4xx/5xx
  liegen noch auf flachem `bg-bg`. Kommt mit R6-05 (Anmeldung) bzw. R6-11 (Fehler-/Leerzustände).
- **`charts.js` nutzt weiter `bewegungReduziert()`**: wird in R6-04 (npChart v2) durch
  `bewegungRuhig()` ersetzt; bis dahin existieren beide Funktionen in `np.js`.
- **`data-leiste` hängt am Beobachter der Navigation**: `registriereLeiste()` wird aus
  `npLeistenUeberlauf` nachgezogen; ohne Hauptnavigation (Gast-Layout) bleibt das Attribut leer.
  Gewollt, weil es nur die Schubladenregel der Seitenleiste steuert.
- **`x-np-licht` wird einmal beim Laden registriert**: Elemente, die später per Alpine erscheinen,
  bekommen das Licht erst, wenn sie die Direktive selbst tragen. Reicht für Welle 1; R6-05 prüft die
  Overlays.
- **LCP-Rauschen**: Wiederholungen auf /dashboard lagen bei 188 und 220 ms (Basislinie Median 172,
  Toleranz 10 %, T = 44 ms). Kein Beleg für eine Verschlechterung, aber auch keiner für «gleich»;
  R6-12 misst drei Läufe als Median.

## R6 Welle 1b (Materialien, npChart v2): bewusst gelassen (03.10.2026)

Review-Empfehlungen und Lücken aus dem Workflow `notenportal-r6-welle`, keine Blocker.

- **`np-glas` unter `kontrastreich:`** ersetzt die ganze Schattenliste durch die 1-px-Kante,
  `glass-overlay` behält dagegen `--elev-3` und ergänzt sie. Der Plan sagt «zusätzlich». Angleichen,
  sobald R6-05 die Leiste in den Views anfasst; sichtbar nur im Theme kontrast / prefers-contrast.
- **`[data-hauptaktion]` Hover/Active mit `color-mix(… black)`**: gleiches Muster wie
  `np-knopf-primaer` und `np-knopf-gefahr` (Bestand). Ein Hover-Token je Akzent wäre sauberer –
  Token-Arbeit über alle 24 Theme-Blöcke, nicht in R6.
- **`np-schalter::after` dupliziert die Werte von `np-glas-moment`** (0.78/0.74, blur 8 px). Ein
  Schieberknopf (`input[type=range]`) existiert im Repo nicht, darum nur der Schalter. Wer die
  Glasmoment-Werte ändert, muss beide Stellen finden (Kommentar steht an beiden).
- **Neue Utilities noch ohne Verwendung**: `np-glas-moment`, `np-glanz`, `np-segment-marke`,
  `np-kante-hart`, `np-einzeichnen`, `[data-hauptaktion]` und die Regeln für `html[data-palette]`
  greifen erst, wenn R6-05/R6-06 die Views und np.js (`data-palette` nach `transitionend` des
  Scrims) nachziehen. Bis dahin bleibt der Palette-Glasanteil bei 16.8 % (zwei Kapseln behalten den
  Weichzeichner unter der offenen Palette).
- **Alpine-`x-transition` in dropdown/navigation/toast** übersteuert die neue scale-Transition von
  `glass-overlay`; die Skalierung mit `--dauer-morph` wirkt erst nach R6-05.
- **Rechnerkurve ohne `stepped`, Achse linear 1–6** (R6-04): nötig, damit der interpolierte
  Scrub-Punkt auf der Linie liegt; die Reihen `_schlechteste`/`_beste` sind Hilfsreihen fürs Band.
  Wechselt die Kurve zwischen flach (2 Reihen) und mit Spielraum (4 Reihen), baut `setze()` neu,
  der Canvas bleibt. Themewechsel baut neu und wiederholt die Staffel – gewollt.
- **Fremde Aufrufer von `x-diagramm` ohne `typ`** bekommen Glas-Tipp und Tastatur automatisch, aber
  keine sichtbare Zusammenfassung; die Umstellung auf `<x-diagramm typ=…>` gehört zu R6-07b/08–10.
- **Veraltete Nennungen** von `glass-bar`, `glass-seitenleiste`, `glass-btn`, `np-fade-in` und der
  Regel «≥ 0.78» in `.claude/skills/notenportal-ui`, `notenportal-dunkelmodus`, `.claude/workflows/*`
  und `docs/gui-konzept.md:115` tilgt R6-11.

## R6 Welle 2 (Funktionsebene, Seitenschliff): bewusst gelassen (03.10.2026)

- **`[data-hauptaktion]` ist Opt-in im Seitenkopf** (Slot `hauptaktion`) und wirkt optisch nur
  innerhalb von `.np-glas-gruppe`; die Aktionen des Seitenkopfs liegen in der Symbolleiste noch in
  einem schlichten Flex-Container. 7 Views tragen den Marker, 24 weitere mit `np-knopf-primaer` im
  Slot `aktionen` bekommen ihn in ihren Seiten-Scheiben R6-08…R6-11, wenn die Leistenaktionen in
  die Kapsel wandern. Geprüft: auf 360 gerenderten Seiten nie mehr als einer.
- **Modal ohne `np-scroll-edge`** – es hat keinen eigenen Scrollbereich; `np-kante-hart` bleibt
  ungenutzt, weil keine sticky Tabellenköpfe existieren.
- **`bestaetigung.js` schluckt ein zweites Submit während der 200-ms-Ausblendung** – gewollt gegen
  Doppelabsenden, aber ohne sichtbare Rückmeldung.
- **Toast `fortsetzen()` setzt die Pause ohne getrennte Hover-/Fokus-Flags zurück**: verlässt die
  Maus den Toast, während er fokussiert ist, läuft die Uhr weiter.
- **Lehrzeit-Servermodus von «Wo stehe ich»** ist aus den Demodaten nicht testbar (kein Lernender
  mit Server-seitigem Lehrzeit-Modus); `WoStehIchTabelleTest` deckt Semester und Umschaltung.
- **`r605-check.mjs` liegt im Scratch**, nicht im Repo; R6-12 übernimmt die Prüfungen in
  `tools/pruefung/notenportal-r6-pruefung.js`.
- **ui-checker Welle 2, bewusst gelassen:** Toast wechselt `role` (`alert`/`status`) zur Laufzeit statt zwei
  fester Live-Regionen; `<x-dropdown>` hat `aria-haspopup`, aber keine Menürolle und keine Pfeiltasten;
  `feedback-widget.blade.php` nutzt `xl:`/`max-xl:` wie die Hauptnavigation (Skill-Ausnahme muss die Komponente
  nennen – R6-11); `[data-hauptaktion]` und `np-knopf-primaer` dunkeln beim Hover mit `color-mix(…, black)`
  (gleiches Muster, siehe Welle 1). Behoben im Fix-Commit: Escape schliesst genau eine Ebene
  (`np.escapeGilt`), Leistenmenü-Escape am window, np-alert mit Bewegungstokens, Menüsymbole ohne Akzent,
  Skalen-Overrides für Toast/Palette/Tastenkürzel, Feedback-Popover als `np-schicht`, `npMorph` in np.js,
  Palette ohne 8er-Kappung mit Ladezustand, CSV-Export im Feedback-Postfach keine Hauptaktion mehr.

## R6 Welle 3 (R6-07a Statistik-Service): bewusst gelassen (03.10.2026)

- **Kennzahlen bleiben bei `kategorie_id` die der ganzen Auswahl** (`Bericht::noten`): Verteilung,
  Schwachstellen und Lehrjahresvergleich folgen dem Kategoriefilter, die Kacheln oben nicht – Annahme
  der Hauptsitzung, damit das Fazit (`admin/berichte/noten.blade.php`) nicht neben einem
  Kategorie-Histogramm kippt. GUI-R6 S10/S11 schweigt dazu; R6-10 entscheidet die Darstellung.
- **Letzter Reihenwert ≠ Gesamtnote**, sobald undatierte Leistungen (IPA, Positionen) zählen: die
  Stichtagsreihe rechnet nur Datiertes (GUI-R6 §11 R6-07a verlangt das), die Liste `meta.ohneDatum`
  erklärt die Differenz. R6-08 zeigt sie unter dem Verlauf; ein Hinweistext ist laut CLAUDE.md tabu.
- **Geplante Prüfung ausserhalb aller Semester** (Datum vor dem ersten oder nach dem letzten
  Semester, `PruefungenController` validiert nur `required|date`): `Statistik::benoetigt` meldet
  `ohne_einfluss`, weil der Rechenkern sie verwirft – Bestand, nicht neu. Saubere Lösung ist eine
  Validierung gegen die Semesterliste beim Planen (eigener Punkt, nicht R6).
- **Keine Cache-Schicht für Stichtagsreihen**: bis zu 60 Rechenkern-Läufe je Aufruf, reine Rechnung
  ohne DB; erst messen (R6-12 `leistung.mjs`), dann entscheiden.
- **Ausreisser ausserhalb 1–6** werden im Histogramm verworfen, nicht in Randklassen gelegt – die
  Skala lässt sie nicht zu, ein Treffer wäre ein Datenfehler.
- **Persönliche Ziele in `benoetigt`** warten auf «Offen für David» (Ziele für Berufsbildner
  sichtbar?); der Parameter `zielwert` ist vorbereitet.


## R6 Welle 3b (R6-07b Endpunkte, npFilter, SVG-Bausteine): bewusst gelassen (03.10.2026)

- **Doppelte Auswertung im HTML-Pfad des Lernenden-Dashboards** (`DashboardController::lernender`
  lädt `StatistikDaten::lernender` für das Paket und danach `Uebersicht::lernender` für die Seite;
  gleiches Muster in `PruefungenController` und `AbschlussController`): gemessen auf der Demo-DB
  (nina.huber, warm) kostet der zweite Lauf 5 Abfragen und ≈2 ms, konstant in der Datenmenge
  (`AbfragenAnzahlTest` wacht). `Auswertung` trägt die Leistungsliste nicht, darum liesse sich das
  nur über eine neue Signatur von `Uebersicht::lernender` zusammenlegen – nicht wert, solange R6-08
  die Dashboard-Karten ohnehin umbaut; dort entscheiden.
- **Berichtsseite tauscht nur HTML** (`npFilter` mit `json: false`): das JSON-Paket wird dort nicht
  gehört, weil die Diagramme als Server-SVG/Blade kommen; der JSON-Endpunkt bleibt für R6-10 und
  die Tests bestehen. Der Untertitel (Semestername) wechselt beim Filtern noch nicht mit → R6-10.
- **Marken (×-Pillen) sind auf der Berichtsseite nicht aktiv** (`cfg.marken` fehlt): kommt mit der
  Neugestaltung der Filterleiste in R6-10.
- **`x-verlauf` doppelt Teile des Markups von `x-diagramm`** (Rahmen, Tabelle «Als Tabelle»):
  zusammenlegen, sobald R6-08 die letzte Stelle von `npChart('verlauf')` abgelöst hat.
- **`shot.mjs` fotografiert Diagramme mitten in der Einblendung**: die Grenzlinie «genügend 4.0»
  erscheint im Bild schräg, nach 4 s ist sie waagrecht (eigene Aufnahme `verlauf-4s.png`). Kein
  Produktfehler; R6-12 soll `shot.mjs`/`rundgang.mjs` auf das Ende der Chart-Animation warten
  lassen (Ereignis oder `--bewegung=reduziert` als Standard für Vergleichsbilder).
